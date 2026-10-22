<?php
require_once __DIR__ . '/includes/app.php';
$destination = (current_user()['role'] ?? '') === 'System Administrator'
	? '/agribid/admin-login.php'
	: '/agribid/login.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
	$cookie = session_get_cookie_params();
	setcookie(session_name(), '', time() - 42000, $cookie['path'], $cookie['domain'], $cookie['secure'], $cookie['httponly']);
}
session_destroy();
redirect($destination);
