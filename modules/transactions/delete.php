<?php
// modules/transactions/delete.php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المعاملة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php?error=" . urlencode("معرف المعاملة غير صحيح"));
    exit;
}

$id = (int)$_GET['id'];

// التحقق من وجود المعاملة
$stmt = $pdo->prepare("SELECT * FROM transactions WHERE id = ?");
$stmt->execute([$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header("Location: index.php?error=" . urlencode("المعاملة غير موجودة"));
    exit;
}

// حذف المعاملة
try {
    $stmt = $pdo->prepare("DELETE FROM transactions WHERE id = ?");
    $result = $stmt->execute([$id]);
    
    if ($result) {
        header("Location: index.php?deleted=success");
        exit;
    } else {
        header("Location: index.php?error=" . urlencode("فشل حذف المعاملة"));
        exit;
    }
    
} catch(PDOException $e) {
    $error = "حدث خطأ أثناء حذف المعاملة: " . $e->getMessage();
    header("Location: index.php?error=" . urlencode($error));
    exit;
}
?>