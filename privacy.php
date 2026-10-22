<?php
require_once __DIR__ . '/includes/app.php';
$user = require_user();
$area = match ($user['role']) {
	'Buyer / Trader' => 'buyer',
	'Farmer / Seller' => 'farmer',
	default => 'admin',
};
app_header('Privacy information', $area, $area === 'admin');
?>
<div class="page-heading"><div><h2>Privacy information</h2><p>What this installation stores and who can see it.</p></div></div>
<section class="panel">
	<h2>Account and marketplace data</h2><p class="muted-copy">AgriBid stores the name, email address, account role, and status needed to run the marketplace. Crop requests, offers, orders, messages, saved addresses, and notifications are visible only to the people who need them for the related workflow and to administrators.</p>
</section>
<section class="panel">
	<h2>Verification documents</h2><p class="muted-copy">Identity documents are saved outside the public website directory. Administrators can download them to review an account; they are not displayed on marketplace pages. Do not submit documents that are not required to confirm your account.</p>
</section>
<section class="panel">
	<h2>Local installation</h2><p class="muted-copy">This system is hosted by the organization running this AgriBid installation. Contact its system administrator to ask about account corrections, data retention, or deletion.</p>
</section>
<?php app_footer(); ?>
