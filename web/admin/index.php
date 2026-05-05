<?php
declare(strict_types=1);

// Admin dashboard — active order fulfillment queue.
// Staff can advance orders through statuses: Paid > Preparing > Ready > Completed.
// SuperAdmin can also cancel orders from here.

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/orders.php';
require_once __DIR__ . '/../lib/reporting.php';
$admin = require_admin();

$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['orderId'] ?? 0);
    
    if ($orderId > 0) {
        if ($action === 'prepare') {
            update_order_status($pdo, $orderId, ORDER_STATUS_PREPARING);
        } elseif ($action === 'ready') {
            update_order_status($pdo, $orderId, ORDER_STATUS_READY);
        } elseif ($action === 'complete') {
            update_order_status($pdo, $orderId, ORDER_STATUS_COMPLETED);
        } elseif ($action === 'cancel' && is_super_admin($admin)) {
            update_order_status($pdo, $orderId, ORDER_STATUS_CANCELLED);
        }
    }
    header('Location: /admin/index.php');
    exit;
}

$queueStatuses = order_active_queue_statuses();
$stmt = $pdo->prepare(
    'SELECT o.*, p.referenceId
     FROM `Order` o
     LEFT JOIN Payment p ON o.orderId = p.orderId
     WHERE o.status IN (' . sql_placeholders($queueStatuses) . ')
     ORDER BY o.pickupTime ASC'
);
$stmt->execute($queueStatuses);
$orders = $stmt->fetchAll();

$orderIds = array_column($orders, 'orderId');
$itemsByOrder = fetch_order_items_by_order_ids($pdo, $orderIds);
$todayKpi = fetch_sales_kpi($pdo, new DateTimeImmutable('today'), new DateTimeImmutable('today'));
$todayStatusCounts = fetch_status_breakdown($pdo, new DateTimeImmutable('today'), new DateTimeImmutable('today'));
$activeQueueCount = count($orders);

render_header('Dashboard');
?>
<section class="hero hero-compact">
  <h2>Active Fulfillment Queue</h2>
  <p class="muted">Manage and process orders in real time.</p>
</section>

<section class="metrics-grid stack-lg">
  <article class="card metric-card">
    <span class="metric-label">Today's Revenue</span>
    <strong class="metric-value">$<?= number_format($todayKpi['totalRevenue'], 2) ?></strong>
  </article>
  <article class="card metric-card">
    <span class="metric-label">Completed Today</span>
    <strong class="metric-value"><?= (int)($todayStatusCounts[ORDER_STATUS_COMPLETED] ?? 0) ?></strong>
  </article>
  <article class="card metric-card">
    <span class="metric-label">Active Queue</span>
    <strong class="metric-value"><?= $activeQueueCount ?></strong>
  </article>
</section>

<div class="stack-lg" data-order-stream="/admin/order_stream.php?scope=queue">
  <?php if (!$orders): ?>
    <div class="alert">No active orders at this time.</div>
  <?php else: ?>
    <div class="order-list">
      <?php foreach ($orders as $o): ?>
        <?php
          $statusClass = order_status_badge_class((string)$o['status']);
          $orderNumberLabel = display_order_number($o);
        ?>
        <div class="card order-card <?= $statusClass ?>">
          <div class="order-header">
            <div>
              <h3 class="order-title">Order <?= h($orderNumberLabel) ?> - <?= h($o['customerName']) ?></h3>
              <div class="muted">Phone: <?= h($o['customerPhone']) ?> | Total: <strong>$<?= h($o['totalAmount']) ?></strong></div>
              <?php if (!empty($o['specialInstructions']) || !empty($o['allergyNotes'])): ?>
                <div class="note-list note-list-top">
                  <?php if (!empty($o['specialInstructions'])): ?>
                    <div class="note-callout note-callout-danger">
                      <strong>Special instructions:</strong> <?= nl2br(h((string)$o['specialInstructions'])) ?>
                    </div>
                  <?php endif; ?>
                  <?php if (!empty($o['allergyNotes'])): ?>
                    <div class="note-callout note-callout-danger">
                      <strong>Allergy notes:</strong> <?= nl2br(h((string)$o['allergyNotes'])) ?>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endif; ?>
            </div>
            <div class="order-side">
              <strong class="status-badge <?= $statusClass ?>"><?= h($o['status']) ?></strong>
              <div class="pickup-time"><?= date('h:i A', strtotime($o['pickupTime'])) ?></div>
            </div>
          </div>
          <div class="order-items">
            <strong class="muted">Items:</strong>
            <ul class="order-items-list">
              <?php foreach (($itemsByOrder[$o['orderId']] ?? []) as $i): ?>
                <li>
                  <?= $i['quantity'] ?>x <?= h((string)$i['lineLabel']) ?>
                  <?php if (!empty($i['lineDescription']) && (string)$i['lineType'] === ORDER_LINE_TYPE_CUSTOM): ?>
                    <div class="muted text-sm"><?= nl2br(h((string)$i['lineDescription'])) ?></div>
                  <?php endif; ?>
                  <?php if (!empty($i['lineNotes'])): ?>
                    <div class="note-callout note-callout-danger note-callout-inline">
                      <strong>Item note:</strong> <?= nl2br(h((string)$i['lineNotes'])) ?>
                    </div>
                  <?php endif; ?>
                </li>
              <?php endforeach; ?>
            </ul>
          </div>
          
          <div class="order-actions">
            <a href="/admin/order.php?orderId=<?= (int)$o['orderId'] ?>" class="btn">View Details</a>
            <?php if ($o['status'] === 'Paid'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="prepare" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-warning-soft">Start Preparing</button>
              </form>
            <?php elseif ($o['status'] === 'Preparing'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="ready" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-ok-soft">Mark Ready</button>
              </form>
            <?php elseif ($o['status'] === 'Ready'): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="complete" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-primary">Complete Order</button>
              </form>
            <?php endif; ?>
            
            <?php if (is_super_admin($admin)): ?>
              <form method="post" class="form-inline">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="cancel" />
                <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                <button type="submit" class="btn btn-danger-outline">Cancel Order</button>
              </form>
            <?php endif; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
