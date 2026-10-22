<?php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
	session_start();
}

require_once dirname(__DIR__) . '/db_connect.php';

function e(mixed $value): string
{
	return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function app_icon(string $name): string
{
	$paths = [
		'home' => '<path d="m3 10 9-7 9 7v10a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"/>',
		'orders' => '<path d="M7 3h10l3 3v15H4V3h3z"/><path d="M8 11h8M8 15h8M8 7h5"/>',
		'chat' => '<path d="M21 11.5a8.4 8.4 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.4 8.4 0 0 1-3.8-.9L3 21l1.9-5.7a8.4 8.4 0 0 1-.9-3.8A8.5 8.5 0 0 1 8.7 4a8.4 8.4 0 0 1 3.8-.9h.5a8.5 8.5 0 0 1 8 8z"/>',
		'nearby' => '<path d="M20 10c0 5-8 11-8 11S4 15 4 10a8 8 0 1 1 16 0z"/><circle cx="12" cy="10" r="2.5"/>',
		'bids' => '<path d="m14 6 4 4M3 21l3.5-.8L19 7.7 15.3 4 2.8 16.5 2 20z"/><path d="m12 8 4 4M4 4l1 2M3 9H1M9 3V1"/>',
		'harvest' => '<path d="M20 4c-8 0-14 3-14 10a6 6 0 0 0 6 6c7 0 8-8 8-16z"/><path d="M4 21c2-6 6-10 12-13"/>',
		'overview' => '<rect x="3" y="3" width="8" height="8" rx="1"/><rect x="13" y="3" width="8" height="5" rx="1"/><rect x="13" y="10" width="8" height="11" rx="1"/><rect x="3" y="13" width="8" height="8" rx="1"/>',
		'users' => '<path d="M16 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="10" cy="7" r="4"/><path d="M20 21v-2a4 4 0 0 0-3-3.9M16 3.1a4 4 0 0 1 0 7.8"/>',
		'documents' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h8"/>',
		'rooms' => '<path d="M3 7h18M5 3h14v18H5z"/><path d="M8 11h8M8 15h8"/>',
		'crops' => '<path d="M12 21V9M12 14c-5 0-8-3-8-8 5 0 8 3 8 8zM12 11c0-5 3-8 8-8 0 5-3 8-8 8z"/>',
		'profile' => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
		'notifications' => '<path d="M18 8a6 6 0 0 0-12 0c0 7-3 7-3 9h18c0-2-3-2-3-9M10 21h4"/>',
		'signout' => '<path d="M10 17l5-5-5-5M15 12H3"/><path d="M12 3h6a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-6"/>',
		'plus' => '<path d="M12 5v14M5 12h14"/>',
	];
	$path = $paths[$name] ?? $paths['home'];
	return '<svg class="app-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}

function redirect(string $path): never
{
	header('Location: ' . $path);
	exit;
}

function current_user(): ?array
{
	return $_SESSION['user'] ?? null;
}

function mark_overdue_harvests(mysqli $conn): void
{
	$conn->query("UPDATE farmer_harvests SET status='Harvested' WHERE status='Planned' AND expected_date <= DATE_SUB(CURDATE(), INTERVAL 3 DAY)");
}

function selected_farmer_for_room(mysqli $conn, int $roomId): ?int
{
	$stmt = $conn->prepare('SELECT fo.farmer_id FROM room_orders o JOIN farmer_offers fo ON fo.offer_id=o.offer_id WHERE o.room_id=? LIMIT 1');
	$stmt->bind_param('i', $roomId);
	$stmt->execute();
	$selection = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	return $selection ? (int) $selection['farmer_id'] : null;
}

function require_user(?string $role = null): array
{
	$user = current_user();
	$loginPage = $role === 'System Administrator' ? '/agribid/admin-login.php' : '/agribid/login.php';
	if (!$user) {
		redirect($loginPage);
	}
	$userId = filter_var($user['user_id'] ?? null, FILTER_VALIDATE_INT);
	if (!$userId) {
		unset($_SESSION['user']);
		flash('Sign in again to continue.', 'warning');
		redirect($loginPage);
	}
	$stmt = $GLOBALS['conn']->prepare('SELECT user_id, full_name, email, role, status FROM users WHERE user_id=?');
	$stmt->bind_param('i', $userId);
	$stmt->execute();
	$activeUser = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$activeUser || $activeUser['status'] === 'Suspended') {
		unset($_SESSION['user']);
		flash('This account cannot access the marketplace. Contact an administrator if you believe this is an error.', 'error');
		redirect($loginPage);
	}
	$_SESSION['user'] = $activeUser;
	if ($role !== null && $activeUser['role'] !== $role) {
		redirect('/agribid/index.php');
	}
	return $activeUser;
}

function csrf_token(): string
{
	if (!isset($_SESSION['csrf_token'])) {
		$_SESSION['csrf_token'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
	$submitted = $_POST['csrf_token'] ?? '';
	if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
		http_response_code(419);
		exit('Your session expired. Go back, refresh the page, and try again.');
	}
}

function flash(string $message, string $type = 'success'): void
{
	$_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

function take_flash(): ?array
{
	$message = $_SESSION['flash'] ?? null;
	unset($_SESSION['flash']);
	return $message;
}

function money(mixed $amount): string
{
	return 'PHP ' . number_format((float) $amount, 2);
}

function format_user_address(array $address): string
{
	return format_local_address(
		(string) ($address['city'] ?? ''),
		(string) ($address['barangay'] ?? ''),
		(string) ($address['full_address'] ?? '')
	);
}

function binangonan_barangays(): array
{
	return [
		'Batingan', 'Bilibiran', 'Calumpang', 'Darangan', 'Ithan', 'Kalawaan',
		'Kalinawan', 'Layunan', 'Libid', 'Libis', 'Limbon-limbon', 'Lunsad',
		'Macamot', 'Mahabang Parang', 'Mambog', 'Pag-asa', 'Palangoy', 'Pantok',
		'Pila-pila', 'Pipindan', 'San Carlos', 'Tagpos', 'Tatala', 'Tayuman',
	];
}

function format_local_address(string $city, string $barangay, string $fullAddress): string
{
	return implode(', ', array_filter([
		trim($fullAddress),
		trim($barangay),
		trim($city),
	], static fn (string $part): bool => $part !== ''));
}

function format_room_location(array $room): string
{
	if (empty($room['city']) || empty($room['barangay'])) {
		return trim((string) ($room['delivery_location'] ?? ''));
	}

	return format_local_address(
		(string) ($room['city'] ?? ''),
		(string) ($room['barangay'] ?? ''),
		(string) ($room['delivery_location'] ?? '')
	);
}

function upload_crop_image(string $field = 'crop_image'): ?string
{
	if (!isset($_FILES[$field]) || $_FILES[$field]['error'] === UPLOAD_ERR_NO_FILE) {
		return null;
	}
	if ($_FILES[$field]['error'] !== UPLOAD_ERR_OK) {
		throw new RuntimeException('The crop image could not be uploaded.');
	}
	if ($_FILES[$field]['size'] > 5 * 1024 * 1024) {
		throw new RuntimeException('Crop images must be 5 MB or smaller.');
	}

	$tmpName = $_FILES[$field]['tmp_name'];
	$imageInfo = @getimagesize($tmpName);
	if ($imageInfo === false) {
		throw new RuntimeException('Upload a valid image file.');
	}
	$extensions = [
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_WEBP => 'webp',
	];
	$type = $imageInfo[2];
	if (!isset($extensions[$type])) {
		throw new RuntimeException('Use a JPG, PNG, or WebP crop image.');
	}

	$directory = dirname(__DIR__) . '/uploads/crops';
	if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
		throw new RuntimeException('The crop image upload directory could not be created.');
	}
	$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$type];
	if (!move_uploaded_file($tmpName, $directory . '/' . $filename)) {
		throw new RuntimeException('The crop image could not be saved.');
	}
	return '/agribid/uploads/crops/' . $filename;
}

function upload_offer_image(string $field = 'offer_image'): ?string
{
	$file = $_FILES[$field] ?? null;
	if ($file === null || ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
		return null;
	}
	if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
		throw new RuntimeException('The product photo could not be uploaded.');
	}
	if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
		throw new RuntimeException('Product photos must be 5 MB or smaller.');
	}
	$tmpName = (string) ($file['tmp_name'] ?? '');
	if ($tmpName === '' || !is_uploaded_file($tmpName)) {
		throw new RuntimeException('The selected product photo is not a valid upload.');
	}
	$imageInfo = @getimagesize($tmpName);
	$extensions = [
		IMAGETYPE_JPEG => 'jpg',
		IMAGETYPE_PNG => 'png',
		IMAGETYPE_WEBP => 'webp',
	];
	if ($imageInfo === false || !isset($extensions[$imageInfo[2]])) {
		throw new RuntimeException('Use a valid JPG, PNG, or WebP product photo.');
	}
	$directory = dirname(__DIR__) . '/uploads/offers';
	if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
		throw new RuntimeException('The product photo upload directory could not be created.');
	}
	$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$imageInfo[2]];
	if (!move_uploaded_file($tmpName, $directory . '/' . $filename)) {
		throw new RuntimeException('The product photo could not be saved.');
	}
	return '/agribid/uploads/offers/' . $filename;
}

function verification_storage_directory(): string
{
	$documentRoot = $_SERVER['DOCUMENT_ROOT'] ?? dirname(__DIR__, 2);
	return dirname($documentRoot) . DIRECTORY_SEPARATOR . 'agribid-private';
}

function upload_verification_document(array $file, bool $imagesOnly = false): string
{
	$uploadError = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
	if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
		throw new RuntimeException('Verification documents must be 5 MB or smaller.');
	}
	if ($uploadError === UPLOAD_ERR_NO_FILE) {
		throw new RuntimeException('Choose a document to upload.');
	}
	if ($uploadError !== UPLOAD_ERR_OK) {
		throw new RuntimeException('The verification document upload failed. Please try again.');
	}
	if (($file['size'] ?? 0) > 5 * 1024 * 1024) {
		throw new RuntimeException('Verification documents must be 5 MB or smaller.');
	}
	$tmpName = (string) ($file['tmp_name'] ?? '');
	if ($tmpName === '' || !is_uploaded_file($tmpName)) {
		throw new RuntimeException('The selected document could not be verified as an upload.');
	}
	$finfo = new finfo(FILEINFO_MIME_TYPE);
	$extensions = [
		'application/pdf' => 'pdf',
		'image/jpeg' => 'jpg',
		'image/png' => 'png',
		'image/webp' => 'webp',
	];
	$mimeType = $finfo->file($tmpName);
	if (!isset($extensions[$mimeType]) || ($imagesOnly && $mimeType === 'application/pdf')) {
		throw new RuntimeException($imagesOnly ? 'Use a JPG, PNG, or WebP image of your ID.' : 'Use a PDF, JPG, PNG, or WebP document.');
	}
	if ($mimeType !== 'application/pdf' && @getimagesize($tmpName) === false) {
		throw new RuntimeException('Upload a valid image file.');
	}
	$directory = verification_storage_directory();
	if (!is_dir($directory) && !mkdir($directory, 0700, true) && !is_dir($directory)) {
		throw new RuntimeException('The private document storage directory could not be created.');
	}
	$filename = bin2hex(random_bytes(16)) . '.' . $extensions[$mimeType];
	if (!move_uploaded_file($tmpName, $directory . DIRECTORY_SEPARATOR . $filename)) {
		throw new RuntimeException('The verification document could not be saved.');
	}
	return $filename;
}

function unread_message_count(mysqli $conn, array $user): int
{
	$userId = (int) $user['user_id'];
	if ($user['role'] === 'Buyer / Trader') {
		$stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM room_messages m JOIN buyer_rooms r ON r.room_id=m.room_id JOIN room_orders o ON o.room_id=r.room_id JOIN farmer_offers fo ON fo.offer_id=o.offer_id AND fo.farmer_id=m.sender_id LEFT JOIN room_message_reads mr ON mr.room_id=m.room_id AND mr.user_id=? WHERE r.buyer_id=? AND m.sender_id<>? AND m.created_at>=o.created_at AND m.message_id>COALESCE(mr.last_read_message_id,0)');
	} elseif ($user['role'] === 'Farmer / Seller') {
		$stmt = $conn->prepare('SELECT COUNT(*) AS unread_count FROM room_messages m JOIN buyer_rooms r ON r.room_id=m.room_id JOIN room_orders o ON o.room_id=r.room_id JOIN farmer_offers fo ON fo.offer_id=o.offer_id AND fo.farmer_id=? LEFT JOIN room_message_reads mr ON mr.room_id=m.room_id AND mr.user_id=? WHERE m.sender_id=r.buyer_id AND m.sender_id<>? AND m.created_at>=o.created_at AND m.message_id>COALESCE(mr.last_read_message_id,0)');
		$stmt->bind_param('iii', $userId, $userId, $userId);
	} else {
		return 0;
	}
	$stmt->bind_param('iii', $userId, $userId, $userId);
	$stmt->execute();
	$count = (int) $stmt->get_result()->fetch_assoc()['unread_count'];
	$stmt->close();
	return $count;
}

function mark_room_messages_read(mysqli $conn, int $roomId, int $userId, int $lastReadMessageId): void
{
	if ($lastReadMessageId <= 0) {
		return;
	}
	$stmt = $conn->prepare('INSERT INTO room_message_reads (room_id, user_id, last_read_message_id) VALUES (?, ?, ?) ON DUPLICATE KEY UPDATE last_read_message_id=GREATEST(last_read_message_id,VALUES(last_read_message_id)), updated_at=CURRENT_TIMESTAMP');
	$stmt->bind_param('iii', $roomId, $userId, $lastReadMessageId);
	$stmt->execute();
	$stmt->close();
}

function app_header(string $title, string $area, bool $desktop = false): void
{
	$user = current_user();
	$flash = take_flash();
	$userId = (int) ($user['user_id'] ?? 0);
	$unreadNotifications = 0;
	$unreadMessages = 0;
	if ($userId > 0) {
		$notificationStmt = $GLOBALS['conn']->prepare('SELECT COUNT(*) AS unread_count FROM notifications WHERE user_id=? AND is_read=0');
		$notificationStmt->bind_param('i', $userId);
		$notificationStmt->execute();
		$unreadNotifications = (int) $notificationStmt->get_result()->fetch_assoc()['unread_count'];
		$notificationStmt->close();
		$unreadMessages = unread_message_count($GLOBALS['conn'], $user);
	}
	$GLOBALS['app_unread_messages'] = $unreadMessages;
	$navItems = match ($area) {
		'buyer' => [
			['/agribid/buyer/index.php', 'overview', 'Dashboard'],
			['/agribid/buyer/rooms.php', 'home', 'Rooms'],
			['/agribid/buyer/orders.php', 'orders', 'Orders'],
			['/agribid/buyer/messages.php', 'chat', 'Post-Bid Chatroom'],
		],
		'farmer' => [
			['/agribid/farmer/index.php', 'overview', 'Dashboard'],
			['/agribid/farmer/rooms.php', 'nearby', 'Rooms'],
			['/agribid/farmer/responses.php', 'bids', 'My bids'],
			['/agribid/farmer/harvest.php', 'harvest', 'Harvest'],
			['/agribid/farmer/orders.php', 'orders', 'Orders'],
			['/agribid/farmer/messages.php', 'chat', 'Post-Bid Chatroom'],
		],
		'admin' => [
			['/agribid/admin/index.php', 'overview', 'Overview'],
			['/agribid/admin/users.php', 'users', 'Users'],
			['/agribid/admin/verifications.php', 'documents', 'Documents'],
			['/agribid/admin/rooms.php', 'rooms', 'Rooms'],
			['/agribid/admin/crops.php', 'crops', 'Crops'],
		],
		default => [],
	};
	?>
	<!doctype html>
	<html lang="en">
	<head>
		<meta charset="utf-8">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<meta name="theme-color" content="#f5f7f1">
		<title><?= e($title) ?> | AgriBid</title>
		<link rel="stylesheet" href="/agribid/assets/app.css?v=26">
	</head>
	<body class="<?= e($area) ?>-area<?= $desktop ? ' desktop-area' : '' ?>">
		<div class="app-frame">
			<aside class="sidebar">
				<a class="brand" href="/agribid/index.php"><span class="brand-mark">✳</span><span>Agri<span>Bid</span></span></a>
				<div class="sidebar-label"><?= e(ucfirst($area)) ?> workspace</div>
				<nav aria-label="<?= e(ucfirst($area)) ?> navigation">
					<?php foreach ($navItems as [$href, $icon, $label]): ?>
						<?php $hasUnread = $label === 'Post-Bid Chatroom' && $unreadMessages > 0; ?>
						<a class="nav-link<?= basename($_SERVER['PHP_SELF']) === basename($href) ? ' is-active' : '' ?>" href="<?= e($href) ?>"<?= $hasUnread ? ' aria-label="Post-Bid Chatroom, unread messages"' : '' ?>><span><?= app_icon($icon) ?><?php if ($hasUnread): ?><i class="nav-unread-dot"></i><?php endif; ?></span><?= e($label) ?></a>
					<?php endforeach; ?>
				</nav>
				<div class="sidebar-account">
					<div class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'A', 0, 1))) ?></div>
					<div class="account-copy"><strong><?= e($user['full_name'] ?? 'Guest') ?></strong><small><?= e($user['role'] ?? '') ?></small></div>
					<a class="signout-link" href="/agribid/logout.php" aria-label="Sign out">↗</a>
				</div>
			</aside>
			<div class="main-column">
				<header class="topbar">
					<div><span class="eyebrow">AGRIBID / <?= e(strtoupper($area)) ?></span><h1><?= e($title) ?></h1></div>
					<div class="topbar-account"><a class="button button-muted button-small profile-link" href="/agribid/profile.php" aria-label="Profile"><?= app_icon('profile') ?><span class="topbar-action-label">Profile</span></a><a class="button button-muted button-small notifications-link<?= $unreadNotifications > 0 ? ' has-unread' : '' ?>" href="/agribid/notifications.php" aria-label="<?= $unreadNotifications > 0 ? 'Notifications, unread notifications' : 'Notifications' ?>"><?= app_icon('notifications') ?><span class="topbar-action-label">Notifications</span></a><span class="user-avatar"><?= e(strtoupper(substr($user['full_name'] ?? 'A', 0, 1))) ?></span><span><?= e($user['full_name'] ?? 'Guest') ?></span><a class="button button-muted button-small topbar-signout" href="/agribid/logout.php" aria-label="Sign out"><?= app_icon('signout') ?><span class="topbar-action-label">Sign out</span></a></div>
				</header>
				<main class="content">
					<?php if ($flash): ?><div class="notice notice-<?= e($flash['type']) ?>" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
	<?php
}

function app_footer(): void
{
	$unreadMessages = (int) ($GLOBALS['app_unread_messages'] ?? 0);
	?>
				</main>
				<footer class="app-footer"><a href="/agribid/help.php">Help</a><a href="/agribid/privacy.php">Privacy</a></footer>
			</div>
			<nav class="mobile-nav" aria-label="Main navigation">
				<?php foreach (match (current_user()['role'] ?? '') {
					'Buyer / Trader' => [['/agribid/buyer/index.php', 'overview', 'Dashboard'], ['/agribid/buyer/rooms.php', 'home', 'Rooms'], ['/agribid/buyer/orders.php', 'orders', 'Orders'], ['/agribid/buyer/messages.php', 'chat', 'Post-Bid Chatroom']],
					'Farmer / Seller' => [['/agribid/farmer/index.php', 'overview', 'Dashboard'], ['/agribid/farmer/rooms.php', 'nearby', 'Rooms'], ['/agribid/farmer/responses.php', 'bids', 'My bids'], ['/agribid/farmer/harvest.php', 'harvest', 'Harvest'], ['/agribid/farmer/orders.php', 'orders', 'Orders'], ['/agribid/farmer/messages.php', 'chat', 'Post-Bid Chatroom']],
					default => [['/agribid/admin/index.php', 'overview', 'Overview'], ['/agribid/admin/users.php', 'users', 'Users'], ['/agribid/admin/verifications.php', 'documents', 'Docs'], ['/agribid/admin/rooms.php', 'rooms', 'Rooms'], ['/agribid/admin/crops.php', 'crops', 'Crops']],
				} as [$href, $icon, $label]): ?>
					<?php $hasUnread = $label === 'Post-Bid Chatroom' && $unreadMessages > 0; ?>
					<?php $mobileLabel = match ($label) { 'Dashboard' => 'Home', 'My bids' => 'Bids', 'Post-Bid Chatroom' => 'Chat', default => $label }; ?>
					<a class="<?= basename($_SERVER['PHP_SELF']) === basename($href) ? 'is-active' : '' ?>" href="<?= e($href) ?>" aria-label="<?= e($hasUnread ? 'Post-Bid Chatroom, unread messages' : $label) ?>"><span><?= app_icon($icon) ?><?php if ($hasUnread): ?><i class="nav-unread-dot"></i><?php endif; ?></span><span class="mobile-nav-label"><?= e($mobileLabel) ?></span></a>
				<?php endforeach; ?>
			</nav>
		</div>
	</body>
	</html>
	<?php
}

function log_notification(mysqli $conn, int $userId, string $title, string $message): void
{
	$stmt = $conn->prepare('INSERT INTO notifications (user_id, title, message) VALUES (?, ?, ?)');
	$stmt->bind_param('iss', $userId, $title, $message);
	$stmt->execute();
	$stmt->close();
}
