<?php
// includes/functions.php

// دالة تنسيق العملة الليبية
function formatLibyanCurrency($amount) {
    $amount = floatval($amount);
    return number_format($amount, 2, '.', ',') . ' د.ل';
}

function currencySymbol() {
    return 'د.ل';
}

function currencyName() {
    return 'دينار ليبي';
}

function displayAmount($amount) {
    $amount = floatval($amount);
    if ($amount >= 0) {
        return '+' . formatLibyanCurrency($amount);
    } else {
        return '-' . formatLibyanCurrency(abs($amount));
    }
}

// ============================================
// إصلاح دالة getSchoolStats - حساب الإيرادات بشكل صحيح
// ============================================
function getSchoolStats($school_id) {
    global $pdo;
    
    // 1. عدد الطلاب
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $total_students = (int)$stmt->fetchColumn();
    
    // 2. عدد المراحل
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM stages WHERE school_id = ?");
    $stmt->execute([$school_id]);
    $total_stages = (int)$stmt->fetchColumn();
    
    // 3. إجمالي الإيرادات (فقط المعاملات المدفوعة)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) 
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        WHERE s.school_id = ? 
        AND t.type = 'income' 
        AND t.status = 'paid'
    ");
    $stmt->execute([$school_id]);
    $total_income = (float)$stmt->fetchColumn();
    
    // 4. إجمالي المصروفات (فقط المعاملات المدفوعة)
    $stmt = $pdo->prepare("
        SELECT COALESCE(SUM(t.amount), 0) 
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        WHERE s.school_id = ? 
        AND t.type = 'expense' 
        AND t.status = 'paid'
    ");
    $stmt->execute([$school_id]);
    $total_expenses = (float)$stmt->fetchColumn();
    
    return [
        'total_students' => $total_students,
        'total_stages' => $total_stages,
        'total_income' => $total_income,
        'total_expenses' => $total_expenses
    ];
}

// دالة الحصول على المدارس
function getSchools() {
    global $pdo;
    $stmt = $pdo->query("SELECT * FROM schools ORDER BY name");
    return $stmt->fetchAll();
}

function getStages($school_id = null) {
    global $pdo;
    $sql = "SELECT s.*, sc.name as school_name FROM stages s 
            JOIN schools sc ON s.school_id = sc.id";
    if ($school_id) {
        $sql .= " WHERE s.school_id = ?";
    }
    $sql .= " ORDER BY s.order_number ASC, s.name ASC";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($school_id ? [$school_id] : []);
    return $stmt->fetchAll();
}

function getStudents($school_id = null) {
    global $pdo;
    $sql = "SELECT st.*, sc.name as school_name, sg.name as stage_name 
            FROM students st 
            JOIN schools sc ON st.school_id = sc.id 
            LEFT JOIN stages sg ON st.current_stage_id = sg.id";
    if ($school_id) {
        $sql .= " WHERE st.school_id = ?";
    }
    $sql .= " ORDER BY st.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($school_id ? [$school_id] : []);
    return $stmt->fetchAll();
}

function getStudentTransactions($student_id) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT * FROM transactions WHERE student_id = ? ORDER BY transaction_date DESC");
    $stmt->execute([$student_id]);
    return $stmt->fetchAll();
}

function promoteStudent($student_id, $to_stage_id) {
    global $pdo;
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("SELECT current_stage_id FROM students WHERE id = ?");
        $stmt->execute([$student_id]);
        $from_stage_id = $stmt->fetchColumn();
        
        $stmt = $pdo->prepare("UPDATE students SET current_stage_id = ? WHERE id = ?");
        $stmt->execute([$to_stage_id, $student_id]);
        
        $stmt = $pdo->prepare("INSERT INTO promotions (student_id, from_stage_id, to_stage_id, promotion_date) 
                              VALUES (?, ?, ?, CURDATE())");
        $stmt->execute([$student_id, $from_stage_id, $to_stage_id]);
        
        $pdo->commit();
        return true;
    } catch(Exception $e) {
        $pdo->rollBack();
        return false;
    }
}

// دالة الحصول على إحصائيات الميزانية
function getBudgetStats() {
    global $pdo;
    $stmt = $pdo->query("SELECT 
        COALESCE(SUM(CASE WHEN type = 'income' AND status = 'paid' THEN amount ELSE 0 END), 0) as total_income,
        COALESCE(SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END), 0) as total_expenses,
        COUNT(CASE WHEN type = 'income' THEN 1 END) as income_count,
        COUNT(CASE WHEN type = 'expense' THEN 1 END) as expense_count
        FROM transactions");
    return $stmt->fetch();
}

// دالة الحصول على تقرير شهري
function getMonthlyReport($month, $year) {
    global $pdo;
    $stmt = $pdo->prepare("SELECT 
        type,
        COALESCE(SUM(amount), 0) as total,
        COUNT(*) as count
        FROM transactions 
        WHERE MONTH(transaction_date) = ? 
        AND YEAR(transaction_date) = ? 
        AND status = 'paid'
        GROUP BY type");
    $stmt->execute([$month, $year]);
    return $stmt->fetchAll();
}
?>