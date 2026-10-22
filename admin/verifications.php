<?php
require_once dirname(__DIR__) . '/includes/app.php';
$admin = require_user('System Administrator');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$verificationId = filter_var($_POST['verification_id'] ?? null, FILTER_VALIDATE_INT);
	$status = (string) ($_POST['status'] ?? '');
	if ($verificationId && in_array($status, ['Verified', 'Rejected'], true)) {
		$conn->begin_transaction();
		try {
			$lookup = $conn->prepare('SELECT v.user_id, v.status, u.status AS account_status FROM user_verifications v JOIN users u ON u.user_id=v.user_id WHERE v.verification_id=? FOR UPDATE');
			$lookup->bind_param('i', $verificationId);
			$lookup->execute();
			$verification = $lookup->get_result()->fetch_assoc();
			$lookup->close();
			if (!$verification || $verification['status'] !== 'Pending') {
				throw new RuntimeException('This document has already been reviewed or no longer exists.');
			}
			$update = $conn->prepare('UPDATE user_verifications SET status=?, reviewed_at=NOW() WHERE verification_id=? AND status=\'Pending\'');
			$update->bind_param('si', $status, $verificationId);
			$update->execute();
			$update->close();
			if ($status === 'Verified' && $verification['account_status'] !== 'Suspended') {
				$account = $conn->prepare("UPDATE users SET status='Verified' WHERE user_id=? AND role IN ('Buyer / Trader','Farmer / Seller')");
				$account->bind_param('i', $verification['user_id']);
				$account->execute();
				$account->close();
			}
			$notice = $status === 'Verified'
				? 'Your identity document was approved. Your account is now verified.'
				: 'Your identity document was not approved. You may submit a new document for review.';
			log_notification($conn, (int) $verification['user_id'], 'Verification review complete', $notice);
			$conn->commit();
			flash('Document review saved.');
		} catch (RuntimeException $exception) {
			$conn->rollback();
			flash($exception->getMessage(), 'warning');
		} catch (Throwable $exception) {
			$conn->rollback();
			throw $exception;
		}
	}
	redirect('/agribid/admin/verifications.php');
}
$filter = (string) ($_GET['status'] ?? '');
if (!in_array($filter, ['Pending', 'Verified', 'Rejected'], true)) {
	$filter = '';
}
if ($filter !== '') {
	$stmt = $conn->prepare('SELECT v.verification_id, v.id_type, v.document_path, v.location, v.status, v.submitted_at, v.reviewed_at, u.user_id, u.full_name, u.email, u.role, u.status AS account_status FROM user_verifications v JOIN users u ON u.user_id=v.user_id WHERE v.status=? ORDER BY FIELD(v.status,\'Pending\',\'Verified\',\'Rejected\'), v.submitted_at DESC');
	$stmt->bind_param('s', $filter);
} else {
	$stmt = $conn->prepare('SELECT v.verification_id, v.id_type, v.document_path, v.location, v.status, v.submitted_at, v.reviewed_at, u.user_id, u.full_name, u.email, u.role, u.status AS account_status FROM user_verifications v JOIN users u ON u.user_id=v.user_id ORDER BY FIELD(v.status,\'Pending\',\'Verified\',\'Rejected\'), v.submitted_at DESC');
}
$stmt->execute();
$documents = $stmt->get_result();
app_header('Identity documents', 'admin', true);
?>
<div class="page-heading"><div><h2>Verification documents</h2><p>Preview each private ID image, open it full-size or download it, then approve or reject the account.</p></div></div>
<div class="table-actions" style="margin-bottom:14px"><?php foreach (['' => 'All documents', 'Pending' => 'Pending', 'Verified' => 'Approved', 'Rejected' => 'Rejected'] as $value => $label): ?><a class="button button-small<?= $filter === $value ? '' : ' button-muted' ?>" href="/agribid/admin/verifications.php<?= $value ? '?status=' . rawurlencode($value) : '' ?>"><?= e($label) ?></a><?php endforeach; ?></div>
<?php if (!$documents->num_rows): ?><div class="empty-state"><strong>No documents<?= $filter ? ' in this status' : ' submitted yet' ?></strong>New identity submissions will appear here for review.</div><?php else: ?>
	<div class="data-table-wrap panel"><table class="data-table"><thead><tr><th>Member</th><th>Role</th><th>Document</th><th>Location</th><th>Submitted</th><th>Account</th><th>Review</th></tr></thead><tbody>
	<?php while ($document = $documents->fetch_assoc()): ?><tr>
		<?php $documentUrl = '/agribid/admin/verification-document.php?id=' . (int) $document['verification_id']; $documentExtension = strtolower(pathinfo((string) $document['document_path'], PATHINFO_EXTENSION)); ?>
		<td><?= e($document['full_name']) ?><small class="table-subtext"><?= e($document['email']) ?></small></td><td><?= e($document['role']) ?></td><td class="document-preview-cell"><?php if (in_array($documentExtension, ['jpg', 'png', 'webp'], true)): ?><a href="<?= e($documentUrl) ?>" target="_blank" rel="noopener"><img class="document-thumbnail" src="<?= e($documentUrl) ?>" alt="Preview of <?= e($document['full_name']) ?>'s submitted ID"></a><?php endif; ?><a href="<?= e($documentUrl) ?>" target="_blank" rel="noopener">View <?= e($document['id_type']) ?></a><a class="table-subtext" href="<?= e($documentUrl) ?>&amp;download=1">Download file</a></td><td><?= e($document['location'] ?: '—') ?></td><td><?= e(date('M j, Y', strtotime($document['submitted_at']))) ?></td><td><span class="status-pill status-<?= e(strtolower($document['account_status'])) ?>"><?= e($document['account_status']) ?></span></td>
		<td><?php if ($document['status'] === 'Pending'): ?><form method="post" class="table-actions"><input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="verification_id" value="<?= (int) $document['verification_id'] ?>"><button class="button button-small" name="status" value="Verified">Approve</button><button class="button button-danger button-small" name="status" value="Rejected">Reject</button></form><?php else: ?><span class="status-pill status-<?= e(strtolower($document['status'])) ?>"><?= e($document['status']) ?></span><?php endif; ?></td>
	</tr><?php endwhile; ?>
	</tbody></table></div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
