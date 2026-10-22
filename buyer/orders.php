<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
	$action = (string) ($_POST['action'] ?? '');
	if ($orderId && in_array($action, ['schedule', 'complete', 'cancel'], true)) {
		if ($action === 'schedule') {
			$meetupInput = trim((string) ($_POST['meetup_at'] ?? ''));
			$meetup = DateTime::createFromFormat('Y-m-d\TH:i', $meetupInput);
			$location = trim((string) ($_POST['meetup_location'] ?? ''));
			if (!$meetup || $meetup <= new DateTime() || $location === '' || mb_strlen($location) > 180) {
				flash('Choose a future meet-up time and a valid location.', 'error');
				redirect('/agribid/buyer/orders.php');
			}
			$status = 'Meet-up scheduled';
			$at = $meetup->format('Y-m-d H:i:s');
			$stmt = $conn->prepare("UPDATE room_orders o JOIN buyer_rooms r ON r.room_id=o.room_id SET o.status=?, o.meetup_at=?, o.meetup_location=? WHERE o.order_id=? AND r.buyer_id=? AND o.buyer_completed_at IS NULL AND o.farmer_completed_at IS NULL AND o.status IN ('Confirmed','Meet-up scheduled')");
			$stmt->bind_param('sssii', $status, $at, $location, $orderId, $user['user_id']);
		} elseif ($action === 'complete') {
			$stmt = $conn->prepare("UPDATE room_orders o JOIN buyer_rooms r ON r.room_id=o.room_id SET o.buyer_completed_at=CURRENT_TIMESTAMP, o.status=IF(o.farmer_completed_at IS NOT NULL,'Completed',o.status) WHERE o.order_id=? AND r.buyer_id=? AND o.buyer_completed_at IS NULL AND o.status IN ('Confirmed','Meet-up scheduled')");
			$stmt->bind_param('ii', $orderId, $user['user_id']);
		} else {
			$status = 'Cancelled';
			$stmt = $conn->prepare("UPDATE room_orders o JOIN buyer_rooms r ON r.room_id=o.room_id SET o.status=? WHERE o.order_id=? AND r.buyer_id=? AND o.buyer_completed_at IS NULL AND o.farmer_completed_at IS NULL AND o.status IN ('Confirmed','Meet-up scheduled')");
			$stmt->bind_param('sii', $status, $orderId, $user['user_id']);
		}
		$stmt->execute();
		$changed = $stmt->affected_rows;
		$stmt->close();
		$completionStatus = null;
		if ($changed) {
			$recipient = $conn->prepare('SELECT f.farmer_id, r.title, o.buyer_completed_at, o.farmer_completed_at, o.status FROM room_orders o JOIN farmer_offers f ON f.offer_id=o.offer_id JOIN buyer_rooms r ON r.room_id=o.room_id WHERE o.order_id=?');
			$recipient->bind_param('i', $orderId);
			$recipient->execute();
			$orderNotice = $recipient->get_result()->fetch_assoc();
			$recipient->close();
			if ($orderNotice) {
				$completionStatus = $orderNotice['status'];
				$noticeText = match ($action) {
					'schedule' => 'The buyer updated the meet-up details for ' . $orderNotice['title'] . '.',
					'complete' => $orderNotice['status'] === 'Completed'
						? 'Both parties confirmed completion of ' . $orderNotice['title'] . '.'
						: 'The buyer marked ' . $orderNotice['title'] . ' complete. Confirm completion from your orders page.',
					default => 'The buyer cancelled the order for ' . $orderNotice['title'] . '.',
				};
				log_notification($conn, (int) $orderNotice['farmer_id'], 'Order updated', $noticeText);
			}
		}
		$message = $action === 'complete' && $changed
			? ($completionStatus === 'Completed' ? 'Both you and the farmer confirmed. The transaction is complete.' : 'You confirmed completion. The transaction will be complete after the farmer confirms too.')
			: 'Order updated.';
		flash($changed ? $message : 'No order was changed. It may have already been updated.', $changed ? 'success' : 'warning');
		redirect('/agribid/buyer/orders.php');
	}
}
$stmt = $conn->prepare('SELECT o.*, r.title, r.delivery_location AS room_location, f.full_name AS farmer_name, f.user_id AS farmer_id, fo.total_amount FROM room_orders o JOIN buyer_rooms r ON r.room_id=o.room_id JOIN farmer_offers fo ON fo.offer_id=o.offer_id JOIN users f ON f.user_id=fo.farmer_id WHERE r.buyer_id=? ORDER BY o.created_at DESC');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$orders = $stmt->get_result();
app_header('My orders', 'buyer');
?>
<div class="page-heading"><div><h2>Orders and meet-ups</h2><p>Track accepted offers and coordinate a place and time to collect your crops.</p></div></div>
<?php if (!$orders->num_rows): ?><div class="empty-state"><strong>No orders yet</strong>Accept a farmer's offer from one of your buying rooms to create an order.</div><?php else: ?>
	<div class="room-list"><?php while ($order = $orders->fetch_assoc()): ?>
		<article class="panel order-card">
			<div class="room-title-line"><div><h3><?= e($order['title']) ?></h3><p class="room-description">Farmer: <?= e($order['farmer_name']) ?></p></div><span class="status-pill status-<?= e(strtolower(str_replace(' ', '-', $order['status']))) ?>"><?= e($order['status']) ?></span></div>
			<div class="room-meta"><span>Agreed amount<strong><?= e(money($order['total_amount'])) ?></strong></span><span>Meet-up location<strong><?= e($order['meetup_location']) ?></strong></span><span>Scheduled for<strong><?= $order['meetup_at'] ? e(date('M j, Y · g:i A', strtotime($order['meetup_at']))) : 'Choose a time' ?></strong></span><span>Order reference<strong>#<?= (int) $order['order_id'] ?></strong></span></div>
			<?php if (in_array($order['status'], ['Confirmed','Meet-up scheduled'], true) && !$order['buyer_completed_at'] && !$order['farmer_completed_at']): ?>
				<form method="post" class="form-stack" style="margin-top:13px">
					<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>">
					<div class="form-grid"><label>Meet-up date and time<input type="datetime-local" name="meetup_at" min="<?= e(date('Y-m-d\TH:i', time() + 600)) ?>" value="<?= $order['meetup_at'] ? e(date('Y-m-d\TH:i', strtotime($order['meetup_at']))) : '' ?>" required></label><label>Meet-up point<input name="meetup_location" maxlength="180" value="<?= e($order['meetup_location']) ?>" required></label></div>
					<div class="table-actions"><button class="button button-small" name="action" value="schedule">Save meet-up</button><button class="button button-danger button-small" name="action" value="cancel">Cancel order</button></div>
				</form>
			<?php endif; ?>
			<?php if ($order['status'] !== 'Completed' && $order['status'] !== 'Cancelled'): ?>
				<p class="field-hint"><?= $order['buyer_completed_at'] ? 'You confirmed completion.' : 'You have not confirmed completion yet.' ?> <?= $order['farmer_completed_at'] ? 'The farmer confirmed completion.' : 'Waiting for the farmer to confirm.' ?></p>
				<?php if (!$order['buyer_completed_at']): ?><form method="post" class="table-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="order_id" value="<?= (int) $order['order_id'] ?>"><button class="button button-muted button-small" name="action" value="complete">Mark transaction complete</button></form><?php endif; ?>
			<?php endif; ?>
			<div class="room-card-actions"><span></span><a class="button button-muted button-small" href="/agribid/buyer/room.php?id=<?= (int) $order['room_id'] ?>#messages">Open Post-Bid Chatroom</a></div>
		</article>
	<?php endwhile; ?></div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
