<?php
require_once 'config.php';

// Delete flash message
$deleted = isset($_GET['deleted']);
$added   = isset($_GET['added']);
$updated = isset($_GET['updated']);

// Category filter
$allCategories = ['Food', 'Transport', 'Entertainment', 'Education', 'Utilities', 'Health', 'Shopping', 'Other'];
$selectedCategory = $_GET['category'] ?? '';
if (!in_array($selectedCategory, $allCategories, true)) {
    $selectedCategory = ''; 
}

// Expenses, filtered by category if one is selected
if ($selectedCategory !== '') {
    $stmt = $pdo->prepare("SELECT * FROM expenses WHERE category = :category ORDER BY date DESC, id DESC");
    $stmt->execute([':category' => $selectedCategory]);
} else {
    $stmt = $pdo->query("SELECT * FROM expenses ORDER BY date DESC, id DESC");
}
$expenses = $stmt->fetchAll();

// Total spent in the selected category
$filteredCategoryTotal = null;
$filteredCategoryCount = 0;
if ($selectedCategory !== '') {
    $stmt = $pdo->prepare(
        "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count
         FROM expenses WHERE category = :category"
    );
    $stmt->execute([':category' => $selectedCategory]);
    $row = $stmt->fetch();
    $filteredCategoryTotal = $row['total'];
    $filteredCategoryCount = (int)$row['count'];
}

// Total spent THIS MONTH (SUM)
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM expenses
     WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())"
);
$stmt->execute();
$monthTotal = $stmt->fetch()['total'];

// This month's spend BY CATEGORY (SUM + GROUP BY)
$stmt = $pdo->prepare(
    "SELECT category, SUM(amount) AS total, COUNT(*) AS count
     FROM expenses
     WHERE YEAR(date) = YEAR(CURDATE()) AND MONTH(date) = MONTH(CURDATE())
     GROUP BY category
     ORDER BY total DESC"
);
$stmt->execute();
$byCategory = $stmt->fetchAll();

// All-time total
$allTimeTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses")->fetch()['t'];

$monthName = date('F Y');

function money($n) {
    return '₹' . number_format((float)$n, 2);
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
function catColor($cat, $map) {
    return $map[$cat] ?? '#6b7280';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Expense Tracker</title>
<link rel="stylesheet" href="css/style.css?v=3">
</head>
<body>

<div class="page">

    <header class="page-header">
        <div>
            <h1>Expense Tracker</h1>
            <p class="subtitle">Track what you spend, month by month.</p>
        </div>
        <a href="add.php" class="btn btn-primary">+ Add Expense</a>
    </header>

    <?php if ($deleted): ?>
        <div class="flash flash-success">Expense deleted.</div>
    <?php endif; ?>
    <?php if ($added): ?>
        <div class="flash flash-success">Expense added.</div>
    <?php endif; ?>
    <?php if ($updated): ?>
        <div class="flash flash-success">Expense updated.</div>
    <?php endif; ?>

    <section class="summary-grid">
        <div class="summary-card summary-card-main">
            <span class="summary-label"><?= htmlspecialchars($monthName) ?> total</span>
            <span class="summary-value"><?= money($monthTotal) ?></span>
            <span class="summary-sub">All-time: <?= money($allTimeTotal) ?></span>
        </div>

        <div class="summary-card summary-card-breakdown">
            <span class="summary-label">By category this month</span>
            <?php if (empty($byCategory)): ?>
                <p class="empty-note">No expenses logged this month yet.</p>
            <?php else: ?>
                <ul class="category-list">
                    <?php foreach ($byCategory as $row): ?>
                        <?php $pct = $monthTotal > 0 ? ($row['total'] / $monthTotal) * 100 : 0; ?>
                        <li>
                            <a href="index.php?category=<?= urlencode($row['category']) ?>" class="category-row category-row-link">
                                <span class="category-dot" style="background:<?= catColor($row['category'], $categoryColors) ?>"></span>
                                <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                                <span class="category-count">(<?= (int)$row['count'] ?>)</span>
                                <span class="category-amount"><?= money($row['total']) ?></span>
                            </a>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= $pct ?>%; background:<?= catColor($row['category'], $categoryColors) ?>"></div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </section>

    <section class="table-section">
        <div class="table-section-header">
            <h2>All Expenses</h2>
        </div>

        <div class="filter-bar">
            <a href="index.php" class="filter-chip <?= $selectedCategory === '' ? 'filter-chip-active' : '' ?>">
                All
            </a>
            <?php foreach ($allCategories as $cat): ?>
                <a href="index.php?category=<?= urlencode($cat) ?>"
                   class="filter-chip <?= $selectedCategory === $cat ? 'filter-chip-active' : '' ?>"
                   style="<?= $selectedCategory === $cat ? 'background:' . catColor($cat, $categoryColors) . ';border-color:' . catColor($cat, $categoryColors) . ';' : '' ?>">
                    <span class="category-dot" style="background:<?= $selectedCategory === $cat ? '#fff' : catColor($cat, $categoryColors) ?>"></span>
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <?php if ($selectedCategory !== ''): ?>
            <div class="filter-total-banner" style="border-color:<?= catColor($selectedCategory, $categoryColors) ?>">
                <span class="category-dot" style="background:<?= catColor($selectedCategory, $categoryColors) ?>"></span>
                Total spent on <strong><?= htmlspecialchars($selectedCategory) ?></strong>:
                <strong class="filter-total-amount"><?= money($filteredCategoryTotal) ?></strong>
                <span class="filter-total-count">(<?= $filteredCategoryCount ?> expense<?= $filteredCategoryCount === 1 ? '' : 's' ?>)</span>
                <a href="index.php" class="filter-clear">Clear filter &times;</a>
            </div>
        <?php endif; ?>

        <?php if (empty($expenses)): ?>
            <div class="empty-state">
                <?php if ($selectedCategory !== ''): ?>
                    <p>No expenses in <strong><?= htmlspecialchars($selectedCategory) ?></strong> yet.</p>
                    <a href="index.php" class="btn btn-ghost">Clear filter</a>
                <?php else: ?>
                    <p>No expenses yet.</p>
                    <a href="add.php" class="btn btn-primary">Add your first expense</a>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Title</th>
                            <th>Category</th>
                            <th>Date</th>
                            <th class="num">Amount</th>
                            <th class="actions">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($expenses as $exp): ?>
                            <tr>
                                <td><?= htmlspecialchars($exp['title']) ?></td>
                                <td>
                                    <span class="chip" style="background:<?= catColor($exp['category'], $categoryColors) ?>1a; color:<?= catColor($exp['category'], $categoryColors) ?>">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td><?= date('d M Y', strtotime($exp['date'])) ?></td>
                                <td class="num"><?= money($exp['amount']) ?></td>
                                <td class="actions">
                                    <a href="edit.php?id=<?= (int)$exp['id'] ?>" class="link-edit">Edit</a>
                                    <form action="delete.php" method="POST" class="inline-form"
                                          onsubmit="return confirm('Delete &quot;<?= htmlspecialchars(addslashes($exp['title'])) ?>&quot;?');">
                                        <input type="hidden" name="id" value="<?= (int)$exp['id'] ?>">
                                        <button type="submit" class="link-delete">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>

</div>
</body>
</html>