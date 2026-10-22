<?php
require_once dirname(__DIR__) . '/includes/app.php';
require_user('System Administrator');
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	verify_csrf();
	$timer = filter_var($_POST['default_timer_mins'] ?? null, FILTER_VALIDATE_INT);
	if (!$timer || $timer < 5 || $timer > 1440) {
		$error = 'Choose a default room duration from 5 to 1,440 minutes.';
	} else {
		$stmt = $conn->prepare('UPDATE system_settings SET default_timer_mins=? WHERE setting_id=1');
		$stmt->bind_param('i', $timer);
		$stmt->execute();
		if ($stmt->affected_rows === 0 && !$conn->query('SELECT setting_id FROM system_settings WHERE setting_id=1')->num_rows) {
			$insert = $conn->prepare('INSERT INTO system_settings (setting_id, default_timer_mins) VALUES (1, ?)');
			$insert->bind_param('i', $timer);
			$insert->execute();
			$insert->close();
		}
		$stmt->close();
		flash('Platform settings saved.');
		redirect('/agribid/admin/settings.php');
	}
}
$settings = $conn->query('SELECT default_timer_mins FROM system_settings ORDER BY setting_id LIMIT 1')->fetch_assoc() ?: ['default_timer_mins' => 30];
app_header('Platform settings', 'admin', true);
?>
<div class="page-heading"><div><h2>Platform settings</h2><p>Set the default time buyers have to receive farmer offers.</p></div></div>
<section class="panel" style="max-width:720px">
	<?php if ($error): ?><div class="notice notice-error"><?= e($error) ?></div><?php endif; ?>
	<form method="post" class="form-stack">
		<input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
		<label>Default room duration <span class="field-hint">(5–1,440 minutes)</span><input type="number" name="default_timer_mins" min="5" max="1440" required value="<?= e($settings['default_timer_mins']) ?>"></label>
		<button class="button" type="submit">Save settings</button>
	</form>
</section>
<?php app_footer(); ?>
