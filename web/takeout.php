<?php
declare(strict_types=1);

// Public takeout menu — displays available takeout sets.
// Customers can add pre-built sets or build a custom box from here.

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/takeout_catalog.php';
ensure_session_started();

$pdo = db();
$sets = fetch_all_takeout_sets($pdo, true);

render_header('Takeout Menu');
?>
<section class="hero hero-centered">
  <h1>Takeout Menu</h1>
  <p class="hero-copy-narrow" style="display: block; width: 100%; max-width: 600px; margin: 0 auto; text-align: center !important;">
    Order our delicious, freshly prepared vegan sets for pickup.
  </p>
  <?php if (!empty($_GET['added'])): ?>
    <div class="alert alert-ok alert-centered">Item added to cart! <a href="/cart.php" class="link-strong">View Cart</a></div>
  <?php endif; ?>
</section>

<div class="card-grid-300">
  <?php foreach ($sets as $set): ?>
    <article class="card card-body-between takeout-card">
      <div>
        <div class="takeout-card-media">
          <?php if (!empty($set['imageUrl'])): ?>
            <img src="<?= h((string)$set['imageUrl']) ?>" alt="<?= h((string)$set['setName']) ?>" class="takeout-card-image" />
          <?php else: ?>
            <div class="takeout-card-empty">No image</div>
          <?php endif; ?>
        </div>
        <h3 class="card-title-accent"><?= h($set['setName']) ?></h3>
        <p class="card-copy-muted"><?= h($set['description']) ?></p>
        <?php if (!empty($set['allowsCustomSelection'])): ?>
          <div class="alert alert-info compact-alert">
            Choose up to <?= (int)$set['selectionLimit'] ?> dishes from today's weekly menu. If you type more than <?= (int)$set['selectionLimit'] ?>, staff will prepare the first <?= (int)$set['selectionLimit'] ?> listed.
          </div>
          <form method="post" action="/cart.php" class="takeout-add-form">
            <?= csrf_input() ?>
            <input type="hidden" name="action" value="add_custom" />
            <input type="hidden" name="setId" value="<?= (int)$set['setId'] ?>" />
            <div class="field">
              <label>Chosen dishes (comma or new line separated)</label>
              <textarea name="selectedDishes" rows="4" required placeholder="Example: Tofu Stir-Fry, Vegetable Curry, Mango Sticky Rice"></textarea>
            </div>
            <div class="field">
              <label>Special note for this box (optional)</label>
              <textarea name="lineNotes" rows="3" placeholder="Less spicy, no peanuts, pack sauce separately..."></textarea>
            </div>
            <div class="card-footer-row">
              <span class="price-tag">$<?= h($set['price']) ?></span>
              <button class="btn btn-primary" type="submit">Add Custom Box</button>
            </div>
          </form>
        <?php else: ?>
          <form method="post" action="/cart.php" class="takeout-add-form">
          <?= csrf_input() ?>
          <input type="hidden" name="action" value="add" />
          <input type="hidden" name="setId" value="<?= (int)$set['setId'] ?>" />
            <div class="field">
              <label>Special note for this set (optional)</label>
              <textarea name="lineNotes" rows="3" placeholder="Remove one ingredient, allergy reminder, packing request..."></textarea>
            </div>
            <div class="card-footer-row">
              <span class="price-tag">$<?= h($set['price']) ?></span>
              <button class="btn btn-primary" type="submit">Add to Cart</button>
            </div>
          </form>
        <?php endif; ?>
      </div>
    </article>
  <?php endforeach; ?>
</div>

<?php render_footer(); ?>
