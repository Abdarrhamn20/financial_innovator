<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود مدارس
$stmt = $pdo->query("SELECT COUNT(*) FROM schools");
$count = $stmt->fetchColumn();

if ($count == 0) {
    header("Location: index.php");
    exit;
}

// حذف جميع المدارس والبيانات المرتبطة بها
try {
    // تعطيل التحقق من المفاتيح الخارجية مؤقتاً
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    
    // بدء المعاملة
    $pdo->beginTransaction();
    
    // 1. حذف جميع سجلات الترقيات
    $pdo->exec("TRUNCATE TABLE promotions");
    
    // 2. حذف جميع المعاملات
    $pdo->exec("TRUNCATE TABLE transactions");
    
    // 3. حذف جميع الطلاب
    $pdo->exec("TRUNCATE TABLE students");
    
    // 4. حذف جميع المراحل
    $pdo->exec("TRUNCATE TABLE stages");
    
    // 5. حذف جميع المدارس
    $pdo->exec("TRUNCATE TABLE schools");
    
    // تأكيد المعاملة
    $pdo->commit();
    
    // إعادة تفعيل التحقق من المفاتيح الخارجية
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // إعادة التوجيه مع رسالة نجاح
    header("Location: index.php?deleted_all=success");
    exit;
    
} catch(PDOException $e) {
    // التراجع عن المعاملة في حالة الخطأ
    $pdo->rollBack();
    
    // إعادة تفعيل التحقق من المفاتيح الخارجية
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // رسالة الخطأ
    $error = "حدث خطأ أثناء حذف جميع المدارس: " . $e->getMessage();
    header("Location: index.php?error=" . urlencode($error));
    exit;
}
?>