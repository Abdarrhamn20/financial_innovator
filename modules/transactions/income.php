<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب قائمة المدارس
$schools = getSchools();

// متغيرات لتخزين قيم النموذج
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$stage_id = isset($_GET['stage_id']) ? (int)$_GET['stage_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : null;
$amount = '';
$description = '';
$transaction_date = date('Y-m-d');
$payment_method = 'cash';
$status = 'paid';

// متغيرات البنود المتعددة
$income_items = [];
$total_amount = 0;

$errors = [];
$success = '';
$student_data = null;
$stage_data = null;
$remaining_amount = 0;
$total_remaining = 0;
$stage_remaining = [];

// أنواع الإيرادات العامة
$general_income_types = [
    'donation' => 'تبرعات',
    'grant' => 'منح ودعم',
    'investment' => 'عوائد استثمارية',
    'rental' => 'إيجار عقارات',
    'services' => 'خدمات مدفوعة',
    'events' => 'فعاليات ونشاطات',
    'sales' => 'مبيعات',
    'interest' => 'فوائد بنكية',
    'other' => 'إيرادات أخرى'
];

// جلب معلومات الطالب إذا تم تمرير student_id
if ($student_id) {
    $stmt = $pdo->prepare("
        SELECT st.*, sc.name as school_name, sg.name as stage_name 
        FROM students st
        JOIN schools sc ON st.school_id = sc.id
        LEFT JOIN stages sg ON st.current_stage_id = sg.id
        WHERE st.id = ?
    ");
    $stmt->execute([$student_id]);
    $student_data = $stmt->fetch();
}

// جلب معلومات المرحلة إذا تم تمرير stage_id
if ($stage_id) {
    $stmt = $pdo->prepare("SELECT * FROM stages WHERE id = ?");
    $stmt->execute([$stage_id]);
    $stage_data = $stmt->fetch();
}

// حساب المتبقي للطالب والمراحل
if ($student_id) {
    // جلب جميع المعاملات المدفوعة للطالب
    $stmt = $pdo->prepare("
        SELECT * FROM transactions 
        WHERE student_id = ? AND status = 'paid'
    ");
    $stmt->execute([$student_id]);
    $transactions = $stmt->fetchAll();
    
    // حساب إجمالي الإيرادات والمصروفات
    $total_paid_income = 0;
    $total_paid_expenses = 0;
    
    foreach ($transactions as $t) {
        if ($t['type'] == 'income') {
            $total_paid_income += $t['amount'];
        } else {
            $total_paid_expenses += $t['amount'];
        }
    }
    
    // جلب جميع مراحل الطالب (المراحل التي مر بها + المرحلة الحالية)
    $stmt = $pdo->prepare("
        SELECT DISTINCT sg.id, sg.name, sg.fee_amount,
               CASE WHEN sg.id = ? THEN 'current' ELSE 'previous' END as stage_status
        FROM stages sg
        JOIN students st ON st.school_id = sg.school_id
        WHERE st.id = ?
        ORDER BY sg.fee_amount
    ");
    $stmt->execute([$student_data['current_stage_id'], $student_id]);
    $all_stages = $stmt->fetchAll();
    
    // حساب المتبقي لكل مرحلة
    $total_fees = 0;
    $stage_remaining = [];
    
    foreach ($all_stages as $stage) {
        // حساب الإيرادات والمصروفات لهذه المرحلة
        $stmt = $pdo->prepare("
            SELECT SUM(CASE WHEN type = 'income' AND status = 'paid' THEN amount ELSE 0 END) as stage_income,
                   SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END) as stage_expenses
            FROM transactions 
            WHERE student_id = ? AND stage_id = ? AND status = 'paid'
        ");
        $stmt->execute([$student_id, $stage['id']]);
        $stage_stats = $stmt->fetch();
        
        $stage_income = $stage_stats['stage_income'] ?? 0;
        $stage_expenses = $stage_stats['stage_expenses'] ?? 0;
        $remaining = $stage['fee_amount'] - $stage_income + $stage_expenses;
        if ($remaining < 0) $remaining = 0;
        
        $stage_remaining[$stage['id']] = [
            'name' => $stage['name'],
            'fee_amount' => $stage['fee_amount'],
            'total_income' => $stage_income,
            'total_expenses' => $stage_expenses,
            'remaining' => $remaining,
            'status' => $stage['stage_status']
        ];
        
        $total_fees += $stage['fee_amount'];
    }
    
    // حساب إجمالي المتبقي
    $total_remaining = $total_fees - $total_paid_income + $total_paid_expenses;
    if ($total_remaining < 0) $total_remaining = 0;
    
    // حساب المتبقي لمرحلة محددة
    if ($stage_id && isset($stage_remaining[$stage_id])) {
        $remaining_amount = $stage_remaining[$stage_id]['remaining'];
        if (empty($amount)) {
            $amount = $remaining_amount;
        }
    }
}

// جلب الطلاب حسب المدرسة المختارة
$students = [];
$stages = [];

if ($school_id) {
    $students = getStudents($school_id);
    $stages = getStages($school_id);
} else {
    $students = getStudents();
    $stages = getStages();
}

// فلترة الطلاب حسب البحث
$filtered_students = $students;
$search_results = [];

if ($search) {
    $filtered_students = array_filter($students, function($student) use ($search) {
        return stripos($student['name'], $search) !== false || 
               stripos($student['student_code'] ?? '', $search) !== false;
    });
    $search_results = $filtered_students;
    
    if ($student_id) {
        $found = false;
        foreach ($filtered_students as $s) {
            if ($s['id'] == $student_id) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $student_id = null;
        }
    }
} else {
    $search_results = [];
}

// إذا كان هناك طالب محدد، جلب مراحله الخاصة
if ($student_id && $student_data) {
    $stages = [];
    foreach ($stage_remaining as $id => $stage) {
        $stages[] = [
            'id' => $id,
            'name' => $stage['name'],
            'fee_amount' => $stage['fee_amount']
        ];
    }
}

// معالجة إضافة الإيراد (بنود متعددة)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $income_type = isset($_POST['income_type']) ? $_POST['income_type'] : 'student';
    $student_id = isset($_POST['student_id']) && !empty($_POST['student_id']) ? (int)$_POST['student_id'] : null;
    $stage_id = isset($_POST['stage_id']) && !empty($_POST['stage_id']) ? (int)$_POST['stage_id'] : null;
    $transaction_date = $_POST['transaction_date'];
    $payment_method = $_POST['payment_method'];
    $status = $_POST['status'];
    $general_type = isset($_POST['general_type']) ? $_POST['general_type'] : null;
    
    // جلب البنود من النموذج
    $item_names = isset($_POST['item_name']) ? $_POST['item_name'] : [];
    $item_amounts = isset($_POST['item_amount']) ? $_POST['item_amount'] : [];
    $item_stage_ids = isset($_POST['item_stage_id']) ? $_POST['item_stage_id'] : [];
    $item_descriptions = isset($_POST['item_description']) ? $_POST['item_description'] : [];
    
    $errors = [];
    
    // التحقق من صحة البيانات الأساسية
    if (empty($transaction_date)) {
        $errors[] = "تاريخ المعاملة مطلوب";
    }
    
    if (empty($payment_method)) {
        $errors[] = "طريقة الدفع مطلوبة";
    }
    
    if (empty($status)) {
        $errors[] = "حالة المعاملة مطلوبة";
    }
    
    // التحقق حسب نوع الإيراد
    if ($income_type == 'student') {
        if (empty($student_id) || $student_id <= 0) {
            $errors[] = "يرجى اختيار الطالب";
        }
    } else {
        // إيراد عام
        if (empty($general_type)) {
            $errors[] = "يرجى اختيار نوع الإيراد العام";
        }
        // تعيين student_id = null للإيرادات العامة
        $student_id = null;
        $stage_id = null;
    }
    
    // التحقق من وجود بنود
    if (empty($item_names) || count($item_names) == 0) {
        $errors[] = "يجب إضافة بند واحد على الأقل";
    }
    
    if (empty($errors)) {
        try {
            $pdo->beginTransaction();
            
            $total_added = 0;
            $items_added = [];
            
            // إضافة كل بند كمعاملة منفصلة
            foreach ($item_names as $index => $item_name) {
                $item_name = trim($item_name);
                $item_amount = isset($item_amounts[$index]) ? (float)$item_amounts[$index] : 0;
                $item_stage_id = isset($item_stage_ids[$index]) && !empty($item_stage_ids[$index]) ? (int)$item_stage_ids[$index] : null;
                $item_description = isset($item_descriptions[$index]) ? trim($item_descriptions[$index]) : '';
                
                // تخطي البنود الفارغة
                if (empty($item_name) || $item_amount <= 0) {
                    continue;
                }
                
                // إنشاء وصف كامل للبند
                $full_description = $item_name;
                if (!empty($item_description)) {
                    $full_description .= ' - ' . $item_description;
                }
                
                // إضافة نوع الإيراد العام للوصف إذا كان إيراد عام
                if ($income_type == 'general' && !empty($general_type)) {
                    $full_description = 'إيراد عام - ' . ($general_income_types[$general_type] ?? $general_type) . ': ' . $full_description;
                }
                
                $stmt = $pdo->prepare("INSERT INTO transactions 
                    (student_id, stage_id, type, amount, description, transaction_date, payment_method, status) 
                    VALUES (?, ?, 'income', ?, ?, ?, ?, ?)");
                
                $stmt->execute([
                    $student_id, 
                    $item_stage_id, 
                    $item_amount, 
                    $full_description, 
                    $transaction_date, 
                    $payment_method, 
                    $status
                ]);
                
                $total_added += $item_amount;
                $items_added[] = $item_name;
            }
            
            $pdo->commit();
            
            $success = "تم إضافة " . count($items_added) . " بنود إيراد بنجاح (المجموع: " . formatLibyanCurrency($total_added) . ")";
            
            // إعادة توجيه إلى صفحة الطالب إذا كان هناك طالب
            if ($income_type == 'student' && $student_id) {
                header("Location: ../students/view.php?id=" . $student_id . "&paid=success");
            } else {
                header("Location: index.php?income=success");
            }
            exit;
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors[] = "حدث خطأ أثناء الإضافة: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات عامة
$stmt = $pdo->query("SELECT COUNT(*) FROM transactions WHERE type = 'income'");
$total_income_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT SUM(amount) FROM transactions WHERE type = 'income' AND status = 'paid'");
$total_income_amount = $stmt->fetchColumn() ?: 0;

// طرق الدفع
$payment_methods = [
    'cash' => 'نقدي',
    'bank' => 'تحويل بنكي',
    'online' => 'دفع إلكتروني'
];

// حالات المعاملة
$status_labels = [
    'paid' => 'مدفوعة',
    'pending' => 'معلقة',
    'cancelled' => 'ملغية'
];

// أنواع الإيرادات المقترحة (للابنود)
$suggested_items = [
    'رسوم الفصل الدراسي',
    'رسوم التسجيل',
    'رسوم الكتب الدراسية',
    'رسوم الأنشطة',
    'رسوم الرحلات المدرسية',
    'رسوم المختبرات',
    'رسوم الرياضة',
    'رسوم النقل المدرسي',
    'رسوم الوجبات المدرسية',
    'رسوم المرافق الإضافية'
];

// أنواع الإيرادات العامة المقترحة
$general_suggested_items = [
    'تبرع نقدي',
    'منحة دراسية',
    'عائد استثماري',
    'إيجار مبنى',
    'خدمات تدريبية',
    'مبيعات منتجات',
    'فوائد بنكية',
    'إيرادات أخرى'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة إيراد - المبتكر المالي</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* نفس التنسيقات السابقة مع إضافة تنسيق للتبويبات */
        .income-tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 25px;
            background: #f7fafc;
            padding: 10px;
            border-radius: 12px;
        }

        .income-tabs .tab-btn {
            flex: 1;
            padding: 12px 20px;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            background: transparent;
            color: #718096;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
        }

        .income-tabs .tab-btn:hover {
            background: rgba(56, 161, 105, 0.05);
            color: #38a169;
        }

        .income-tabs .tab-btn.active {
            background: #38a169;
            color: white;
            box-shadow: 0 4px 15px rgba(56, 161, 105, 0.3);
        }

        .income-tabs .tab-btn i {
            font-size: 16px;
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
            animation: fadeIn 0.3s ease;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* باقي التنسيقات كما هي */
        .page-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 25px;
            flex-wrap: wrap;
            gap: 15px;
        }

        .page-header h2 {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .page-header .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }

        .btn-back {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #2d3748;
            border: 2px solid #e2e8f0;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-back:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .btn-back i {
            color: #667eea;
        }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 950px;
            margin: 0 auto;
            border-top: 5px solid #38a169;
        }

        .form-container .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .form-container .form-title i {
            color: #38a169;
        }

        .form-container .form-subtitle {
            color: #718096;
            font-size: 14px;
            margin-bottom: 25px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #2d3748;
            font-size: 14px;
        }

        .form-group label .required {
            color: #e53e3e;
            margin-right: 3px;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            font-size: 15px;
            font-family: 'Tajawal', sans-serif;
            transition: all 0.3s;
            background: white;
            color: #2d3748;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #38a169;
            box-shadow: 0 0 0 3px rgba(56, 161, 105, 0.1);
        }

        .form-group textarea {
            min-height: 80px;
            resize: vertical;
        }

        .form-group .help-text {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
        }

        /* بنود الإيرادات */
        .income-items {
            background: #f7fafc;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            border: 2px dashed #e2e8f0;
        }

        .income-items .items-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .income-items .items-title .item-count {
            font-size: 13px;
            color: #718096;
            font-weight: 400;
        }

        .income-item {
            background: white;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            border: 1px solid #e2e8f0;
            position: relative;
            transition: all 0.3s;
        }

        .income-item:hover {
            border-color: #38a169;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .income-item .item-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 10px;
        }

        .income-item .item-number {
            font-size: 13px;
            font-weight: 600;
            color: #718096;
        }

        .income-item .btn-remove-item {
            background: #fff5f5;
            color: #e53e3e;
            border: none;
            border-radius: 8px;
            padding: 5px 12px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .income-item .btn-remove-item:hover {
            background: #fed7d7;
        }

        .income-item .item-row {
            display: grid;
            grid-template-columns: 2fr 1fr 1fr;
            gap: 15px;
        }

        .income-item .item-row .form-group {
            margin-bottom: 0;
        }

        .income-item .item-row .form-group label {
            font-size: 12px;
            color: #718096;
            margin-bottom: 3px;
        }

        .income-item .item-row .form-group input,
        .income-item .item-row .form-group select {
            padding: 8px 12px;
            font-size: 14px;
        }

        .btn-add-item {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: white;
            color: #38a169;
            border: 2px dashed #38a169;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            width: 100%;
            justify-content: center;
        }

        .btn-add-item:hover {
            background: #f0fff4;
            border-color: #38a169;
        }

        .total-amount-display {
            background: linear-gradient(135deg, #f0fff4, #e6ffed);
            border-radius: 10px;
            padding: 15px 20px;
            margin-top: 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .total-amount-display .label {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }

        .total-amount-display .amount {
            font-size: 24px;
            font-weight: 800;
            color: #38a169;
        }

        .suggested-items {
            display: flex;
            flex-wrap: wrap;
            gap: 8px;
            margin-top: 8px;
        }

        .suggested-items .tag {
            padding: 4px 12px;
            background: #edf2f7;
            border-radius: 20px;
            font-size: 12px;
            cursor: pointer;
            transition: all 0.3s;
            color: #2d3748;
        }

        .suggested-items .tag:hover {
            background: #667eea;
            color: white;
        }

        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #c6f6d5;
        }

        .alert-error {
            background: #fff5f5;
            color: #9b2c2c;
            border: 1px solid #fed7d7;
        }

        .alert i {
            font-size: 20px;
        }

        .alert .close-alert {
            margin-right: auto;
            background: none;
            border: none;
            font-size: 20px;
            cursor: pointer;
            color: inherit;
        }

        .form-actions {
            display: flex;
            gap: 15px;
            margin-top: 25px;
            padding-top: 20px;
            border-top: 2px solid #edf2f7;
        }

        .btn-submit {
            flex: 1;
            padding: 14px 30px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(56, 161, 105, 0.3);
        }

        .btn-submit:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .btn-cancel {
            padding: 14px 30px;
            background: #edf2f7;
            color: #2d3748;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-cancel:hover {
            background: #e2e8f0;
        }

        /* إحصائيات سريعة */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stats-mini .stat-box {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            display: flex;
            align-items: center;
            gap: 15px;
        }

        .stats-mini .stat-box i {
            font-size: 28px;
            color: #38a169;
        }

        .stats-mini .stat-box .info {
            flex: 1;
        }

        .stats-mini .stat-box .info .number {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
        }

        .stats-mini .stat-box .info .label {
            font-size: 13px;
            color: #718096;
        }

        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
            font-size: 18px;
        }

        .input-with-icon input {
            padding-left: 45px;
        }

        /* فلتر المدرسة والبحث */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .filter-container .filter-title {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .filter-container .filter-title i {
            color: #667eea;
        }

        .filter-container .filter-row {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
            align-items: center;
        }

        .filter-container .filter-row select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            background: white;
            color: #2d3748;
            min-width: 200px;
            transition: all 0.3s;
            flex: 1;
        }

        .filter-container .filter-row select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-group {
            flex: 2;
            min-width: 250px;
        }

        .search-group .search-wrapper {
            display: flex;
            gap: 10px;
            align-items: center;
        }

        .search-group .search-wrapper input {
            flex: 1;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            background: white;
            color: #2d3748;
            transition: all 0.3s;
            min-width: 150px;
        }

        .search-group .search-wrapper input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .search-group .search-wrapper input::placeholder {
            color: #a0aec0;
        }

        .search-group .search-wrapper .search-icon {
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .search-group .search-wrapper .search-icon:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .search-group .search-wrapper .search-clear {
            padding: 10px 20px;
            background: #edf2f7;
            color: #2d3748;
            border: none;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
            white-space: nowrap;
        }

        .search-group .search-wrapper .search-clear:hover {
            background: #e2e8f0;
        }

        .search-group .search-hint {
            font-size: 12px;
            color: #718096;
            margin-top: 5px;
        }

        .search-group .search-hint i {
            color: #667eea;
        }

        .search-results-box {
            margin-top: 15px;
            padding: 15px;
            background: #f7fafc;
            border-radius: 8px;
            border: 2px solid #e2e8f0;
        }

        .search-results-box .results-title {
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .search-results-box .results-title i {
            color: #667eea;
        }

        .search-results-box .student-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .search-results-box .student-item {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            background: white;
            border-radius: 20px;
            border: 2px solid #e2e8f0;
            cursor: pointer;
            transition: all 0.3s;
            font-size: 13px;
        }

        .search-results-box .student-item:hover {
            border-color: #667eea;
            background: #ebf4ff;
            transform: translateY(-2px);
        }

        .search-results-box .student-item.selected {
            border-color: #38a169;
            background: #f0fff4;
        }

        .search-results-box .student-item .student-code {
            color: #718096;
            font-size: 11px;
        }

        .search-results-box .student-item .select-badge {
            display: none;
            color: #38a169;
        }

        .search-results-box .student-item.selected .select-badge {
            display: inline;
        }

        .search-results-box .student-item .select-btn {
            padding: 2px 12px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 11px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .search-results-box .student-item .select-btn:hover {
            transform: scale(1.05);
        }

        .search-results-box .student-item.selected .select-btn {
            display: none;
        }

        .search-results-box .no-results {
            color: #e53e3e;
            font-size: 14px;
            text-align: center;
            padding: 10px;
        }

        .search-results-box .no-results i {
            margin-left: 8px;
        }

        #student_id option.highlight-option {
            background-color: #ebf4ff;
            font-weight: 600;
            color: #2d3748;
        }

        /* معلومات المتبقي */
        .remaining-info {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 20px;
        }

        .remaining-info .info-title {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .remaining-info .info-title i {
            color: #667eea;
        }

        .remaining-info .stage-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
        }

        .remaining-info .stage-item {
            background: white;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
        }

        .remaining-info .stage-item .stage-name {
            font-size: 13px;
            font-weight: 600;
            color: #2d3748;
        }

        .remaining-info .stage-item .stage-status {
            font-size: 11px;
            padding: 2px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-right: 5px;
        }

        .remaining-info .stage-item .stage-status.current {
            background: #bee3f8;
            color: #2a4365;
        }

        .remaining-info .stage-item .stage-status.previous {
            background: #edf2f7;
            color: #4a5568;
        }

        .remaining-info .stage-item .stage-amount {
            display: flex;
            justify-content: space-between;
            margin-top: 5px;
            font-size: 13px;
        }

        .remaining-info .stage-item .stage-amount .label {
            color: #718096;
        }

        .remaining-info .stage-item .stage-amount .value {
            font-weight: 600;
        }

        .remaining-info .stage-item .stage-amount .value.positive {
            color: #e53e3e;
        }

        .remaining-info .stage-item .stage-amount .value.zero {
            color: #38a169;
        }

        .remaining-info .total-remaining {
            margin-top: 10px;
            padding-top: 10px;
            border-top: 2px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-size: 16px;
            font-weight: 700;
        }

        .remaining-info .total-remaining .label {
            color: #2d3748;
        }

        .remaining-info .total-remaining .value {
            color: #e53e3e;
        }

        .remaining-info .total-remaining .value.paid {
            color: #38a169;
        }

        /* تنسيق أنواع الإيرادات العامة */
        .general-type-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(150px, 1fr));
            gap: 10px;
            margin-top: 10px;
        }

        .general-type-grid .type-option {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            background: white;
        }

        .general-type-grid .type-option:hover {
            border-color: #38a169;
            background: #f0fff4;
        }

        .general-type-grid .type-option input[type="radio"] {
            width: 18px;
            height: 18px;
            accent-color: #38a169;
            cursor: pointer;
        }

        .general-type-grid .type-option label {
            cursor: pointer;
            font-weight: 500;
            color: #2d3748;
            margin: 0;
        }

        .general-type-grid .type-option.selected {
            border-color: #38a169;
            background: #f0fff4;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .header-actions {
                flex-direction: column;
            }

            .btn-back {
                justify-content: center;
            }

            .form-container {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .income-item .item-row {
                grid-template-columns: 1fr;
                gap: 10px;
            }

            .form-actions {
                flex-direction: column;
            }

            .stats-mini {
                grid-template-columns: 1fr 1fr;
            }

            .filter-container .filter-row {
                flex-direction: column;
            }

            .filter-container .filter-row select {
                width: 100%;
                min-width: unset;
            }

            .search-group {
                width: 100%;
                min-width: unset;
            }

            .search-group .search-wrapper {
                flex-wrap: wrap;
            }

            .search-group .search-wrapper input {
                min-width: 100%;
            }

            .search-results-box .student-list {
                flex-direction: column;
            }

            .search-results-box .student-item {
                justify-content: space-between;
            }

            .remaining-info .stage-grid {
                grid-template-columns: 1fr;
            }

            .income-tabs {
                flex-direction: column;
            }

            .general-type-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-mini {
                grid-template-columns: 1fr;
            }

            .general-type-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
        <!-- الشريط الجانبي -->
        <aside class="sidebar">
            <div class="sidebar-header">
                <div class="logo-container">
                    <i class="fas fa-coins logo-icon"></i>
                    <div class="logo-text">
                        <h2>المبتكر المالي</h2>
                        <span>نظام الإدارة المتكامل</span>
                    </div>
                </div>
            </div>

            <nav class="sidebar-nav">
                <ul>
                    <li class="nav-item">
                        <a href="../../index.php">
                            <i class="fas fa-th-large"></i>
                            <span>لوحة التحكم</span>
                        </a>
                    </li>
                    <li class="nav-section">الإدارة</li>
                    <li class="nav-item">
                        <a href="../schools/index.php">
                            <i class="fas fa-school"></i>
                            <span>المدارس والمعاهد</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../stages/index.php">
                            <i class="fas fa-layer-group"></i>
                            <span>المراحل الدراسية</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../students/index.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>الطلاب</span>
                        </a>
                    </li>

                    <li class="nav-section">المعاملات المالية</li>
                    <li class="nav-item">
                        <a href="index.php">
                            <i class="fas fa-exchange-alt"></i>
                            <span>جميع المعاملات</span>
                        </a>
                    </li>
                    <li class="nav-item active">
                        <a href="income.php">
                            <i class="fas fa-arrow-up text-success"></i>
                            <span>الإيرادات</span>
                            <span class="badge"><?php echo $total_income_count; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="expense.php">
                            <i class="fas fa-arrow-down text-danger"></i>
                            <span>المصروفات</span>
                        </a>
                    </li>

                    <li class="nav-section">التقارير</li>
                    <li class="nav-item">
                        <a href="../reports/index.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>التقارير والإحصائيات</span>
                        </a>
                    </li>
                </ul>
            </nav>

            <div class="sidebar-footer">
                <div class="user-info">
                    <i class="fas fa-user-circle"></i>
                    <div>
                        <span class="user-name">مدير النظام</span>
                        <span class="user-role">مدير مالي</span>
                    </div>
                </div>
                <a href="#" class="logout-btn">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>تسجيل الخروج</span>
                </a>
            </div>
        </aside>

        <!-- المحتوى الرئيسي -->
        <main class="main-content">
            <!-- الهيدر العلوي -->
            <header class="top-header">
                <div class="header-left">
                    <button class="toggle-sidebar" id="toggleSidebar">
                        <i class="fas fa-bars"></i>
                    </button>
                    <h1>إضافة إيرادات</h1>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="بحث..." id="searchInput">
                    </div>
                    <div class="header-actions">
                        <button class="icon-btn" title="الإشعارات">
                            <i class="fas fa-bell"></i>
                            <span class="notification-dot"></span>
                        </button>
                        <button class="icon-btn" title="الإعدادات">
                            <i class="fas fa-cog"></i>
                        </button>
                    </div>
                </div>
            </header>

            <!-- المحتوى -->
            <div class="content-area">
                <div class="page-header">
                    <h2>
                        <i class="fas fa-arrow-up" style="color: #38a169;"></i>
                        إضافة إيرادات جديدة
                    </h2>
                    <div class="header-actions">
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة للقائمة
                        </a>
                    </div>
                </div>

                <!-- رسائل التنبيه -->
                <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div>
                        <?php foreach ($errors as $error): ?>
                        <div>• <?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <!-- إحصائيات سريعة -->
                <div class="stats-mini">
                    <div class="stat-box">
                        <i class="fas fa-coins"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_income_amount); ?></div>
                            <div class="label">إجمالي الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-list"></i>
                        <div class="info">
                            <div class="number"><?php echo $total_income_count; ?></div>
                            <div class="label">عدد الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-school"></i>
                        <div class="info">
                            <div class="number"><?php echo count($schools); ?></div>
                            <div class="label">المدارس والمعاهد</div>
                        </div>
                    </div>
                </div>

                <!-- معلومات المتبقي - تفاصيل المراحل -->
                <?php if ($student_data && $student_id && !empty($stage_remaining)): ?>
                <div class="remaining-info">
                    <div class="info-title">
                        <i class="fas fa-layer-group"></i> تفاصيل المستحقات لجميع مراحل الطالب
                    </div>
                    <div class="stage-grid">
                        <?php 
                        $sorted_stages = $stage_remaining;
                        uasort($sorted_stages, function($a, $b) {
                            return strcmp($a['name'], $b['name']);
                        });
                        foreach ($sorted_stages as $id => $stage): 
                        ?>
                        <div class="stage-item">
                            <div>
                                <span class="stage-name"><?php echo htmlspecialchars($stage['name']); ?></span>
                                <span class="stage-status <?php echo $stage['status']; ?>">
                                    <?php echo $stage['status'] == 'current' ? 'الحالية' : 'سابقة'; ?>
                                </span>
                            </div>
                            <div class="stage-amount">
                                <span class="label">المتبقي:</span>
                                <span class="value <?php echo $stage['remaining'] > 0 ? 'positive' : 'zero'; ?>">
                                    <?php echo formatLibyanCurrency($stage['remaining']); ?>
                                </span>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="total-remaining">
                        <span class="label">إجمالي المستحق على الطالب:</span>
                        <span class="value <?php echo $total_remaining > 0 ? '' : 'paid'; ?>">
                            <?php echo formatLibyanCurrency($total_remaining); ?>
                            <?php if ($total_remaining == 0): ?>
                            ✅ (مدفوع بالكامل)
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- فلتر المدرسة والبحث -->
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i> فلترة الطلاب (لإيرادات الطلاب)
                    </div>
                    <form method="GET" id="filterForm">
                        <div class="filter-row">
                            <select id="filterSchool" name="school_id" onchange="this.form.submit()">
                                <option value="">جميع المدارس</option>
                                <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['name']); ?>
                                    (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>

                            <div class="search-group">
                                <div class="search-wrapper">
                                    <input type="text" name="search" 
                                           value="<?php echo htmlspecialchars($search); ?>" 
                                           placeholder="بحث برقم الطالب أو اسمه ..."
                                           id="searchInputMain">
                                    <button type="submit" class="search-icon">
                                        <i class="fas fa-search"></i> بحث
                                    </button>
                                    <?php if ($search): ?>
                                    <a href="income.php<?php echo $school_id ? '?school_id=' . $school_id : ''; ?>" class="search-clear">
                                        <i class="fas fa-times"></i> إلغاء
                                    </a>
                                    <?php endif; ?>
                                </div>
                                <div class="search-hint">
                                    <i class="fas fa-info-circle"></i> يمكنك البحث برقم الطالب (مثل: S2024001) أو باسم الطالب
                                </div>
                            </div>
                        </div>
                    </form>

                    <!-- عرض نتائج البحث -->
                    <?php if ($search): ?>
                    <div class="search-results-box">
                        <div class="results-title">
                            <i class="fas fa-users"></i> نتائج البحث عن "<strong><?php echo htmlspecialchars($search); ?></strong>"
                            <span style="font-size: 12px; color: #718096; font-weight: 400;">(<?php echo count($search_results); ?> طالب)</span>
                        </div>
                        
                        <?php if (count($search_results) > 0): ?>
                        <div class="student-list">
                            <?php foreach ($search_results as $student): ?>
                            <div class="student-item <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>">
                                <span>
                                    <?php echo htmlspecialchars($student['name']); ?>
                                    <span class="student-code">(<?php echo htmlspecialchars($student['student_code'] ?? '---'); ?>)</span>
                                </span>
                                <span class="select-badge">
                                    <i class="fas fa-check-circle"></i> مختار
                                </span>
                                <button class="select-btn" onclick="selectStudent(<?php echo $student['id']; ?>)">
                                    <i class="fas fa-check"></i> اختيار
                                </button>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="no-results">
                            <i class="fas fa-exclamation-circle"></i> لا توجد نتائج مطابقة للبحث
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- نموذج الإضافة -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-arrow-up"></i>
                        إضافة إيرادات جديدة
                    </div>
                    <div class="form-subtitle">
                        اختر نوع الإيراد ثم أضف البنود المطلوبة
                    </div>

                    <!-- تبويب نوع الإيراد -->
                    <div class="income-tabs">
                        <button type="button" class="tab-btn active" data-tab="student" onclick="switchTab('student')">
                            <i class="fas fa-user-graduate"></i>
                            إيراد طالب
                        </button>
                        <button type="button" class="tab-btn" data-tab="general" onclick="switchTab('general')">
                            <i class="fas fa-building"></i>
                            إيراد عام
                        </button>
                    </div>

                    <form method="POST" id="addIncomeForm" onsubmit="return validateForm()">
                        <!-- تبويب إيراد طالب -->
                        <div id="tab-student" class="tab-content active">
                            <div class="form-row">
                                <div class="form-group">
                                    <label for="student_id">
                                        الطالب
                                        <span class="required">*</span>
                                    </label>
                                    <select id="student_id" name="student_id" required>
                                        <option value="">-- اختر الطالب --</option>
                                        <?php 
                                        if ($search && count($search_results) > 0):
                                            foreach ($search_results as $student):
                                        ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                            <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>
                                            class="highlight-option">
                                            ⭐ <?php echo htmlspecialchars($student['name']); ?>
                                            (<?php echo htmlspecialchars($student['student_code'] ?? '---'); ?>)
                                            - نتيجة بحث
                                        </option>
                                        <?php 
                                            endforeach;
                                        else:
                                            foreach ($filtered_students as $student):
                                        ?>
                                        <option value="<?php echo $student['id']; ?>" 
                                            <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($student['name']); ?>
                                            (<?php echo htmlspecialchars($student['student_code'] ?? '---'); ?>)
                                        </option>
                                        <?php 
                                            endforeach;
                                        endif;
                                        ?>
                                    </select>
                                    <div class="help-text">
                                        <?php if ($search && count($search_results) == 0): ?>
                                        <span style="color: #e53e3e;">
                                            <i class="fas fa-exclamation-circle"></i>
                                            لا يوجد طلاب مطابقين للبحث
                                        </span>
                                        <?php else: ?>
                                        الطالب المرتبط بهذه الإيرادات
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="stage_id">المرحلة الدراسية (افتراضية للبنود)</label>
                                    <select id="stage_id" name="stage_id">
                                        <option value="">-- اختر المرحلة (اختياري) --</option>
                                        <?php foreach ($stages as $stage): ?>
                                        <option value="<?php echo $stage['id']; ?>" 
                                            <?php echo $stage_id == $stage['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($stage['name']); ?>
                                            (<?php echo formatLibyanCurrency($stage['fee_amount']); ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="help-text">المرحلة الافتراضية للبنود (يمكن تغييرها لكل بند)</div>
                                </div>
                            </div>
                        </div>

                        <!-- تبويب إيراد عام -->
                        <div id="tab-general" class="tab-content">
                            <div class="form-group">
                                <label for="general_type">
                                    نوع الإيراد العام
                                    <span class="required">*</span>
                                </label>
                                <div class="general-type-grid">
                                    <?php foreach ($general_income_types as $key => $label): ?>
                                    <div class="type-option" onclick="selectGeneralType('<?php echo $key; ?>')">
                                        <input type="radio" id="general_<?php echo $key; ?>" name="general_type" value="<?php echo $key; ?>">
                                        <label for="general_<?php echo $key; ?>"><?php echo $label; ?></label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                                <div class="help-text">اختر نوع الإيراد العام</div>
                            </div>
                        </div>

                        <!-- بنود الإيرادات -->
                        <div class="income-items">
                            <div class="items-title">
                                <span>
                                    <i class="fas fa-list"></i> بنود الإيراد
                                    <span class="item-count" id="itemCount">(بند واحد)</span>
                                </span>
                                <button type="button" class="btn-add-item" onclick="addItem()">
                                    <i class="fas fa-plus"></i> إضافة بند جديد
                                </button>
                            </div>

                            <div id="itemsContainer">
                                <!-- سيتم إضافة البنود هنا عبر JavaScript -->
                            </div>

                            <div class="total-amount-display">
                                <span class="label">المجموع الكلي:</span>
                                <span class="amount" id="totalAmount">0.00 د.ل</span>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="transaction_date">
                                    تاريخ الإيراد
                                    <span class="required">*</span>
                                </label>
                                <input type="date" id="transaction_date" name="transaction_date" 
                                       value="<?php echo $transaction_date; ?>" required>
                                <div class="help-text">تاريخ استلام الإيرادات</div>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">
                                    طريقة الدفع
                                    <span class="required">*</span>
                                </label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">-- اختر طريقة الدفع --</option>
                                    <?php foreach ($payment_methods as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" <?php echo $payment_method == $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">طريقة دفع الإيرادات</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status">
                                حالة الإيراد
                                <span class="required">*</span>
                            </label>
                            <select id="status" name="status" required>
                                <option value="">-- اختر الحالة --</option>
                                <?php foreach ($status_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" <?php echo $status == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">الحالة الحالية للإيرادات</div>
                        </div>

                        <input type="hidden" name="income_type" id="income_type" value="student">

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-plus"></i>
                                <i class="fas fa-arrow-up"></i>
                                إضافة الإيرادات
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i>
                                إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        let itemCounter = 0;
        const suggestedItems = <?php echo json_encode($suggested_items); ?>;
        const generalSuggestedItems = <?php echo json_encode($general_suggested_items); ?>;

        // ===== تبديل التبويب =====
        function switchTab(tab) {
            // إخفاء جميع التبويبات
            document.querySelectorAll('.tab-content').forEach(el => {
                el.classList.remove('active');
            });
            // إظهار التبويب المحدد
            document.getElementById('tab-' + tab).classList.add('active');
            
            // تحديث الأزرار
            document.querySelectorAll('.tab-btn').forEach(el => {
                el.classList.remove('active');
            });
            document.querySelector(`.tab-btn[data-tab="${tab}"]`).classList.add('active');
            
            // تحديث حقل income_type
            document.getElementById('income_type').value = tab;
            
            // إظهار/إخفاء حقل الطالب حسب التبويب
            const studentField = document.getElementById('student_id');
            const studentLabel = studentField.closest('.form-group');
            const stageField = document.getElementById('stage_id');
            const stageLabel = stageField.closest('.form-group');
            
            if (tab === 'student') {
                studentLabel.style.display = 'block';
                stageLabel.style.display = 'block';
                studentField.required = true;
            } else {
                studentLabel.style.display = 'none';
                stageLabel.style.display = 'none';
                studentField.required = false;
                // إلغاء تحديد الطالب
                studentField.value = '';
            }
        }

        // ===== اختيار نوع الإيراد العام =====
        function selectGeneralType(type) {
            document.querySelectorAll('.type-option').forEach(el => {
                el.classList.remove('selected');
            });
            document.querySelector(`.type-option input[value="${type}"]`).closest('.type-option').classList.add('selected');
            document.getElementById('general_' + type).checked = true;
        }

        // ===== دالة إضافة بند جديد =====
        function addItem(name = '', amount = '', stageId = '', description = '') {
            itemCounter++;
            const container = document.getElementById('itemsContainer');
            const itemDiv = document.createElement('div');
            itemDiv.className = 'income-item';
            itemDiv.id = 'item-' + itemCounter;
            
            const isGeneral = document.getElementById('income_type').value === 'general';
            const suggestions = isGeneral ? generalSuggestedItems : suggestedItems;
            
            itemDiv.innerHTML = `
                <div class="item-header">
                    <span class="item-number">بند #${itemCounter}</span>
                    <button type="button" class="btn-remove-item" onclick="removeItem(${itemCounter})">
                        <i class="fas fa-trash"></i> حذف
                    </button>
                </div>
                <div class="item-row">
                    <div class="form-group">
                        <label>اسم البند <span class="required">*</span></label>
                        <input type="text" name="item_name[]" value="${name}" placeholder="مثال: ${isGeneral ? 'تبرع نقدي' : 'رسوم الفصل الدراسي'}" required>
                        <div class="suggested-items">
                            ${suggestions.map(s => `<span class="tag" onclick="fillItemName(${itemCounter}, '${s}')">${s}</span>`).join('')}
                        </div>
                    </div>
                    <div class="form-group">
                        <label>المبلغ (د.ل) <span class="required">*</span></label>
                        <input type="number" name="item_amount[]" value="${amount}" step="0.01" min="0.01" placeholder="0.00" required oninput="updateTotal()">
                    </div>
                    <div class="form-group">
                        <label>المرحلة</label>
                        <select name="item_stage_id[]">
                            <option value="">-- اختر --</option>
                            <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo $stage['id']; ?>" ${stageId == <?php echo $stage['id']; ?> ? 'selected' : ''}>
                                <?php echo htmlspecialchars($stage['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="form-group" style="margin-top: 10px; margin-bottom: 0;">
                    <label>وصف إضافي (اختياري)</label>
                    <input type="text" name="item_description[]" value="${description}" placeholder="وصف تفصيلي للبند">
                </div>
            `;
            container.appendChild(itemDiv);
            updateItemCount();
            updateTotal();
        }

        // دالة حذف بند
        function removeItem(id) {
            const item = document.getElementById('item-' + id);
            if (item && document.querySelectorAll('.income-item').length > 1) {
                item.remove();
                updateItemCount();
                updateTotal();
            } else {
                alert('يجب أن يكون هناك بند واحد على الأقل');
            }
        }

        // دالة تعبئة اسم البند من الاقتراحات
        function fillItemName(id, name) {
            const item = document.getElementById('item-' + id);
            if (item) {
                const input = item.querySelector('input[name="item_name[]"]');
                if (input) {
                    input.value = name;
                }
            }
        }

        // دالة تحديث عدد البنود
        function updateItemCount() {
            const count = document.querySelectorAll('.income-item').length;
            document.getElementById('itemCount').textContent = `(${count} ${count == 1 ? 'بند' : 'بنود'})`;
        }

        // دالة تحديث المجموع الكلي
        function updateTotal() {
            const amounts = document.querySelectorAll('input[name="item_amount[]"]');
            let total = 0;
            amounts.forEach(input => {
                const val = parseFloat(input.value);
                if (!isNaN(val) && val > 0) {
                    total += val;
                }
            });
            document.getElementById('totalAmount').textContent = total.toFixed(2) + ' د.ل';
        }

        // وظيفة اختيار الطالب من نتائج البحث
        function selectStudent(studentId) {
            const select = document.getElementById('student_id');
            for (let option of select.options) {
                if (option.value == studentId) {
                    option.selected = true;
                    break;
                }
            }
            
            const form = document.getElementById('filterForm');
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'student_id';
            input.value = studentId;
            form.appendChild(input);
            form.submit();
        }
        
        // التحقق من صحة النموذج
        function validateForm() {
            const incomeType = document.getElementById('income_type').value;
            const transaction_date = document.getElementById('transaction_date').value;
            const payment_method = document.getElementById('payment_method').value;
            const status = document.getElementById('status').value;
            
            // التحقق من التاريخ
            if (transaction_date === '') {
                alert('يرجى اختيار تاريخ الإيراد');
                document.getElementById('transaction_date').focus();
                return false;
            }
            
            // التحقق من طريقة الدفع
            if (payment_method === '') {
                alert('يرجى اختيار طريقة الدفع');
                document.getElementById('payment_method').focus();
                return false;
            }
            
            // التحقق من الحالة
            if (status === '') {
                alert('يرجى اختيار حالة الإيراد');
                document.getElementById('status').focus();
                return false;
            }
            
            // التحقق حسب نوع الإيراد
            if (incomeType === 'student') {
                const student_id = document.getElementById('student_id').value;
                if (student_id === '') {
                    alert('يرجى اختيار الطالب');
                    document.getElementById('student_id').focus();
                    return false;
                }
            } else {
                const generalType = document.querySelector('input[name="general_type"]:checked');
                if (!generalType) {
                    alert('يرجى اختيار نوع الإيراد العام');
                    return false;
                }
            }
            
            // التحقق من وجود بنود
            const items = document.querySelectorAll('.income-item');
            if (items.length === 0) {
                alert('يرجى إضافة بند واحد على الأقل');
                return false;
            }
            
            // التحقق من صحة البنود
            let hasValidItem = false;
            items.forEach(item => {
                const name = item.querySelector('input[name="item_name[]"]').value.trim();
                const amount = item.querySelector('input[name="item_amount[]"]').value.trim();
                if (name !== '' && parseFloat(amount) > 0) {
                    hasValidItem = true;
                }
            });
            
            if (!hasValidItem) {
                alert('يرجى إدخال اسم ومبلغ صحيح لكل بند');
                return false;
            }
            
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإضافة...';
            
            return true;
        }
        
        // إضافة اختصار Enter للبحث
        document.getElementById('searchInputMain').addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('filterForm').submit();
            }
        });
        
        // إضافة تأثيرات فورية عند تغيير الحقول
        document.addEventListener('DOMContentLoaded', function() {
            addItem('', '', '', '');
            
            const inputs = document.querySelectorAll('input, select, textarea');
            inputs.forEach(input => {
                input.addEventListener('change', function() {
                    if (this.value.trim() !== '') {
                        this.style.borderColor = '#48bb78';
                    } else {
                        this.style.borderColor = '#e2e8f0';
                    }
                });
            });
            
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#38a169';
                });
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#e2e8f0';
                    }
                });
            });
            
            document.addEventListener('input', function(e) {
                if (e.target.name === 'item_amount[]') {
                    updateTotal();
                }
            });
        });
        
        // تأكيد الخروج        let formChanged = false;
        document.querySelectorAll('input, select, textarea').forEach(element => {
            element.addEventListener('change', function() {
                formChanged = true;
            });
            element.addEventListener('input', function() {
                if (this.value.trim() !== '') {
                    formChanged = true;
                }
            });
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'لديك تغييرات غير محفوظة. هل أنت متأكد من المغادرة؟';
            }
        });
        
        document.getElementById('addIncomeForm').addEventListener('submit', function() {
            formChanged = false;
        });
        
        // اختصار Ctrl+Enter
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('addIncomeForm').submit();
            }
        });
    </script>
</body>
</html>