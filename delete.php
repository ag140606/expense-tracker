<?php
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id = (int)($_POST['id'] ?? 0);

    if ($id > 0) {
        $stmt = $pdo->prepare("DELETE FROM expenses WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }

    header('Location: index.php?deleted=1');
    exit;
}

// If someone hits this file directly with GET, redirect to home page
header('Location: index.php');
exit;