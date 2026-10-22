<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
	$action = (string) ($_POST['action'] ?? '');
	if ($orderId && $action === 'complete') {
		$complete = $conn->prepare("UPDATE room_orders o JOIN farmer_offers fo ON fo.offer_id=o.offer_id SET o.farmer_completed_at=CURRENT_TIMESTAMP, o.status=IF(o.buyer_completed_at IS NOT NULL,'Completed',o.status) WHERE o.order_id=? AND fo.farmer_id=? AND o.farmer_completed_at IS NULL AND o.status IN ('Confirmed','Meet-up scheduled')");
		$complete->bind_param('ii', $orderId, $user['user_id']);
		$complete->execute();
		$changed = $complete->affected_rows;
		$complete->close();
		if ($changed) {
			$orderStateStmt = $conn->prepare('SELECT o.status, o.buyer_completed_at, r.buyer_id, r.title FROM room_orders o JOIN farmer_offers fo ON fo.offer_id=o.offer_id JOIN buyer_rooms r ON r.room_id=o.room_id WHERE o.order_id=? AND fo.farmer_id=?');
			$orderStateStmt->bind_param('ii', $orderId, $user['user_id']);
			$orderStateStmt->execute();
			$orderState = $orderStateStmt->get_result()->fetch_assoc();
			$orderStateStmt->close();
			if ($orderState) {
				$notice = $orderState['status'] === 'Completed'
					? 'Both parties confirmed completion of ' . $orderState['title'] . '.'
					: 'The farmer marked ' . $orderState['title'] . ' complete. Confirm completion from your orders page.';
				log_notification($conn, (int) $orderState['buyer_id'], 'Order completion confirmation', $notice);
			}
			flash($orderState && $orderState['status'] === 'Completed'
				? 'Both you and the buyer confirmed. The transaction is complete.'
				: 'You confirmed completion. The transaction will be complete after the buyer confirms too.');
		} else {
			flash('No order was changed. It may have already been updated.', 'warning');
		}
		redirect('/agribid/farmer/orders.php');
	}
}
$stmt = $conn->prepare('SELECT o.order_id, o.status, o.meetup_at, o.meetup_location, o.buyer_completed_at, o.farmer_completed_at, r.title, r.room_id, b.full_name AS buyer_name, fo.total_amount FROM room_orders o JOIN farmer_offers fo ON fo.offer_id=o.offer_id JOIN buyer_rooms r ON r.room_id=o.room_id JOIN users b ON b.user_id=r.buyer_id WHERE fo.farmer_id=? ORDER BY o.created_at DESC');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$orders = $stmt->get_result();
app_header('Farmer orders', 'farmer');
?>
<div class="page-heading"><div><h2>Accepted offers and meet-ups</h2><p>Coordinate with buyers after an offer is accepted.</p></div></div>
<?php if (!$orders->num_rows): ?><div class="empty-state"><strong>No accepted offers yet</strong>When a buyer chooses your offer, meet-up details will appear here.</div><?php else: ?><div class="room-list"><?php while ($order = $orders->fetch_assoc()): ?><article class="panel order-card"><div class="room-title-line"><div><h3><?= e($order['title']) ?></h3><p class="room-description">Buyer: <?= e($order['buyer_name']) ?></p></div><span class="status-pill status-<?= e(strtolower(str_replace(' ', '-', $order['status']))) ?>"><?= e($order['status']) ?></span></div><div class="room-meta"><span>Offer total<strong><?= e(money($order['total_amount'])) ?></strong></span><span>Order reference<strong>#<?= (int) $order['order_id'] ?></strong></span><span>Meet-up time<strong><?= $order['meetup_at'] ? e(date('M j, Y · g:i A', strtotime($order['meetup_at']))) : 'Awaiting buyer' ?></strong></span><span>Location<strong><?= e($order['meetup_location']) ?></strong></span></div><?php if ($order['status'] !== 'Completed' && $order['status'] !== 'Cancelled'): ?><p class="field-hint"><?= $order['farmer_completed_at'] ? 'You confirmed completion.' : 'You have not confirmed completion yet.' ?> <?= $order['buyer_completed_at'] ? 'The buyer confirmed completion.' : 'Waiting for the buyer to confirm.' ?></p><?php if (!$order['farmer_completed_at']): ?><form method="post" class="table-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>"><button class="button button-muted button-small" name="action" value="complete">Mark transaction complete</button></form><?php endif; ?><?php endif; ?><div class="room-card-actions"><span></span><a class="button button-small" href="/agribid/farmer/room.php?id=<?= (int) $order['room_id'] ?>#messages">Open Post-Bid Chatroom</a></div></article><?php endwhile; ?></div><?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
