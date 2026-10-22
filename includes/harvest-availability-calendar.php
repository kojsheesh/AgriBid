<?php
mark_overdue_harvests($conn);
$availabilityCrops = $conn->query('SELECT crop_id, crop_name FROM crops ORDER BY crop_name')->fetch_all(MYSQLI_ASSOC);
$availabilityYears = range(2026, 2030);
$currentYear = (int) date('Y');
$defaultYear = min(max($currentYear, 2026), 2030);
$requestedYear = filter_var($_GET['year'] ?? $defaultYear, FILTER_VALIDATE_INT);
$availabilityYear = $requestedYear !== false && in_array($requestedYear, $availabilityYears, true) ? $requestedYear : $defaultYear;
$requestedQuarter = filter_var($_GET['quarter'] ?? 1, FILTER_VALIDATE_INT);
$availabilityQuarter = $requestedQuarter !== false && $requestedQuarter >= 1 && $requestedQuarter <= 4 ? $requestedQuarter : 1;
$requestedCropId = filter_var($_GET['crop_id'] ?? 0, FILTER_VALIDATE_INT);
$availabilityCropId = $requestedCropId !== false && $requestedCropId > 0
	&& in_array($requestedCropId, array_map('intval', array_column($availabilityCrops, 'crop_id')), true)
	? $requestedCropId
	: 0;

$firstMonth = (($availabilityQuarter - 1) * 3) + 1;
$periodStart = new DateTimeImmutable(sprintf('%04d-%02d-01', $availabilityYear, $firstMonth));
$periodEnd = $periodStart->modify('+3 months');
$periodStartSql = $periodStart->format('Y-m-d');
$periodEndSql = $periodEnd->format('Y-m-d');
$availabilityStmt = $conn->prepare("SELECT crop_id, MONTH(expected_date) AS harvest_month, FLOOR((DAY(expected_date)-1)/7)+1 AS harvest_week, status, SUM(quantity) AS total_quantity FROM farmer_harvests WHERE expected_date>=? AND expected_date<? AND status IN ('Planned','Harvested') AND (?=0 OR crop_id=?) GROUP BY crop_id, MONTH(expected_date), harvest_week, status");
$availabilityStmt->bind_param('ssii', $periodStartSql, $periodEndSql, $availabilityCropId, $availabilityCropId);
$availabilityStmt->execute();
$availabilityResult = $availabilityStmt->get_result();
$availabilityTotals = [];
while ($availabilityEntry = $availabilityResult->fetch_assoc()) {
	$availabilityTotals[(int) $availabilityEntry['crop_id']][(int) $availabilityEntry['harvest_month']][(int) $availabilityEntry['harvest_week']][$availabilityEntry['status']] = (float) $availabilityEntry['total_quantity'];
}
$availabilityStmt->close();

$availabilityMonths = [];
for ($month = $firstMonth; $month < $firstMonth + 3; $month++) {
	$monthDate = $periodStart->setDate($availabilityYear, $month, 1);
	$weekCount = (int) ceil((int) $monthDate->format('t') / 7);
	$weeks = [];
	for ($week = 1; $week <= $weekCount; $week++) {
		$weekStart = (($week - 1) * 7) + 1;
		$weeks[] = [
			'number' => $week,
			'label' => $weekStart . '–' . min($weekStart + 6, (int) $monthDate->format('t')),
		];
	}
	$availabilityMonths[] = [
		'number' => $month,
		'name' => $monthDate->format('M'),
		'weeks' => $weeks,
	];
}
$quarterLabels = [1 => 'Jan–Mar', 2 => 'Apr–Jun', 3 => 'Jul–Sep', 4 => 'Oct–Dec'];
$availabilityFilteredCrops = array_values(array_filter(
	$availabilityCrops,
	static fn (array $crop): bool => $availabilityCropId === 0 || (int) $crop['crop_id'] === $availabilityCropId
));
?>
<section class="panel harvest-availability">
	<div class="page-heading">
		<div><h2>Harvest availability</h2><p>Monthly harvest estimates combined across all farmers. Totals are in kilograms.</p></div>
	</div>
	<div class="harvest-calendar-toolbar">
		<div class="harvest-calendar-legend" aria-label="Harvest status key">
			<span><i class="availability-swatch availability-planned" aria-hidden="true"></i>Not harvested</span>
			<span><i class="availability-swatch availability-harvested" aria-hidden="true"></i>Harvested</span>
		</div>
		<form method="get" class="harvest-calendar-filters">
			<label>Crop
				<select name="crop_id" onchange="this.form.submit()">
					<option value="0">All crops</option>
					<?php foreach ($availabilityCrops as $availabilityCrop): ?>
						<option value="<?= (int) $availabilityCrop['crop_id'] ?>"<?= $availabilityCropId === (int) $availabilityCrop['crop_id'] ? ' selected' : '' ?>><?= e($availabilityCrop['crop_name']) ?></option>
					<?php endforeach; ?>
				</select>
			</label>
				<label>Months
					<select name="quarter" onchange="this.form.submit()">
						<?php foreach ($quarterLabels as $quarterNumber => $quarterLabel): ?>
							<option value="<?= $quarterNumber ?>"<?= $availabilityQuarter === $quarterNumber ? ' selected' : '' ?>><?= e($quarterLabel) ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label>Year
					<select name="year" onchange="this.form.submit()">
					<?php foreach ($availabilityYears as $yearOption): ?>
						<option value="<?= $yearOption ?>"<?= $availabilityYear === $yearOption ? ' selected' : '' ?>><?= $yearOption ?></option>
					<?php endforeach; ?>
				</select>
			</label>
			<noscript><button class="button button-small" type="submit">Apply filters</button></noscript>
		</form>
	</div>
	<?php if (!$availabilityFilteredCrops): ?>
		<div class="empty-state"><strong>No crops available</strong>Crop availability will appear here when crops are added to the catalog.</div>
	<?php else: ?>
		<p class="field-hint calendar-scroll-hint">Scroll horizontally to view all weeks.</p>
		<div class="data-table-wrap harvest-calendar-wrap">
			<table class="data-table harvest-calendar">
				<thead>
					<tr>
						<th scope="col" rowspan="2">Crop</th><th scope="col" rowspan="2">Availability</th>
						<?php foreach ($availabilityMonths as $month): ?><th scope="colgroup" colspan="<?= count($month['weeks']) ?>"><?= e($month['name']) ?></th><?php endforeach; ?>
					</tr>
					<tr><?php foreach ($availabilityMonths as $month): ?><?php foreach ($month['weeks'] as $week): ?><th scope="col">Week <?= $week['number'] ?><small class="calendar-week-range"><?= e($week['label']) ?></small></th><?php endforeach; ?><?php endforeach; ?></tr>
				</thead>
				<tbody>
					<?php foreach ($availabilityFilteredCrops as $availabilityCrop): ?>
						<?php foreach (['Planned' => 'Not harvested', 'Harvested' => 'Harvested'] as $statusKey => $statusLabel): ?>
							<tr>
								<?php if ($statusKey === 'Planned'): ?><th class="availability-crop" scope="rowgroup" rowspan="2"><?= e($availabilityCrop['crop_name']) ?></th><?php endif; ?>
								<th class="availability-status" scope="row"><?= e($statusLabel) ?></th>
								<?php foreach ($availabilityMonths as $month): ?>
									<?php foreach ($month['weeks'] as $week): ?>
										<?php $weekQuantity = $availabilityTotals[(int) $availabilityCrop['crop_id']][$month['number']][$week['number']][$statusKey] ?? 0; ?>
										<td class="availability-cell<?= $weekQuantity > 0 ? ($statusKey === 'Harvested' ? ' availability-harvested' : ' availability-planned') : '' ?>">
											<?= $weekQuantity > 0 ? e(rtrim(rtrim(number_format($weekQuantity, 2, '.', ''), '0'), '.')) . ' kg' : '—' ?>
										</td>
									<?php endforeach; ?>
								<?php endforeach; ?>
							</tr>
						<?php endforeach; ?>
					<?php endforeach; ?>
				</tbody>
			</table>
		</div>
	<?php endif; ?>
</section>
