<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');
$roomId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$roomId) {
	http_response_code(404);
	exit('Buying room not found.');
}
$roomStmt = $conn->prepare("SELECT r.*, u.full_name,
	(SELECT c.crop_name FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_crop,
	(SELECT c.image_path FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_image
	FROM buyer_rooms r JOIN users u ON u.user_id=r.buyer_id WHERE r.room_id=?");
$roomStmt->bind_param('i', $roomId);
$roomStmt->execute();
$room = $roomStmt->get_result()->fetch_assoc();
$roomStmt->close();
if (!$room || (int) $room['buyer_id'] !== (int) $user['user_id']) {
	http_response_code(404);
	exit('Buying room not found.');
}
$selectedFarmerId = selected_farmer_for_room($conn, $roomId);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? '');
	if ($action === 'accept_offer') {
		$offerId = filter_var($_POST['offer_id'] ?? null, FILTER_VALIDATE_INT);
		if (!$offerId || $room['status'] !== 'Open' || strtotime($room['deadline']) <= time()) {
			flash('This room is no longer accepting offers.', 'error');
			redirect('/agribid/buyer/room.php?id=' . $roomId);
		}
		$pricesCheck = $conn->prepare('SELECT COUNT(*) AS item_count, SUM(unit_price <= 0) AS missing_prices FROM buyer_room_items WHERE room_id=?');
		$pricesCheck->bind_param('i', $roomId);
		$pricesCheck->execute();
		$priceStatus = $pricesCheck->get_result()->fetch_assoc();
		$pricesCheck->close();
		if (!$priceStatus || (int) $priceStatus['item_count'] === 0 || (int) $priceStatus['missing_prices'] > 0) {
			flash('This older request has no buyer-set price for every crop and cannot accept offers.', 'error');
			redirect('/agribid/buyer/room.php?id=' . $roomId);
		}
		try {
			$conn->begin_transaction();
			$lock = $conn->prepare("SELECT o.offer_id, o.farmer_id, o.status, o.total_amount FROM farmer_offers o JOIN users f ON f.user_id=o.farmer_id WHERE o.offer_id=? AND o.room_id=? AND f.status='Verified' FOR UPDATE");
			$lock->bind_param('ii', $offerId, $roomId);
			$lock->execute();
			$offer = $lock->get_result()->fetch_assoc();
			$lock->close();
			if (!$offer || $offer['status'] !== 'Pending') {
				throw new RuntimeException('That offer is no longer available.');
			}
			$offerTotalStmt = $conn->prepare('SELECT COUNT(*) AS item_count, SUM(fi.quantity * fi.unit_price) AS counter_total, SUM(fi.quantity < bri.quantity OR fi.unit_price > bri.unit_price) AS invalid_counter, SUM(bri.unit_price <= 0) AS missing_prices FROM farmer_offer_items fi JOIN buyer_room_items bri ON bri.room_id=? AND bri.crop_id=fi.crop_id WHERE fi.offer_id=?');
			$offerTotalStmt->bind_param('ii', $roomId, $offerId);
			$offerTotalStmt->execute();
			$offerTotals = $offerTotalStmt->get_result()->fetch_assoc();
			$offerTotalStmt->close();
			if (!$offerTotals || (int) $offerTotals['item_count'] === 0 || (int) $offerTotals['invalid_counter'] > 0 || (int) $offerTotals['missing_prices'] > 0 || (float) $offerTotals['counter_total'] <= 0) {
				throw new RuntimeException('This counter-offer no longer matches the requested quantities and prices.');
			}
			$counterTotal = (float) $offerTotals['counter_total'];
			$updateOfferTotal = $conn->prepare('UPDATE farmer_offers SET total_amount=? WHERE offer_id=?');
			$updateOfferTotal->bind_param('di', $counterTotal, $offerId);
			$updateOfferTotal->execute();
			$updateOfferTotal->close();
			$updateRoom = $conn->prepare("UPDATE buyer_rooms SET status='Awarded' WHERE room_id=? AND status='Open'");
			$updateRoom->bind_param('i', $roomId);
			$updateRoom->execute();
			if ($updateRoom->affected_rows !== 1) {
				throw new RuntimeException('Another offer has already been selected.');
			}
			$updateRoom->close();
			$accept = $conn->prepare("UPDATE farmer_offers SET status=IF(offer_id=?,'Accepted','Declined') WHERE room_id=? AND status='Pending'");
			$accept->bind_param('ii', $offerId, $roomId);
			$accept->execute();
			$accept->close();
			$location = format_room_location($room);
			$order = $conn->prepare("INSERT INTO room_orders (room_id, offer_id, status, meetup_location) VALUES (?, ?, 'Confirmed', ?)");
			$order->bind_param('iis', $roomId, $offerId, $location);
			$order->execute();
			$order->close();
			log_notification($conn, (int) $offer['farmer_id'], 'Your offer was accepted', 'The buyer accepted your offer for ' . $room['title'] . '. Arrange the meet-up in your orders.');
			$conn->commit();
			flash('Offer accepted. Your order is ready for meet-up arrangements.');
			redirect('/agribid/buyer/orders.php');
		} catch (Throwable $exception) {
			$conn->rollback();
			if ($exception instanceof RuntimeException) {
				flash($exception->getMessage(), 'error');
				redirect('/agribid/buyer/room.php?id=' . $roomId);
			}
			throw $exception;
		}
	}
	if ($action === 'send_message') {
		$message = trim((string) ($_POST['message'] ?? ''));
		if ($selectedFarmerId === null) {
			flash('The Post-Bid Chatroom opens after you accept a farmer offer.', 'warning');
		} elseif ($message === '' || mb_strlen($message) > 2000) {
			flash('Write a message of up to 2,000 characters.', 'error');
		} else {
			$stmt = $conn->prepare('INSERT INTO room_messages (room_id, sender_id, message) VALUES (?, ?, ?)');
			$senderId = (int) $user['user_id'];
			$stmt->bind_param('iis', $roomId, $senderId, $message);
			$stmt->execute();
			$stmt->close();
			log_notification($conn, $selectedFarmerId, 'New Post-Bid Chatroom message', 'There is a new message in ' . $room['title'] . '.');
			flash('Message sent.');
		}
		redirect('/agribid/buyer/room.php?id=' . $roomId . '#messages');
	}
}

$itemsStmt = $conn->prepare('SELECT c.crop_name, c.image_path, i.quantity, i.unit_price FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=? ORDER BY i.quantity DESC');
$itemsStmt->bind_param('i', $roomId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();
$hasFixedPrices = count($items) > 0 && count(array_filter($items, static fn (array $item): bool => (float) $item['unit_price'] <= 0)) === 0;
$offersStmt = $conn->prepare("SELECT o.*, u.full_name FROM farmer_offers o JOIN users u ON u.user_id=o.farmer_id WHERE o.room_id=? ORDER BY FIELD(o.status,'Pending','Accepted','Declined','Withdrawn'), o.total_amount ASC, o.created_at ASC");
$offersStmt->bind_param('i', $roomId);
$offersStmt->execute();
$offers = $offersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$offersStmt->close();
$pendingOfferTotals = array_map(
	static fn (array $offer): float => (float) $offer['total_amount'],
	array_filter($offers, static fn (array $offer): bool => $offer['status'] === 'Pending')
);
$lowestPendingOffer = $pendingOfferTotals ? min($pendingOfferTotals) : null;
$leadingOfferCount = $lowestPendingOffer === null ? 0 : count(array_filter(
	$pendingOfferTotals,
	static fn (float $total): bool => round($total, 2) === round($lowestPendingOffer, 2)
));
$messagesStmt = $conn->prepare('SELECT m.message_id, m.message, m.created_at, m.sender_id, u.full_name FROM room_messages m JOIN users u ON u.user_id=m.sender_id JOIN room_orders o ON o.room_id=m.room_id WHERE m.room_id=? AND (? > 0 AND m.sender_id IN (?,?) AND m.created_at>=o.created_at) ORDER BY m.message_id');
$selectedFarmerForQuery = $selectedFarmerId ?? 0;
$messagesStmt->bind_param('iiii', $roomId, $selectedFarmerForQuery, $user['user_id'], $selectedFarmerForQuery);
$messagesStmt->execute();
$messages = $messagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$messagesStmt->close();
$lastReadMessageId = $messages ? (int) $messages[count($messages) - 1]['message_id'] : 0;
if ($selectedFarmerId !== null) {
	mark_room_messages_read($conn, $roomId, (int) $user['user_id'], $lastReadMessageId);
}
$dominant = $items[0]['crop_name'] ?? '';
$tone = in_array($dominant, ['RICE', 'CORN'], true) ? 'corn' : (in_array($dominant, ['ONION', 'GARLIC'], true) ? 'onion' : 'leafy');

app_header($room['title'], 'buyer');
?>
<div class="page-heading"><div><span class="status-pill"><?= e($room['status']) ?></span><h2><?= e($room['title']) ?></h2><p><?= e($room['description'] ?: 'Review crop requests and offers.') ?></p></div><a class="button button-muted button-small" href="/agribid/buyer/rooms.php">Back to rooms</a></div>
<div class="two-column">
	<div>
		<article class="room-card">
			<?php if ($room['dominant_image']): ?><img class="room-image" src="<?= e($room['dominant_image']) ?>" alt="<?= e($dominant) ?> — dominant requested crop"><?php else: ?><div class="room-image room-image-placeholder <?= e($tone) ?>" aria-label="<?= e($dominant) ?> illustration"><?= e(mb_substr($dominant, 0, 1)) ?></div><?php endif; ?>
			<div class="room-card-content">
				<h3>Requested crops and fixed prices</h3><div class="crop-chip-list"><?php foreach ($items as $item): ?><span class="crop-chip"><?= e($item['crop_name']) ?> <strong><?= e($item['quantity']) ?> kg</strong> · <?= (float) $item['unit_price'] > 0 ? e(money($item['unit_price'])) . '/kg' : 'Price not set' ?></span><?php endforeach; ?></div>
				<div class="room-meta"><span>Meet-up area<strong><?= e(format_room_location($room)) ?></strong></span><span>Deadline<strong><?= e(date('M j, Y · g:i A', strtotime($room['deadline']))) ?></strong></span><span>Pricing<strong>Fixed by buyer</strong></span><span>Posted by<strong><?= e($room['full_name']) ?></strong></span></div>
			</div>
		</article>
		<?php if (!$hasFixedPrices): ?><div class="notice notice-warning">This older request does not have buyer-set prices for every crop. New offers and acceptance are disabled; please post a new request with fixed prices.</div><?php endif; ?>
		<section class="panel" id="messages">
			<h2>Post-Bid Chatroom</h2>
			<?php if ($selectedFarmerId === null): ?><p class="muted-copy">Accept a farmer offer to open a private chat with your selected farmer.</p>
			<?php else: ?>
				<p class="field-hint">Only you and the farmer whose offer you accepted can see this conversation.</p>
				<?php if (!$messages): ?><p class="muted-copy">Start the conversation with your selected farmer.</p><?php else: ?><div class="message-list"><?php foreach ($messages as $message): ?><div class="message-bubble<?= (int) $message['sender_id'] === (int) $user['user_id'] ? ' is-mine' : '' ?>"><?= nl2br(e($message['message'])) ?><small><?= e($message['full_name']) ?> · <?= e(date('M j, g:i A', strtotime($message['created_at']))) ?></small></div><?php endforeach; ?></div><?php endif; ?>
				<form method="post" class="form-stack" style="margin-top:12px"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="send_message"><label>Send a message<textarea name="message" maxlength="2000" required placeholder="Message your selected farmer"></textarea></label><button class="button button-small" type="submit">Send message</button></form>
			<?php endif; ?>
		</section>
	</div>
	<section class="panel buyer-offers-panel">
		<h2>Farmer offers <span class="field-hint">(<?= count($offers) ?>)</span></h2>
		<?php if (!$offers): ?><div class="empty-state"><strong>No offers yet</strong>Farmers will appear here when they respond to your request.</div><?php else: ?>
			<div class="farmer-offer-list">
			<?php foreach ($offers as $offer): ?>
				<?php $isLeadingOffer = $room['status'] === 'Open' && $offer['status'] === 'Pending' && $lowestPendingOffer !== null && round((float) $offer['total_amount'], 2) === round($lowestPendingOffer, 2); ?>
				<article class="farmer-offer-card">
					<div class="room-title-line"><h3><?= e($offer['full_name']) ?></h3><div class="farmer-offer-badges"><?php if ($isLeadingOffer): ?><span class="status-pill status-leading"><?= $leadingOfferCount > 1 ? 'Joint leading offer' : 'Leading offer' ?></span><?php endif; ?><span class="status-pill status-<?= e(strtolower($offer['status'])) ?>"><?= e($offer['status']) ?></span></div></div>
					<p class="room-description"><?= e($offer['message'] ?: 'Offer submitted for your crop request.') ?></p>
					<?php if (!empty($offer['image_path'])): ?><a class="offer-quality-link" href="<?= e($offer['image_path']) ?>" target="_blank" rel="noopener"><img class="offer-quality-image" src="<?= e($offer['image_path']) ?>" alt="<?= e($offer['full_name']) ?>’s product quality photo"><span>View product photo</span></a><?php endif; ?>
					<div class="farmer-offer-total"><span>Total offer</span><strong><?= e(money($offer['total_amount'])) ?></strong></div>
					<?php
					$offerItemsStmt = $conn->prepare('SELECT c.crop_name, fi.quantity, fi.unit_price, ri.quantity AS requested_quantity, ri.unit_price AS requested_price FROM farmer_offer_items fi JOIN crops c ON c.crop_id=fi.crop_id JOIN buyer_room_items ri ON ri.room_id=? AND ri.crop_id=fi.crop_id WHERE fi.offer_id=?');
					$offerItemsStmt->bind_param('ii', $roomId, $offer['offer_id']);
					$offerItemsStmt->execute();
					$offerItems = $offerItemsStmt->get_result();
					?>
					<div class="farmer-offer-crops"><?php while ($offerItem = $offerItems->fetch_assoc()): ?><div class="farmer-offer-crop"><strong><?= e($offerItem['crop_name']) ?></strong><span><b><?= e($offerItem['quantity']) ?> kg</b><small><?= (float) $offerItem['quantity'] > (float) $offerItem['requested_quantity'] ? 'Buyer requested ' . e($offerItem['requested_quantity']) . ' kg' : 'Buyer quantity' ?></small></span><span><b><?= e(money($offerItem['unit_price'])) ?>/kg</b><small><?= (float) $offerItem['unit_price'] < (float) $offerItem['requested_price'] ? 'Below buyer price' : 'Buyer price' ?></small></span></div><?php endwhile; ?></div>
					<?php $offerItemsStmt->close(); ?>
					<?php if ($room['status'] === 'Open' && $offer['status'] === 'Pending' && $hasFixedPrices): ?><form method="post" class="farmer-offer-actions" onsubmit="return confirm('Accept this farmer’s counter-offer at the quantities and prices shown, and close the room to other offers?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="accept_offer"><input type="hidden" name="offer_id" value="<?= (int) $offer['offer_id'] ?>"><button class="button" type="submit">Accept offer</button></form><?php endif; ?>
				</article>
			<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</section>
</div>
<?php app_footer(); ?>
