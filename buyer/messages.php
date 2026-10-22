<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');
$stmt = $conn->prepare("SELECT r.room_id, r.title, r.status, r.created_at, fo.farmer_id, f.full_name AS farmer_name, (SELECT COUNT(*) FROM room_messages m WHERE m.room_id=r.room_id AND m.sender_id IN (r.buyer_id,fo.farmer_id) AND m.created_at>=o.created_at) AS message_count, (SELECT MAX(m.created_at) FROM room_messages m WHERE m.room_id=r.room_id AND m.sender_id IN (r.buyer_id,fo.farmer_id) AND m.created_at>=o.created_at) AS last_message FROM buyer_rooms r JOIN room_orders o ON o.room_id=r.room_id JOIN farmer_offers fo ON fo.offer_id=o.offer_id JOIN users f ON f.user_id=fo.farmer_id WHERE r.buyer_id=? ORDER BY COALESCE(last_message,r.created_at) DESC");
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$rooms = $stmt->get_result();
app_header('Post-Bid Chatroom', 'buyer');
?>
<div class="page-heading"><div><h2>Post-Bid Chatroom</h2><p>Private conversations with farmers whose offers you accepted.</p></div></div>
<?php if (!$rooms->num_rows): ?><div class="empty-state"><strong>No selected farmers yet</strong>After you accept an offer, the private chatroom will appear here.</div><?php else: ?><div class="room-list"><?php while ($room = $rooms->fetch_assoc()): ?><a class="panel conversation-link" href="/agribid/buyer/room.php?id=<?= (int) $room['room_id'] ?>#messages"><div class="room-title-line"><h3><?= e($room['title']) ?></h3><span class="status-pill"><?= e($room['status']) ?></span></div><p class="room-description">Selected farmer: <?= e($room['farmer_name']) ?> · <?= (int) $room['message_count'] ?> messages<?= $room['last_message'] ? ' · Updated ' . e(date('M j, g:i A', strtotime($room['last_message']))) : ' · Start the conversation' ?></p></a><?php endwhile; ?></div><?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
