<?php
declare(strict_types=1);

// Order history — shows all past orders grouped by date.
// SuperAdmin can permanently delete order records from here.

require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/orders.php';
$admin = require_admin();

$pdo = db();
cancel_stale_pending_orders($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = $_POST['action'] ?? '';
    $orderId = (int)($_POST['orderId'] ?? 0);
    
    if ($orderId > 0 && $action === 'delete' && is_super_admin($admin)) {
        $pdo->prepare("DELETE FROM `Order` WHERE orderId = ?")->execute([$orderId]);
    }
    header('Location: /admin/history.php');
    exit;
}

$stmt = $pdo->query("
    SELECT * FROM `Order`
    ORDER BY DATE(createdTime) DESC, dailyOrderNumber ASC
");
$allOrders = $stmt->fetchAll();
$countedStatuses = order_counted_revenue_statuses();

$groupedByDate = [];
foreach ($allOrders as $o) {
    $dateStr = date('Y-m-d', strtotime($o['createdTime']));
    $groupedByDate[$dateStr][] = $o;
}

render_header('Order History');
?>
<section class="hero hero-compact">
  <h2>Historical Order Review</h2>
  <p class="muted">Recent and past orders for staff review. Destructive cleanup remains limited to the SuperAdmin.</p>
</section>

<div class="stack-lg">
  <?php if (!$groupedByDate): ?>
    <div class="alert">No historical data available.</div>
  <?php else: ?>
    <?php foreach ($groupedByDate as $date => $ordersToday): ?>
      <?php
         $dailyTotal = array_sum(array_map(
             static fn(array $order): float => in_array((string)$order['status'], $countedStatuses, true)
                 ? (float)$order['totalAmount']
                 : 0.0,
             $ordersToday
         ));
         $completedCount = count(array_filter($ordersToday, fn($o) => $o['status'] === 'Completed'));
      ?>
      <div class="card table-group-card">
        <div class="table-group-header">
          <h3 class="table-group-title"><?= date('F j, Y', strtotime($date)) ?></h3>
          <div class="text-right">
            <div class="text-semibold">Total Revenue: $<?= number_format($dailyTotal, 2) ?></div>
            <div class="muted text-sm"><?= count($ordersToday) ?> Orders / <?= $completedCount ?> Completed</div>
          </div>
        </div>
        
        <div class="table-shell">
          <table class="table">
            <thead>
              <tr>
                <th>Order #</th>
                <th>Customer</th>
                <th>Phone</th>
                <th>Amount</th>
                <th>Status</th>
                <th class="align-right">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($ordersToday as $o): ?>
                <?php $orderNumberLabel = display_order_number($o); ?>
                <tr>
                  <td><strong><?= h($orderNumberLabel) ?></strong></td>
                  <td><?= h($o['customerName']) ?></td>
                  <td><?= h($o['customerPhone']) ?></td>
                  <td>$<?= h($o['totalAmount']) ?></td>
                  <td>
                     <span class="status-pill <?= order_status_badge_class((string)$o['status']) ?>"><?= h($o['status']) ?></span>
                  </td>
                  <td class="align-right">
                    <div class="table-actions">
                      <a href="/admin/order.php?orderId=<?= (int)$o['orderId'] ?>" class="btn">View</a>
                      <?php if (is_super_admin($admin)): ?>
                        <form method="post" class="form-inline" onsubmit="return confirm('Are you sure you want to completely erase this order records? This cannot be undone.');">
                          <?= csrf_input() ?>
                          <input type="hidden" name="action" value="delete" />
                          <input type="hidden" name="orderId" value="<?= $o['orderId'] ?>" />
                          <button type="submit" class="btn-link-danger">Delete</button>
                        </form>
                      <?php endif; ?>
                    </div>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<?php render_footer(); ?>
