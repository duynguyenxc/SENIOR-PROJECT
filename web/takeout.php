<?php
declare(strict_types=1);
require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/db.php';
ensure_session_started();

$pdo = db();
$sets = $pdo->query('SELECT * FROM TakeoutSet WHERE isAvailable = 1')->fetchAll();

render_header('Takeout Menu');
?>
<section class="hero hero-centered">
  <h1>Takeout Menu</h1>
  <p class="hero-copy-narrow" style="display:block; width:100%; max-width:600px; margin:0 auto; text-align:center;">
    Order our delicious, freshly prepared vegan sets for pickup.
  </p>
  <?php if (!empty($_GET['added'])): ?>
    <div class="alert alert-ok alert-centered">Item added to cart! <a href="/cart.php" class="link-strong">View Cart</a></div>
  <?php endif; ?>
</section>

<div class="card-grid-300">
  <?php foreach ($sets as $set): ?>
    <div class="card card-body-between">
      <div>
        <h3 class="card-title-accent"><?= h($set['setName']) ?></h3>
        <p class="card-copy-muted"><?= h($set['description']) ?></p>
      </div>
      <div class="card-footer-row">
        <span class="price-tag">$<?= h($set['price']) ?></span>
        <form method="post" action="/cart.php" class="form-no-margin">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="setId" value="<?= (int)$set['setId'] ?>" />
          <button class="btn btn-primary" type="submit">Add to Cart</button>
        </form>
      </div>
    </div>
  <?php endforeach; ?>
</div>

<?php render_footer(); ?>
