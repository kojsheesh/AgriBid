<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
$error = '';
$userId = (int) $user['user_id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$action = (string) ($_POST['action'] ?? '');
	$addressId = filter_var($_POST['address_id'] ?? null, FILTER_VALIDATE_INT);
	if ($action === 'delete' && $addressId) {
		$stmt = $conn->prepare('DELETE FROM user_addresses WHERE address_id=? AND user_id=?');
		$stmt->bind_param('ii', $addressId, $userId);
		$stmt->execute();
		$deleted = $stmt->affected_rows > 0;
		$stmt->close();
		flash($deleted ? 'Address removed.' : 'Address not found.', $deleted ? 'success' : 'warning');
		redirect('/agribid/addresses.php');
	}
	if ($action === 'default' && $addressId) {
		$exists = $conn->prepare('SELECT address_id FROM user_addresses WHERE address_id=? AND user_id=?');
		$exists->bind_param('ii', $addressId, $userId);
		$exists->execute();
		$allowed = (bool) $exists->get_result()->fetch_row();
		$exists->close();
		if (!$allowed) {
			flash('Address not found.', 'error');
			redirect('/agribid/addresses.php');
		}
		$conn->begin_transaction();
		try {
			$clear = $conn->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?');
			$clear->bind_param('i', $userId);
			$clear->execute();
			$clear->close();
			$set = $conn->prepare('UPDATE user_addresses SET is_default=1 WHERE address_id=? AND user_id=?');
			$set->bind_param('ii', $addressId, $userId);
			$set->execute();
			$set->close();
			$conn->commit();
			flash('Default address updated.');
		} catch (Throwable $exception) {
			$conn->rollback();
			throw $exception;
		}
		redirect('/agribid/addresses.php');
	}
	if ($action === 'save') {
		$city = trim((string) ($_POST['city'] ?? ''));
		$barangay = trim((string) ($_POST['barangay'] ?? ''));
		$location = trim((string) ($_POST['full_address'] ?? ''));
		$makeDefault = isset($_POST['is_default']);
		$barangays = binangonan_barangays();
		$formattedAddress = format_local_address($city, $barangay, $location);
		if ($city !== 'Binangonan' || !in_array($barangay, $barangays, true) || $location === '' || mb_strlen($location) > 120 || mb_strlen($formattedAddress) > 180) {
			$error = 'Choose Binangonan and a valid barangay, then enter the full address. Keep the complete address within 180 characters.';
		} else {
			$conn->begin_transaction();
			try {
				$countStmt = $conn->prepare('SELECT COUNT(*) AS total FROM user_addresses WHERE user_id=?');
				$countStmt->bind_param('i', $userId);
				$countStmt->execute();
				$addressCount = (int) $countStmt->get_result()->fetch_assoc()['total'];
				$countStmt->close();
				$isFirst = $addressCount === 0;
				if ($makeDefault || $isFirst) {
					$clear = $conn->prepare('UPDATE user_addresses SET is_default=0 WHERE user_id=?');
					$clear->bind_param('i', $userId);
					$clear->execute();
					$clear->close();
				}
				$isDefault = ($makeDefault || $isFirst) ? 1 : 0;
				if ($addressId) {
					$stmt = $conn->prepare('UPDATE user_addresses SET city=?, barangay=?, full_address=?, is_default=? WHERE address_id=? AND user_id=?');
					$stmt->bind_param('sssiii', $city, $barangay, $location, $isDefault, $addressId, $userId);
					$stmt->execute();
					if ($stmt->affected_rows === 0 && !$conn->query("SELECT address_id FROM user_addresses WHERE address_id=" . (int) $addressId . " AND user_id=" . $userId)->num_rows) {
						throw new RuntimeException('Address not found.');
					}
					$stmt->close();
				} else {
					$label = $addressCount === 0 ? 'Address' : 'Address ' . ($addressCount + 1);
					$stmt = $conn->prepare('INSERT INTO user_addresses (user_id,label,city,barangay,full_address,is_default) VALUES (?,?,?,?,?,?)');
					$stmt->bind_param('issssi', $userId, $label, $city, $barangay, $location, $isDefault);
					$stmt->execute();
					$stmt->close();
				}
				$conn->commit();
				flash('Address saved.');
				redirect('/agribid/addresses.php');
			} catch (Throwable $exception) {
				$conn->rollback();
				if ($exception instanceof RuntimeException) {
					$error = $exception->getMessage();
				} else {
					throw $exception;
				}
			}
		}
	}
}

$editId = filter_var($_GET['edit'] ?? null, FILTER_VALIDATE_INT);
$edit = null;
if ($editId) {
	$stmt = $conn->prepare('SELECT address_id,label,city,barangay,full_address,is_default FROM user_addresses WHERE address_id=? AND user_id=?');
	$stmt->bind_param('ii', $editId, $userId);
	$stmt->execute();
	$edit = $stmt->get_result()->fetch_assoc();
	$stmt->close();
	if (!$edit) {
		http_response_code(404);
		exit('Address not found.');
	}
}
$stmt = $conn->prepare('SELECT address_id,label,city,barangay,full_address,is_default FROM user_addresses WHERE user_id=? ORDER BY is_default DESC,label');
$stmt->bind_param('i', $userId);
$stmt->execute();
$addresses = $stmt->get_result();
app_header('Your addresses', match ($user['role']) {
	'Buyer / Trader' => 'buyer',
	'Farmer / Seller' => 'farmer',
	default => 'admin',
});
?>
<div class="page-heading"><div><h2>Saved addresses</h2><p>Add complete locations for deliveries, collection, and meet-ups.</p></div><a class="button button-muted button-small" href="/agribid/profile.php">Back to profile</a></div>
<div class="two-column">
	<section class="panel address-panel"><?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
		<form method="post" class="address-form"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save"><?php if ($edit): ?><input type="hidden" name="address_id" value="<?= (int) $edit['address_id'] ?>"><?php endif; ?>
			<h2>Address</h2>
			<label class="address-field">City<select name="city" required><option value="">Select city</option><option value="Binangonan"<?= ($edit['city'] ?? '') === 'Binangonan' ? ' selected' : '' ?>>Binangonan</option></select></label>
			<label class="address-field">Barangay<select name="barangay" required><option value="">Select barangay</option><?php foreach (binangonan_barangays() as $barangay): ?><option value="<?= e($barangay) ?>"<?= ($edit['barangay'] ?? '') === $barangay ? ' selected' : '' ?>><?= e($barangay) ?></option><?php endforeach; ?></select></label>
			<label class="address-field">Full address<input name="full_address" maxlength="120" required placeholder="Street, building, or house number" value="<?= e($edit['full_address'] ?? '') ?>"></label>
			<label class="address-default"><span>Set as Default</span><input type="checkbox" name="is_default" value="1"<?= ($edit['is_default'] ?? ($addresses->num_rows === 0)) ? ' checked' : '' ?>><span class="address-switch" aria-hidden="true"></span></label>
			<div class="address-actions"><button class="button" type="submit"><?= $edit ? 'Save Changes' : 'Submit' ?></button><?php if ($edit): ?><a class="button button-muted" href="/agribid/addresses.php">Cancel</a><?php endif; ?></div>
		</form>
	</section>
	<section class="panel"><h2>Address book</h2>
		<?php if (!$addresses->num_rows): ?><div class="empty-state"><strong>No saved addresses</strong>Add a location to make it easy to arrange future meet-ups.</div><?php else: ?><div class="item-row-list"><?php while ($address = $addresses->fetch_assoc()): ?><article class="panel"><div class="room-title-line"><strong><?= e($address['label']) ?></strong><?php if ($address['is_default']): ?><span class="status-pill">Default</span><?php endif; ?></div><p class="room-description"><?= e(format_user_address($address)) ?></p><div class="table-actions"><a class="button button-muted button-small" href="/agribid/addresses.php?edit=<?= (int) $address['address_id'] ?>">Edit</a><?php if (!$address['is_default']): ?><form method="post"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="default"><input type="hidden" name="address_id" value="<?= (int) $address['address_id'] ?>"><button class="button button-muted button-small" type="submit">Make default</button></form><?php endif; ?><form method="post" onsubmit="return confirm('Remove this saved address?')"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="address_id" value="<?= (int) $address['address_id'] ?>"><button class="button button-danger button-small" type="submit">Remove</button></form></div></article><?php endwhile; ?></div><?php endif; ?>
	</section>
</div>
<?php $stmt->close(); app_footer(); ?>
