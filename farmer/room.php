<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
$roomId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if (!$roomId) {
	http_response_code(404);
	exit('Buying room not found.');
}
$stmt = $conn->prepare("SELECT r.*, u.full_name,
	(SELECT c.crop_name FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_crop,
	(SELECT c.image_path FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_image
	FROM buyer_rooms r JOIN users u ON u.user_id=r.buyer_id WHERE r.room_id=?");
$stmt->bind_param('i', $roomId);
$stmt->execute();
$room = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$room) {
	http_response_code(404);
	exit('Buying room not found.');
}
$selectedFarmerId = selected_farmer_for_room($conn, $roomId);
$isSelectedFarmer = $selectedFarmerId === (int) $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? '');
	if ($action === 'submit_offer') {
		if ($user['status'] !== 'Verified') {
			flash('Your account must be verified before you can submit an offer.', 'warning');
			redirect('/agribid/farmer/room.php?id=' . $roomId);
		}
		if ($room['status'] !== 'Open' || strtotime($room['deadline']) <= time()) {
			flash('This room is no longer accepting offers.', 'error');
			redirect('/agribid/farmer/room.php?id=' . $roomId);
		}
		$includedCrops = $_POST['include_crop'] ?? [];
		$counterQuantities = $_POST['counter_quantity'] ?? [];
		$counterPrices = $_POST['counter_price'] ?? [];
		if (!is_array($includedCrops) || !is_array($counterQuantities) || !is_array($counterPrices)) {
			flash('Check the counter-offer quantities and prices and try again.', 'error');
			redirect('/agribid/farmer/room.php?id=' . $roomId);
		}
		$requestedStmt = $conn->prepare('SELECT crop_id, quantity, unit_price FROM buyer_room_items WHERE room_id=?');
		$requestedStmt->bind_param('i', $roomId);
		$requestedStmt->execute();
		$requestedRows = $requestedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
		$requestedStmt->close();
		$validItems = [];
		$total = 0.0;
		$hasImprovement = false;
		foreach ($requestedRows as $requested) {
			$id = (int) $requested['crop_id'];
			$includeValue = $includedCrops[$id] ?? 'no';
			$quantityValue = $counterQuantities[$id] ?? '';
			$priceValue = $counterPrices[$id] ?? '';
			if (!is_scalar($includeValue) || !in_array((string) $includeValue, ['yes', 'no'], true)
				|| !is_scalar($quantityValue) || !is_scalar($priceValue)) {
				$validItems = [];
				break;
			}
			if ((string) $includeValue === 'no') {
				continue;
			}
			$quantityInput = trim((string) $quantityValue);
			$priceInput = trim((string) $priceValue);
			if ($quantityInput === '' && $priceInput === '') {
				continue;
			}
			$requestedQuantity = (float) $requested['quantity'];
			$requestedPrice = (float) $requested['unit_price'];
			$quantity = $quantityInput === '' ? $requestedQuantity : filter_var($quantityInput, FILTER_VALIDATE_FLOAT);
			$unitPrice = $priceInput === '' ? $requestedPrice : filter_var($priceInput, FILTER_VALIDATE_FLOAT);
			if ($quantity === false || ($quantityInput !== '' && ($quantity < $requestedQuantity || $quantity > 999999))
				|| $unitPrice === false || $unitPrice <= 0 || ($priceInput !== '' && $unitPrice > $requestedPrice)) {
				$validItems = [];
				break;
			}
			$hasImprovement = $hasImprovement || (float) $quantity > $requestedQuantity || (float) $unitPrice < $requestedPrice;
			$validItems[] = ['crop_id' => $id, 'quantity' => (float) $quantity, 'unit_price' => (float) $unitPrice];
			$total += round((float) $quantity * (float) $unitPrice, 2);
		}
		if (!$validItems || !$hasImprovement || $total <= 0) {
			flash('For at least one crop, offer the same or more quantity and the same or lower price, improving at least one of the two. Leave the other field blank to use the buyer’s amount.', 'error');
			redirect('/agribid/farmer/room.php?id=' . $roomId);
		}
		$newImagePath = null;
		$previousImagePath = null;
		try {
			$newImagePath = upload_offer_image();
			$conn->begin_transaction();
			$existsStmt = $conn->prepare('SELECT offer_id, status, image_path FROM farmer_offers WHERE room_id=? AND farmer_id=? FOR UPDATE');
			$farmerId = (int) $user['user_id'];
			$existsStmt->bind_param('ii', $roomId, $farmerId);
			$existsStmt->execute();
			$existing = $existsStmt->get_result()->fetch_assoc();
			$existsStmt->close();
			if ($existing && $existing['status'] !== 'Pending') {
				throw new RuntimeException('You have already responded to this room.');
			}
			if ($existing) {
				$offerId = (int) $existing['offer_id'];
				$previousImagePath = $existing['image_path'];
				$imagePath = $newImagePath ?? $existing['image_path'];
				$update = $conn->prepare('UPDATE farmer_offers SET total_amount=?, message=NULL, image_path=? WHERE offer_id=?');
				$update->bind_param('dsi', $total, $imagePath, $offerId);
				$update->execute();
				$update->close();
				$delete = $conn->prepare('DELETE FROM farmer_offer_items WHERE offer_id=?');
				$delete->bind_param('i', $offerId);
				$delete->execute();
				$delete->close();
			} else {
				$insert = $conn->prepare("INSERT INTO farmer_offers (room_id, farmer_id, total_amount, message, image_path, status) VALUES (?, ?, ?, NULL, ?, 'Pending')");
				$insert->bind_param('iids', $roomId, $farmerId, $total, $newImagePath);
				$insert->execute();
				$offerId = $conn->insert_id;
				$insert->close();
			}
			$itemInsert = $conn->prepare('INSERT INTO farmer_offer_items (offer_id, crop_id, quantity, unit_price) VALUES (?, ?, ?, ?)');
			foreach ($validItems as $item) {
				$cropId = (int) $item['crop_id'];
				$quantity = (float) $item['quantity'];
				$unitPrice = (float) $item['unit_price'];
				$itemInsert->bind_param('iidd', $offerId, $cropId, $quantity, $unitPrice);
				$itemInsert->execute();
			}
			$itemInsert->close();
			log_notification($conn, (int) $room['buyer_id'], 'New farmer offer', $user['full_name'] . ' submitted an offer for ' . $room['title'] . '.');
			$conn->commit();
			if ($newImagePath !== null && $previousImagePath && $previousImagePath !== $newImagePath && str_starts_with($previousImagePath, '/agribid/uploads/offers/')) {
				$oldFile = dirname(__DIR__) . '/uploads/offers/' . basename($previousImagePath);
				if (is_file($oldFile) && !unlink($oldFile)) {
					error_log('Could not remove replaced offer image: ' . $oldFile);
				}
			}
			flash('Your offer was sent to the buyer.');
			redirect('/agribid/farmer/room.php?id=' . $roomId);
		} catch (Throwable $exception) {
			$conn->rollback();
			if ($newImagePath !== null) {
				$storedFile = dirname(__DIR__) . '/uploads/offers/' . basename($newImagePath);
				if (is_file($storedFile) && !unlink($storedFile)) {
					error_log('Could not remove failed offer image upload: ' . $storedFile);
				}
			}
			if ($exception instanceof RuntimeException) {
				flash($exception->getMessage(), 'error');
				redirect('/agribid/farmer/room.php?id=' . $roomId);
			}
			throw $exception;
		}
	}
	if ($action === 'send_message') {
		$message = trim((string) ($_POST['message'] ?? ''));
		if (!$isSelectedFarmer) {
			flash('The Post-Bid Chatroom is available only to the farmer selected by the buyer.', 'warning');
		} elseif ($message === '' || mb_strlen($message) > 2000) {
			flash('Write a message of up to 2,000 characters.', 'error');
		} else {
			$senderId = (int) $user['user_id'];
			$messageStmt = $conn->prepare('INSERT INTO room_messages (room_id, sender_id, message) VALUES (?, ?, ?)');
			$messageStmt->bind_param('iis', $roomId, $senderId, $message);
			$messageStmt->execute();
			$messageStmt->close();
			log_notification($conn, (int) $room['buyer_id'], 'New room message', $user['full_name'] . ' sent a message about ' . $room['title'] . '.');
			flash('Message sent to the buyer.');
		}
		redirect('/agribid/farmer/room.php?id=' . $roomId . '#messages');
	}
}

$itemsStmt = $conn->prepare('SELECT c.crop_id, c.crop_name, c.image_path, i.quantity, i.unit_price FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=? ORDER BY i.quantity DESC');
$itemsStmt->bind_param('i', $roomId);
$itemsStmt->execute();
$items = $itemsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$itemsStmt->close();
$offerStmt = $conn->prepare('SELECT * FROM farmer_offers WHERE room_id=? AND farmer_id=?');
$offerStmt->bind_param('ii', $roomId, $user['user_id']);
$offerStmt->execute();
$myOffer = $offerStmt->get_result()->fetch_assoc();
$offerStmt->close();
$messagesStmt = $conn->prepare('SELECT m.message_id, m.message, m.created_at, m.sender_id, u.full_name FROM room_messages m JOIN users u ON u.user_id=m.sender_id JOIN room_orders o ON o.room_id=m.room_id WHERE m.room_id=? AND (?=1 AND m.sender_id IN (?,?) AND m.created_at>=o.created_at) ORDER BY m.message_id');
$selectedFlag = $isSelectedFarmer ? 1 : 0;
$messagesStmt->bind_param('iiii', $roomId, $selectedFlag, $room['buyer_id'], $user['user_id']);
$messagesStmt->execute();
$messages = $messagesStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$messagesStmt->close();
if ($isSelectedFarmer) {
	$lastReadMessageId = $messages ? (int) $messages[count($messages) - 1]['message_id'] : 0;
	mark_room_messages_read($conn, $roomId, (int) $user['user_id'], $lastReadMessageId);
}
$dominant = (string) $room['dominant_crop'];
$tone = in_array($dominant, ['RICE', 'CORN'], true) ? 'corn' : (in_array($dominant, ['ONION', 'GARLIC'], true) ? 'onion' : 'leafy');

app_header($room['title'], 'farmer');
?>
<div class="page-heading farmer-room-heading"><div><span class="status-pill"><?= e($room['status']) ?></span><h2><?= e($room['title']) ?></h2><p><?= e($room['description'] ?: 'Review the buyer’s crop requirements and submit a supply offer.') ?></p></div><a class="button button-muted button-small" href="/agribid/farmer/rooms.php">Back to rooms</a></div>
<div class="two-column">
	<div>
		<article class="room-card farmer-room-summary">
			<?php if ($room['dominant_image']): ?><img class="room-image" src="<?= e($room['dominant_image']) ?>" alt="<?= e($dominant) ?> — largest requested crop"><?php else: ?><div class="room-image room-image-placeholder <?= e($tone) ?>" role="img" aria-label="<?= e($dominant) ?> crop illustration"><?= e(mb_substr($dominant, 0, 1)) ?></div><?php endif; ?>
			<div class="room-card-content"><div class="room-title-line"><h3>Requested crops</h3><span class="status-pill"><?= count($items) ?> crop<?= count($items) === 1 ? '' : 's' ?></span></div>
				<div class="item-row-list" style="margin:12px 0"><?php foreach ($items as $item): ?><div class="crop-chip"><?= e($item['crop_name']) ?> <strong><?= e($item['quantity']) ?> kg</strong> · <?= e(money($item['unit_price'])) ?>/kg</div><?php endforeach; ?></div>
				<div class="room-meta"><span>Buyer<strong><?= e($room['full_name']) ?></strong></span><span>Meet-up area<strong><?= e(format_room_location($room)) ?></strong></span><span>Deadline<strong><?= e(date('M j, Y · g:i A', strtotime($room['deadline']))) ?></strong></span></div>
			</div>
		</article>
	</div>
	<div>
		<?php if ($myOffer && $myOffer['status'] === 'Accepted'): ?>
			<section class="panel farmer-own-offer"><h2>Your offer · <?= e($myOffer['status']) ?></h2><p class="muted-copy">Current offer: <strong><?= e(money($myOffer['total_amount'])) ?></strong></p>
				<?php if ($myOffer['image_path']): ?><img class="offer-quality-image" src="<?= e($myOffer['image_path']) ?>" alt="Your product quality photo"><?php endif; ?>
				<?php $mine = $conn->prepare('SELECT c.crop_name, i.quantity, i.unit_price FROM farmer_offer_items i JOIN crops c ON c.crop_id=i.crop_id JOIN buyer_room_items r ON r.room_id=? AND r.crop_id=i.crop_id WHERE i.offer_id=?'); $mine->bind_param('ii', $roomId, $myOffer['offer_id']); $mine->execute(); $myItems = $mine->get_result(); ?>
				<div class="crop-chip-list"><?php while ($item = $myItems->fetch_assoc()): ?><span class="crop-chip"><?= e($item['crop_name']) ?> <?= e($item['quantity']) ?> kg · <?= e(money($item['unit_price'])) ?>/kg</span><?php endwhile; ?></div><?php $mine->close(); ?>
			</section>
		<?php elseif ($room['status'] === 'Open' && strtotime($room['deadline']) > time()): ?>
			<section class="panel">
				<h2><?= $myOffer ? 'Update your offer' : 'Make an offer' ?></h2><p class="offer-intro">For each crop, match or improve the buyer’s terms: same or more kilograms, and same or lower price. Leave one field blank to match that term; leave both blank to skip the crop.</p>
				<?php if ($user['status'] !== 'Verified'): ?><div class="notice notice-warning">Your account must be verified before you can make offers.</div><?php elseif (array_filter($items, static fn (array $item): bool => (float) $item['unit_price'] <= 0)): ?><div class="notice notice-warning">This older request does not have fixed prices set for every crop, so it cannot accept new offers.</div><?php else: ?>
				<form method="post" enctype="multipart/form-data" class="form-stack offer-form">
					<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="submit_offer">
					<?php foreach ($items as $item):
						$current = null;
						if ($myOffer) { $q = $conn->prepare('SELECT quantity, unit_price FROM farmer_offer_items WHERE offer_id=? AND crop_id=?'); $q->bind_param('ii', $myOffer['offer_id'], $item['crop_id']); $q->execute(); $current = $q->get_result()->fetch_assoc(); $q->close(); }
						$currentQuantityCounter = $current && (float) $current['quantity'] > (float) $item['quantity'] ? $current['quantity'] : '';
						$currentPriceCounter = $current && (float) $current['unit_price'] < (float) $item['unit_price'] ? $current['unit_price'] : '';
					?>
						<section class="offer-crop" data-buyer-quantity="<?= e($item['quantity']) ?>" data-buyer-price="<?= e($item['unit_price']) ?>">
							<div class="offer-crop-heading"><h3><?= e($item['crop_name']) ?></h3><p>Buyer requested <strong><?= e($item['quantity']) ?> kg</strong> at <strong><?= e(money($item['unit_price'])) ?>/kg</strong></p></div>
							<fieldset class="offer-crop-choice">
								<legend>Include this crop?</legend>
								<label><input type="radio" name="include_crop[<?= (int) $item['crop_id'] ?>]" value="yes"<?= $current ? ' checked' : (!$myOffer ? ' checked' : '') ?>><span>Add to offer</span></label>
								<label><input type="radio" name="include_crop[<?= (int) $item['crop_id'] ?>]" value="no"<?= $myOffer && !$current ? ' checked' : '' ?>><span>Skip crop</span></label>
							</fieldset>
							<div class="offer-crop-fields">
								<label>Your quantity (kg)<span class="field-hint">Same or more than requested</span><input name="counter_quantity[<?= (int) $item['crop_id'] ?>]" type="number" min="<?= e(number_format((float) $item['quantity'], 2, '.', '')) ?>" max="999999" step="0.01" placeholder="Match buyer quantity" value="<?= e($currentQuantityCounter) ?>"></label>
								<label>Your price per kg<span class="field-hint">Same or less than requested</span><input name="counter_price[<?= (int) $item['crop_id'] ?>]" type="number" min="0.01" max="<?= e(number_format((float) $item['unit_price'], 2, '.', '')) ?>" step="0.01" placeholder="Match buyer price" value="<?= e($currentPriceCounter) ?>"></label>
							</div>
						</section>
					<?php endforeach; ?>
					<?php if ($myOffer && $myOffer['image_path']): ?><p class="muted-copy">Current product photo. Choose a new one below to replace it.</p><img class="offer-quality-image" src="<?= e($myOffer['image_path']) ?>" alt="Current product quality photo"><?php endif; ?>
					<label>Product quality photo <span class="field-hint">(optional · JPG, PNG, or WebP · up to 5 MB)</span><input type="file" name="offer_image" accept="image/jpeg,image/png,image/webp"></label>
					<div class="offer-total-preview" aria-live="polite"><span>Your offer total</span><strong data-offer-total>PHP 0.00</strong><small data-offer-total-hint>Enter a quantity or price for at least one crop to calculate your total.</small></div>
					<button class="button" type="submit"><?= $myOffer ? 'Update offer' : 'Send offer' ?></button>
				</form><?php endif; ?>
			</section>
		<?php else: ?><div class="notice notice-warning">This room is no longer accepting offers.</div><?php endif; ?>
	</div>
</div>
<section class="panel farmer-room-chat" id="messages">
	<h2>Post-Bid Chatroom</h2>
	<?php if (!$isSelectedFarmer): ?><p class="muted-copy">The chat opens only after the buyer accepts your offer. Only the selected farmer and buyer can view or send messages.</p>
	<?php else: ?>
		<p class="field-hint">Only you and the buyer can see this conversation.</p>
		<?php if (!$messages): ?><p class="muted-copy">Start the conversation with the buyer who selected your offer.</p><?php else: ?><div class="message-list"><?php foreach ($messages as $message): ?><div class="message-bubble<?= (int) $message['sender_id'] === (int) $user['user_id'] ? ' is-mine' : '' ?>"><?= nl2br(e($message['message'])) ?><small><?= e($message['full_name']) ?> · <?= e(date('M j, g:i A', strtotime($message['created_at']))) ?></small></div><?php endforeach; ?></div><?php endif; ?>
		<form method="post" class="form-stack" style="margin-top:12px"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="send_message"><label>Send a message<textarea name="message" maxlength="2000" required></textarea></label><button class="button button-small" type="submit">Send message</button></form>
	<?php endif; ?>
</section>
<script>
(() => {
	const form = document.querySelector('.offer-form');
	if (!form) return;
	const totalOutput = form.querySelector('[data-offer-total]');
	const hintOutput = form.querySelector('[data-offer-total-hint]');
	const money = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
	const updateTotal = () => {
		let totalCents = 0;
		let cropCount = 0;
		form.querySelectorAll('.offer-crop').forEach((crop) => {
			const includeCrop = crop.querySelector('input[name^="include_crop"]:checked')?.value === 'yes';
			crop.classList.toggle('is-skipped', !includeCrop);
			crop.querySelectorAll('.offer-crop-fields input').forEach((input) => {
				input.disabled = !includeCrop;
			});
			if (!includeCrop) return;
			const quantityInput = crop.querySelector('input[name^="counter_quantity"]');
			const priceInput = crop.querySelector('input[name^="counter_price"]');
			const quantityValue = quantityInput.value.trim();
			const priceValue = priceInput.value.trim();
			const quantity = quantityValue ? Number(quantityValue) : Number(crop.dataset.buyerQuantity);
			const price = priceValue ? Number(priceValue) : Number(crop.dataset.buyerPrice);
			if (!Number.isFinite(quantity) || !Number.isFinite(price)) return;
			totalCents += Math.round(quantity * price * 100);
			cropCount += 1;
		});
		totalOutput.textContent = `PHP ${money.format(totalCents / 100)}`;
		hintOutput.textContent = cropCount
			? `${cropCount} crop${cropCount === 1 ? '' : 's'} included. A blank field uses the buyer’s amount.`
			: 'Enter a quantity or price for at least one crop to calculate your total.';
	};
	form.addEventListener('input', updateTotal);
	form.addEventListener('change', updateTotal);
	updateTotal();
})();
</script>
<?php app_footer(); ?>
