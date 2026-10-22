<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');
$statusFilter = (string) ($_GET['status'] ?? '');
$validStatuses = ['Pending', 'Verified', 'Suspended'];
if (!in_array($statusFilter, $validStatuses, true)) {
	$statusFilter = '';
}
$latestVerification = 'LEFT JOIN (
	SELECT v.user_id, v.status
	FROM user_verifications v
	JOIN (
		SELECT user_id, MAX(verification_id) AS verification_id
		FROM user_verifications
		GROUP BY user_id
	) latest ON latest.verification_id=v.verification_id
) verification ON verification.user_id=u.user_id';
if ($statusFilter) {
	$stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.role, u.status, u.created_at, verification.status AS verification_status FROM users u $latestVerification WHERE u.role IN ('Buyer / Trader','Farmer / Seller') AND u.status=? ORDER BY u.created_at DESC");
	$stmt->bind_param('s', $statusFilter);
} else {
	$stmt = $conn->prepare("SELECT u.user_id, u.full_name, u.email, u.role, u.status, u.created_at, verification.status AS verification_status FROM users u $latestVerification WHERE u.role IN ('Buyer / Trader','Farmer / Seller') ORDER BY u.created_at DESC");
}
$stmt->execute();
$members = $stmt->get_result();
app_header('Manage members', 'admin', true);
?>
<div class="page-heading"><div><h2>Member accounts</h2><p>Account status updates automatically when you approve an ID in the Documents section.</p></div></div>
<div class="table-actions" style="margin-bottom:14px"><?php foreach (['' => 'All members', 'Pending' => 'Pending', 'Verified' => 'Verified', 'Suspended' => 'Suspended'] as $value => $label): ?><a class="button button-small<?= $statusFilter === $value ? '' : ' button-muted' ?>" href="/agribid/admin/users.php<?= $value ? '?status=' . rawurlencode($value) : '' ?>"><?= e($label) ?></a><?php endforeach; ?></div>
<?php if (!$members->num_rows): ?><div class="empty-state"><strong>No members<?= $statusFilter ? ' in this status' : ' yet' ?></strong>Registered buyers and farmers will appear here.</div><?php else: ?>
	<div class="data-table-wrap panel"><table class="data-table"><thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Account status</th><th>Joined</th><th>ID review</th></tr></thead><tbody>
	<?php while ($member = $members->fetch_assoc()): ?><tr>
		<td><?= e($member['full_name']) ?></td><td><?= e($member['email']) ?></td><td><?= e($member['role']) ?></td><td><span class="status-pill status-<?= e(strtolower($member['status'])) ?>"><?= e($member['status']) ?></span></td><td><?= e(date('M j, Y', strtotime($member['created_at']))) ?></td>
		<td><?php if ($member['verification_status']): ?><span class="status-pill status-<?= e(strtolower($member['verification_status'])) ?>"><?= e($member['verification_status']) ?></span><?php else: ?><span class="status-pill">Not submitted</span><?php endif; ?><?php if ($member['status'] === 'Pending'): ?><a class="table-subtext" href="/agribid/admin/verifications.php?status=Pending">Review documents</a><?php endif; ?></td>
	</tr><?php endwhile; ?>
	</tbody></table></div>
<?php endif; ?>
<?php $stmt->close(); app_footer(); ?>
