<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
$sql = "SELECT r.*, u.full_name,
	(SELECT c.crop_name FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_crop,
	(SELECT c.image_path FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_image,
	(SELECT COUNT(*) FROM farmer_offers o WHERE o.room_id=r.room_id AND o.status='Pending') AS offer_count,
	(SELECT o.status FROM farmer_offers o WHERE o.room_id=r.room_id AND o.farmer_id=? LIMIT 1) AS my_offer_status,
	(SELECT o.offer_id FROM farmer_offers o WHERE o.room_id=r.room_id AND o.farmer_id=? LIMIT 1) AS my_offer_id
	FROM buyer_rooms r JOIN users u ON u.user_id=r.buyer_id
	WHERE r.status='Open' AND r.deadline>NOW() AND r.city='Binangonan'
	ORDER BY r.created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param('ii', $user['user_id'], $user['user_id']);
$stmt->execute();
$rooms = $stmt->get_result();
app_header('Buyer rooms', 'farmer');
?>
<section class="page-heading"><div><h2>Browse buyer rooms</h2><p>Respond to requests with the crop quantities you can supply at the buyer’s fixed price.</p></div></section>
<?php if ($user['status'] !== 'Verified'): ?><div class="notice notice-warning">Your account is <?= e(strtolower($user['status'])) ?>. You can browse requests, but you must be verified before submitting offers.</div><?php endif; ?>
<?php if (!$rooms->num_rows): ?><div class="empty-state"><strong>No open requests right now</strong>Check back soon for new local buyer rooms.</div><?php else: ?>
	<div class="room-list">
	<?php while ($room = $rooms->fetch_assoc()): ?>
		<?php
		$dominantCrop = (string) $room['dominant_crop'];
		$tone = in_array($dominantCrop, ['RICE', 'CORN'], true) ? 'corn' : (in_array($dominantCrop, ['ONION', 'GARLIC'], true) ? 'onion' : (in_array($dominantCrop, ['POTATO'], true) ? 'potato' : 'leafy'));
		$items = $conn->prepare('SELECT c.crop_name, i.quantity, i.unit_price FROM buyer_room_items i JOIN crops c ON c.crop_id=i.crop_id WHERE i.room_id=? ORDER BY i.quantity DESC');
		$items->bind_param('i', $room['room_id']);
		$items->execute();
		$roomItems = $items->get_result();
		?>
		<article class="room-card">
			<?php if ($room['dominant_image']): ?><img class="room-image" src="<?= e($room['dominant_image']) ?>" alt="<?= e($dominantCrop) ?> — largest requested quantity"><?php else: ?><div class="room-image room-image-placeholder <?= e($tone) ?>" role="img" aria-label="<?= e($dominantCrop) ?> crop illustration"><?= e(mb_substr($dominantCrop, 0, 1)) ?></div><?php endif; ?>
			<div class="room-card-content">
				<div class="room-title-line"><h3><?= e($room['title']) ?></h3><?php if ($room['my_offer_status']): ?><span class="status-pill status-<?= e(strtolower($room['my_offer_status'])) ?>"><?= e($room['my_offer_status']) ?></span><?php else: ?><span class="status-pill"><?= (int) $room['offer_count'] ?> offers</span><?php endif; ?></div>
				<p class="room-description"><?= e($room['description'] ?: 'The buyer is looking for fresh local produce.') ?></p>
				<div class="crop-chip-list"><?php while ($item = $roomItems->fetch_assoc()): ?><span class="crop-chip"><?= e($item['crop_name']) ?> <strong><?= e($item['quantity']) ?> kg</strong> · <?= (float) $item['unit_price'] > 0 ? e(money($item['unit_price'])) . '/kg' : 'Price not set' ?></span><?php endwhile; ?></div>
				<div class="room-meta"><span>Buyer<strong><?= e($room['full_name']) ?></strong></span><span>Price<strong>Fixed by buyer</strong></span><span>Meet-up area<strong><?= e(format_room_location($room)) ?></strong></span><span>Offer deadline<strong><?= e(date('M j, g:i A', strtotime($room['deadline']))) ?></strong></span></div>
				<div class="room-card-actions"><span class="muted-copy">Main crop: <?= e($dominantCrop) ?></span><a class="button button-small" href="/agribid/farmer/room.php?id=<?= (int) $room['room_id'] ?>"><?= $room['my_offer_id'] ? 'View response' : 'View and offer' ?></a></div>
			</div>
		</article>
		<?php $items->close(); ?>
	<?php endwhile; ?>
	</div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
