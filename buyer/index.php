<?php
require_once dirname(__DIR__) . '/includes/app.php';
$user = require_user('Buyer / Trader');

$myRoomCount = $conn->prepare("SELECT COUNT(*) AS total FROM buyer_rooms WHERE buyer_id=? AND status='Open' AND deadline>NOW()");
$myRoomCount->bind_param('i', $user['user_id']);
$myRoomCount->execute();
$myOpenRooms = (int) $myRoomCount->get_result()->fetch_assoc()['total'];
$myRoomCount->close();

$offerCount = $conn->prepare("SELECT COUNT(*) AS total FROM farmer_offers o JOIN buyer_rooms r ON r.room_id=o.room_id WHERE r.buyer_id=? AND r.status='Open' AND o.status='Pending'");
$offerCount->bind_param('i', $user['user_id']);
$offerCount->execute();
$myOffers = (int) $offerCount->get_result()->fetch_assoc()['total'];
$offerCount->close();

$marketplaceCount = $conn->query("SELECT COUNT(*) AS total FROM buyer_rooms WHERE status='Open' AND deadline>NOW()");
$liveRooms = (int) $marketplaceCount->fetch_assoc()['total'];

app_header('Buyer dashboard', 'buyer');
?>
<section class="page-heading buyer-dashboard-heading">
	<div><h2>Your dashboard</h2><p>Check your buying activity, review crop availability, and get to your next task.</p></div>
	<a class="button" href="/agribid/buyer/create-room.php"><span aria-hidden="true"><?= app_icon('plus') ?></span>Post a request</a>
</section>
<?php if ($user['status'] !== 'Verified'): ?>
	<div class="notice notice-warning">Your account is <?= e(strtolower($user['status'])) ?>. You can explore requests, but your own requests are limited until an administrator verifies your account.</div>
<?php endif; ?>
<section class="stats-grid" aria-label="Your buyer activity">
	<div class="stat-card"><span>Your open requests</span><strong><?= $myOpenRooms ?></strong><small>Active buying rooms</small></div>
	<div class="stat-card"><span>Offers to review</span><strong><?= $myOffers ?></strong><small>Waiting for your response</small></div>
	<div class="stat-card"><span>Live marketplace</span><strong><?= $liveRooms ?></strong><small>Open local requests</small></div>
	<div class="stat-card"><span>Your account</span><strong class="account-status"><?= e($user['status']) ?></strong><small>Verification status</small></div>
</section>
<?php require dirname(__DIR__) . '/includes/harvest-availability-calendar.php'; ?>
<section class="panel farmer-dashboard-next">
	<div><h2>What would you like to do?</h2><p class="muted-copy">Browse current crop requests or track accepted offers and meet-ups.</p></div>
	<div class="farmer-dashboard-actions">
		<a class="button" href="/agribid/buyer/rooms.php">Browse buying rooms</a>
		<a class="button button-muted" href="/agribid/buyer/orders.php">View my orders</a>
	</div>
</section>
<?php app_footer(); ?>
