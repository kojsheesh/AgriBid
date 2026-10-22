<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Farmer / Seller');

$roomsStmt = $conn->prepare("SELECT COUNT(*) AS total FROM buyer_rooms WHERE status='Open' AND deadline>NOW()");
$roomsStmt->execute();
$openRooms = (int) $roomsStmt->get_result()->fetch_assoc()['total'];
$roomsStmt->close();

$offersStmt = $conn->prepare("SELECT COUNT(*) AS total FROM farmer_offers WHERE farmer_id=? AND status IN ('Pending','Accepted')");
$offersStmt->bind_param('i', $user['user_id']);
$offersStmt->execute();
$activeOffers = (int) $offersStmt->get_result()->fetch_assoc()['total'];
$offersStmt->close();

$ordersStmt = $conn->prepare("SELECT COUNT(*) AS total FROM room_orders o JOIN farmer_offers f ON f.offer_id=o.offer_id WHERE f.farmer_id=? AND o.status IN ('Confirmed','Meet-up scheduled')");
$ordersStmt->bind_param('i', $user['user_id']);
$ordersStmt->execute();
$upcomingOrders = (int) $ordersStmt->get_result()->fetch_assoc()['total'];
$ordersStmt->close();

app_header('Farmer dashboard', 'farmer');
?>
<section class="page-heading"><div><h2>Your dashboard</h2><p>A quick view of your marketplace activity and next steps.</p></div></section>
<?php if ($user['status'] !== 'Verified'): ?><div class="notice notice-warning">Your account is <?= e(strtolower($user['status'])) ?>. You can browse requests, but you must be verified before submitting offers.</div><?php endif; ?>
<section class="stats-grid" aria-label="Farmer activity">
	<div class="stat-card"><span>Open rooms</span><strong><?= $openRooms ?></strong><small>Buyer crop requests</small></div>
	<div class="stat-card"><span>Your active offers</span><strong><?= $activeOffers ?></strong><small>Pending or accepted</small></div>
	<div class="stat-card"><span>Meet-ups to arrange</span><strong><?= $upcomingOrders ?></strong><small>Accepted offers</small></div>
	<div class="stat-card"><span>Account status</span><strong class="account-status"><?= e($user['status']) ?></strong><small>Verification</small></div>
</section>
<section class="panel farmer-dashboard-next">
	<div><h2>What would you like to do?</h2><p class="muted-copy">Browse current buyer requests, review your offers, or update your harvest availability.</p></div>
	<div class="farmer-dashboard-actions">
		<a class="button" href="/agribid/farmer/rooms.php">Browse buyer rooms</a>
		<a class="button button-muted" href="/agribid/farmer/responses.php">View my offers</a>
		<a class="button button-muted" href="/agribid/farmer/harvest.php">Manage harvest calendar</a>
	</div>
</section>
<?php app_footer(); ?>
