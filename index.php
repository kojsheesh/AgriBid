<?php
require_once __DIR__ . '/includes/app.php';

$user = current_user();
if ($user) {
	$destination = match ($user['role']) {
		'Buyer / Trader' => '/agribid/buyer/index.php',
		'Farmer / Seller' => '/agribid/farmer/index.php',
		'System Administrator' => '/agribid/admin/index.php',
		default => '/agribid/login.php',
	};
	redirect($destination);
}
redirect('/agribid/login.php');
