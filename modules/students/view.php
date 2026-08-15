<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف الطالب
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات الطالب مع معلومات المدرسة والمرحلة
$stmt = $pdo->prepare("
    SELECT st.*, 
           sc.name as school_name, 
           sc.type as school_type,
           sg.name as stage_name,
           sg.fee_amount as stage_fee,
           sg.order_number as stage_order
    FROM students st 
    JOIN schools sc ON st.school_id = sc.id 
    LEFT JOIN stages sg ON st.current_stage_id = sg.id 
    WHERE st.id = ?
");
$stmt->execute([$id]);
$student = $stmt->fetch();

if (!$student) {
    header("Location: index.php");
    exit;
}

// جلب جميع المراحل الخاصة بالمدرسة (جميع المراحل)
$stmt = $pdo->prepare("
    SELECT id, name, fee_amount, order_number 
    FROM stages 
    WHERE school_id = ?
    ORDER BY order_number ASC, fee_amount ASC
");
$stmt->execute([$student['school_id']]);
$all_school_stages = $stmt->fetchAll();

// جلب المراحل التي مر بها الطالب (من سجل الترقيات + المرحلة الحالية + المرحلة الأولى)
$stage_history = [];

// 1. جلب المرحلة الحالية
if ($student['current_stage_id']) {
    $stmt = $pdo->prepare("
        SELECT id, name, fee_amount, 
               'current' as status,
               NULL as promotion_date
        FROM stages 
        WHERE id = ?
    ");
    $stmt->execute([$student['current_stage_id']]);
    $current_stage = $stmt->fetch();
    if ($current_stage) {
        $stage_history[$current_stage['id']] = $current_stage;
    }
}

// 2. جلب المراحل السابقة من سجل الترقيات (المراحل التي تم الترقية إليها)
$stmt = $pdo->prepare("
    SELECT DISTINCT 
        ts.id, 
        ts.name, 
        ts.fee_amount,
        'previous' as status,
        p.promotion_date
    FROM promotions p
    JOIN stages ts ON p.to_stage_id = ts.id
    WHERE p.student_id = ?
    ORDER BY p.promotion_date DESC
");
$stmt->execute([$id]);
$previous_stages = $stmt->fetchAll();

// دمج المراحل السابقة
foreach ($previous_stages as $stage) {
    if (!isset($stage_history[$stage['id']])) {
        $stage_history[$stage['id']] = $stage;
    }
}

// 3. جلب المرحلة الأولى (المرحلة التي بدأ فيها الطالب)
// نبحث عن أول مرحلة في سجل الترقيات (from_stage_id)
$stmt = $pdo->prepare("
    SELECT from_stage_id 
    FROM promotions 
    WHERE student_id = ? 
    ORDER BY promotion_date ASC 
    LIMIT 1
");
$stmt->execute([$id]);
$first_promotion = $stmt->fetch();

if ($first_promotion && $first_promotion['from_stage_id']) {
    $first_stage_id = $first_promotion['from_stage_id'];
    // التحقق من أن هذه المرحلة ليست موجودة بالفعل في القائمة
    if (!isset($stage_history[$first_stage_id])) {
        $stmt = $pdo->prepare("
            SELECT id, name, fee_amount, 
                   'previous' as status,
                   NULL as promotion_date
            FROM stages 
            WHERE id = ?
        ");
        $stmt->execute([$first_stage_id]);
        $first_stage = $stmt->fetch();
        if ($first_stage) {
            $stage_history[$first_stage_id] = $first_stage;
        }
    }
}

// 4. إذا لم يكن هناك سجل ترقيات (طالب جديد)، نضيف المرحلة الحالية فقط
// ونبحث عن المراحل التي سبقت المرحلة الحالية بناءً على الترتيب
if (empty($previous_stages) && $student['current_stage_id']) {
    // جلب جميع المراحل التي تأتي قبل المرحلة الحالية
    $current_order = $student['stage_order'] ?? 0;
    foreach ($all_school_stages as $stage) {
        if ($stage['order_number'] < $current_order) {
            if (!isset($stage_history[$stage['id']])) {
                $stage_history[$stage['id']] = [
                    'id' => $stage['id'],
                    'name' => $stage['name'],
                    'fee_amount' => $stage['fee_amount'],
                    'status' => 'previous',
                    'promotion_date' => null
                ];
            }
        }
    }
}

// الآن ننشئ قائمة مرتبة بجميع المراحل التي مر بها الطالب حسب الترتيب
$ordered_stages = [];
foreach ($all_school_stages as $school_stage) {
    if (isset($stage_history[$school_stage['id']])) {
        $ordered_stages[] = $stage_history[$school_stage['id']];
    }
}

// إذا كان الطالب في مرحلة وليست في القائمة (للتأكد)
if ($student['current_stage_id'] && !in_array($student['current_stage_id'], array_column($ordered_stages, 'id'))) {
    $ordered_stages[] = $current_stage;
}

// جلب جميع معاملات الطالب
$transactions = getStudentTransactions($id);

// حساب إحصائيات لكل مرحلة
$stage_stats = [];
foreach ($ordered_stages as $stage) {
    $stage_id = $stage['id'];
    $stage_stats[$stage_id] = [
        'id' => $stage_id,
        'name' => $stage['name'],
        'fee_amount' => $stage['fee_amount'],
        'total_income' => 0,
        'total_expenses' => 0,
        'remaining' => 0,
        'status' => $stage['status'] ?? 'previous',
        'promotion_date' => $stage['promotion_date'] ?? null
    ];
    
    // حساب الإيرادات والمصروفات لهذه المرحلة
    foreach ($transactions as $t) {
        if ($t['stage_id'] == $stage_id) {
            if ($t['type'] == 'income' && $t['status'] == 'paid') {
                $stage_stats[$stage_id]['total_income'] += $t['amount'];
            } elseif ($t['type'] == 'expense' && $t['status'] == 'paid') {
                $stage_stats[$stage_id]['total_expenses'] += $t['amount'];
            }
        }
    }
    
    // حساب المتبقي (الرسوم - الإيرادات + المصروفات)
    $stage_stats[$stage_id]['remaining'] = 
        $stage_stats[$stage_id]['fee_amount'] - 
        $stage_stats[$stage_id]['total_income'] + 
        $stage_stats[$stage_id]['total_expenses'];
    
    // تحديد حالة المتبقي
    if ($stage_stats[$stage_id]['remaining'] <= 0) {
        $stage_stats[$stage_id]['remaining_status'] = 'paid';
        $stage_stats[$stage_id]['remaining_text'] = 'مدفوع بالكامل ✅';
        $stage_stats[$stage_id]['can_pay'] = false;
    } elseif ($stage_stats[$stage_id]['remaining'] > 0 && $stage_stats[$stage_id]['total_income'] > 0) {
        $stage_stats[$stage_id]['remaining_status'] = 'partial';
        $stage_stats[$stage_id]['remaining_text'] = 'مدفوع جزئياً ⚠️';
        $stage_stats[$stage_id]['can_pay'] = true;
    } else {
        $stage_stats[$stage_id]['remaining_status'] = 'unpaid';
        $stage_stats[$stage_id]['remaining_text'] = 'غير مدفوع ❌';
        $stage_stats[$stage_id]['can_pay'] = true;
    }
}

// حساب إحصائيات عامة
$total_income = 0;
$total_expenses = 0;
$paid_count = 0;
$pending_count = 0;

foreach ($transactions as $t) {
    if ($t['type'] == 'income' && $t['status'] == 'paid') {
        $total_income += $t['amount'];
    } elseif ($t['type'] == 'expense' && $t['status'] == 'paid') {
        $total_expenses += $t['amount'];
    }
    if ($t['status'] == 'paid') {
        $paid_count++;
    } elseif ($t['status'] == 'pending') {
        $pending_count++;
    }
}

// جلب سجل الترقيات
$stmt = $pdo->prepare("
    SELECT p.*, 
           fs.name as from_stage, 
           ts.name as to_stage 
    FROM promotions p
    LEFT JOIN stages fs ON p.from_stage_id = fs.id
    LEFT JOIN stages ts ON p.to_stage_id = ts.id
    WHERE p.student_id = ?
    ORDER BY p.promotion_date DESC
");
$stmt->execute([$id]);
$promotions = $stmt->fetchAll();

// جلب إحصائيات إضافية
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE student_id = ? AND status = 'paid'");
$stmt->execute([$id]);
$total_transactions = $stmt->fetchColumn();

// حساب إجمالي المتبقي
$total_remaining = 0;
foreach ($stage_stats as $stat) {
    if ($stat['remaining'] > 0) {
        $total_remaining += $stat['remaining'];
    }
}

// جلب معرفات المراحل التي عليها متبقي
$stages_with_remaining = [];
foreach ($stage_stats as $stat) {
    if ($stat['remaining'] > 0) {
        $stages_with_remaining[] = $stat['id'];
    }
}

// حساب إجمالي الرسوم
$total_fees = 0;
foreach ($stage_stats as $stat) {
    $total_fees += $stat['fee_amount'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تفاصيل الطالب - المبتكر المالي</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <!-- نفس الـ Styles السابق -->
    <style>
        /* تنسيقات الصفحة */
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

        .btn-edit {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-pay {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: linear-gradient(135deg, #48bb78, #38a169);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 13px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            white-space: nowrap;
        }

        .btn-pay:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(56, 161, 105, 0.3);
        }

        .btn-pay-all {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #f6ad55, #ed8936);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-pay-all:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(237, 137, 54, 0.3);
        }

        /* بطاقة معلومات الطالب */
        .student-profile {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .student-profile .profile-header {
            display: flex;
            align-items: center;
            gap: 25px;
            flex-wrap: wrap;
        }

        .student-profile .profile-avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            font-weight: 700;
        }

        .student-profile .profile-info {
            flex: 1;
        }

        .student-profile .profile-info .name {
            font-size: 28px;
            font-weight: 800;
            color: #2d3748;
        }

        .student-profile .profile-info .code {
            font-size: 16px;
            color: #718096;
            margin: 5px 0;
        }

        .student-profile .profile-info .meta {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
            margin-top: 10px;
        }

        .student-profile .profile-info .meta span {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 5px 15px;
            background: #f7fafc;
            border-radius: 20px;
            font-size: 13px;
            color: #2d3748;
        }

        .student-profile .profile-info .meta span i {
            color: #667eea;
        }

        .status-badge {
            padding: 6px 18px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.active {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.inactive {
            background: #fed7d7;
            color: #9b2c2c;
        }

        .status-badge.graduated {
            background: #bee3f8;
            color: #2a4365;
        }

        /* شبكة الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stats-grid .stat-card {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }

        .stats-grid .stat-card i {
            font-size: 28px;
            color: #667eea;
            margin-bottom: 10px;
        }

        .stats-grid .stat-card .number {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .stats-grid .stat-card .label {
            font-size: 13px;
            color: #718096;
            display: block;
            margin-top: 3px;
        }

        .stats-grid .stat-card.income .number {
            color: #38a169;
        }

        .stats-grid .stat-card.expense .number {
            color: #e53e3e;
        }

        .stats-grid .stat-card.paid .number {
            color: #3182ce;
        }

        .stats-grid .stat-card.remaining .number {
            color: #f6ad55;
        }

        /* جدول المراحل والمدفوعات */
        .stages-payments {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .stages-payments .table-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .stages-payments .table-title i {
            color: #667eea;
            margin-left: 8px;
        }

        .table-scroll {
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f7fafc;
        }

        table th {
            padding: 12px 15px;
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: #4a5568;
            border-bottom: 2px solid #edf2f7;
            white-space: nowrap;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2d3748;
            vertical-align: middle;
        }

        table tbody tr:hover {
            background: #f7fafc;
        }

        table tbody tr.current-stage {
            background: #ebf4ff;
        }

        table tbody tr.current-stage:hover {
            background: #dbeafe;
        }

        .stage-status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            display: inline-block;
        }

        .stage-status-badge.current {
            background: #bee3f8;
            color: #2a4365;
        }

        .stage-status-badge.previous {
            background: #edf2f7;
            color: #4a5568;
        }

        .remaining-paid {
            color: #38a169;
            font-weight: 700;
        }

        .remaining-partial {
            color: #f6ad55;
            font-weight: 700;
        }

        .remaining-unpaid {
            color: #e53e3e;
            font-weight: 700;
        }

        .status-paid {
            background: #c6f6d5;
            color: #22543d;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-pending {
            background: #fefcbf;
            color: #975a16;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .status-cancelled {
            background: #fed7d7;
            color: #9b2c2c;
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .amount-income {
            color: #38a169;
            font-weight: 700;
        }

        .amount-expense {
            color: #e53e3e;
            font-weight: 700;
        }

        .empty-state {
            text-align: center;
            padding: 30px;
            color: #718096;
        }

        .empty-state i {
            font-size: 40px;
            color: #cbd5e0;
            margin-bottom: 10px;
        }

        /* تفاصيل إضافية */
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .details-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .details-card .card-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .details-card .card-title i {
            color: #667eea;
            margin-left: 8px;
        }

        .details-card .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .details-card .detail-row:last-child {
            border-bottom: none;
        }

        .details-card .detail-row .label {
            color: #718096;
            font-weight: 500;
        }

        .details-card .detail-row .value {
            color: #2d3748;
            font-weight: 600;
        }

        /* جدول المعاملات */
        .transactions-table {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .transactions-table .table-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .transactions-table .table-title i {
            color: #667eea;
            margin-left: 8px;
        }

        /* رسالة نجاح */
        .alert-success {
            background: #f0fff4;
            color: #22543d;
            border: 1px solid #c6f6d5;
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-success i {
            font-size: 20px;
        }

        @media (max-width: 768px) {
            .student-profile .profile-header {
                flex-direction: column;
                text-align: center;
            }

            .student-profile .profile-info .meta {
                justify-content: center;
            }

            .details-grid {
                grid-template-columns: 1fr;
            }

            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .header-actions {
                flex-direction: column;
            }

            .btn-back, .btn-edit {
                justify-content: center;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .stages-payments .table-title {
                flex-direction: column;
                align-items: stretch;
            }

            .btn-pay-all {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
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
                    <li class="nav-item active">
                        <a href="index.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>الطلاب</span>
                        </a>
                    </li>

                    <li class="nav-section">المعاملات المالية</li>
                    <li class="nav-item">
                        <a href="../transactions/index.php">
                            <i class="fas fa-exchange-alt"></i>
                            <span>جميع المعاملات</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../transactions/income.php">
                            <i class="fas fa-arrow-up text-success"></i>
                            <span>الإيرادات</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="../transactions/expense.php">
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
                    <h1>تفاصيل الطالب</h1>
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
                        <i class="fas fa-user-graduate" style="color: #667eea;"></i>
                        تفاصيل الطالب
                    </h2>
                    <div class="header-actions">
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة للقائمة
                        </a>
                        <a href="edit.php?id=<?php echo $student['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i>
                            تعديل الطالب
                        </a>
                    </div>
                </div>

                <!-- رسالة نجاح (إذا تم التسديد) -->
                <?php if (isset($_GET['paid']) && $_GET['paid'] == 'success'): ?>
                <div class="alert-success">
                    <i class="fas fa-check-circle"></i>
                    تم تسديد المبلغ بنجاح ✅
                </div>
                <?php endif; ?>

                <!-- بطاقة معلومات الطالب -->
                <div class="student-profile">
                    <div class="profile-header">
                        <div class="profile-avatar">
                            <?php 
                            $nameParts = explode(' ', $student['name']);
                            $initials = '';
                            foreach ($nameParts as $part) {
                                if (!empty($part)) {
                                    $initials .= mb_substr($part, 0, 1);
                                }
                            }
                            echo mb_strtoupper(mb_substr($initials, 0, 2));
                            ?>
                        </div>
                        <div class="profile-info">
                            <div class="name"><?php echo htmlspecialchars($student['name']); ?></div>
                            <div class="code">
                                <i class="fas fa-id-card"></i>
                                رقم الطالب: <?php echo htmlspecialchars($student['student_code'] ?? 'غير محدد'); ?>
                            </div>
                            <div class="meta">
                                <span>
                                    <i class="fas fa-school"></i>
                                    <?php echo htmlspecialchars($student['school_name']); ?>
                                    (<?php echo $student['school_type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                                </span>
                                <span>
                                    <i class="fas fa-layer-group"></i>
                                    <?php echo htmlspecialchars($student['stage_name'] ?? 'غير محدد'); ?>
                                </span>
                                <span>
                                    <i class="fas fa-coins"></i>
                                    الرسوم: <?php echo formatLibyanCurrency($student['stage_fee'] ?? 0); ?>
                                </span>
                                <span class="status-badge <?php echo $student['status']; ?>">
                                    <?php 
                                    $statusLabels = [
                                        'active' => 'نشط',
                                        'inactive' => 'غير نشط',
                                        'graduated' => 'متخرج'
                                    ];
                                    echo $statusLabels[$student['status']] ?? $student['status'];
                                    ?>
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- إحصائيات سريعة -->
                <div class="stats-grid">
                    <div class="stat-card income">
                        <i class="fas fa-arrow-up"></i>
                        <div class="number"><?php echo formatLibyanCurrency($total_income); ?></div>
                        <span class="label">إجمالي الإيرادات</span>
                    </div>
                    <div class="stat-card expense">
                        <i class="fas fa-arrow-down"></i>
                        <div class="number"><?php echo formatLibyanCurrency($total_expenses); ?></div>
                        <span class="label">إجمالي المصروفات</span>
                    </div>
                    <div class="stat-card paid">
                        <i class="fas fa-check-circle"></i>
                        <div class="number"><?php echo $paid_count; ?></div>
                        <span class="label">معاملات مدفوعة</span>
                    </div>
                    <div class="stat-card remaining">
                        <i class="fas fa-wallet"></i>
                        <div class="number" style="color: <?php echo $total_remaining > 0 ? '#e53e3e' : '#38a169'; ?>;">
                            <?php echo formatLibyanCurrency($total_remaining); ?>
                        </div>
                        <span class="label">إجمالي المتبقي</span>
                    </div>
                </div>

                <!-- جدول المراحل والمدفوعات -->
                <?php if (count($stage_stats) > 0): ?>
                <div class="stages-payments">
                    <div class="table-title">
                        <span>
                            <i class="fas fa-layer-group"></i>
                            تفاصيل المراحل الدراسية والمدفوعات
                            <span style="font-size: 14px; font-weight: 400; color: #718096;">
                                (<?php echo count($stage_stats); ?> مرحلة)
                            </span>
                        </span>
                        <?php if ($total_remaining > 0): ?>
                        <a href="../transactions/income.php?student_id=<?php echo $student['id']; ?>&amount=<?php echo $total_remaining; ?>&stage_id=<?php echo implode(',', $stages_with_remaining); ?>" class="btn-pay-all">
                            <i class="fas fa-hand-holding-usd"></i>
                            تسديد الكل (<?php echo formatLibyanCurrency($total_remaining); ?>)
                        </a>
                        <?php endif; ?>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>المرحلة</th>
                                    <th>الرسوم</th>
                                    <th>الإيرادات</th>
                                    <th>المصروفات</th>
                                    <th>المتبقي</th>
                                    <th>الحالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $index = 1;
                                foreach ($stage_stats as $stat): 
                                    $row_class = $stat['status'] == 'current' ? 'current-stage' : '';
                                ?>
                                <tr class="<?php echo $row_class; ?>">
                                    <td><?php echo $index++; ?></td>
                                    <td>
                                        <?php echo htmlspecialchars($stat['name']); ?>
                                        <?php if ($stat['status'] == 'current'): ?>
                                        <span class="stage-status-badge current">الحالية</span>
                                        <?php else: ?>
                                        <span class="stage-status-badge previous">سابقة</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo formatLibyanCurrency($stat['fee_amount']); ?></td>
                                    <td class="amount-income"><?php echo formatLibyanCurrency($stat['total_income']); ?></td>
                                    <td class="amount-expense"><?php echo formatLibyanCurrency($stat['total_expenses']); ?></td>
                                    <td>
                                        <span class="
                                            <?php 
                                            if ($stat['remaining_status'] == 'paid') echo 'remaining-paid';
                                            elseif ($stat['remaining_status'] == 'partial') echo 'remaining-partial';
                                            else echo 'remaining-unpaid';
                                            ?>
                                        ">
                                            <?php echo formatLibyanCurrency($stat['remaining']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="
                                            <?php 
                                            if ($stat['remaining_status'] == 'paid') echo 'status-paid';
                                            elseif ($stat['remaining_status'] == 'partial') echo 'status-pending';
                                            else echo 'status-cancelled';
                                            ?>
                                        ">
                                            <?php echo $stat['remaining_text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($stat['can_pay'] && $stat['remaining'] > 0): ?>
                                        <a href="../transactions/income.php?student_id=<?php echo $student['id']; ?>&stage_id=<?php echo $stat['id']; ?>&amount=<?php echo $stat['remaining']; ?>" class="btn-pay">
                                            <i class="fas fa-hand-holding-usd"></i>
                                            تسديد (<?php echo formatLibyanCurrency($stat['remaining']); ?>)
                                        </a>
                                        <?php else: ?>
                                        <span style="color: #38a169; font-size: 13px;">
                                            <i class="fas fa-check-circle"></i> مكتمل
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot style="background: #f7fafc; font-weight: 700;">
                                <tr>
                                    <td colspan="2" style="text-align: center;">الإجمالي</td>
                                    <td><?php echo formatLibyanCurrency($total_fees); ?></td>
                                    <td class="amount-income"><?php echo formatLibyanCurrency($total_income); ?></td>
                                    <td class="amount-expense"><?php echo formatLibyanCurrency($total_expenses); ?></td>
                                    <td>
                                        <span style="color: <?php echo $total_remaining > 0 ? '#e53e3e' : '#38a169'; ?>;">
                                            <?php echo formatLibyanCurrency($total_remaining); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="<?php echo $total_remaining > 0 ? 'status-cancelled' : 'status-paid'; ?>">
                                            <?php echo $total_remaining > 0 ? 'متبقي ❌' : 'مدفوع بالكامل ✅'; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($total_remaining > 0): ?>
                                        <a href="../transactions/income.php?student_id=<?php echo $student['id']; ?>&amount=<?php echo $total_remaining; ?>&stage_id=<?php echo implode(',', $stages_with_remaining); ?>" class="btn-pay-all" style="padding: 4px 12px; font-size: 12px;">
                                            <i class="fas fa-hand-holding-usd"></i>
                                            تسديد الكل
                                        </a>
                                        <?php else: ?>
                                        <span style="color: #38a169; font-size: 13px;">
                                            <i class="fas fa-check-circle"></i> مكتمل
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- تفاصيل إضافية -->
                <div class="details-grid">
                    <div class="details-card">
                        <div class="card-title">
                            <i class="fas fa-info-circle"></i>
                            المعلومات الشخصية
                        </div>
                        <div class="detail-row">
                            <span class="label">الاسم الكامل</span>
                            <span class="value"><?php echo htmlspecialchars($student['name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">رقم الطالب</span>
                            <span class="value"><?php echo htmlspecialchars($student['student_code'] ?? '---'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">تاريخ الميلاد</span>
                            <span class="value"><?php echo $student['birth_date'] ? date('Y-m-d', strtotime($student['birth_date'])) : '---'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">رقم الهاتف</span>
                            <span class="value"><?php echo htmlspecialchars($student['phone'] ?? '---'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">العنوان</span>
                            <span class="value"><?php echo htmlspecialchars($student['address'] ?? '---'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">تاريخ التسجيل</span>
                            <span class="value"><?php echo $student['enrollment_date'] ? date('Y-m-d', strtotime($student['enrollment_date'])) : '---'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">الحالة</span>
                            <span class="value">
                                <span class="status-badge <?php echo $student['status']; ?>">
                                    <?php echo $statusLabels[$student['status']] ?? $student['status']; ?>
                                </span>
                            </span>
                        </div>
                    </div>

                    <div class="details-card">
                        <div class="card-title">
                            <i class="fas fa-graduation-cap"></i>
                            المعلومات الدراسية
                        </div>
                        <div class="detail-row">
                            <span class="label">المدرسة / المعهد</span>
                            <span class="value"><?php echo htmlspecialchars($student['school_name']); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">نوع المؤسسة</span>
                            <span class="value"><?php echo $student['school_type'] == 'school' ? 'مدرسة' : 'معهد'; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">المرحلة الحالية</span>
                            <span class="value"><?php echo htmlspecialchars($student['stage_name'] ?? 'غير محدد'); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">رسوم المرحلة</span>
                            <span class="value"><?php echo formatLibyanCurrency($student['stage_fee'] ?? 0); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">عدد المعاملات</span>
                            <span class="value"><?php echo $total_transactions; ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label">تاريخ الانضمام</span>
                            <span class="value"><?php echo $student['created_at'] ? date('Y-m-d', strtotime($student['created_at'])) : '---'; ?></span>
                        </div>
                        <div class="detail-row" style="border-top: 2px solid #edf2f7; padding-top: 10px; margin-top: 5px;">
                            <span class="label" style="font-weight: 700; color: #e53e3e;">إجمالي المتبقي</span>
                            <span class="value" style="font-weight: 700; color: <?php echo $total_remaining > 0 ? '#e53e3e' : '#38a169'; ?>;">
                                <?php echo formatLibyanCurrency($total_remaining); ?>
                                <?php if ($total_remaining > 0): ?>
                                <a href="../transactions/income.php?student_id=<?php echo $student['id']; ?>&amount=<?php echo $total_remaining; ?>&stage_id=<?php echo implode(',', $stages_with_remaining); ?>" class="btn-pay" style="margin-right: 10px; padding: 4px 12px; font-size: 12px;">
                                    <i class="fas fa-hand-holding-usd"></i>
                                    تسديد
                                </a>
                                <?php endif; ?>
                            </span>
                        </div>
                    </div>
                </div>

                <!-- سجل الترقيات -->
                <?php if (count($promotions) > 0): ?>
                <div class="transactions-table">
                    <div class="table-title">
                        <i class="fas fa-arrow-up"></i>
                        سجل الترقيات
                        <span style="font-size: 14px; font-weight: 400; color: #718096;">
                            (<?php echo count($promotions); ?> ترقية)
                        </span>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>من مرحلة</th>
                                    <th>إلى مرحلة</th>
                                    <th>تاريخ الترقية</th>
                                    <th>ملاحظات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($promotions as $index => $promotion): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($promotion['from_stage'] ?? 'بداية'); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['to_stage'] ?? '---'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($promotion['promotion_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['notes'] ?? '---'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- قائمة المعاملات -->
                <div class="transactions-table">
                    <div class="table-title">
                        <i class="fas fa-exchange-alt"></i>
                        المعاملات المالية
                        <span style="font-size: 14px; font-weight: 400; color: #718096;">
                            (<?php echo count($transactions); ?> معاملة)
                        </span>
                    </div>
                    <?php if (count($transactions) > 0): ?>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>النوع</th>
                                    <th>المرحلة</th>
                                    <th>المبلغ</th>
                                    <th>الوصف</th>
                                    <th>التاريخ</th>
                                    <th>طريقة الدفع</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $index => $t): 
                                    $stage_name = '---';
                                    foreach ($stage_stats as $stage_id => $stat) {
                                        if ($t['stage_id'] == $stage_id) {
                                            $stage_name = $stat['name'];
                                            break;
                                        }
                                    }
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php if ($t['type'] == 'income'): ?>
                                        <span style="color: #38a169;">
                                            <i class="fas fa-arrow-up"></i> إيراد
                                        </span>
                                        <?php else: ?>
                                        <span style="color: #e53e3e;">
                                            <i class="fas fa-arrow-down"></i> مصروف
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($stage_name); ?></td>
                                    <td class="<?php echo $t['type'] == 'income' ? 'amount-income' : 'amount-expense'; ?>">
                                        <?php echo $t['type'] == 'income' ? '+' : '-'; ?>
                                        <?php echo formatLibyanCurrency($t['amount']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($t['description'] ?? '---'); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($t['transaction_date'])); ?></td>
                                    <td>
                                        <?php 
                                        $methods = [
                                            'cash' => 'نقدي',
                                            'bank' => 'تحويل بنكي',
                                            'online' => 'دفع إلكتروني'
                                        ];
                                        echo $methods[$t['payment_method']] ?? $t['payment_method'];
                                        ?>
                                    </td>
                                    <td>
                                        <span class="status-<?php echo $t['status']; ?>">
                                            <?php 
                                            $statusLabels = [
                                                'paid' => 'مدفوعة',
                                                'pending' => 'معلقة',
                                                'cancelled' => 'ملغية'
                                            ];
                                            echo $statusLabels[$t['status']] ?? $t['status'];
                                            ?>
                                        </span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt"></i>
                        <p>لا توجد معاملات مالية لهذا الطالب</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
</body>
</html>