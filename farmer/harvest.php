<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');
mark_overdue_harvests($conn);
$crops = $conn->query('SELECT crop_id, crop_name FROM crops WHERE is_active=1 ORDER BY crop_name')->fetch_all(MYSQLI_ASSOC);
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? 'create');
	if ($action === 'update_status') {
		$harvestId = filter_var($_POST['harvest_id'] ?? null, FILTER_VALIDATE_INT);
		$status = (string) ($_POST['status'] ?? '');
		if (!$harvestId || !in_array($status, ['Planned', 'Harvested', 'Cancelled'], true)) {
			$error = 'Choose a valid harvest entry and status.';
		} else {
			$stmt = $conn->prepare('UPDATE farmer_harvests SET status=? WHERE harvest_id=? AND farmer_id=?');
			$stmt->bind_param('sii', $status, $harvestId, $user['user_id']);
			$stmt->execute();
			$changed = $stmt->affected_rows > 0;
			$stmt->close();
			flash($changed ? 'Harvest entry updated.' : 'No harvest entry was changed.', $changed ? 'success' : 'warning');
			redirect('/agribid/farmer/harvest.php');
		}
	} else {
		$cropId = filter_var($_POST['crop_id'] ?? null, FILTER_VALIDATE_INT);
		$quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_FLOAT);
		$dateInput = (string) ($_POST['expected_date'] ?? '');
		$date = DateTime::createFromFormat('!Y-m-d', $dateInput);
		$notes = trim((string) ($_POST['notes'] ?? ''));
		$validCropIds = array_map('intval', array_column($crops, 'crop_id'));
		$today = new DateTime('today');
		if (!$cropId || !in_array($cropId, $validCropIds, true) || $quantity === false || $quantity <= 0 || $quantity > 999999 || !$date || $date->format('Y-m-d') !== $dateInput || $date < $today || mb_strlen($notes) > 250) {
			$error = 'Enter a valid crop, positive quantity in kilograms, harvest date today or later, and note under 250 characters.';
		} else {
			$stmt = $conn->prepare("INSERT INTO farmer_harvests (farmer_id, crop_id, expected_date, quantity, unit, notes) VALUES (?, ?, ?, ?, 'kg', ?)");
			$farmerId = (int) $user['user_id'];
			$amount = (float) $quantity;
			$dateSql = $date->format('Y-m-d');
			$stmt->bind_param('iisds', $farmerId, $cropId, $dateSql, $amount, $notes);
			$stmt->execute();
			$stmt->close();
			flash('Harvest added to your calendar.');
			redirect('/agribid/farmer/harvest.php');
		}
	}
}
$stmt = $conn->prepare('SELECT h.harvest_id, h.expected_date, h.quantity, h.notes, h.status, c.crop_name FROM farmer_harvests h JOIN crops c ON c.crop_id=h.crop_id WHERE h.farmer_id=? ORDER BY (h.status=\'Cancelled\'), h.expected_date ASC LIMIT 100');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$harvests = $stmt->get_result();
app_header('Harvest calendar', 'farmer');
?>
<div class="page-heading"><div><h2>Harvest calendar</h2><p>Plan upcoming crops and keep your harvest schedule in one place.</p></div></div>
<section class="panel">
	<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
	<form method="post" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
		<div class="form-grid">
			<label>Crop<select name="crop_id" required><option value="">Choose a crop</option><?php foreach ($crops as $crop): ?><option value="<?= (int) $crop['crop_id'] ?>"><?= e($crop['crop_name']) ?></option><?php endforeach; ?></select></label>
			<label>Expected harvest date<input type="date" name="expected_date" min="<?= e(date('Y-m-d')) ?>" required value="<?= e($_POST['expected_date'] ?? '') ?>"></label>
			<label>Expected quantity <span class="field-hint">(kg)</span><input type="number" name="quantity" min="0.01" max="999999" step="0.01" required value="<?= e($_POST['quantity'] ?? '') ?>"></label>
			<div class="unit-label harvest-unit" aria-label="Unit of measurement: kilograms">kg</div>
			<label class="span-2">Notes <span class="field-hint">(optional)</span><input name="notes" maxlength="250" value="<?= e($_POST['notes'] ?? '') ?>" placeholder="Planting area, variety, or other reminders"></label>
		</div>
		<button class="button" type="submit">Add to calendar</button>
	</form>
</section>
<section class="panel" style="margin-top:14px">
	<div class="page-heading"><div><h2>Your planned harvests</h2><p>These are planning estimates, not live inventory or guaranteed availability.</p></div></div>
	<?php if (!$harvests->num_rows): ?><div class="empty-state"><strong>No harvests planned</strong>Add a crop and expected date to start your calendar.</div><?php else: ?><div class="harvest-list"><?php while ($harvest = $harvests->fetch_assoc()): ?><article class="panel harvest-card"><div class="room-title-line"><h3><?= e($harvest['crop_name']) ?></h3><span class="status-pill status-<?= e(strtolower($harvest['status'])) ?>"><?= e($harvest['status']) ?></span></div><div class="room-meta"><span>Expected date<strong><?= e(date('M j, Y', strtotime($harvest['expected_date']))) ?></strong></span><span>Estimated quantity<strong><?= e($harvest['quantity']) ?> kg</strong></span><span>Notes<strong><?= e($harvest['notes'] ?: '—') ?></strong></span></div><?php if ($harvest['status'] !== 'Cancelled'): ?><form method="post" class="table-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update_status"><input type="hidden" name="harvest_id" value="<?= (int) $harvest['harvest_id'] ?>"><button class="button button-small" name="status" value="<?= $harvest['status'] === 'Planned' ? 'Harvested' : 'Planned' ?>"><?= $harvest['status'] === 'Planned' ? 'Mark harvested' : 'Reopen plan' ?></button><?php if ($harvest['status'] === 'Planned'): ?><button class="button button-danger button-small" name="status" value="Cancelled">Cancel plan</button><?php endif; ?></form><?php endif; ?></article><?php endwhile; ?></div><?php endif; ?>
</section>
<?php $stmt->close(); app_footer(); ?>
