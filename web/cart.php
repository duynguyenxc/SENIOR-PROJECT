<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/db.php';
ensure_session_started();

$cust = current_customer();

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = $_POST['action'] ?? '';
    $setId = (int)($_POST['setId'] ?? 0);
    
    if ($action === 'add' && $setId > 0) {
        if (!isset($_SESSION['cart'][$setId])) {
            $_SESSION['cart'][$setId] = 1;
        } else {
            $_SESSION['cart'][$setId]++;
        }
        header('Location: /takeout.php?added=1');
        exit;
    } elseif ($action === 'remove' && $setId > 0) {
        unset($_SESSION['cart'][$setId]);
        header('Location: /cart.php');
        exit;
    }
}

$pdo = db();
$cartItems = [];
$total = 0.0;
if (!empty($_SESSION['cart'])) {
    $ids = array_keys($_SESSION['cart']);
    $in = str_repeat('?,', count($ids) - 1) . '?';
    $stmt = $pdo->prepare("SELECT setId, setName, price FROM TakeoutSet WHERE setId IN ($in)");
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();
    
    foreach ($rows as $r) {
        $id = (int)$r['setId'];
        $qty = $_SESSION['cart'][$id];
        $sub = $qty * (float)$r['price'];
        $total += $sub;
        $cartItems[] = [
            'setId' => $id,
            'setName' => $r['setName'],
            'price' => $r['price'],
            'qty' => $qty,
            'subtotal' => $sub
        ];
    }
}

render_header('Your Cart');
?>
<section class="hero">
  <h1>Your Cart</h1>
</section>

<div class="grid">
  <section class="card">
    <h2 class="section-title">Order Details</h2>
    <?php if (empty($cartItems)): ?>
      <div class="alert">Your cart is empty. <br/><br/><a href="/takeout.php" class="btn btn-primary">Browse Takeout Menu</a></div>
    <?php else: ?>
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
            <td><?= h($item['setName']) ?></td>
            <td>$<?= number_format((float)$item['price'], 2) ?></td>
            <td><?= $item['qty'] ?></td>
            <td class="align-right">$<?= number_format($item['subtotal'], 2) ?></td>
            <td class="align-right">
               <form method="post" action="/cart.php" class="form-inline">
                 <?= csrf_input() ?>
                 <input type="hidden" name="action" value="remove" />
                 <input type="hidden" name="setId" value="<?= $item['setId'] ?>" />
                 <button type="submit" class="btn">X</button>
               </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </table>
      
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
