<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');

$sql = "SELECT r.*, u.full_name,
	(SELECT c.crop_name FROM buyer_room_items i JOIN crops c ON c.crop_id = i.crop_id WHERE i.room_id = r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_crop,
	(SELECT c.image_path FROM buyer_room_items i JOIN crops c ON c.crop_id = i.crop_id WHERE i.room_id = r.room_id ORDER BY i.quantity DESC, i.item_id LIMIT 1) AS dominant_image,
	(SELECT COUNT(*) FROM farmer_offers o WHERE o.room_id = r.room_id) AS offer_count
	FROM buyer_rooms r JOIN users u ON u.user_id = r.buyer_id
	WHERE r.buyer_id = ?
	ORDER BY r.created_at DESC";
$roomStmt = $conn->prepare($sql);
$roomStmt->bind_param('i', $user['user_id']);
$roomStmt->execute();
$rooms = $roomStmt->get_result();

app_header('Buyer rooms', 'buyer');
?>
<section class="page-heading buyer-dashboard-heading">
	<div><h2>My buying rooms</h2><p>Only you can view your rooms here. Review their requests, offers, and current status.</p></div>
	<a class="button" href="/agribid/buyer/create-room.php"><span aria-hidden="true"><?= app_icon('plus') ?></span>Post a request</a>
</section>
<?php if ($user['status'] !== 'Verified'): ?>
	<div class="notice notice-warning">Your account is <?= e(strtolower($user['status'])) ?>. You can explore requests, but your own requests are limited until an administrator verifies your account.</div>
<?php endif; ?>
<?php if ($rooms->num_rows === 0): ?>
	<div class="empty-state"><strong>You have not posted any rooms yet</strong>Create a crop request and nearby farmers can respond with offers.</div>
<?php else: ?>
	<div class="room-list">
	<?php while ($room = $rooms->fetch_assoc()): ?>
		<?php
		$image = $room['dominant_image'] ?? '';
		$slug = strtolower((string) $room['dominant_crop']);
		$tone = in_array($slug, ['rice', 'corn'], true) ? 'corn' : (in_array($slug, ['onion', 'garlic'], true) ? 'onion' : (in_array($slug, ['potato'], true) ? 'potato' : 'leafy'));
		$items = $conn->prepare('SELECT c.crop_name, i.quantity, i.unit_price FROM buyer_room_items i JOIN crops c ON c.crop_id = i.crop_id WHERE i.room_id = ? ORDER BY i.quantity DESC');
		$items->bind_param('i', $room['room_id']);
		$items->execute();
		$roomItems = $items->get_result();
		?>
		<article class="room-card">
			<?php if ($image): ?><img class="room-image" src="<?= e($image) ?>" alt="<?= e($room['dominant_crop']) ?> crop — largest quantity in this room"><?php else: ?><div class="room-image room-image-placeholder <?= e($tone) ?>" role="img" aria-label="<?= e($room['dominant_crop']) ?> illustration"><?= e(mb_substr((string) $room['dominant_crop'], 0, 1)) ?></div><?php endif; ?>
			<div class="room-card-content">
				<div class="room-title-line"><h3><?= e($room['title']) ?></h3><span class="status-pill status-<?= e(strtolower($room['status'])) ?>"><?= e($room['status']) ?></span></div>
				<p class="room-description"><?= e($room['description'] ?: 'A local buyer is looking for fresh crops.') ?></p>
				<div class="crop-chip-list">
					<?php while ($item = $roomItems->fetch_assoc()): ?><span class="crop-chip"><?= e($item['crop_name']) ?> <strong><?= e($item['quantity']) ?> kg</strong> · <?= (float) $item['unit_price'] > 0 ? e(money($item['unit_price'])) . '/kg' : 'Price not set' ?></span><?php endwhile; ?>
				</div>
				<div class="room-meta">
					<span>Buyer<strong><?= e($room['full_name']) ?></strong></span>
					<span>Pricing<strong>Fixed per crop</strong></span>
					<span>Meet-up area<strong><?= e(format_room_location($room)) ?></strong></span>
					<span>Deadline<strong><?= e(date('M j, g:i A', strtotime($room['deadline']))) ?></strong></span>
				</div>
				<div class="room-card-actions"><span class="muted-copy"><?= (int) $room['offer_count'] ?> offer<?= (int) $room['offer_count'] === 1 ? '' : 's' ?></span><a class="button button-small" href="/agribid/buyer/room.php?id=<?= (int) $room['room_id'] ?>">View room</a></div>
			</div>
		</article>
		<?php $items->close(); ?>
	<?php endwhile; ?>
	</div>
<?php endif; ?>
<?php $roomStmt->close(); app_footer(); ?>
