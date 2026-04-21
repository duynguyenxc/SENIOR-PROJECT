<?php
require_once __DIR__ . '/lib/db.php';
$pdo = db();

try {
  $hash = password_hash('password', PASSWORD_DEFAULT);
  $stmt = $pdo->prepare("INSERT IGNORE INTO Admin (email, passwordHash, role, isActive, createdTime) VALUES ('admin@vegbuffet.com', ?, 'SuperAdmin', 1, NOW())");
  $stmt->execute([$hash]);

  $pdo->exec("INSERT IGNORE INTO TakeoutSet (setName, description, price, isAvailable) VALUES 
  ('Vegan Delight Box', 'A delicious combo of Tofu Stir-fry, Spring Rolls, and Jasmine Rice.', 14.99, 1),
  ('Gluten-Free Harvest', 'Zucchini Noodles with Pesto, Chickpea Salad, and Lentil Soup.', 16.50, 1),
  ('Spicy Thai Combo', 'Vegan Pad Thai, Cauliflower Wings, and Mango Sticky Rice.', 15.00, 1)");
  
  echo "Seed complete.";
} catch (Exception $e) {
  echo "Error: " . $e->getMessage();
}
