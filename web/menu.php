<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/menu_catalog.php';

$pdo = db();

$days = fetch_days($pdo);

$dayId = (int)($_GET['day'] ?? 0);
if ($dayId <= 0 && $days) {
  $currentDaySort = (int)date('N');
  foreach ($days as $d) {
    if ((int)$d['sortOrder'] === $currentDaySort) {
      $dayId = (int)$d['dayId'];
      break;
    }
  }
  if ($dayId <= 0) $dayId = (int)$days[0]['dayId'];
}

$day = $dayId > 0 ? fetch_day_by_id($pdo, $dayId) : null;

$dishes = $day ? fetch_day_menu_dishes($pdo, (int)$day['dayId']) : [];

$price = ($day && (int)$day['sortOrder'] === 4) ? 15 : 17;

render_header('Menu');
?>

<section class="hero">
  <h1>Weekly Menu</h1>
  <p>
    Buffet price: <b>$<?= h((string)$price) ?></b> per person
    <?php if ($day && (int)$day['sortOrder'] === 4): ?>
      <span class="muted">(Thursday discount)</span>
    <?php endif; ?>
  </p>
</section>

<div class="grid">
  <aside class="card">
    <h2 class="section-title">Days</h2>
    <div class="btnrow btnrow-wrap btnrow-no-margin">
      <?php foreach ($days as $d): ?>
        <?php $active = ((int)$d['dayId'] === $dayId); ?>
        <a class="btn <?= $active ? 'btn-primary' : '' ?>" href="/menu.php?day=<?= (int)$d['dayId'] ?>">
          <?= h((string)$d['dayName']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="card">
    <h2 class="section-title"><?= $day ? h((string)$day['dayName']) : 'Menu' ?></h2>
    <?php if (!$day): ?>
      <div class="alert alert-danger">No day selected.</div>
    <?php elseif (!$dishes): ?>
      <div class="alert">No dishes set for this day yet.</div>
    <?php else: ?>
      <div class="card-grid-200">
        <?php foreach ($dishes as $dish): ?>
          <button
            type="button"
            class="card dish-card dish-card-button"
            data-dish-trigger
            data-dish-name="<?= h((string)$dish['dishName']) ?>"
            data-dish-description="<?= h((string)($dish['description'] ?? 'Ingredients will be added soon.')) ?>"
            data-dish-image="<?= h((string)($dish['imageUrl'] ?? '')) ?>"
          >
            <div class="dish-card-media">
              <?php if ($dish['imageUrl']): ?>
                <img src="<?= h((string)$dish['imageUrl']) ?>" alt="<?= h((string)$dish['dishName']) ?>" class="dish-card-image" />
              <?php else: ?>
                <div class="dish-card-empty">No Image</div>
              <?php endif; ?>
            </div>
            <div class="dish-card-body">
              <h3 class="dish-card-title"><?= h((string)$dish['dishName']) ?></h3>
              <p class="dish-card-copy">Click to view ingredients and details.</p>
            </div>
          </button>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<dialog class="dish-dialog" data-dish-dialog>
  <div class="dish-dialog-card">
    <button type="button" class="dish-dialog-close" data-dish-close aria-label="Close dish details">×</button>
    <div class="dish-dialog-media" data-dish-dialog-media>
      <img src="" alt="" data-dish-dialog-image class="dish-dialog-image" hidden />
      <div class="dish-dialog-empty" data-dish-dialog-empty>No image available</div>
    </div>
    <div class="dish-dialog-body">
      <h3 data-dish-dialog-title></h3>
      <p class="dish-dialog-copy muted" data-dish-dialog-description></p>
    </div>
  </div>
</dialog>

<?php render_footer(); ?>

