<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/menu_catalog.php';
require_once __DIR__ . '/../lib/upload.php';
require_super_admin();

$pdo = db();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create_and_add') {
            $dayId = (int)($_POST['dayId'] ?? 0);
            if ($dayId <= 0) {
                throw new RuntimeException('Please choose a day first.');
            }

            create_or_update_dish_for_day(
                $pdo,
                $dayId,
                (string)($_POST['dishName'] ?? ''),
                trim((string)($_POST['description'] ?? '')),
                store_uploaded_image('image', 'dishes')
            );
        } elseif ($action === 'remove') {
            $dayMenuItemId = (int)($_POST['dayMenuItemId'] ?? 0);
            if ($dayMenuItemId > 0) {
                remove_day_menu_item($pdo, $dayMenuItemId);
            }
        }

        header('Location: /admin/menu.php?day=' . (int)($_POST['dayId'] ?? 0));
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$days = fetch_days($pdo);

$dayId = (int)($_GET['day'] ?? ($days[0]['dayId'] ?? 0));

$menuItems = $dayId > 0 ? fetch_day_menu_dishes($pdo, $dayId) : [];

render_header('Manage Menu');
?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<div class="grid">
  <aside class="card">
    <h2 class="section-title">Select Day</h2>
    <div class="sidebar-list">
      <?php foreach ($days as $d): ?>
        <a href="?day=<?= $d['dayId'] ?>" class="btn btn-justify-start <?= ($d['dayId']==$dayId) ? 'btn-primary' : '' ?>">
          <?= h($d['dayName']) ?>
        </a>
      <?php endforeach; ?>
    </div>
  </aside>

  <section class="card">
    <div class="section-header">
      <div>
        <h2 class="section-title">Day's Menu Items</h2>
        <p class="muted">Create the dish here with its name, description, and image, then add it straight to the selected day.</p>
      </div>
    </div>
    <?php if (!$menuItems): ?>
      <p class="muted">No dishes scheduled.</p>
    <?php else: ?>
      <div class="table-shell">
        <table class="table table-compact">
          <?php foreach ($menuItems as $item): ?>
            <tr>
              <td>
                <strong><?= h($item['dishName']) ?></strong>
                <?php if (!empty($item['description'])): ?>
                  <div class="muted text-sm"><?= h($item['description']) ?></div>
                <?php endif; ?>
              </td>
              <td class="align-right">
                <form method="post" class="form-inline">
                  <?= csrf_input() ?>
                  <input type="hidden" name="action" value="remove" />
                  <input type="hidden" name="dayMenuItemId" value="<?= $item['dayMenuItemId'] ?>" />
                  <input type="hidden" name="dayId" value="<?= $dayId ?>" />
                  <button type="submit" class="btn">Remove</button>
                </form>
              </td>
            </tr>
          <?php endforeach; ?>
        </table>
      </div>
    <?php endif; ?>
    
    <hr class="rule" />
    
    <h3 class="section-title">Add Dish to Menu</h3>
    <form method="post" enctype="multipart/form-data" class="admin-simple-form">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="create_and_add" />
      <input type="hidden" name="dayId" value="<?= $dayId ?>" />
      <div class="field">
        <label>Dish Name</label>
        <input type="text" name="dishName" required placeholder="Example: Tofu Stir-Fry" />
      </div>
      <div class="field">
        <label>Description / Ingredients</label>
        <textarea name="description" rows="4" required placeholder="Short ingredients or dish description..."></textarea>
      </div>
      <div class="field">
        <label>Upload Image</label>
        <input type="file" name="image" accept="image/*" />
      </div>
      <button type="submit" class="btn btn-primary admin-form-submit">Add</button>
    </form>
  </section>
</div>
<?php render_footer(); ?>
