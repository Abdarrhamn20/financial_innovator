<?php
// config/database.php

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// إعدادات الاتصال بقاعدة البيانات
$host = 'localhost';
$dbname = 'financial_innovator';
$username = 'root';  // تغيير حسب إعداداتك
$password = '';      // تغيير حسب إعداداتك

try {
    // إنشاء اتصال PDO مع إعدادات إضافية
    $pdo = new PDO(
        "mysql:host=$host;charset=utf8mb4",
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // التحقق من وجود قاعدة البيانات وإنشائها إذا لم تكن موجودة
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname` 
                CHARACTER SET utf8mb4 
                COLLATE utf8mb4_unicode_ci");
    
    // اختيار قاعدة البيانات
    $pdo->exec("USE `$dbname`");
    
    // ============================================
    // إنشاء الجداول فقط (بدون بيانات تلقائية)
    // ============================================
    
    // 1. جدول المدارس
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS schools (
            id INT PRIMARY KEY AUTO_INCREMENT,
            name VARCHAR(100) NOT NULL,
            type ENUM('school', 'institute') NOT NULL,
            address TEXT,
            phone VARCHAR(20),
            email VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 2. جدول المراحل (مع order_number)
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS stages (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT,
            name VARCHAR(50) NOT NULL,
            order_number INT DEFAULT 0,
            fee_amount DECIMAL(10,2) NOT NULL DEFAULT 0.00,
            description TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 3. جدول الطلاب
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS students (
            id INT PRIMARY KEY AUTO_INCREMENT,
            school_id INT,
            current_stage_id INT,
            name VARCHAR(100) NOT NULL,
            student_code VARCHAR(50) UNIQUE,
            birth_date DATE,
            phone VARCHAR(20),
            address TEXT,
            enrollment_date DATE,
            status ENUM('active', 'inactive', 'graduated') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (school_id) REFERENCES schools(id) ON DELETE CASCADE,
            FOREIGN KEY (current_stage_id) REFERENCES stages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 4. جدول المعاملات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS transactions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT,
            stage_id INT,
            type ENUM('income', 'expense') NOT NULL,
            amount DECIMAL(10,2) NOT NULL,
            description TEXT,
            transaction_date DATE,
            payment_method ENUM('cash', 'bank', 'online'),
            status ENUM('pending', 'paid', 'cancelled') DEFAULT 'pending',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (stage_id) REFERENCES stages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // 5. جدول الترقيات
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS promotions (
            id INT PRIMARY KEY AUTO_INCREMENT,
            student_id INT,
            from_stage_id INT,
            to_stage_id INT,
            promotion_date DATE,
            notes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE,
            FOREIGN KEY (from_stage_id) REFERENCES stages(id) ON DELETE SET NULL,
            FOREIGN KEY (to_stage_id) REFERENCES stages(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
    ");
    
    // ============================================
    // ❌ تم إزالة إضافة البيانات التلقائية
    // لن يتم إضافة أي مدارس أو طلاب تلقائياً
    // يمكنك استخدام ملف database.sql لإضافة البيانات يدوياً
    // ============================================
    
} catch(PDOException $e) {
    // عرض رسالة خطأ مفيدة
    die("❌ فشل الاتصال بقاعدة البيانات: " . $e->getMessage());
}
?>