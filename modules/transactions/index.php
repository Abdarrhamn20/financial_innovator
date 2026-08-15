<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب معلمات الفلتر
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
$stage_id = isset($_GET['stage_id']) ? (int)$_GET['stage_id'] : null;
$student_id = isset($_GET['student_id']) ? (int)$_GET['student_id'] : null;
$type = isset($_GET['type']) ? $_GET['type'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : null;
$date_from = isset($_GET['date_from']) ? $_GET['date_from'] : null;
$date_to = isset($_GET['date_to']) ? $_GET['date_to'] : null;

// جلب المدارس للفلتر
$schools = getSchools();

// بناء استعلام المعاملات (مع دعم المعاملات العامة)
$sql = "SELECT t.*, 
        s.name as student_name, 
        s.student_code,
        sc.name as school_name,
        sg.name as stage_name
        FROM transactions t
        LEFT JOIN students s ON t.student_id = s.id
        LEFT JOIN schools sc ON s.school_id = sc.id
        LEFT JOIN stages sg ON t.stage_id = sg.id
        WHERE 1=1";

$params = [];

if ($school_id) {
    $sql .= " AND s.school_id = ?";
    $params[] = $school_id;
}

if ($stage_id) {
    $sql .= " AND t.stage_id = ?";
    $params[] = $stage_id;
}

if ($student_id) {
    $sql .= " AND t.student_id = ?";
    $params[] = $student_id;
}

if ($type) {
    $sql .= " AND t.type = ?";
    $params[] = $type;
}

if ($status) {
    $sql .= " AND t.status = ?";
    $params[] = $status;
}

if ($date_from) {
    $sql .= " AND t.transaction_date >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $sql .= " AND t.transaction_date <= ?";
    $params[] = $date_to;
}

$sql .= " ORDER BY t.transaction_date DESC, t.created_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$transactions = $stmt->fetchAll();

// جلب إحصائيات عامة
$total_income = 0;
$total_expenses = 0;
$total_paid = 0;
$total_pending = 0;
$total_cancelled = 0;
$general_count = 0;

foreach ($transactions as $t) {
    if ($t['type'] == 'income') {
        $total_income += $t['amount'];
    } else {
        $total_expenses += $t['amount'];
    }
    
    if ($t['status'] == 'paid') {
        $total_paid += $t['amount'];
    } elseif ($t['status'] == 'pending') {
        $total_pending += $t['amount'];
    } elseif ($t['status'] == 'cancelled') {
        $total_cancelled += $t['amount'];
    }
    
    // حساب المعاملات العامة (التي ليس لها طالب)
    if ($t['student_id'] == 0 || $t['student_id'] === null) {
        $general_count++;
    }
}

$total_transactions = count($transactions);
$balance = $total_income - $total_expenses;

// جلب قائمة الطلاب للفلتر
$students = [];
if ($school_id) {
    $students = getStudents($school_id);
} else {
    $students = getStudents();
}

// جلب قائمة المراحل للفلتر
$stages = [];
if ($school_id) {
    $stages = getStages($school_id);
} else {
    $stages = getStages();
}

// عرض رسائل النجاح أو الخطأ
$success_message = '';
$error_message = '';

if (isset($_GET['deleted']) && $_GET['deleted'] == 'success') {
    $success_message = "✅ تم حذف المعاملة بنجاح";
}

if (isset($_GET['error'])) {
    $error_message = "❌ " . urldecode($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>جميع المعاملات - المبتكر المالي</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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

        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 25px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-add-income {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .btn-add-income:hover {
            box-shadow: 0 10px 20px rgba(72, 187, 120, 0.3);
        }

        .btn-add-expense {
            background: linear-gradient(135deg, #fc8181, #e53e3e);
        }

        .btn-add-expense:hover {
            box-shadow: 0 10px 20px rgba(229, 62, 62, 0.3);
        }

        /* إحصائيات سريعة */
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
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
        }

        .stats-mini .stat-box .info {
            flex: 1;
        }

        .stats-mini .stat-box .info .number {
            font-size: 20px;
            font-weight: 700;
            color: #2d3748;
        }

        .stats-mini .stat-box .info .label {
            font-size: 12px;
            color: #718096;
        }

        .stats-mini .stat-box.income i {
            color: #38a169;
        }

        .stats-mini .stat-box.expense i {
            color: #e53e3e;
        }

        .stats-mini .stat-box.balance i {
            color: #667eea;
        }

        .stats-mini .stat-box.paid i {
            color: #3182ce;
        }

        .stats-mini .stat-box.pending i {
            color: #d69e2e;
        }

        .stats-mini .stat-box.general i {
            color: #9f7aea;
        }

        /* رسائل التنبيه */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            transition: transform 0.3s;
        }

        .alert .close-alert:hover {
            transform: rotate(90deg);
        }

        /* فلتر متقدم */
        .filter-container {
            background: white;
            padding: 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .filter-container .filter-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
        }

        .filter-container .filter-title i {
            color: #667eea;
            margin-left: 8px;
        }

        .filter-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap: 15px;
        }

        .filter-grid .filter-group label {
            display: block;
            font-size: 12px;
            font-weight: 600;
            color: #4a5568;
            margin-bottom: 4px;
        }

        .filter-grid .filter-group select,
        .filter-grid .filter-group input {
            width: 100%;
            padding: 8px 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 13px;
            background: white;
            color: #2d3748;
            transition: all 0.3s;
        }

        .filter-grid .filter-group select:focus,
        .filter-grid .filter-group input:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-actions {
            display: flex;
            gap: 10px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .btn-filter-apply {
            padding: 10px 25px;
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
        }

        .btn-filter-apply:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-filter-reset {
            padding: 10px 25px;
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
        }

        .btn-filter-reset:hover {
            background: #e2e8f0;
        }

        /* جدول المعاملات */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
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
            font-size: 12px;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
            white-space: nowrap;
        }

        table td {
            padding: 12px 15px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
            color: #2d3748;
            vertical-align: middle;
        }

        table tbody tr {
            transition: all 0.3s;
        }

        table tbody tr:hover {
            background: #f7fafc;
        }

        .transaction-type {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        .transaction-type.income {
            background: #c6f6d5;
            color: #22543d;
        }

        .transaction-type.expense {
            background: #fed7d7;
            color: #9b2c2c;
        }

        .transaction-type.general {
            background: #e9d8fd;
            color: #553c9a;
        }

        .amount-income {
            color: #38a169;
            font-weight: 700;
        }

        .amount-expense {
            color: #e53e3e;
            font-weight: 700;
        }

        .status-badge {
            padding: 3px 12px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge.paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge.pending {
            background: #fefcbf;
            color: #975a16;
        }

        .status-badge.cancelled {
            background: #fed7d7;
            color: #9b2c2c;
        }

        .table-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }

        .table-actions a {
            padding: 4px 10px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 11px;
            font-weight: 500;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 3px;
        }

        .btn-view-trans {
            background: #ebf4ff;
            color: #3182ce;
        }

        .btn-view-trans:hover {
            background: #bee3f8;
        }

        .btn-edit-trans {
            background: #edf2f7;
            color: #2d3748;
        }

        .btn-edit-trans:hover {
            background: #e2e8f0;
        }

        .btn-delete-trans {
            background: #fff5f5;
            color: #e53e3e;
            border: none;
            cursor: pointer;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-delete-trans:hover {
            background: #fed7d7;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
        }

        .empty-state i {
            font-size: 64px;
            color: #cbd5e0;
            margin-bottom: 20px;
        }

        .empty-state h3 {
            font-size: 24px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #718096;
            margin-bottom: 20px;
        }

        .general-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 2px 10px;
            background: #e9d8fd;
            color: #553c9a;
            border-radius: 12px;
            font-size: 10px;
            font-weight: 600;
        }

        /* مودال تأكيد الحذف */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.5);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            backdrop-filter: blur(4px);
        }

        .modal-overlay.active {
            display: flex;
        }

        .modal-box {
            background: white;
            border-radius: 20px;
            padding: 30px;
            max-width: 450px;
            width: 90%;
            text-align: center;
            animation: modalFade 0.3s ease;
        }

        @keyframes modalFade {
            from {
                opacity: 0;
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        .modal-box i {
            font-size: 48px;
            color: #e53e3e;
            margin-bottom: 15px;
        }

        .modal-box h3 {
            font-size: 20px;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .modal-box p {
            color: #718096;
            margin-bottom: 20px;
            line-height: 1.8;
        }

        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-box .modal-actions button,
        .modal-box .modal-actions a {
            padding: 10px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            text-decoration: none;
        }

        .btn-cancel-modal {
            background: #edf2f7;
            color: #2d3748;
        }

        .btn-cancel-modal:hover {
            background: #e2e8f0;
        }

        .btn-confirm-delete {
            background: #e53e3e;
            color: white;
        }

        .btn-confirm-delete:hover {
            background: #c53030;
            transform: scale(1.02);
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .header-actions {
                flex-direction: column;
            }

            .btn-add {
                justify-content: center;
            }

            .stats-mini {
                grid-template-columns: 1fr 1fr;
            }

            .filter-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-actions {
                flex-direction: column;
            }

            .btn-filter-apply,
            .btn-filter-reset {
                justify-content: center;
            }

            table td, table th {
                padding: 8px 10px;
                font-size: 12px;
            }

            .modal-box {
                padding: 20px;
                width: 95%;
            }
        }

        @media (max-width: 480px) {
            .stats-mini {
                grid-template-columns: 1fr;
            }

            .filter-grid {
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
                    <li class="nav-item active">
                        <a href="index.php">
                            <i class="fas fa-exchange-alt"></i>
                            <span>جميع المعاملات</span>
                            <span class="badge"><?php echo $total_transactions; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="income.php">
                            <i class="fas fa-arrow-up text-success"></i>
                            <span>الإيرادات</span>
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
                    <h1>جميع المعاملات</h1>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="بحث في المعاملات..." id="searchInput">
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
                        <i class="fas fa-exchange-alt" style="color: #667eea;"></i>
                        جميع المعاملات المالية
                        <span style="font-size: 14px; color: #718096; font-weight: 400;">
                            (<?php echo $total_transactions; ?> معاملة)
                            <?php if ($general_count > 0): ?>
                            <span style="font-size: 12px; color: #9f7aea; font-weight: 500;">
                                <i class="fas fa-building"></i>
                                <?php echo $general_count; ?> عامة
                            </span>
                            <?php endif; ?>
                        </span>
                    </h2>
                    <div class="header-actions">
                        <a href="income.php" class="btn-add btn-add-income">
                            <i class="fas fa-plus"></i>
                            <i class="fas fa-arrow-up"></i>
                            إيراد جديد
                        </a>
                        <a href="expense.php" class="btn-add btn-add-expense">
                            <i class="fas fa-plus"></i>
                            <i class="fas fa-arrow-down"></i>
                            مصروف جديد
                        </a>
                    </div>
                </div>

                <!-- رسائل التنبيه -->
                <?php if ($success_message): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success_message; ?>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <?php if ($error_message): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error_message; ?>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <!-- إحصائيات سريعة -->
                <div class="stats-mini">
                    <div class="stat-box income">
                        <i class="fas fa-arrow-up"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_income); ?></div>
                            <div class="label">إجمالي الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-box expense">
                        <i class="fas fa-arrow-down"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_expenses); ?></div>
                            <div class="label">إجمالي المصروفات</div>
                        </div>
                    </div>
                    <div class="stat-box balance">
                        <i class="fas fa-wallet"></i>
                        <div class="info">
                            <div class="number" style="color: <?php echo $balance >= 0 ? '#38a169' : '#e53e3e'; ?>;">
                                <?php echo formatLibyanCurrency($balance); ?>
                            </div>
                            <div class="label">الرصيد</div>
                        </div>
                    </div>
                    <div class="stat-box paid">
                        <i class="fas fa-check-circle"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_paid); ?></div>
                            <div class="label">مدفوع</div>
                        </div>
                    </div>
                    <div class="stat-box pending">
                        <i class="fas fa-clock"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_pending); ?></div>
                            <div class="label">معلق</div>
                        </div>
                    </div>
                    <div class="stat-box general">
                        <i class="fas fa-building"></i>
                        <div class="info">
                            <div class="number"><?php echo $general_count; ?></div>
                            <div class="label">معاملات عامة</div>
                        </div>
                    </div>
                </div>

                <!-- فلتر متقدم -->
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        فلترة المعاملات
                    </div>
                    <form method="GET" id="filterForm">
                        <div class="filter-grid">
                            <div class="filter-group">
                                <label for="filterSchool">المدرسة</label>
                                <select id="filterSchool" name="school_id">
                                    <option value="">جميع المدارس</option>
                                    <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterStage">المرحلة</label>
                                <select id="filterStage" name="stage_id">
                                    <option value="">جميع المراحل</option>
                                    <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>" <?php echo $stage_id == $stage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterStudent">الطالب</label>
                                <select id="filterStudent" name="student_id">
                                    <option value="">جميع الطلاب</option>
                                    <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" <?php echo $student_id == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterType">النوع</label>
                                <select id="filterType" name="type">
                                    <option value="">الكل</option>
                                    <option value="income" <?php echo $type == 'income' ? 'selected' : ''; ?>>إيراد</option>
                                    <option value="expense" <?php echo $type == 'expense' ? 'selected' : ''; ?>>مصروف</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterStatus">الحالة</label>
                                <select id="filterStatus" name="status">
                                    <option value="">الكل</option>
                                    <option value="paid" <?php echo $status == 'paid' ? 'selected' : ''; ?>>مدفوع</option>
                                    <option value="pending" <?php echo $status == 'pending' ? 'selected' : ''; ?>>معلق</option>
                                    <option value="cancelled" <?php echo $status == 'cancelled' ? 'selected' : ''; ?>>ملغي</option>
                                </select>
                            </div>

                            <div class="filter-group">
                                <label for="filterDateFrom">من تاريخ</label>
                                <input type="date" id="filterDateFrom" name="date_from" value="<?php echo $date_from; ?>">
                            </div>

                            <div class="filter-group">
                                <label for="filterDateTo">إلى تاريخ</label>
                                <input type="date" id="filterDateTo" name="date_to" value="<?php echo $date_to; ?>">
                            </div>
                        </div>

                        <div class="filter-actions">
                            <button type="submit" class="btn-filter-apply">
                                <i class="fas fa-search"></i>
                                تطبيق الفلتر
                            </button>
                            <a href="index.php" class="btn-filter-reset">
                                <i class="fas fa-times"></i>
                                إلغاء الفلتر
                            </a>
                        </div>
                    </form>
                </div>

                <!-- جدول المعاملات -->
                <div class="table-container">
                    <div class="table-scroll">
                        <?php if ($total_transactions > 0): ?>
                        <table id="transactionsTable">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>النوع</th>
                                    <th>الطالب</th>
                                    <th>المدرسة</th>
                                    <th>المرحلة</th>
                                    <th>المبلغ</th>
                                    <th>الوصف</th>
                                    <th>التاريخ</th>
                                    <th>طريقة الدفع</th>
                                    <th>الحالة</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($transactions as $index => $t): 
                                    $is_general = ($t['student_id'] == 0 || $t['student_id'] === null);
                                ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <?php if ($is_general): ?>
                                        <span class="transaction-type <?php echo $t['type']; ?>">
                                            <i class="fas fa-<?php echo $t['type'] == 'income' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            <?php echo $t['type'] == 'income' ? 'إيراد' : 'مصروف'; ?>
                                            <span class="general-badge">
                                                <i class="fas fa-building"></i> عام
                                            </span>
                                        </span>
                                        <?php else: ?>
                                        <span class="transaction-type <?php echo $t['type']; ?>">
                                            <i class="fas fa-<?php echo $t['type'] == 'income' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            <?php echo $t['type'] == 'income' ? 'إيراد' : 'مصروف'; ?>
                                        </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($is_general): ?>
                                        <span style="color: #9f7aea; font-weight: 500;">
                                            <i class="fas fa-building"></i> عام
                                        </span>
                                        <?php else: ?>
                                        <a href="../students/view.php?id=<?php echo $t['student_id']; ?>" style="color: #667eea; text-decoration: none; font-weight: 500;">
                                            <?php echo htmlspecialchars($t['student_name']); ?>
                                        </a>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $is_general ? '<span style="color: #a0aec0;">---</span>' : htmlspecialchars($t['school_name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['stage_name'] ?? '---'); ?></td>
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
                                        <span class="status-badge <?php echo $t['status']; ?>">
                                            <?php 
                                            $statusLabels = [
                                                'paid' => 'مدفوع',
                                                'pending' => 'معلق',
                                                'cancelled' => 'ملغي'
                                            ];
                                            echo $statusLabels[$t['status']] ?? $t['status'];
                                            ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="view.php?id=<?php echo $t['id']; ?>" class="btn-view-trans" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $t['id']; ?>" class="btn-edit-trans" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn-delete-trans" title="حذف" onclick="confirmDelete(<?php echo $t['id']; ?>, '<?php echo addslashes($t['description'] ?? 'معاملة'); ?>')">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-exchange-alt"></i>
                            <h3>لا توجد معاملات</h3>
                            <p>لم يتم تسجيل أي معاملات مالية بعد</p>
                            <div style="display: flex; gap: 10px; justify-content: center; flex-wrap: wrap;">
                                <a href="income.php" class="btn-add btn-add-income" style="display: inline-flex;">
                                    <i class="fas fa-plus"></i>
                                    <i class="fas fa-arrow-up"></i>
                                    إضافة إيراد
                                </a>
                                <a href="expense.php" class="btn-add btn-add-expense" style="display: inline-flex;">
                                    <i class="fas fa-plus"></i>
                                    <i class="fas fa-arrow-down"></i>
                                    إضافة مصروف
                                </a>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- مودال تأكيد الحذف -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>تأكيد الحذف</h3>
            <p id="deleteMessage">هل أنت متأكد من حذف هذه المعاملة؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                لا يمكن استعادة المعاملة بعد الحذف
            </p>
            <div class="modal-actions">
                <button class="btn-cancel-modal" onclick="closeModal()">إلغاء</button>
                <a href="#" id="confirmDeleteBtn" class="btn-confirm-delete">
                    نعم، حذف
                </a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // ===== تأكيد الحذف مع عرض وصف المعاملة =====
        function confirmDelete(id, description) {
            const modal = document.getElementById('deleteModal');
            const confirmBtn = document.getElementById('confirmDeleteBtn');
            const message = document.getElementById('deleteMessage');
            
            // تحديث رابط التأكيد
            confirmBtn.href = 'delete.php?id=' + id;
            
            // عرض وصف المعاملة
            if (description && description !== 'معاملة') {
                message.innerHTML = 'هل أنت متأكد من حذف هذه المعاملة؟<br><strong style="color: #2d3748;">"' + description + '"</strong>';
            } else {
                message.innerHTML = 'هل أنت متأكد من حذف هذه المعاملة؟';
            }
            
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // ===== إغلاق المودال عند الضغط على ESC =====
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // ===== إغلاق المودال عند الضغط خارجها =====
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // ===== البحث المباشر في الجدول =====
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const value = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#transactionsTable tbody tr');
            let visible = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                if (text.includes(value)) {
                    row.style.display = '';
                    visible++;
                } else {
                    row.style.display = 'none';
                }
            });
        });

        // ===== تحديث قائمة المراحل والطلاب عند تغيير المدرسة =====
        document.getElementById('filterSchool').addEventListener('change', function() {
            const schoolId = this.value;
            const stageSelect = document.getElementById('filterStage');
            const studentSelect = document.getElementById('filterStudent');
            
            // إعادة تعيين قائمة المراحل
            stageSelect.innerHTML = '<option value="">جميع المراحل</option>';
            studentSelect.innerHTML = '<option value="">جميع الطلاب</option>';
            
            if (schoolId) {
                // جلب المراحل عبر AJAX
                fetch(`../../api/get_stages.php?school_id=${schoolId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(stage => {
                            const option = document.createElement('option');
                            option.value = stage.id;
                            option.textContent = stage.name;
                            stageSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('خطأ في جلب المراحل:', error));
                
                // جلب الطلاب عبر AJAX
                fetch(`../../api/get_students.php?school_id=${schoolId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(student => {
                            const option = document.createElement('option');
                            option.value = student.id;
                            option.textContent = student.name;
                            studentSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('خطأ في جلب الطلاب:', error));
            }
        });

        // ===== إخفاء رسائل التنبيه تلقائياً =====
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 4000);
            });
        }, 1000);
    </script>
</body>
</html>