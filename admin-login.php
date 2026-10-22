<?php
require_once __DIR__ . '/includes/app.php';

if (current_user()) {
	redirect('/agribid/index.php');
}

$error = '';
$flash = take_flash();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$email = strtolower(trim((string) ($_POST['email'] ?? '')));
	$password = (string) ($_POST['password'] ?? '');
	if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
		$error = 'Enter your email and password.';
	} else {
		$stmt = $conn->prepare('SELECT user_id, full_name, email, password_hash, role, status FROM users WHERE email = ? LIMIT 1');
		$stmt->bind_param('s', $email);
		$stmt->execute();
		$user = $stmt->get_result()->fetch_assoc();
		$stmt->close();
		if (!$user || !password_verify($password, $user['password_hash']) || $user['role'] !== 'System Administrator') {
			$error = 'Administrator email or password is incorrect.';
		} elseif ($user['status'] === 'Suspended') {
			$error = 'This account is suspended. Contact an administrator.';
		} else {
			session_regenerate_id(true);
			unset($user['password_hash']);
			$_SESSION['user'] = $user;
			redirect('/agribid/admin/index.php');
		}
	}
}
?>
<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
	<title>Administrator sign in | AgriBid</title><link rel="stylesheet" href="/agribid/assets/app.css?v=5">
</head>
<body class="auth-page">
	<main class="auth-layout">
		<section class="auth-story">
			<a class="brand" href="/agribid/"><span class="brand-mark">✳</span><span>Agri<span>Bid</span></span></a>
			<p class="eyebrow">PLATFORM ADMINISTRATION</p>
			<h1>Keep the marketplace growing.</h1>
			<p>Manage member verification, buying rooms, and marketplace activity from your administrator workspace.</p>
			<div class="story-points"><span>01&nbsp; Review member accounts</span><span>02&nbsp; Monitor marketplace activity</span><span>03&nbsp; Keep the community moving</span></div>
		</section>
		<section class="auth-card">
			<div class="auth-heading"><span class="eyebrow">ADMINISTRATOR ACCESS</span><h2>Sign in to AgriBid</h2><p>Use your administrator account to continue.</p></div>
			<?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div><?php endif; ?>
			<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
			<form method="post" class="form-stack">
				<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
				<label>Email address<input type="email" name="email" autocomplete="email" required value="<?= e($_POST['email'] ?? '') ?>"></label>
				<label>Password<input type="password" name="password" autocomplete="current-password" required></label>
				<button class="button button-primary button-wide" type="submit">Sign in</button>
			</form>
			<p class="auth-switch"><a href="/agribid/login.php">Back to buyer and farmer login</a></p>
		</section>
	</main>
</body>
</html>
