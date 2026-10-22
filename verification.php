<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
if (!in_array($user['role'], ['Buyer / Trader', 'Farmer / Seller'], true)) {
	redirect('/agribid/admin/verifications.php');
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$storageName = null;
	$conn->begin_transaction();
	try {
		$account = $conn->prepare('SELECT status FROM users WHERE user_id=? FOR UPDATE');
		$account->bind_param('i', $user['user_id']);
		$account->execute();
		$accountStatus = $account->get_result()->fetch_column();
		$account->close();
		if ($accountStatus === 'Verified') {
			throw new RuntimeException('Your account is already verified.');
		}
		$pending = $conn->prepare("SELECT verification_id FROM user_verifications WHERE user_id=? AND status='Pending' LIMIT 1");
		$pending->bind_param('i', $user['user_id']);
		$pending->execute();
		$hasPending = $pending->get_result()->num_rows > 0;
		$pending->close();
		if ($hasPending) {
			throw new RuntimeException('Your document is already awaiting administrator review.');
		}
		$idType = trim((string) ($_POST['id_type'] ?? ''));
		$location = trim((string) ($_POST['location'] ?? ''));
		if (!in_array($idType, ['Government ID', 'Barangay certificate', 'Business permit', 'Other'], true) || mb_strlen($location) > 150) {
			throw new RuntimeException('Choose a valid document type and keep the location within 150 characters.');
		}
		$storageName = upload_verification_document($_FILES['document'] ?? []);
		$stmt = $conn->prepare("INSERT INTO user_verifications (user_id, id_type, document_path, location, status) VALUES (?, ?, ?, ?, 'Pending')");
		$stmt->bind_param('isss', $user['user_id'], $idType, $storageName, $location);
		$stmt->execute();
		$stmt->close();
		$conn->commit();
		flash('Your verification document was submitted for review.');
		redirect('/agribid/verification.php');
	} catch (RuntimeException $exception) {
		$conn->rollback();
		if ($storageName !== null) {
			$storedFile = verification_storage_directory() . DIRECTORY_SEPARATOR . $storageName;
			if (is_file($storedFile)) {
				unlink($storedFile);
			}
		}
		$error = $exception->getMessage();
	} catch (Throwable $exception) {
		$conn->rollback();
		if ($storageName !== null) {
			$storedFile = verification_storage_directory() . DIRECTORY_SEPARATOR . $storageName;
			if (is_file($storedFile)) {
				unlink($storedFile);
			}
		}
		throw $exception;
	}
}
$stmt = $conn->prepare('SELECT id_type, location, status, submitted_at, reviewed_at FROM user_verifications WHERE user_id=? ORDER BY submitted_at DESC');
$stmt->bind_param('i', $user['user_id']);
$stmt->execute();
$submissions = $stmt->get_result();
$area = $user['role'] === 'Buyer / Trader' ? 'buyer' : 'farmer';
app_header('Account verification', $area);
?>
<div class="page-heading"><div><h2>Verify your account</h2><p>Verification helps buyers and farmers trade with confidence.</p></div></div>
<section class="panel">
	<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
	<div class="room-meta"><span>Account status<strong><?= e($user['status']) ?></strong></span><span>Document storage<strong>Private and administrator-only</strong></span></div>
	<?php if ($user['status'] === 'Verified'): ?>
		<p class="muted-copy">Your marketplace account is verified. No further document upload is needed.</p>
	<?php else: ?>
		<form method="post" enctype="multipart/form-data" class="form-stack" style="max-width:640px;margin-top:18px">
			<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
			<label>Document type<select name="id_type" required><option value="">Choose a document</option><?php foreach (['Government ID', 'Barangay certificate', 'Business permit', 'Other'] as $idType): ?><option value="<?= e($idType) ?>"><?= e($idType) ?></option><?php endforeach; ?></select></label>
			<label>Municipality or barangay <span class="field-hint">(optional)</span><input name="location" maxlength="150" value="<?= e($_POST['location'] ?? '') ?>"></label>
			<label>Document file <span class="field-hint">PDF, JPG, or PNG · up to 5 MB</span><input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,application/pdf,image/jpeg,image/png" required></label>
			<p class="muted-copy">Only administrators can download this document. It is stored outside the public website directory.</p>
			<button class="button" type="submit">Submit for review</button>
		</form>
	<?php endif; ?>
</section>
<section class="panel" style="margin-top:14px">
	<h2>Submission history</h2>
	<?php if (!$submissions->num_rows): ?><p class="muted-copy">You have not submitted a document yet.</p><?php else: ?><div class="harvest-list"><?php while ($submission = $submissions->fetch_assoc()): ?><article class="harvest-card"><div class="room-title-line"><strong><?= e($submission['id_type']) ?></strong><span class="status-pill status-<?= e(strtolower($submission['status'])) ?>"><?= e($submission['status']) ?></span></div><div class="room-meta"><span>Submitted<strong><?= e(date('M j, Y', strtotime($submission['submitted_at']))) ?></strong></span><span>Location<strong><?= e($submission['location'] ?: 'Not provided') ?></strong></span><span>Reviewed<strong><?= $submission['reviewed_at'] ? e(date('M j, Y', strtotime($submission['reviewed_at']))) : 'Awaiting review' ?></strong></span></div></article><?php endwhile; ?></div><?php endif; ?>
</section>
<?php $stmt->close(); app_footer(); ?>
