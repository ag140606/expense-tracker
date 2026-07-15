<?php
require_once 'config.php';

$categories = ['Food', 'Transport', 'Entertainment', 'Education', 'Utilities', 'Health', 'Shopping', 'Other'];

$errors = [];
$title = '';
$amount = '';
$category = 'Food';
$date = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title    = trim($_POST['title'] ?? '');
    $amount   = trim($_POST['amount'] ?? '');
    $category = trim($_POST['category'] ?? '');
    $date     = trim($_POST['date'] ?? '');

    // Validation
    if ($title === '') {
        $errors[] = 'Title is required.';
    }
    if ($amount === '' || !is_numeric($amount) || (float)$amount <= 0) {
        $errors[] = 'Amount must be a positive number.';
    }
    if (!in_array($category, $categories, true)) {
        $errors[] = 'Please choose a valid category.';
    }
    if ($date === '' || !DateTime::createFromFormat('Y-m-d', $date)) {
        $errors[] = 'Please choose a valid date.';
    }

    if (empty($errors)) {
        $stmt = $pdo->prepare(
            "INSERT INTO expenses (title, amount, category, date) VALUES (:title, :amount, :category, :date)"
        );
        $stmt->execute([
            ':title'    => $title,
            ':amount'   => $amount,
            ':category' => $category,
            ':date'     => $date,
        ]);

        header('Location: index.php?added=1');
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Add Expense</title>
<link rel="stylesheet" href="css/style.css">
</head>
<body>
<div class="page page-narrow">

    <a href="index.php" class="back-link">&larr; Back to all expenses</a>

    <h1>Add Expense</h1>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="add.php" method="POST" class="card-form">
        <label for="title">Title</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>" placeholder="e.g. Grocery shopping" required>

        <label for="amount">Amount (₹)</label>
        <input type="number" id="amount" name="amount" value="<?= htmlspecialchars($amount) ?>" step="0.01" min="0.01" placeholder="0.00" required>

        <label for="category">Category</label>
        <select id="category" name="category" required>
            <?php foreach ($categories as $cat): ?>
                <option value="<?= $cat ?>" <?= $category === $cat ? 'selected' : '' ?>><?= $cat ?></option>
            <?php endforeach; ?>
        </select>

        <label for="date">Date</label>
        <input type="date" id="date" name="date" value="<?= htmlspecialchars($date) ?>" required>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Expense</button>
            <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>

</div>
</body>
</html>