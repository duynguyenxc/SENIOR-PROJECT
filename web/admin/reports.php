<?php
declare(strict_types=1);

// Sales analytics dashboard (SuperAdmin only).
// Shows KPIs, best/worst sellers, pickup windows, and status breakdown
// for a selectable date range.

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/reporting.php';

require_super_admin();

$pdo = db();
cancel_stale_pending_orders($pdo);
$range = normalized_report_range((string)($_GET['range'] ?? 'week'));
$startInput = (string)($_GET['start'] ?? '');
$endInput = (string)($_GET['end'] ?? '');
[$startDate, $endDate] = report_date_bounds($range, $startInput, $endInput);

$overview = fetch_report_overview($pdo);
$selectedKpi = fetch_sales_kpi($pdo, $startDate, $endDate);
$statusBreakdown = fetch_status_breakdown($pdo, $startDate, $endDate);
$bestSellers = fetch_best_selling_sets($pdo, $startDate, $endDate, 5);
$lowSellingSets = fetch_low_selling_available_sets($pdo, $startDate, $endDate, 3);
$pickupWindows = fetch_peak_pickup_windows($pdo, $startDate, $endDate, 3);
$insights = build_report_insights($selectedKpi, $bestSellers, $lowSellingSets, $pickupWindows);

$rangeLabel = match ($range) {
    'today' => 'Today',
    'week' => 'This Week',
    'month' => 'This Month',
    'custom' => 'Custom Range',
    default => 'This Week',
};

render_header('Admin Reports');
?>
<section class="hero hero-compact">
  <h2>Sales Analytics</h2>
  <p class="muted">Track revenue, order performance, and best-selling takeout sets in one place.</p>
</section>

<section class="card stack-lg">
  <form method="get" action="/admin/reports.php" class="report-filter">
    <div class="field">
      <label for="range">Report Range</label>
      <select id="range" name="range">
        <option value="today" <?= $range === 'today' ? 'selected' : '' ?>>Today</option>
        <option value="week" <?= $range === 'week' ? 'selected' : '' ?>>This Week</option>
        <option value="month" <?= $range === 'month' ? 'selected' : '' ?>>This Month</option>
        <option value="custom" <?= $range === 'custom' ? 'selected' : '' ?>>Custom Range</option>
      </select>
    </div>
    <div class="field">
      <label for="start">Start Date</label>
      <input id="start" name="start" type="date" value="<?= h($startDate->format('Y-m-d')) ?>" />
    </div>
    <div class="field">
      <label for="end">End Date</label>
      <input id="end" name="end" type="date" value="<?= h($endDate->format('Y-m-d')) ?>" />
    </div>
    <div class="report-filter-actions">
      <button type="submit" class="btn btn-primary">Apply Filter</button>
    </div>
  </form>
</section>

<section class="metrics-grid stack-lg">
  <article class="card metric-card">
    <span class="metric-label">Today</span>
    <strong class="metric-value">$<?= number_format($overview['today']['totalRevenue'], 2) ?></strong>
    <span class="metric-meta"><?= $overview['today']['orderCount'] ?> paid orders</span>
  </article>
  <article class="card metric-card">
    <span class="metric-label">This Week</span>
    <strong class="metric-value">$<?= number_format($overview['week']['totalRevenue'], 2) ?></strong>
    <span class="metric-meta"><?= $overview['week']['orderCount'] ?> paid orders</span>
  </article>
  <article class="card metric-card">
    <span class="metric-label">This Month</span>
    <strong class="metric-value">$<?= number_format($overview['month']['totalRevenue'], 2) ?></strong>
    <span class="metric-meta"><?= $overview['month']['orderCount'] ?> paid orders</span>
  </article>
</section>

<section class="report-layout stack-lg">
  <div class="report-main">
    <section class="card">
      <div class="report-section-header">
        <div>
          <h3 class="section-title"><?= h($rangeLabel) ?> Summary</h3>
          <p class="muted">From <?= h($startDate->format('M j, Y')) ?> to <?= h($endDate->format('M j, Y')) ?></p>
        </div>
      </div>

      <div class="metrics-grid metrics-grid-compact">
        <article class="metric-card metric-card-plain">
          <span class="metric-label">Revenue</span>
          <strong class="metric-value">$<?= number_format($selectedKpi['totalRevenue'], 2) ?></strong>
        </article>
        <article class="metric-card metric-card-plain">
          <span class="metric-label">Paid Orders</span>
          <strong class="metric-value"><?= $selectedKpi['orderCount'] ?></strong>
        </article>
        <article class="metric-card metric-card-plain">
          <span class="metric-label">Average Order Value</span>
          <strong class="metric-value">$<?= number_format($selectedKpi['averageOrderValue'], 2) ?></strong>
        </article>
      </div>
    </section>

    <section class="card stack-lg">
      <div class="report-section-header">
        <div>
          <h3 class="section-title">Best-Selling Sets</h3>
          <p class="muted">Top performers by unit sales and revenue.</p>
        </div>
      </div>

      <?php if ($bestSellers === []): ?>
        <div class="alert">No paid sales found in the selected period.</div>
      <?php else: ?>
        <div class="table-shell">
          <table class="table">
            <thead>
              <tr>
                <th>Set</th>
                <th>Units Sold</th>
                <th>Revenue</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($bestSellers as $set): ?>
                <tr>
                  <td><?= h((string)$set['setName']) ?></td>
                  <td><?= (int)$set['totalQuantity'] ?></td>
                  <td>$<?= number_format((float)$set['totalRevenue'], 2) ?></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      <?php endif; ?>
    </section>
  </div>

  <aside class="report-sidebar">
    <section class="card">
      <h3 class="section-title">Status Breakdown</h3>
      <div class="status-grid">
        <?php foreach ($statusBreakdown as $status => $count): ?>
          <div class="status-card">
            <span class="status-pill <?= order_status_badge_class((string)$status) ?>"><?= h((string)$status) ?></span>
            <strong class="status-count"><?= (int)$count ?></strong>
          </div>
        <?php endforeach; ?>
      </div>
    </section>

    <section class="card stack-lg">
      <h3 class="section-title">Business Insights</h3>
      <?php if ($insights === []): ?>
        <p class="muted">Insights will appear after real sales are recorded.</p>
      <?php else: ?>
        <ul class="insight-list">
          <?php foreach ($insights as $insight): ?>
            <li><?= h($insight) ?></li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card stack-lg">
      <h3 class="section-title">Slow-Moving Sets</h3>
      <?php if ($lowSellingSets === []): ?>
        <p class="muted">No available sets to evaluate yet.</p>
      <?php else: ?>
        <ul class="plain-list">
          <?php foreach ($lowSellingSets as $set): ?>
            <li>
              <strong><?= h((string)$set['setName']) ?></strong>
              <span class="muted"><?= (int)$set['totalQuantity'] ?> orders</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>

    <section class="card stack-lg">
      <h3 class="section-title">Peak Pickup Windows</h3>
      <?php if ($pickupWindows === []): ?>
        <p class="muted">Pickup demand trends will appear once sales are recorded.</p>
      <?php else: ?>
        <ul class="plain-list">
          <?php foreach ($pickupWindows as $window): ?>
            <li>
              <strong><?= h((string)$window['pickupWindow']) ?></strong>
              <span class="muted"><?= (int)$window['orderCount'] ?> orders</span>
            </li>
          <?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </section>
  </aside>
</section>

<?php render_footer(); ?>
