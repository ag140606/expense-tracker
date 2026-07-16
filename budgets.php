<?php
require_once 'config.php';

$categories = ['Food', 'Transport', 'Entertainment', 'Education', 'Utilities', 'Health', 'Shopping', 'Other'];

$errors = [];
$saved  = isset($_GET['saved']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submitted = $_POST['limit'] ?? [];

    // Validation
    $toUpsert = [];
    $toClear  = [];
    foreach ($categories as $cat) {
        $val = trim($submitted[$cat] ?? '');
        if ($val === '') {
            $toClear[] = $cat; // Blank input = remove the budget for this category
            continue;
        }
        if (!is_numeric($val) || (float)$val < 0) {
            $errors[] = "Budget for $cat must be a non-negative number.";
            continue;
        }
        $toUpsert[$cat] = (float)$val;
    }

    if (empty($errors)) {
        $upsert = $pdo->prepare(
            "INSERT INTO budgets (category, monthly_limit) VALUES (:category, :limit)
             ON DUPLICATE KEY UPDATE monthly_limit = :limit2"
        );
        foreach ($toUpsert as $cat => $limit) {
            $upsert->execute([':category' => $cat, ':limit' => $limit, ':limit2' => $limit]);
        }

        if (!empty($toClear)) {
            $placeholders = implode(',', array_fill(0, count($toClear), '?'));
            $del = $pdo->prepare("DELETE FROM budgets WHERE category IN ($placeholders)");
            $del->execute($toClear);
        }

        header('Location: budgets.php?saved=1');
        exit;
    }
}

// Current budgets (by category)
$stmt = $pdo->query("SELECT category, monthly_limit FROM budgets");
$existing = [];
foreach ($stmt->fetchAll() as $row) {
    $existing[$row['category']] = $row['monthly_limit'];
}

// Prefill with submitted values if the form failed validation
$values = [];
foreach ($categories as $cat) {
    if (!empty($errors)) {
        $values[$cat] = $_POST['limit'][$cat] ?? '';
    } else {
        $values[$cat] = isset($existing[$cat]) ? $existing[$cat] : '';
    }
}

$categoryColors = [
    'Food'          => '#e07a5f',
    'Transport'     => '#3d8bfd',
    'Entertainment' => '#9c6ade',
    'Education'     => '#2a9d8f',
    'Utilities'     => '#e9a23b',
    'Health'        => '#e63946',
    'Shopping'      => '#457b9d',
    'Other'         => '#6b7280',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Budgets</title>
<link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>
<div class="page page-narrow">

    <a href="index.php" class="back-link">&larr; Back to all expenses</a>

    <h1>Monthly Budgets</h1>
    <p class="subtitle">Set a monthly spending limit per category. Leave a field blank to remove its budget.</p>

    <?php if ($saved): ?>
        <div class="flash flash-success">Budgets saved.</div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="flash flash-error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="budgets.php" method="POST" class="card-form">
        <?php foreach ($categories as $cat): ?>
            <label for="limit-<?= strtolower($cat) ?>" class="budget-cat-label">
                <span class="category-dot" style="background:<?= $categoryColors[$cat] ?>"></span>
                <?= htmlspecialchars($cat) ?>
            </label>
            <input type="number" id="limit-<?= strtolower($cat) ?>" name="limit[<?= htmlspecialchars($cat) ?>]"
                   value="<?= htmlspecialchars($values[$cat]) ?>" step="0.01" min="0" placeholder="No budget set">
        <?php endforeach; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Budgets</button>
            <a href="index.php" class="btn btn-ghost">Cancel</a>
        </div>
    </form>

</div>
</body>
</html>
