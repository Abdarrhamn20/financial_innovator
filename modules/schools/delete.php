<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المدرسة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// التحقق من وجود المدرسة
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();

if (!$school) {
    header("Location: index.php");
    exit;
}

// حذف المدرسة وجميع البيانات المرتبطة بها
try {
    // تعطيل التحقق من المفاتيح الخارجية مؤقتاً
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // 1. حذف سجل الترقيات المرتبط بالطلاب
    $stmt = $pdo->prepare("
        DELETE p FROM promotions p
        JOIN students s ON p.student_id = s.id
        WHERE s.school_id = ?
    ");
    $stmt->execute([$id]);
    
    // 2. حذف المعاملات المرتبطة بالطلاب في هذه المدرسة
    $stmt = $pdo->prepare("
        DELETE t FROM transactions t
        JOIN students s ON t.student_id = s.id
        WHERE s.school_id = ?
    ");
    $stmt->execute([$id]);
    
    // 3. حذف الطلاب
    $stmt = $pdo->prepare("DELETE FROM students WHERE school_id = ?");
    $stmt->execute([$id]);
    
    // 4. حذف المراحل
    $stmt = $pdo->prepare("DELETE FROM stages WHERE school_id = ?");
    $stmt->execute([$id]);
    
    // 5. حذف المدرسة
    $stmt = $pdo->prepare("DELETE FROM schools WHERE id = ?");
    $stmt->execute([$id]);
    
    // تأكيد المعاملة
    $pdo->commit();
    
    // إعادة تفعيل التحقق من المفاتيح الخارجية
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // إعادة التوجيه مع رسالة نجاح
    header("Location: index.php?deleted=success");
    exit;
    
} catch(PDOException $e) {
    // التراجع عن المعاملة في حالة الخطأ
    $pdo->rollBack();
    
    // إعادة تفعيل التحقق من المفاتيح الخارجية
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // رسالة الخطأ
    $error = "حدث خطأ أثناء حذف المدرسة: " . $e->getMessage();
    header("Location: index.php?error=" . urlencode($error));
    exit;
}
?>