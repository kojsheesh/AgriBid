<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
$stmt = $conn->prepare("SELECT r.room_id, r.title, r.status, b.full_name AS buyer_name, (SELECT COUNT(*) FROM room_messages m WHERE m.room_id=r.room_id AND m.sender_id IN (r.buyer_id,o.farmer_id) AND m.created_at>=ro.created_at) AS message_count, (SELECT MAX(m.created_at) FROM room_messages m WHERE m.room_id=r.room_id AND m.sender_id IN (r.buyer_id,o.farmer_id) AND m.created_at>=ro.created_at) AS last_message FROM buyer_rooms r JOIN room_orders ro ON ro.room_id=r.room_id JOIN farmer_offers o ON o.offer_id=ro.offer_id JOIN users b ON b.user_id=r.buyer_id WHERE o.farmer_id=? ORDER BY COALESCE(last_message,r.created_at) DESC");
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$rooms = $stmt->get_result();
app_header('Post-Bid Chatroom', 'farmer');
?>
<div class="page-heading"><div><h2>Post-Bid Chatroom</h2><p>Private conversations with buyers who selected your offers.</p></div></div>
<?php if (!$rooms->num_rows): ?><div class="empty-state"><strong>No selected offers yet</strong>When a buyer accepts your offer, the private chatroom will appear here.</div><?php else: ?><div class="room-list"><?php while ($room = $rooms->fetch_assoc()): ?><a class="panel conversation-link" href="/agribid/farmer/room.php?id=<?= (int) $room['room_id'] ?>#messages"><div class="room-title-line"><h3><?= e($room['title']) ?></h3><span class="status-pill"><?= e($room['status']) ?></span></div><p class="room-description">Buyer: <?= e($room['buyer_name']) ?> · <?= (int) $room['message_count'] ?> messages<?= $room['last_message'] ? ' · Updated ' . e(date('M j, g:i A', strtotime($room['last_message']))) : ' · Start the conversation' ?></p></a><?php endwhile; ?></div><?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
