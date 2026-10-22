<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
$area = match ($user['role']) {
	'Buyer / Trader' => 'buyer',
	'Farmer / Seller' => 'farmer',
	default => 'admin',
};
app_header('Help and support', $area, $area === 'admin');
?>
<div class="page-heading"><div><h2>How AgriBid works</h2><p>Quick guidance for your marketplace workspace.</p></div></div>
<section class="panel">
	<h2>Buyers</h2><p class="muted-copy">Post a crop request with quantities and a meet-up area. Review farmer offers in the room, accept one offer, then set the meet-up time and location from Orders. Use the Post-Bid Chatroom to message only the farmer whose offer you accepted. Both you and the selected farmer must confirm completion before the transaction is recorded as complete.</p>
</section>
<section class="panel">
	<h2>Farmers</h2><p class="muted-copy">Browse open crop requests, submit an itemized offer, and track accepted offers in Orders. The Post-Bid Chatroom becomes available when a buyer selects your offer. Confirm an order as complete after delivery; it is recorded as complete once the buyer confirms too. Add expected crop dates in Harvest; these are planning estimates and do not reserve or publish stock.</p>
</section>
<section class="panel">
	<h2>Account verification</h2><p class="muted-copy">New buyer and farmer accounts need administrator approval before posting requests or offers. Submit a document from your Profile or the verification page. Only administrators can download submitted documents.</p>
</section>
<section class="panel">
	<h2>Payments and support</h2><p class="muted-copy">AgriBid currently records agreed offer totals and meet-up details but does not process online payments. For account or safety concerns, contact the system administrator for your installation.</p>
</section>
<?php app_footer(); ?>
