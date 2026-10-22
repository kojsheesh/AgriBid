<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
	redirect('/agribid/index.php');
}

$error = '';
$flash = take_flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$name = trim((string) ($_POST['full_name'] ?? ''));
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$role = (string) ($_POST['role'] ?? '');
	$password = (string) ($_POST['password'] ?? '');
	if ($name === '' || mb_strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($email) > 100) {
		$error = 'Enter your name and a valid email address.';
	} elseif (!in_array($role, ['Buyer / Trader', 'Farmer / Seller'], true)) {
		$error = 'Choose whether you are joining as a buyer or farmer.';
	} elseif (strlen($password) < 8 || strlen($password) > 72) {
		$error = 'Choose a password between 8 and 72 characters.';
	} else {
		$documentName = null;
		try {
			$conn->begin_transaction();
			$documentName = upload_verification_document($_FILES['id_image'] ?? [], true);
			$hash = password_hash($password, PASSWORD_DEFAULT);
			$stmt = $conn->prepare("INSERT INTO users (full_name, email, password_hash, role, status) VALUES (?, ?, ?, ?, 'Pending')");
			$stmt->bind_param('ssss', $name, $email, $hash, $role);
			$stmt->execute();
			$userId = (int) $conn->insert_id;
			$stmt->close();
			$idType = 'Government ID';
			$verification = $conn->prepare("INSERT INTO user_verifications (user_id, id_type, document_path, status) VALUES (?, ?, ?, 'Pending')");
			$verification->bind_param('iss', $userId, $idType, $documentName);
			$verification->execute();
			$verification->close();
			$conn->commit();
			flash('Your account and ID were submitted. An administrator must review your ID before your account is verified.');
			redirect('/agribid/login.php');
		} catch (mysqli_sql_exception $exception) {
			$conn->rollback();
			if ($documentName !== null) {
				$storedFile = verification_storage_directory() . DIRECTORY_SEPARATOR . $documentName;
				if (is_file($storedFile)) {
					unlink($storedFile);
				}
			}
			if ($exception->getCode() === 1062) {
				$error = 'An account already uses that email address.';
			} else {
				throw $exception;
			}
		} catch (RuntimeException $exception) {
			$conn->rollback();
			if ($documentName !== null) {
				$storedFile = verification_storage_directory() . DIRECTORY_SEPARATOR . $documentName;
				if (is_file($storedFile)) {
					unlink($storedFile);
				}
			}
			$error = $exception->getMessage();
		} catch (Throwable $exception) {
			$conn->rollback();
			if ($documentName !== null) {
				$storedFile = verification_storage_directory() . DIRECTORY_SEPARATOR . $documentName;
				if (is_file($storedFile)) {
					unlink($storedFile);
				}
			}
			throw $exception;
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Create account | AgriBid</title><link rel="stylesheet" href="/agribid/assets/app.css?v=5">
</head>
<body class="auth-page">
	<main class="auth-layout">
		<section class="auth-story">
			<a class="brand" href="/agribid/"><span class="brand-mark">✳</span><span>Agri<span>Bid</span></span></a>
			<p class="eyebrow">GROW YOUR NETWORK</p><h1>Trade fresh.<br>Trade together.</h1>
			<p>Join a local market designed to make crop sourcing more open and dependable.</p>
			<div class="story-points"><span>Buyers post what they need</span><span>Farmers offer fresh local crops</span><span>An administrator reviews your ID</span></div>
		</section>
		<section class="auth-card">
			<div class="auth-heading"><span class="eyebrow">GET STARTED</span><h2>Create your account</h2><p>Choose the workspace that fits you.</p></div>
			<?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
			<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
			<form method="post" enctype="multipart/form-data" class="form-stack">
				<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
				<label>Full name<input type="text" name="full_name" autocomplete="name" maxlength="100" required value="<?= e($_POST['full_name'] ?? '') ?>"></label>
				<label>Email address<input type="email" name="email" autocomplete="email" maxlength="100" required value="<?= e($_POST['email'] ?? '') ?>"></label>
				<label>I am a
					<select name="role" required><option value="">Choose a role</option><option value="Buyer / Trader"<?= ($_POST['role'] ?? '') === 'Buyer / Trader' ? ' selected' : '' ?>>Buyer / Trader</option><option value="Farmer / Seller"<?= ($_POST['role'] ?? '') === 'Farmer / Seller' ? ' selected' : '' ?>>Farmer / Seller</option></select>
				</label>
				<label>Photo of your government ID <span class="field-hint">JPG, PNG, or WebP · up to 5 MB · required</span><input type="file" name="id_image" accept="image/jpeg,image/png,image/webp" required></label>
				<p class="muted-copy">An administrator will review your ID before verifying your account. It is stored privately and only administrators can view it.</p>
				<label>Password <span class="field-hint">(at least 8 characters)</span><input type="password" name="password" autocomplete="new-password" minlength="8" required></label>
				<button class="button button-primary button-wide" type="submit">Create account</button>
			</form>
			<p class="auth-switch">Already have an account? <a href="/agribid/login.php">Log in</a></p>
		</section>
	</main>
</body>
</html>
