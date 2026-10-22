<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? 'update');
	if ($action === 'create') {
		$name = mb_strtoupper(trim((string) ($_POST['crop_name'] ?? '')));
		$category = trim((string) ($_POST['category'] ?? ''));
		$description = trim((string) ($_POST['description'] ?? ''));
		$season = trim((string) ($_POST['planting_season'] ?? ''));
		$harvestInput = trim((string) ($_POST['days_to_harvest'] ?? ''));
		$harvest = $harvestInput === '' ? null : filter_var($harvestInput, FILTER_VALIDATE_INT);
		if ($name === '' || mb_strlen($name) > 80 || !preg_match('/^[\p{L}\p{N}][\p{L}\p{N}\s\'-]*$/u', $name)
			|| $category === '' || mb_strlen($category) > 50
			|| mb_strlen($description) > 2000 || mb_strlen($season) > 120
			|| ($harvestInput !== '' && ($harvest === false || $harvest < 1 || $harvest > 3650))) {
			$error = 'Enter a valid crop name and category. Check the optional description, planting season, and harvest days.';
		} else {
			$imagePath = null;
			try {
				$imagePath = upload_crop_image();
				$descriptionValue = $description === '' ? null : $description;
				$seasonValue = $season === '' ? null : $season;
				$stmt = $conn->prepare('INSERT INTO crops (crop_name, category, description, image_path, planting_season, days_to_harvest, is_active) VALUES (?, ?, ?, ?, ?, ?, 1)');
				$stmt->bind_param('sssssi', $name, $category, $descriptionValue, $imagePath, $seasonValue, $harvest);
				$stmt->execute();
				$stmt->close();
				flash('Crop added to the catalog.');
				redirect('/agribid/admin/crops.php');
			} catch (mysqli_sql_exception $exception) {
				if ($imagePath !== null) {
					$storedFile = dirname(__DIR__) . '/uploads/crops/' . basename($imagePath);
					if (is_file($storedFile)) {
						unlink($storedFile);
					}
				}
				if ($exception->getCode() === 1062) {
					$error = 'A crop with that name already exists.';
				} else {
					throw $exception;
				}
			} catch (RuntimeException $exception) {
				if ($imagePath !== null) {
					$storedFile = dirname(__DIR__) . '/uploads/crops/' . basename($imagePath);
					if (is_file($storedFile)) {
						unlink($storedFile);
					}
				}
				$error = $exception->getMessage();
			} catch (Throwable $exception) {
				if ($imagePath !== null) {
					$storedFile = dirname(__DIR__) . '/uploads/crops/' . basename($imagePath);
					if (is_file($storedFile)) {
						unlink($storedFile);
					}
				}
				throw $exception;
			}
		}
	} else {
		$cropId = filter_var($_POST['crop_id'] ?? null, FILTER_VALIDATE_INT);
		$description = trim((string) ($_POST['description'] ?? ''));
		$season = trim((string) ($_POST['planting_season'] ?? ''));
		$harvestInput = trim((string) ($_POST['days_to_harvest'] ?? ''));
		$harvest = $harvestInput === '' ? null : filter_var($harvestInput, FILTER_VALIDATE_INT);
		$active = isset($_POST['is_active']) ? 1 : 0;
		if (!$cropId || mb_strlen($description) > 2000 || mb_strlen($season) > 120 || ($harvestInput !== '' && ($harvest === false || $harvest < 1 || $harvest > 3650))) {
			$error = 'Check the crop information and try again.';
		} else {
			try {
				$imagePath = upload_crop_image();
				if ($imagePath) {
					$stmt = $conn->prepare('UPDATE crops SET description=?, planting_season=?, days_to_harvest=?, is_active=?, image_path=? WHERE crop_id=?');
					$stmt->bind_param('ssiisi', $description, $season, $harvest, $active, $imagePath, $cropId);
				} else {
					$stmt = $conn->prepare('UPDATE crops SET description=?, planting_season=?, days_to_harvest=?, is_active=? WHERE crop_id=?');
					$stmt->bind_param('ssiii', $description, $season, $harvest, $active, $cropId);
				}
				$stmt->execute();
				$stmt->close();
				flash('Crop catalog updated.');
				redirect('/agribid/admin/crops.php');
			} catch (RuntimeException $exception) {
				$error = $exception->getMessage();
			}
		}
	}
}
$crops = $conn->query('SELECT crop_id, crop_name, category, description, image_path, planting_season, days_to_harvest, is_active FROM crops ORDER BY category, crop_name');
app_header('Crop catalog', 'admin', true);
?>
<div class="page-heading"><div><h2>Crop catalog</h2><p>Manage crop photos and details. Room images automatically use the crop requested in the greatest quantity.</p></div></div>
<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
<section class="panel crop-create-panel">
	<h2>Add a crop</h2>
	<p class="muted-copy">New crops become available to buyers as soon as they are added.</p>
	<form method="post" enctype="multipart/form-data" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="create">
		<div class="form-grid">
			<label>Crop name<input name="crop_name" maxlength="80" required placeholder="e.g. TOMATO" value="<?= e($_POST['action'] ?? '') === 'create' ? e($_POST['crop_name'] ?? '') : '' ?>"></label>
			<label>Category<input name="category" maxlength="50" required placeholder="e.g. Vegetable" value="<?= e($_POST['action'] ?? '') === 'create' ? e($_POST['category'] ?? '') : '' ?>"></label>
			<label class="span-2">Description <span class="field-hint">(optional)</span><textarea name="description" maxlength="2000"><?= e($_POST['action'] ?? '') === 'create' ? e($_POST['description'] ?? '') : '' ?></textarea></label>
			<label>Planting season <span class="field-hint">(optional)</span><input name="planting_season" maxlength="120" value="<?= e($_POST['action'] ?? '') === 'create' ? e($_POST['planting_season'] ?? '') : '' ?>"></label>
			<label>Days to harvest <span class="field-hint">(optional)</span><input name="days_to_harvest" type="number" min="1" max="3650" value="<?= e($_POST['action'] ?? '') === 'create' ? e($_POST['days_to_harvest'] ?? '') : '' ?>"></label>
			<label class="span-2">Crop image <span class="field-hint">(optional · JPG, PNG, WebP · up to 5 MB)</span><input type="file" name="crop_image" accept="image/jpeg,image/png,image/webp"></label>
		</div>
		<button class="button" type="submit">Add crop</button>
	</form>
</section>
<div class="room-list">
	<?php while ($crop = $crops->fetch_assoc()): ?>
		<section class="panel">
			<div class="room-title-line"><div><h3><?= e($crop['crop_name']) ?></h3><p class="field-hint"><?= e($crop['category']) ?></p></div><span class="status-pill<?= $crop['is_active'] ? '' : ' status-suspended' ?>"><?= $crop['is_active'] ? 'Active' : 'Inactive' ?></span></div>
			<?php if ($crop['image_path']): ?><img src="<?= e($crop['image_path']) ?>" alt="<?= e($crop['crop_name']) ?>" style="width:100%;height:130px;object-fit:cover;border-radius:9px;margin:10px 0"><?php else: ?><div class="empty-state" style="margin:10px 0;padding:13px">No crop image uploaded</div><?php endif; ?>
			<form method="post" enctype="multipart/form-data" class="form-stack">
				<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="crop_id" value="<?= (int) $crop['crop_id'] ?>">
				<label>Description<textarea name="description" maxlength="2000"><?= e($crop['description'] ?? '') ?></textarea></label>
				<div class="form-row"><label>Planting season<input name="planting_season" maxlength="120" value="<?= e($crop['planting_season'] ?? '') ?>"></label><label>Days to harvest<input name="days_to_harvest" type="number" min="1" max="3650" value="<?= e($crop['days_to_harvest'] ?? '') ?>"></label></div>
				<label>Replace crop image <span class="field-hint">(JPG, PNG, WebP · up to 5 MB)</span><input type="file" name="crop_image" accept="image/jpeg,image/png,image/webp"></label>
				<label class="checkbox-label"><input type="checkbox" name="is_active" value="1"<?= $crop['is_active'] ? ' checked' : '' ?>> Available to buyers</label>
				<button class="button button-small" type="submit">Save crop</button>
			</form>
		</section>
	<?php endwhile; ?>
</div>
<?php app_footer(); ?>
