<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$stmt = $conn->prepare('UPDATE notifications SET is_read=1 WHERE user_id=?');
	$stmt->bind_param('i', $user['user_id']);
	$stmt->execute();
	$stmt->close();
	flash('Notifications marked as read.');
	redirect('/agribid/notifications.php');
}
$stmt = $conn->prepare('SELECT notif_id, title, message, is_read, created_at FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$notifications = $stmt->get_result();
app_header('Notifications', match ($user['role']) {
	'Buyer / Trader' => 'buyer',
	'Farmer / Seller' => 'farmer',
	default => 'admin',
});
?>
<div class="page-heading notification-heading"><div><h2>Your activity</h2><p>Offers, meet-up updates, and account changes related to your marketplace work.</p></div>
	<form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><button class="button button-muted button-small" type="submit">Mark all as read</button></form>
</div>
<?php if (!$notifications->num_rows): ?><div class="empty-state"><strong>You are all caught up</strong>Updates from the marketplace will show here.</div><?php else: ?>
	<div class="notification-list"><?php while ($notification = $notifications->fetch_assoc()): ?><article class="panel notification-card<?= $notification['is_read'] ? '' : ' notification-unread' ?>"><div class="room-title-line"><h3><?= e($notification['title']) ?></h3><?php if (!$notification['is_read']): ?><span class="status-pill">New</span><?php endif; ?></div><p><?= e($notification['message']) ?></p><time datetime="<?= e(date(DATE_ATOM, strtotime($notification['created_at']))) ?>"><?= e(date('M j, Y · g:i A', strtotime($notification['created_at']))) ?></time></article><?php endwhile; ?></div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
