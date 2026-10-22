<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$roomId = filter_var($_POST['room_id'] ?? null, FILTER_VALIDATE_INT);
	$status = (string) ($_POST['status'] ?? '');
	if ($roomId && in_array($status, ['Open', 'Closed', 'Cancelled'], true)) {
		$stmt = $conn->prepare("UPDATE buyer_rooms SET status=? WHERE room_id=? AND status IN ('Open','Closed','Cancelled')");
		$stmt->bind_param('si', $status, $roomId);
		$stmt->execute();
		$changed = $stmt->affected_rows > 0;
		$stmt->close();
		flash($changed ? 'Buying room status updated.' : 'Room not found or its status is unchanged.', $changed ? 'success' : 'warning');
	}
	redirect('/agribid/admin/rooms.php');
}
$rooms = $conn->query("SELECT r.room_id, r.title, r.status, r.deadline, r.created_at, b.full_name, (SELECT COUNT(*) FROM farmer_offers o WHERE o.room_id=r.room_id) AS offer_count, (SELECT COUNT(*) FROM buyer_room_items i WHERE i.room_id=r.room_id) AS crop_count, (SELECT COUNT(*) FROM buyer_room_items i WHERE i.room_id=r.room_id AND i.unit_price>0) AS priced_crop_count FROM buyer_rooms r JOIN users b ON b.user_id=r.buyer_id ORDER BY r.created_at DESC");
app_header('Manage buying rooms', 'admin', true);
?>
<div class="page-heading"><div><h2>Marketplace rooms</h2><p>Monitor buyer requests and close rooms when moderation or deadline handling is needed.</p></div></div>
<?php if (!$rooms->num_rows): ?><div class="empty-state"><strong>No buying rooms found</strong>Buyer requests will appear here once posted.</div><?php else: ?>
	<div class="data-table-wrap panel"><table class="data-table"><thead><tr><th>Room</th><th>Buyer</th><th>Priced crops</th><th>Offers</th><th>Deadline</th><th>Status</th><th>Moderation</th></tr></thead><tbody>
	<?php while ($room = $rooms->fetch_assoc()): ?><tr><td><?= e($room['title']) ?><small class="field-hint" style="display:block">#<?= (int) $room['room_id'] ?></small></td><td><?= e($room['full_name']) ?></td><td><?= (int) $room['priced_crop_count'] ?> / <?= (int) $room['crop_count'] ?></td><td><?= (int) $room['offer_count'] ?></td><td><?= e(date('M j, Y · g:i A', strtotime($room['deadline']))) ?></td><td><span class="status-pill"><?= e($room['status']) ?></span></td><td><form method="post" class="table-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="room_id" value="<?= (int) $room['room_id'] ?>"><select class="input" name="status" style="width:auto;min-height:32px;padding:4px" aria-label="Room status"><?php foreach (['Open','Closed','Cancelled'] as $status): ?><option value="<?= e($status) ?>"<?= $room['status'] === $status ? ' selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select><button class="button button-small" type="submit">Save</button></form></td></tr><?php endwhile; ?>
	</tbody></table></div>
<?php endif; ?>
<?php app_footer(); ?>
