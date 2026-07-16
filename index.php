<?php
require_once 'config.php';

// Delete flash message
$deleted = isset($_GET['deleted']);
$added   = isset($_GET['added']);
$updated = isset($_GET['updated']);

$allCategories = ['Food', 'Transport', 'Entertainment', 'Education', 'Utilities', 'Health', 'Shopping', 'Other'];

// Month picker
$currentYm = date('Y-m');

$stmt = $pdo->query("SELECT DISTINCT DATE_FORMAT(date, '%Y-%m') AS ym FROM expenses ORDER BY ym DESC");
$availableMonths = array_column($stmt->fetchAll(), 'ym');
if (!in_array($currentYm, $availableMonths, true)) {
    array_unshift($availableMonths, $currentYm);
    rsort($availableMonths);
}

$selectedMonth = $_GET['month'] ?? $currentYm;
if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $selectedMonth)) {
    $selectedMonth = $currentYm;
}
[$selYear, $selMonthNum] = array_map('intval', explode('-', $selectedMonth));
$monthName = date('F Y', strtotime($selectedMonth . '-01'));

// Search and filter
$selectedCategory = $_GET['category'] ?? '';
if (!in_array($selectedCategory, $allCategories, true)) {
    $selectedCategory = '';
}

$searchText = trim($_GET['q'] ?? '');

function isValidDate($d) {
    return $d !== '' && DateTime::createFromFormat('Y-m-d', $d) !== false;
}
$dateFrom = $_GET['date_from'] ?? '';
$dateTo   = $_GET['date_to'] ?? '';
if (!isValidDate($dateFrom)) $dateFrom = '';
if (!isValidDate($dateTo))   $dateTo   = '';

$where  = [];
$params = [];
if ($selectedCategory !== '') {
    $where[] = "category = :category";
    $params[':category'] = $selectedCategory;
}
if ($searchText !== '') {
    $where[] = "title LIKE :search";
    $params[':search'] = '%' . $searchText . '%';
}
if ($dateFrom !== '' && $dateTo !== '') {
    $where[] = "date BETWEEN :date_from AND :date_to";
    $params[':date_from'] = $dateFrom;
    $params[':date_to']   = $dateTo;
} elseif ($dateFrom !== '') {
    $where[] = "date >= :date_from";
    $params[':date_from'] = $dateFrom;
} elseif ($dateTo !== '') {
    $where[] = "date <= :date_to";
    $params[':date_to'] = $dateTo;
}
$hasActiveFilter = !empty($where);

$sql = "SELECT * FROM expenses";
if ($where) $sql .= " WHERE " . implode(' AND ', $where);
$sql .= " ORDER BY date DESC, id DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$expenses = $stmt->fetchAll();

// Total for whatever filters are currently active
$filteredTotal = null;
$filteredCount = 0;
if ($hasActiveFilter) {
    $sql2 = "SELECT COALESCE(SUM(amount), 0) AS total, COUNT(*) AS count FROM expenses";
    if ($where) $sql2 .= " WHERE " . implode(' AND ', $where);
    $stmt2 = $pdo->prepare($sql2);
    $stmt2->execute($params);
    $row = $stmt2->fetch();
    $filteredTotal = $row['total'];
    $filteredCount = (int)$row['count'];
}

// Helper: build an index.php link, overriding/removing specific query params
// while preserving the others currently in the URL.
function filterUrl($overrides = []) {
    $params = $_GET;
    foreach ($overrides as $k => $v) {
        if ($v === '' || $v === null) {
            unset($params[$k]);
        } else {
            $params[$k] = $v;
        }
    }
    $qs = http_build_query($params);
    return 'index.php' . ($qs !== '' ? '?' . $qs : '');
}

// Dashboard summary for selected month
$stmt = $pdo->prepare(
    "SELECT COALESCE(SUM(amount), 0) AS total
     FROM expenses
     WHERE YEAR(date) = :y AND MONTH(date) = :m"
);
$stmt->execute([':y' => $selYear, ':m' => $selMonthNum]);
$monthTotal = $stmt->fetch()['total'];

$stmt = $pdo->prepare(
    "SELECT category, SUM(amount) AS total, COUNT(*) AS count
     FROM expenses
     WHERE YEAR(date) = :y AND MONTH(date) = :m
     GROUP BY category
     ORDER BY total DESC"
);
$stmt->execute([':y' => $selYear, ':m' => $selMonthNum]);
$byCategory = $stmt->fetchAll();

// All-time total
$allTimeTotal = $pdo->query("SELECT COALESCE(SUM(amount),0) AS t FROM expenses")->fetch()['t'];

// Budgets, keyed by category
$stmt = $pdo->query("SELECT category, monthly_limit FROM budgets");
$budgets = [];
foreach ($stmt->fetchAll() as $row) {
    $budgets[$row['category']] = (float)$row['monthly_limit'];
}

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

// Budget progress: returns null if no budget set for this category
function budgetInfo($spent, $limit) {
    if ($limit === null || $limit <= 0) return null;
    $pct = ($spent / $limit) * 100;
    if ($pct >= 100) {
        $status = 'over';
    } elseif ($pct >= 80) {
        $status = 'warning';
    } else {
        $status = 'ok';
    }
    return ['pct' => min($pct, 100), 'rawPct' => $pct, 'status' => $status, 'limit' => $limit];
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
        <div class="header-actions">
            <a href="budgets.php" class="btn btn-ghost">Budgets</a>
            <a href="add.php" class="btn btn-primary">+ Add Expense</a>
        </div>
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
            <div class="summary-card-breakdown-header">
                <span class="summary-label">By category</span>
                <form method="GET" class="month-form">
                    <?php foreach ($_GET as $k => $v): if ($k !== 'month'): ?>
                        <input type="hidden" name="<?= htmlspecialchars($k) ?>" value="<?= htmlspecialchars($v) ?>">
                    <?php endif; endforeach; ?>
                    <select name="month" onchange="this.form.submit()" aria-label="Select month">
                        <?php foreach ($availableMonths as $ym): ?>
                            <option value="<?= $ym ?>" <?= $ym === $selectedMonth ? 'selected' : '' ?>>
                                <?= htmlspecialchars(date('F Y', strtotime($ym . '-01'))) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </form>
            </div>
            <?php if (empty($byCategory)): ?>
                <p class="empty-note">No expenses logged in <?= htmlspecialchars($monthName) ?>.</p>
            <?php else: ?>
                <ul class="category-list">
                    <?php foreach ($byCategory as $row): ?>
                        <?php
                            $pct = $monthTotal > 0 ? ($row['total'] / $monthTotal) * 100 : 0;
                            $limit = $budgets[$row['category']] ?? null;
                            $budget = budgetInfo($row['total'], $limit);
                        ?>
                        <li>
                            <a href="<?= filterUrl(['category' => $row['category']]) ?>" class="category-row category-row-link">
                                <span class="category-dot" style="background:<?= catColor($row['category'], $categoryColors) ?>"></span>
                                <span class="category-name"><?= htmlspecialchars($row['category']) ?></span>
                                <span class="category-count">(<?= (int)$row['count'] ?>)</span>
                                <span class="category-amount"><?= money($row['total']) ?></span>
                            </a>
                            <div class="bar-track">
                                <div class="bar-fill" style="width:<?= $pct ?>%; background:<?= catColor($row['category'], $categoryColors) ?>"></div>
                            </div>
                            <?php if ($budget !== null): ?>
                                <div class="budget-row budget-status-<?= $budget['status'] ?>">
                                    <div class="bar-track bar-track-thin">
                                        <div class="bar-fill" style="width:<?= $budget['pct'] ?>%"></div>
                                    </div>
                                    <span class="budget-label">
                                        <?php if ($budget['status'] === 'over'): ?>
                                            Over budget — <?= money($row['total']) ?> of <?= money($budget['limit']) ?> (<?= round($budget['rawPct']) ?>%)
                                        <?php elseif ($budget['status'] === 'warning'): ?>
                                            Nearing budget — <?= round($budget['rawPct']) ?>% of <?= money($budget['limit']) ?>
                                        <?php else: ?>
                                            <?= round($budget['rawPct']) ?>% of <?= money($budget['limit']) ?> budget
                                        <?php endif; ?>
                                    </span>
                                </div>
                            <?php endif; ?>
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
            <a href="<?= filterUrl(['category' => '']) ?>" class="filter-chip <?= $selectedCategory === '' ? 'filter-chip-active' : '' ?>">
                All
            </a>
            <?php foreach ($allCategories as $cat): ?>
                <a href="<?= filterUrl(['category' => $cat]) ?>"
                   class="filter-chip <?= $selectedCategory === $cat ? 'filter-chip-active' : '' ?>"
                   style="<?= $selectedCategory === $cat ? 'background:' . catColor($cat, $categoryColors) . ';border-color:' . catColor($cat, $categoryColors) . ';' : '' ?>">
                    <span class="category-dot" style="background:<?= $selectedCategory === $cat ? '#fff' : catColor($cat, $categoryColors) ?>"></span>
                    <?= htmlspecialchars($cat) ?>
                </a>
            <?php endforeach; ?>
        </div>

        <form method="GET" class="search-filter-bar">
            <?php if ($selectedCategory !== ''): ?>
                <input type="hidden" name="category" value="<?= htmlspecialchars($selectedCategory) ?>">
            <?php endif; ?>
            <input type="text" name="q" value="<?= htmlspecialchars($searchText) ?>" placeholder="Search by title&hellip;" class="search-input">
            <div class="date-range">
                <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>" aria-label="From date">
                <span class="date-sep">&ndash;</span>
                <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>" aria-label="To date">
            </div>
            <button type="submit" class="btn btn-ghost">Filter</button>
            <?php if ($hasActiveFilter): ?>
                <a href="index.php" class="filter-clear">Clear all &times;</a>
            <?php endif; ?>
        </form>

        <?php if ($hasActiveFilter): ?>
            <div class="filter-total-banner">
                <?= $filteredCount ?> expense<?= $filteredCount === 1 ? '' : 's' ?> matching your filters:
                <strong class="filter-total-amount"><?= money($filteredTotal) ?></strong>
            </div>
        <?php endif; ?>

        <?php if (empty($expenses)): ?>
            <div class="empty-state">
                <?php if ($hasActiveFilter): ?>
                    <p>No expenses match your filters.</p>
                    <a href="index.php" class="btn btn-ghost">Clear filters</a>
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
                                <td data-label="Title"><?= htmlspecialchars($exp['title']) ?></td>
                                <td data-label="Category">
                                    <span class="chip" style="background:<?= catColor($exp['category'], $categoryColors) ?>1a; color:<?= catColor($exp['category'], $categoryColors) ?>">
                                        <?= htmlspecialchars($exp['category']) ?>
                                    </span>
                                </td>
                                <td data-label="Date"><?= date('d M Y', strtotime($exp['date'])) ?></td>
                                <td class="num" data-label="Amount"><?= money($exp['amount']) ?></td>
                                <td class="actions" data-label="Actions">
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
