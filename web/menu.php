<?php
declare(strict_types=1);

require_once __DIR__ . '/lib/layout.php';
require_once __DIR__ . '/lib/db.php';

$pdo = db();

$days = $pdo->query('SELECT dayId, dayName, sortOrder FROM Day ORDER BY sortOrder')->fetchAll();

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

$day = null;
if ($dayId > 0) {
  $stmt = $pdo->prepare('SELECT dayId, dayName, sortOrder FROM Day WHERE dayId = ? LIMIT 1');
  $stmt->execute([$dayId]);
  $day = $stmt->fetch() ?: null;
}

$dishes = [];
if ($day) {
  $stmt = $pdo->prepare(
    'SELECT Dish.dishId, Dish.dishName, Dish.imageUrl
     FROM DayMenuItem
     JOIN Dish ON Dish.dishId = DayMenuItem.dishId
     WHERE DayMenuItem.dayId = ?
     ORDER BY Dish.dishName'
  );
  $stmt->execute([(int)$day['dayId']]);
  $dishes = $stmt->fetchAll();
}

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
          <div class="card dish-card">
            <div class="dish-card-media">
              <?php if ($dish['imageUrl']): ?>
                <img src="<?= h((string)$dish['imageUrl']) ?>" alt="<?= h((string)$dish['dishName']) ?>" class="dish-card-image" />
              <?php else: ?>
                <div class="dish-card-empty">No Image</div>
              <?php endif; ?>
            </div>
            <div class="dish-card-body">
              <h3 class="dish-card-title"><?= h((string)$dish['dishName']) ?></h3>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </section>
</div>

<?php render_footer(); ?>

