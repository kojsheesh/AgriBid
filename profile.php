<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$name = trim((string) ($_POST['full_name'] ?? ''));
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$currentPassword = (string) ($_POST['current_password'] ?? '');
	$newPassword = (string) ($_POST['new_password'] ?? '');
	if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
		$error = 'Enter your name and a valid email address.';
	} elseif ($newPassword !== '' && (strlen($newPassword) < 8 || strlen($newPassword) > 72)) {
		$error = 'A new password must be between 8 and 72 characters.';
	} elseif ($newPassword !== '' && $currentPassword === '') {
		$error = 'Enter your current password to change it.';
	} else {
		try {
			if ($newPassword !== '') {
				$stmt = $conn->prepare('SELECT password_hash FROM users WHERE user_id=?');
				$stmt->bind_param('i', $user['user_id']);
				$stmt->execute();
				$passwordHash = $stmt->get_result()->fetch_column();
				$stmt->close();
				if (!$passwordHash || !password_verify($currentPassword, $passwordHash)) {
					throw new RuntimeException('Your current password is incorrect.');
				}
				$newHash = password_hash($newPassword, PASSWORD_DEFAULT);
				$stmt = $conn->prepare('UPDATE users SET full_name=?, email=?, password_hash=? WHERE user_id=?');
				$stmt->bind_param('sssi', $name, $email, $newHash, $user['user_id']);
			} else {
				$stmt = $conn->prepare('UPDATE users SET full_name=?, email=? WHERE user_id=?');
				$stmt->bind_param('ssi', $name, $email, $user['user_id']);
			}
			$stmt->execute();
			$stmt->close();
			$_SESSION['user']['full_name'] = $name;
			$_SESSION['user']['email'] = $email;
			flash('Your profile has been updated.');
			redirect('/agribid/profile.php');
		} catch (RuntimeException $exception) {
			$error = $exception->getMessage();
		} catch (mysqli_sql_exception $exception) {
			if ($exception->getCode() === 1062) {
				$error = 'That email address is already registered.';
			} else {
				throw $exception;
			}
		}
	}
}
$addressStmt = $conn->prepare('SELECT address_id, label, city, barangay, full_address, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, label');
$addressStmt->bind_param('i', $user['user_id']);
$addressStmt->execute();
$addresses = $addressStmt->get_result();
app_header('Your profile', match ($user['role']) {
	'Buyer / Trader' => 'buyer',
	'Farmer / Seller' => 'farmer',
	default => 'admin',
});
?>
<div class="page-heading"><div><h2>Account information</h2><p>Keep your contact details up to date and manage your sign-in security.</p></div></div>
<section class="panel" style="max-width:740px">
	<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
	<form method="post" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
		<label>Full name<input name="full_name" maxlength="100" autocomplete="name" required value="<?= e($user['full_name']) ?>"></label>
		<label>Email address<input name="email" type="email" maxlength="100" autocomplete="email" required value="<?= e($user['email']) ?>"></label>
		<hr class="section-rule">
		<h3>Change password</h3><p class="muted-copy">Leave the new password blank to keep your current password.</p>
		<label>Current password<input name="current_password" type="password" autocomplete="current-password"></label>
		<label>New password <span class="field-hint">(8–72 characters)</span><input name="new_password" type="password" minlength="8" maxlength="72" autocomplete="new-password"></label>
		<button class="button" type="submit">Save profile</button>
	</form>
</section>
<section class="panel" style="max-width:740px">
	<div class="page-heading"><div><h2>Saved addresses</h2><p>Use these locations for buyer requests and meet-up arrangements.</p></div><a class="button button-small" href="/agribid/addresses.php">Manage addresses</a></div>
	<?php if (!$addresses->num_rows): ?><p class="muted-copy">You have not saved any addresses yet.</p><?php else: ?><div class="item-row-list"><?php while ($address = $addresses->fetch_assoc()): ?><div class="room-title-line"><span><strong><?= e($address['label']) ?></strong><span class="field-hint" style="display:block"><?= e(format_user_address($address)) ?></span></span><?php if ($address['is_default']): ?><span class="status-pill">Default</span><?php endif; ?></div><?php endwhile; ?></div><?php endif; ?>
</section>
<?php $addressStmt->close(); app_footer(); ?>
