<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/orders.php';
require_once __DIR__ . '/lib/takeout_catalog.php';
ensure_session_started();

$cust = current_customer();
$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = (string)($_POST['action'] ?? '');
    $setId = (int)($_POST['setId'] ?? 0);
    $lineId = (string)($_POST['lineId'] ?? '');

    try {
        if ($action === 'add' && $setId > 0) {
            add_set_to_cart($setId, (string)($_POST['lineNotes'] ?? ''));
            header('Location: /takeout.php?added=1');
            exit;
        }

        if ($action === 'add_custom' && $setId > 0) {
            $customSet = fetch_takeout_set_by_id($pdo, $setId);
            if (!$customSet || empty($customSet['allowsCustomSelection'])) {
                throw new RuntimeException('Custom takeout box is not available right now.');
            }

            add_custom_takeout_to_cart(
                $setId,
                (string)($_POST['selectedDishes'] ?? ''),
                (string)($_POST['lineNotes'] ?? ''),
                max(1, (int)($customSet['selectionLimit'] ?? CUSTOM_TAKEOUT_DEFAULT_LIMIT))
            );
            header('Location: /takeout.php?added=1');
            exit;
        }

        if ($action === 'remove' && $lineId !== '') {
            remove_cart_item($lineId);
            header('Location: /cart.php');
            exit;
        }
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$cartItems = build_cart_items_for_display($pdo, normalize_cart_items());
$total = calculate_cart_total_amount($pdo, normalize_cart_items());

render_header('Your Cart');
?>
<section class="hero">
  <h1>Your Cart</h1>
  <p class="muted">Review your order and confirm your pickup details before payment.</p>
</section>

<?php if ($error !== ''): ?>
  <div class="alert alert-danger stack-lg"><?= h($error) ?></div>
<?php endif; ?>

<div class="grid">
  <section class="card">
    <h2 class="section-title">Order Details</h2>
    <?php if (empty($cartItems)): ?>
      <div class="alert">Your cart is empty. <br/><br/><a href="/takeout.php" class="btn btn-primary">Browse Takeout Menu</a></div>
    <?php else: ?>
      <div class="table-shell">
        <table class="table">
          <tr class="table-header-row">
            <th>Item</th>
            <th>Price</th>
            <th>Qty</th>
            <th class="align-right">Total</th>
            <th></th>
          </tr>
          <?php foreach ($cartItems as $item): ?>
            <tr class="table-row">
              <td>
                <strong><?= h($item['setName']) ?></strong>
                <?php if (!empty($item['selectedDishes'])): ?>
                  <div class="muted text-sm">Selected dishes: <?= h((string)$item['selectedDishes']) ?></div>
                <?php endif; ?>
                <?php if (!empty($item['lineNotes'])): ?>
                  <div class="muted text-sm">Item note: <?= h((string)$item['lineNotes']) ?></div>
                <?php endif; ?>
              </td>
              <td>$<?= number_format((float)$item['price'], 2) ?></td>
              <td><?= $item['qty'] ?></td>
              <td class="align-right">$<?= number_format($item['subtotal'], 2) ?></td>
              <td class="align-right">
                 <form method="post" action="/cart.php" class="form-inline">
                   <?= csrf_input() ?>
                   <input type="hidden" name="action" value="remove" />
                   <input type="hidden" name="lineId" value="<?= h((string)$item['id']) ?>" />
                   <button type="submit" class="btn">X</button>
                 </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
      
      <div class="summary-total">
        Total: <strong class="text-accent">$<?= number_format($total, 2) ?></strong>
      </div>
    <?php endif; ?>
  </section>

  <aside class="card">
    <h2 class="section-title">Checkout</h2>
    <?php if (empty($cartItems)): ?>
      <p class="muted">Add items to your cart to checkout.</p>
    <?php elseif (!$cust): ?>
      <div class="alert">
        Please sign in or create an account before placing a takeout order.
      </div>
      <div class="btnrow btn-stack-vertical">
        <a href="/login.php?redirect=%2Fcart.php" class="btn btn-primary">Log In to Continue</a>
        <a href="/register.php?redirect=%2Fcart.php" class="btn">Create Account</a>
      </div>
    <?php else: ?>
      <form method="post" action="/checkout.php">
        <?= csrf_input() ?>
        <div class="field">
          <label>Name</label>
          <input type="text" name="customerName" required value="<?= h($cust['fullName']) ?>" />
        </div>
        <div class="field">
          <label>Phone</label>
          <input type="tel" name="customerPhone" required />
        </div>
        <div class="field">
          <label>Pickup Time (today)</label>
          <input type="time" name="pickupTime" required />
        </div>
        <button class="btn btn-success btn-full btn-lg" type="submit">Proceed to Payment</button>
      </form>
    <?php endif; ?>
  </aside>
</div>
<?php render_footer(); ?>
