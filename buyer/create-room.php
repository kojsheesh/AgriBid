<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');
if ($user['status'] !== 'Verified') {
	flash('Your account must be verified before you can post a buying room.', 'warning');
	redirect('/agribid/buyer/index.php');
}
$crops = $conn->query('SELECT crop_id, crop_name, category FROM crops WHERE is_active = 1 ORDER BY category, crop_name')->fetch_all(MYSQLI_ASSOC);
$addressStmt = $conn->prepare('SELECT label, city, barangay, full_address, is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC, label');
$addressStmt->bind_param('i', $user['user_id']);
$addressStmt->execute();
$savedAddresses = $addressStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$addressStmt->close();
$defaultAddress = null;
foreach ($savedAddresses as $savedAddress) {
	if ($savedAddress['is_default']) {
		$defaultAddress = $savedAddress;
		break;
	}
}
$defaultCity = $defaultAddress['city'] ?? 'Binangonan';
$defaultBarangay = $defaultAddress['barangay'] ?? '';
$defaultFullAddress = $defaultAddress['full_address'] ?? '';
$settings = $conn->query('SELECT default_timer_mins FROM system_settings ORDER BY setting_id LIMIT 1')->fetch_assoc();
$defaultTimer = max(5, min(1440, (int) ($settings['default_timer_mins'] ?? 30)));
$defaultDeadline = (new DateTime('+' . $defaultTimer . ' minutes'))->format('Y-m-d\TH:i');
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$title = trim((string) ($_POST['title'] ?? ''));
	$description = trim((string) ($_POST['description'] ?? ''));
	$city = trim((string) ($_POST['city'] ?? ''));
	$barangay = trim((string) ($_POST['barangay'] ?? ''));
	$fullAddress = trim((string) ($_POST['full_address'] ?? ''));
	$location = format_local_address($city, $barangay, $fullAddress);
	$deadlineInput = trim((string) ($_POST['deadline'] ?? ''));
	$cropIds = $_POST['crop_id'] ?? [];
	$quantities = $_POST['quantity'] ?? [];
	$prices = $_POST['unit_price'] ?? [];
	$deadline = $deadlineInput ? DateTime::createFromFormat('Y-m-d\TH:i', $deadlineInput) : false;
	$validCrops = array_column($crops, null, 'crop_id');

	if ($title === '' || mb_strlen($title) > 150 || $city !== 'Binangonan' || !in_array($barangay, binangonan_barangays(), true) || $fullAddress === '' || mb_strlen($fullAddress) > 120 || mb_strlen($location) > 180) {
		$error = 'Add a room title, select Binangonan and a valid barangay, and enter the full meet-up address.';
	} elseif (!$deadline || $deadline <= new DateTime()) {
		$error = 'Choose a future deadline for this room.';
	} elseif (!is_array($cropIds) || !is_array($quantities) || !is_array($prices) || count($cropIds) === 0 || count($cropIds) !== count($quantities) || count($cropIds) !== count($prices)) {
		$error = 'Add at least one crop, its requested quantity, and a fixed price per kilogram.';
	} else {
		$items = [];
		foreach ($cropIds as $index => $cropIdValue) {
			$cropId = is_scalar($cropIdValue) ? filter_var($cropIdValue, FILTER_VALIDATE_INT) : false;
			$quantityValue = $quantities[$index] ?? null;
			$priceValue = $prices[$index] ?? null;
			$quantity = is_scalar($quantityValue) ? filter_var($quantityValue, FILTER_VALIDATE_FLOAT) : false;
			$unitPrice = is_scalar($priceValue) ? filter_var($priceValue, FILTER_VALIDATE_FLOAT) : false;
			if (!$cropId || !isset($validCrops[$cropId]) || $quantity === false || $quantity <= 0 || $quantity > 999999 || $unitPrice === false || $unitPrice <= 0 || $unitPrice > 999999) {
				$error = 'Each crop needs a valid quantity in kilograms and fixed price per kilogram.';
				break;
			}
			$items[$cropId] = ['quantity' => $quantity, 'unit_price' => $unitPrice];
		}
		if (!$error && count($items) !== count($cropIds)) {
			$error = 'Each crop can appear only once in a buying room.';
		}
		if (!$error) {
			try {
				$conn->begin_transaction();
				$stmt = $conn->prepare('INSERT INTO buyer_rooms (buyer_id, title, description, city, barangay, delivery_location, deadline) VALUES (?, ?, ?, ?, ?, ?, ?)');
				$buyerId = (int) $user['user_id'];
				$deadlineSql = $deadline->format('Y-m-d H:i:s');
				$stmt->bind_param('issssss', $buyerId, $title, $description, $city, $barangay, $fullAddress, $deadlineSql);
				$stmt->execute();
				$roomId = $conn->insert_id;
				$stmt->close();
				$itemStmt = $conn->prepare("INSERT INTO buyer_room_items (room_id, crop_id, quantity, unit, unit_price) VALUES (?, ?, ?, 'kg', ?)");
				foreach ($items as $cropId => $item) {
					$quantity = (float) $item['quantity'];
					$unitPrice = (float) $item['unit_price'];
					$itemStmt->bind_param('iidd', $roomId, $cropId, $quantity, $unitPrice);
					$itemStmt->execute();
				}
				$itemStmt->close();
				$conn->commit();
				flash('Your buying room is live. Farmers can now send offers.');
				redirect('/agribid/buyer/room.php?id=' . $roomId);
			} catch (Throwable $exception) {
				$conn->rollback();
				throw $exception;
			}
		}
	}
}

app_header('Post a crop request', 'buyer');
?>
<div class="page-heading"><div><h2>Create a buying room</h2><p>Set the requested crop quantities and fixed prices per kilogram for farmers.</p></div><a class="button button-muted button-small" href="/agribid/buyer/rooms.php">Back to rooms</a></div>
<div class="panel">
	<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
	<form method="post" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
		<div class="form-grid">
			<label class="span-2">Room title<input name="title" maxlength="150" required placeholder="e.g. Weekly fresh vegetables for our cafe" value="<?= e($_POST['title'] ?? '') ?>"></label>
			<label class="span-2">What are you looking for?<textarea name="description" maxlength="2000" placeholder="Describe freshness, variety, or other requirements."><?= e($_POST['description'] ?? '') ?></textarea></label>
			<label>City<select name="city" required><option value="">Select city</option><option value="Binangonan"<?= ($_POST['city'] ?? $defaultCity) === 'Binangonan' ? ' selected' : '' ?>>Binangonan</option></select></label>
			<label>Barangay<select name="barangay" required><option value="">Select barangay</option><?php foreach (binangonan_barangays() as $barangayOption): ?><option value="<?= e($barangayOption) ?>"<?= ($_POST['barangay'] ?? $defaultBarangay) === $barangayOption ? ' selected' : '' ?>><?= e($barangayOption) ?></option><?php endforeach; ?></select></label>
			<label class="span-2">Full address<input name="full_address" maxlength="120" required placeholder="Street, building, or house number" value="<?= e($_POST['full_address'] ?? $defaultFullAddress) ?>"></label>
			<label>Offer deadline <span class="field-hint">Default: <?= $defaultTimer ?> minutes from now</span><input type="datetime-local" name="deadline" min="<?= e(date('Y-m-d\TH:i', time() + 300)) ?>" required value="<?= e($_POST['deadline'] ?? $defaultDeadline) ?>"></label>
		</div>
		<div><span class="field-label">Crops, quantities, and fixed price per kilogram</span><div class="item-row-list" id="crop-rows">
			<?php foreach ($_POST['crop_id'] ?? [null] as $index => $cropId): ?>
				<div class="item-editor">
					<label class="form-stack"><select name="crop_id[]" aria-label="Crop" required><option value="">Select crop</option><?php foreach ($crops as $crop): ?><option value="<?= (int) $crop['crop_id'] ?>"<?= (string) $cropId === (string) $crop['crop_id'] ? ' selected' : '' ?>><?= e($crop['crop_name']) ?> · <?= e($crop['category']) ?></option><?php endforeach; ?></select></label>
					<label class="form-stack"><input name="quantity[]" type="number" min="0.01" step="0.01" placeholder="kg" aria-label="Quantity in kilograms" required value="<?= e($_POST['quantity'][$index] ?? '') ?>"></label>
					<label class="form-stack"><input name="unit_price[]" type="number" min="0.01" max="999999" step="0.01" placeholder="PHP/kg" aria-label="Fixed price per kilogram" required value="<?= e($_POST['unit_price'][$index] ?? '') ?>"></label>
					<button type="button" class="remove-crop" aria-label="Remove crop" title="Remove crop">×</button>
					<span class="unit-label" aria-label="Unit of measurement: kilograms">kg</span>
				</div>
			<?php endforeach; ?>
		</div><button class="button button-muted button-small" type="button" id="add-crop">＋ Add another crop</button></div>
		<button class="button button-primary" type="submit">Open buying room</button>
	</form>
</div>
<template id="crop-row-template"><div class="item-editor">
	<label class="form-stack"><select name="crop_id[]" aria-label="Crop" required><option value="">Select crop</option><?php foreach ($crops as $crop): ?><option value="<?= (int) $crop['crop_id'] ?>"><?= e($crop['crop_name']) ?> · <?= e($crop['category']) ?></option><?php endforeach; ?></select></label>
	<label class="form-stack"><input name="quantity[]" type="number" min="0.01" step="0.01" placeholder="kg" aria-label="Quantity in kilograms" required></label>
	<label class="form-stack"><input name="unit_price[]" type="number" min="0.01" max="999999" step="0.01" placeholder="PHP/kg" aria-label="Fixed price per kilogram" required></label>
	<button type="button" class="remove-crop" aria-label="Remove crop" title="Remove crop">×</button>
	<span class="unit-label" aria-label="Unit of measurement: kilograms">kg</span>
</div></template>
<script>
const cropRows = document.getElementById('crop-rows');
document.getElementById('add-crop').addEventListener('click', () => cropRows.append(document.getElementById('crop-row-template').content.cloneNode(true)));
cropRows.addEventListener('click', event => {
	const button = event.target.closest('.remove-crop');
	if (button && cropRows.children.length > 1) button.closest('.item-editor').remove();
});
</script>
<?php app_footer(); ?>
