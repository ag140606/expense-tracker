<?php
require_once 'config.php';

// Delete flash message (from delete.php redirect)
$deleted = isset($_GET['deleted']);
$added   = isset($_GET['added']);
$updated = isset($_GET['updated']);

// All expenses, most recent first
$stmt = $pdo->query("SELECT * FROM expenses ORDER BY date DESC, id DESC");
$expenses = $stmt->fetchAll();

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
<link rel="stylesheet" href="css/style.css?v=2">
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
                            <div class="category-row">
                                <span class="category-dot" style="background:<?= catColor($row['category'], $categoryColors) ?>"></span>
                                <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                                <span class="category-count">(<?= (int)$row['count'] ?>)</span>
                                <span class="category-amount"><?= money($row['total']) ?></span>
                            </div>
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
        <h2>All Expenses</h2>

        <?php if (empty($expenses)): ?>
            <div class="empty-state">
                <p>No expenses yet.</p>
                <a href="add.php" class="btn btn-primary">Add your first expense</a>
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