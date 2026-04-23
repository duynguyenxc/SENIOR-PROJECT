<?php
declare(strict_types=1);
require_once __DIR__ . '/../lib/layout.php';
require_once __DIR__ . '/../lib/takeout_catalog.php';
require_once __DIR__ . '/../lib/upload.php';
require_super_admin();

$pdo = db();
$error = '';
$editId = (int)($_GET['edit'] ?? 0);
$editingSet = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_valid_csrf_token();
    $action = (string)($_POST['action'] ?? '');

    try {
        if ($action === 'create') {
            $name = trim((string)($_POST['setName'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            if ($name === '' || $description === '' || $price <= 0) {
                throw new RuntimeException('Name, description, and a positive price are required.');
            }

            create_takeout_set($pdo, [
                'setName' => $name,
                'description' => $description,
                'price' => $price,
                'imageUrl' => store_uploaded_image('image', 'takeout'),
                'isAvailable' => true,
                'sortOrder' => 0,
                'allowsCustomSelection' => false,
                'selectionLimit' => 0,
            ]);
        } elseif ($action === 'update') {
            $setId = (int)($_POST['setId'] ?? 0);
            $existingSet = fetch_takeout_set_by_id($pdo, $setId);
            if (!$existingSet) {
                throw new RuntimeException('Takeout set not found.');
            }

            $name = trim((string)($_POST['setName'] ?? ''));
            $description = trim((string)($_POST['description'] ?? ''));
            $price = (float)($_POST['price'] ?? 0);
            if ($name === '' || $description === '' || $price <= 0) {
                throw new RuntimeException('Name, description, and a positive price are required.');
            }

            update_takeout_set($pdo, $setId, [
                'setName' => $name,
                'description' => $description,
                'price' => $price,
                'imageUrl' => store_uploaded_image('image', 'takeout', (string)($existingSet['imageUrl'] ?? '')),
                'isAvailable' => (bool)$existingSet['isAvailable'],
                'sortOrder' => (int)$existingSet['sortOrder'],
                'allowsCustomSelection' => (bool)$existingSet['allowsCustomSelection'],
                'selectionLimit' => (int)$existingSet['selectionLimit'],
            ]);
        } elseif ($action === 'delete') {
            $setId = (int)($_POST['setId'] ?? 0);
            if ($setId > 0) {
                delete_takeout_set($pdo, $setId);
            }
        }

        header('Location: /admin/takeout.php');
        exit;
    } catch (Throwable $exception) {
        $error = $exception->getMessage();
    }
}

$sets = fetch_all_takeout_sets($pdo);
$editingSet = $editId > 0 ? fetch_takeout_set_by_id($pdo, $editId) : null;
$formMode = $editingSet ? 'update' : 'create';

render_header('Manage Takeout Sets');
?>
<?php if ($error !== ''): ?>
  <div class="alert alert-danger"><?= h($error) ?></div>
<?php endif; ?>
<div class="grid">
  <section class="card">
    <div class="section-header">
      <div>
        <h2 class="section-title">Existing Takeout Sets</h2>
        <p class="muted">Keep the current sets clean and simple: view, edit, or delete each one from here.</p>
      </div>
    </div>

    <div class="takeout-admin-list">
      <?php foreach ($sets as $set): ?>
        <article class="takeout-admin-row">
          <div class="takeout-admin-row-content">
            <div class="takeout-admin-row-header">
              <div class="takeout-admin-row-main">
                <h3 class="takeout-admin-row-title"><?= h((string)$set['setName']) ?></h3>
                <p class="takeout-admin-row-copy muted"><?= h((string)$set['description']) ?></p>
                <div class="catalog-meta">
                  <span class="status-pill status-muted"><?= !empty($set['imageUrl']) ? 'Image added' : 'No image yet' ?></span>
                  <?php if (!empty($set['allowsCustomSelection'])): ?>
                    <span class="status-pill status-paid">Custom Box</span>
                  <?php endif; ?>
                  <?php if (empty($set['isAvailable'])): ?>
                    <span class="status-pill status-cancelled">Hidden</span>
                  <?php endif; ?>
                </div>
              </div>
              <strong class="price-tag">$<?= number_format((float)$set['price'], 2) ?></strong>
            </div>
            <div class="takeout-admin-row-actions">
              <a href="/admin/takeout.php?edit=<?= (int)$set['setId'] ?>" class="btn">Edit</a>
              <form method="post" class="form-inline" onsubmit="return confirm('Delete this takeout set?');">
                <?= csrf_input() ?>
                <input type="hidden" name="action" value="delete" />
                <input type="hidden" name="setId" value="<?= (int)$set['setId'] ?>" />
                <button class="btn btn-danger-outline" type="submit">Delete</button>
              </form>
            </div>
          </div>
        </article>
      <?php endforeach; ?>
    </div>
  </section>
  
  <aside class="card">
    <div class="section-header">
      <div>
        <h2 class="section-title"><?= $editingSet ? 'Edit Set' : 'Add New Set' ?></h2>
        <p class="muted"><?= $editingSet ? 'Update the selected takeout set here.' : 'Create a new takeout set with image, description, and price.' ?></p>
      </div>
      <?php if ($editingSet): ?>
        <a href="/admin/takeout.php" class="btn">New Set</a>
      <?php endif; ?>
    </div>
    <form method="post" enctype="multipart/form-data">
      <?= csrf_input() ?>
      <input type="hidden" name="action" value="<?= $formMode ?>" />
      <?php if ($editingSet): ?>
        <input type="hidden" name="setId" value="<?= (int)$editingSet['setId'] ?>" />
      <?php endif; ?>
      <div class="field">
        <label>Name</label>
        <input type="text" name="setName" required value="<?= h((string)($editingSet['setName'] ?? '')) ?>" />
      </div>
      <div class="field">
        <label>Description (ingredients)</label>
        <textarea name="description" rows="4" required><?= h((string)($editingSet['description'] ?? '')) ?></textarea>
      </div>
      <div class="field">
        <label>Price ($)</label>
        <input type="number" step="0.01" name="price" required value="<?= h((string)($editingSet['price'] ?? '')) ?>" />
      </div>
      <div class="field">
        <label><?= $editingSet ? 'Replace Image' : 'Upload Image' ?></label>
        <input type="file" name="image" accept="image/*" />
      </div>
      <?php if ($editingSet && (!empty($editingSet['allowsCustomSelection']) || empty($editingSet['isAvailable']))): ?>
        <div class="catalog-meta">
          <?php if (!empty($editingSet['allowsCustomSelection'])): ?>
            <span class="status-pill status-paid">This is the current custom box template</span>
          <?php endif; ?>
          <?php if (empty($editingSet['isAvailable'])): ?>
            <span class="status-pill status-cancelled">Currently hidden from customers</span>
          <?php endif; ?>
        </div>
      <?php endif; ?>
      <button type="submit" class="btn btn-primary btn-full"><?= $editingSet ? 'Save Changes' : 'Add Set' ?></button>
    </form>
  </aside>
</div>
<?php render_footer(); ?>
