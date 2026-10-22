<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
$stmt = $conn->prepare('SELECT o.*, r.title, r.status AS room_status, r.deadline, b.full_name AS buyer_name FROM farmer_offers o JOIN buyer_rooms r ON r.room_id=o.room_id JOIN users b ON b.user_id=r.buyer_id WHERE o.farmer_id=? ORDER BY o.created_at DESC');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$offers = $stmt->get_result();
app_header('My offers', 'farmer');
?>
<div class="page-heading"><div><h2>Your responses</h2><p>Follow every offer you have sent to buyers and see its current room status.</p></div></div>
<?php if (!$offers->num_rows): ?><div class="empty-state"><strong>You have not sent any offers yet</strong>Explore nearby buyer rooms to find crops you can supply.</div><?php else: ?>
	<div class="data-table-wrap panel"><table class="data-table"><thead><tr><th>Buyer room</th><th>Buyer</th><th>Offer total</th><th>Offer status</th><th>Room status</th><th>Deadline</th><th></th></tr></thead><tbody>
	<?php while ($offer = $offers->fetch_assoc()): ?><tr><td><?= e($offer['title']) ?></td><td><?= e($offer['buyer_name']) ?></td><td><?= e(money($offer['total_amount'])) ?></td><td><span class="status-pill status-<?= e(strtolower($offer['status'])) ?>"><?= e($offer['status']) ?></span></td><td><?= e($offer['room_status']) ?></td><td><?= e(date('M j, Y', strtotime($offer['deadline']))) ?></td><td><a class="button button-muted button-small" href="/agribid/farmer/room.php?id=<?= (int) $offer['room_id'] ?>">View room</a></td></tr><?php endwhile; ?>
	</tbody></table></div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
