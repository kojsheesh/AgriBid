<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');

$stats = [];
$queries = [
	'users' => "SELECT COUNT(*) AS total FROM users",
	'pending_users' => "SELECT COUNT(*) AS total FROM users WHERE status='Pending'",
	'open_rooms' => "SELECT COUNT(*) AS total FROM buyer_rooms WHERE status='Open' AND deadline>NOW()",
	'orders' => "SELECT COUNT(*) AS total FROM room_orders WHERE status IN ('Confirmed','Meet-up scheduled')",
	'completed' => "SELECT COUNT(*) AS total FROM room_orders WHERE status='Completed'",
];
foreach ($queries as $key => $sql) {
	$stats[$key] = (int) $conn->query($sql)->fetch_assoc()['total'];
}
$recent = $conn->query("SELECT r.room_id, r.title, r.status, r.created_at, u.full_name, (SELECT COUNT(*) FROM farmer_offers o WHERE o.room_id=r.room_id) AS offers FROM buyer_rooms r JOIN users u ON u.user_id=r.buyer_id ORDER BY r.created_at DESC LIMIT 8");
$pending = $conn->query("SELECT user_id, full_name, email, role, created_at FROM users WHERE status='Pending' ORDER BY created_at LIMIT 6");
app_header('Admin overview', 'admin', true);
?>
<section class="page-heading"><div><h2>Marketplace overview</h2><p>Monitor member verification, buying rooms, and accepted crop orders.</p></div><a class="button button-muted" href="/agribid/admin/settings.php">Platform settings</a></section>
<section class="stats-grid">
	<div class="stat-card"><span>Total members</span><strong><?= $stats['users'] ?></strong><small>Buyer, farmer, and admin accounts</small></div>
	<div class="stat-card"><span>Verification queue</span><strong><?= $stats['pending_users'] ?></strong><small>Accounts awaiting review</small></div>
	<div class="stat-card"><span>Open buying rooms</span><strong><?= $stats['open_rooms'] ?></strong><small>Requests open to farmer offers</small></div>
	<div class="stat-card"><span>Active meet-ups</span><strong><?= $stats['orders'] ?></strong><small><?= $stats['completed'] ?> trades completed</small></div>
</section>
<?php require dirname(__DIR__) . '/includes/harvest-availability-calendar.php'; ?>
<div class="two-column">
	<section class="panel"><div class="page-heading"><div><h2>Recent buying rooms</h2><p>Newest requests in the marketplace.</p></div><a href="/agribid/admin/rooms.php">View all</a></div>
		<?php if (!$recent->num_rows): ?><div class="empty-state"><strong>No buyer rooms yet</strong>New crop requests will appear in this overview.</div><?php else: ?>
			<div class="data-table-wrap"><table class="data-table"><thead><tr><th>Room</th><th>Buyer</th><th>Status</th><th>Offers</th></tr></thead><tbody><?php while ($room = $recent->fetch_assoc()): ?><tr><td><a href="/agribid/admin/rooms.php?id=<?= (int) $room['room_id'] ?>"><?= e($room['title']) ?></a><small class="field-hint" style="display:block"><?= e(date('M j, Y', strtotime($room['created_at']))) ?></small></td><td><?= e($room['full_name']) ?></td><td><span class="status-pill"><?= e($room['status']) ?></span></td><td><?= (int) $room['offers'] ?></td></tr><?php endwhile; ?></tbody></table></div>
		<?php endif; ?>
	</section>
	<section class="panel"><div class="page-heading"><div><h2>Pending accounts</h2><p>New member accounts awaiting administrator approval.</p></div><a href="/agribid/admin/users.php?status=Pending">Review</a></div>
		<?php if (!$pending->num_rows): ?><div class="empty-state"><strong>All caught up</strong>There are no accounts waiting for verification.</div><?php else: ?><div class="item-row-list"><?php while ($member = $pending->fetch_assoc()): ?><div class="room-title-line"><div><strong><?= e($member['full_name']) ?></strong><div class="field-hint"><?= e($member['role']) ?> · <?= e($member['email']) ?></div></div><span class="status-pill status-pending">Pending</span></div><?php endwhile; ?></div><?php endif; ?>
	</section>
</div>
<?php app_footer(); ?>
