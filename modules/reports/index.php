<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// بدء الجلسة إذا لم تكن قد بدأت
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// جلب معرف المدرسة من الرابط أو من الجلسة
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

// إذا كان هناك طلب لإلغاء الفلتر
if (isset($_GET['clear_filter'])) {
    unset($_SESSION['reports_school_filter']);
    $school_id = null;
    // إعادة التوجيه بدون معلمات الفلتر
    header("Location: index.php");
    exit;
}

// إذا تم تمرير school_id في الرابط (من الفلتر)، حفظه في الجلسة
if (isset($_GET['school_id']) && $_GET['school_id'] !== '') {
    $school_id = (int)$_GET['school_id'];
    $_SESSION['reports_school_filter'] = $school_id;
} 
// إذا لم يتم تمرير school_id في الرابط، جلب من الجلسة
elseif (isset($_SESSION['reports_school_filter']) && !isset($_GET['school_id'])) {
    $school_id = $_SESSION['reports_school_filter'];
}
// إذا كان school_id = 0 أو فارغ، إلغاء الفلتر
elseif (isset($_GET['school_id']) && $_GET['school_id'] === '') {
    unset($_SESSION['reports_school_filter']);
    $school_id = null;
}

$school_name = '';

// جلب المدارس للفلتر
$schools = getSchools();

// إذا كان هناك فلتر بمدرسة معينة، جلب اسمها
if ($school_id) {
    foreach ($schools as $s) {
        if ($s['id'] == $school_id) {
            $school_name = $s['name'];
            break;
        }
    }
}

// بناء شروط الفلتر
$school_condition = "";
$school_params = [];

if ($school_id) {
    $school_condition = " AND s.school_id = ?";
    $school_params[] = $school_id;
}

// جلب البيانات للإحصائيات
$total_schools = count($schools);

// إحصائيات الطلاب (مع فلتر المدرسة)
$sql = "SELECT COUNT(*) as total, 
        SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
        SUM(CASE WHEN status = 'inactive' THEN 1 ELSE 0 END) as inactive,
        SUM(CASE WHEN status = 'graduated' THEN 1 ELSE 0 END) as graduated
        FROM students s WHERE 1=1" . $school_condition;
$stmt = $pdo->prepare($sql);
$stmt->execute($school_params);
$student_stats = $stmt->fetch();

// إحصائيات المعاملات (مع فلتر المدرسة)
$sql = "SELECT 
        SUM(CASE WHEN t.type = 'income' AND t.status = 'paid' THEN t.amount ELSE 0 END) as total_income,
        SUM(CASE WHEN t.type = 'expense' AND t.status = 'paid' THEN t.amount ELSE 0 END) as total_expenses,
        COUNT(CASE WHEN t.type = 'income' AND t.status = 'paid' THEN 1 END) as income_count,
        COUNT(CASE WHEN t.type = 'expense' AND t.status = 'paid' THEN 1 END) as expense_count,
        SUM(CASE WHEN t.type = 'income' AND t.status = 'pending' THEN t.amount ELSE 0 END) as pending_income,
        SUM(CASE WHEN t.type = 'expense' AND t.status = 'pending' THEN t.amount ELSE 0 END) as pending_expenses
        FROM transactions t
        JOIN students s ON t.student_id = s.id
        WHERE 1=1" . $school_condition;
$stmt = $pdo->prepare($sql);
$stmt->execute($school_params);
$transaction_stats = $stmt->fetch();

$total_income = $transaction_stats['total_income'] ?? 0;
$total_expenses = $transaction_stats['total_expenses'] ?? 0;
$balance = $total_income - $total_expenses;

// إحصائيات المراحل (مع فلتر المدرسة)
$sql = "SELECT COUNT(*) as total FROM stages s WHERE 1=1" . $school_condition;
$stmt = $pdo->prepare($sql);
$stmt->execute($school_params);
$total_stages = $stmt->fetchColumn();

// جلب المدارس مع إحصائياتها (مع فلتر المدرسة)
$schools_stats = [];
if ($school_id) {
    // جلب مدرسة محددة فقط
    foreach ($schools as $school) {
        if ($school['id'] == $school_id) {
            $stats = getSchoolStats($school['id']);
            $schools_stats[] = [
                'id' => $school['id'],
                'name' => $school['name'],
                'type' => $school['type'],
                'students' => $stats['total_students'] ?? 0,
                'stages' => $stats['total_stages'] ?? 0,
                'income' => $stats['total_income'] ?? 0,
                'expenses' => $stats['total_expenses'] ?? 0
            ];
            break;
        }
    }
} else {
    // جلب جميع المدارس
    foreach ($schools as $school) {
        $stats = getSchoolStats($school['id']);
        $schools_stats[] = [
            'id' => $school['id'],
            'name' => $school['name'],
            'type' => $school['type'],
            'students' => $stats['total_students'] ?? 0,
            'stages' => $stats['total_stages'] ?? 0,
            'income' => $stats['total_income'] ?? 0,
            'expenses' => $stats['total_expenses'] ?? 0
        ];
    }
}

// جلب المعاملات الشهرية (آخر 12 شهر) مع فلتر المدرسة
$monthly_data = [];
for ($i = 11; $i >= 0; $i--) {
    $month = date('Y-m', strtotime("-$i months"));
    $month_name = date('M Y', strtotime("-$i months"));
    
    $sql = "SELECT 
            SUM(CASE WHEN t.type = 'income' AND t.status = 'paid' THEN t.amount ELSE 0 END) as income,
            SUM(CASE WHEN t.type = 'expense' AND t.status = 'paid' THEN t.amount ELSE 0 END) as expenses
            FROM transactions t
            JOIN students s ON t.student_id = s.id
            WHERE DATE_FORMAT(t.transaction_date, '%Y-%m') = ?" . $school_condition;
    $stmt = $pdo->prepare($sql);
    $params = array_merge([$month], $school_params);
    $stmt->execute($params);
    $data = $stmt->fetch();
    
    $monthly_data[] = [
        'month' => $month_name,
        'income' => $data['income'] ?? 0,
        'expenses' => $data['expenses'] ?? 0
    ];
}

// جلب أحدث المعاملات (مع فلتر المدرسة)
$sql = "SELECT t.*, s.name as student_name, sc.name as school_name 
        FROM transactions t 
        JOIN students s ON t.student_id = s.id 
        JOIN schools sc ON s.school_id = sc.id 
        WHERE 1=1" . $school_condition . "
        ORDER BY t.created_at DESC LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($school_params);
$recent_transactions = $stmt->fetchAll();

// جلب توزيع الطلاب حسب المراحل (مع فلتر المدرسة)
$stage_distribution = [];
if ($school_id) {
    // جلب مراحل مدرسة محددة فقط
    $sql = "SELECT sg.name, COUNT(st.id) as count 
            FROM stages sg 
            LEFT JOIN students st ON st.current_stage_id = sg.id 
            WHERE sg.school_id = ?
            GROUP BY sg.id 
            ORDER BY sg.order_number ASC, sg.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$school_id]);
} else {
    $sql = "SELECT sg.name, COUNT(st.id) as count 
            FROM stages sg 
            LEFT JOIN students st ON st.current_stage_id = sg.id 
            GROUP BY sg.id 
            ORDER BY sg.order_number ASC, sg.name";
    $stmt = $pdo->prepare($sql);
    $stmt->execute();
}
$stage_distribution = $stmt->fetchAll();

// جلب أفضل الطلاب من حيث الإيرادات (مع فلتر المدرسة)
$sql = "SELECT s.id, s.name, s.student_code, 
        SUM(t.amount) as total_income 
        FROM students s 
        JOIN transactions t ON t.student_id = s.id 
        WHERE t.type = 'income' AND t.status = 'paid'" . $school_condition . "
        GROUP BY s.id 
        ORDER BY total_income DESC 
        LIMIT 10";
$stmt = $pdo->prepare($sql);
$stmt->execute($school_params);
$top_students = $stmt->fetchAll();

// حساب نسبة الإيرادات والمصروفات
$income_percentage = $total_income + $total_expenses > 0 ? ($total_income / ($total_income + $total_expenses)) * 100 : 0;
$expense_percentage = $total_income + $total_expenses > 0 ? ($total_expenses / ($total_income + $total_expenses)) * 100 : 0;
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>التقارير والإحصائيات - المبتكر المالي</title>
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

        /* فلتر المدرسة */
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
            min-width: 250px;
            transition: all 0.3s;
            flex: 1;
        }

        .filter-container .filter-row select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-container .filter-actions {
            display: flex;
            gap: 10px;
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

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 12px;
            background: #ebf4ff;
            color: #3182ce;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }

        /* بطاقات الإحصائيات */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
            transition: all 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
        }

        .stat-card .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            color: white;
        }

        .stat-card .stat-icon.blue { background: linear-gradient(135deg, #667eea, #764ba2); }
        .stat-card .stat-icon.green { background: linear-gradient(135deg, #48bb78, #38a169); }
        .stat-card .stat-icon.red { background: linear-gradient(135deg, #fc8181, #e53e3e); }
        .stat-card .stat-icon.gold { background: linear-gradient(135deg, #f6ad55, #ed8936); }
        .stat-card .stat-icon.purple { background: linear-gradient(135deg, #9f7aea, #805ad5); }
        .stat-card .stat-icon.teal { background: linear-gradient(135deg, #38b2ac, #319795); }

        .stat-card .stat-number {
            font-size: 28px;
            font-weight: 800;
            color: #2d3748;
        }

        .stat-card .stat-label {
            font-size: 14px;
            color: #718096;
            margin-top: 5px;
        }

        .stat-card .stat-change {
            font-size: 12px;
            font-weight: 600;
            padding: 3px 10px;
            border-radius: 20px;
            display: inline-block;
            margin-top: 5px;
        }

        .stat-card .stat-change.positive {
            background: #c6f6d5;
            color: #22543d;
        }

        .stat-card .stat-change.negative {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* شبكة التقارير */
        .reports-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .report-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .report-card .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .report-card .card-title i {
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
            padding: 10px 12px;
            text-align: right;
            font-size: 12px;
            font-weight: 700;
            color: #4a5568;
            border-bottom: 2px solid #edf2f7;
            white-space: nowrap;
        }

        table td {
            padding: 10px 12px;
            border-bottom: 1px solid #edf2f7;
            font-size: 13px;
            color: #2d3748;
        }

        table tbody tr:hover {
            background: #f7fafc;
        }

        .amount-income {
            color: #38a169;
            font-weight: 600;
        }

        .amount-expense {
            color: #e53e3e;
            font-weight: 600;
        }

        .bar-chart {
            margin-top: 15px;
        }

        .bar-item {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 8px;
        }

        .bar-item .bar-label {
            min-width: 80px;
            font-size: 12px;
            color: #4a5568;
        }

        .bar-item .bar-track {
            flex: 1;
            height: 20px;
            background: #edf2f7;
            border-radius: 10px;
            overflow: hidden;
            position: relative;
        }

        .bar-item .bar-fill {
            height: 100%;
            border-radius: 10px;
            transition: width 1s ease;
        }

        .bar-item .bar-fill.income {
            background: linear-gradient(135deg, #48bb78, #38a169);
        }

        .bar-item .bar-fill.expense {
            background: linear-gradient(135deg, #fc8181, #e53e3e);
        }

        .bar-item .bar-value {
            min-width: 70px;
            font-size: 12px;
            font-weight: 600;
            text-align: left;
        }

        /* توزيع الطلاب */
        .distribution-chart {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .distribution-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .distribution-item .dist-label {
            min-width: 120px;
            font-size: 13px;
            color: #2d3748;
        }

        .distribution-item .dist-track {
            flex: 1;
            height: 25px;
            background: #edf2f7;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .distribution-item .dist-fill {
            height: 100%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: flex-end;
            padding-right: 10px;
            color: white;
            font-size: 12px;
            font-weight: 600;
            transition: width 1s ease;
        }

        .status-badge-sm {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge-sm.paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge-sm.pending {
            background: #fefcbf;
            color: #975a16;
        }

        .status-badge-sm.cancelled {
            background: #fed7d7;
            color: #9b2c2c;
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

        .report-card.full-width {
            grid-column: 1 / -1;
        }

        @media (max-width: 992px) {
            .reports-grid {
                grid-template-columns: 1fr;
            }

            .report-card.full-width {
                grid-column: 1;
            }
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .stats-grid {
                grid-template-columns: 1fr 1fr;
            }

            .filter-container .filter-row {
                flex-direction: column;
            }

            .filter-container .filter-row select {
                width: 100%;
                min-width: unset;
            }

            .filter-container .filter-actions {
                width: 100%;
                flex-direction: column;
            }

            .btn-filter-apply,
            .btn-filter-reset {
                justify-content: center;
            }

            .distribution-item .dist-label {
                min-width: 80px;
                font-size: 12px;
            }

            .bar-item .bar-label {
                min-width: 60px;
                font-size: 11px;
            }

            .bar-item .bar-value {
                min-width: 50px;
                font-size: 11px;
            }
        }

        @media (max-width: 480px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }

            .stat-card .stat-number {
                font-size: 22px;
            }

            .distribution-item {
                flex-wrap: wrap;
            }

            .distribution-item .dist-label {
                min-width: 100%;
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
                    <li class="nav-item active">
                        <a href="index.php">
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
                    <h1>
                        <?php if ($school_name): ?>
                            تقارير: <?php echo htmlspecialchars($school_name); ?>
                        <?php else: ?>
                            التقارير والإحصائيات العامة
                        <?php endif; ?>
                    </h1>
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
                        <i class="fas fa-chart-bar" style="color: #667eea;"></i>
                        <?php if ($school_name): ?>
                            تقارير: <?php echo htmlspecialchars($school_name); ?>
                            <span class="filter-badge">
                                <i class="fas fa-school"></i>
                                <?php echo htmlspecialchars($school_name); ?>
                            </span>
                        <?php else: ?>
                            التقارير والإحصائيات العامة
                        <?php endif; ?>
                    </h2>
                    <?php if ($school_id): ?>
                    <a href="?clear_filter=1" class="btn-back">
                        <i class="fas fa-arrow-right"></i>
                        عرض جميع التقارير
                    </a>
                    <?php endif; ?>
                </div>

                <!-- فلتر المدرسة -->
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        تصفية حسب المدرسة / المعهد
                    </div>
                    <form method="GET" id="filterForm">
                        <div class="filter-row">
                            <select id="filterSchool" name="school_id">
                                <option value="">جميع المدارس والمعاهد</option>
                                <?php foreach ($schools as $school): ?>
                                <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($school['name']); ?>
                                    (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="filter-actions">
                                <button type="submit" class="btn-filter-apply">
                                    <i class="fas fa-search"></i>
                                    عرض
                                </button>
                                <?php if ($school_id): ?>
                                <a href="?clear_filter=1" class="btn-filter-reset">
                                    <i class="fas fa-times"></i>
                                    إلغاء الفلتر
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- بطاقات الإحصائيات -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="stat-number"><?php echo $school_id ? 1 : $total_schools; ?></div>
                        <div class="stat-label">المدارس والمعاهد</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon purple">
                            <i class="fas fa-layer-group"></i>
                        </div>
                        <div class="stat-number"><?php echo $total_stages; ?></div>
                        <div class="stat-label">المراحل الدراسية</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-number"><?php echo $student_stats['total'] ?? 0; ?></div>
                        <div class="stat-label">إجمالي الطلاب</div>
                        <div class="stat-change positive">
                            <i class="fas fa-check-circle"></i>
                            <?php echo $student_stats['active'] ?? 0; ?> نشط
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon gold">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-number" style="color: <?php echo $balance >= 0 ? '#38a169' : '#e53e3e'; ?>;">
                            <?php echo formatLibyanCurrency($balance); ?>
                        </div>
                        <div class="stat-label">الرصيد الإجمالي</div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-arrow-up"></i>
                        </div>
                        <div class="stat-number"><?php echo formatLibyanCurrency($total_income); ?></div>
                        <div class="stat-label">إجمالي الإيرادات</div>
                        <div class="stat-change positive">
                            <i class="fas fa-arrow-up"></i>
                            <?php echo $transaction_stats['income_count'] ?? 0; ?> معاملة
                        </div>
                    </div>

                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fas fa-arrow-down"></i>
                        </div>
                        <div class="stat-number"><?php echo formatLibyanCurrency($total_expenses); ?></div>
                        <div class="stat-label">إجمالي المصروفات</div>
                        <div class="stat-change negative">
                            <i class="fas fa-arrow-down"></i>
                            <?php echo $transaction_stats['expense_count'] ?? 0; ?> معاملة
                        </div>
                    </div>
                </div>

                <!-- التقارير المتقدمة -->
                <div class="reports-grid">
                    <!-- المدارس -->
                    <div class="report-card">
                        <div class="card-title">
                            <i class="fas fa-school"></i>
                            <?php if ($school_name): ?>
                                تفاصيل <?php echo htmlspecialchars($school_name); ?>
                            <?php else: ?>
                                إحصائيات المدارس والمعاهد
                            <?php endif; ?>
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>المدرسة</th>
                                        <th>النوع</th>
                                        <th>الطلاب</th>
                                        <th>المراحل</th>
                                        <th>الإيرادات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($schools_stats as $school): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($school['name']); ?></td>
                                        <td><?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?></td>
                                        <td><?php echo $school['students']; ?></td>
                                        <td><?php echo $school['stages']; ?></td>
                                        <td class="amount-income"><?php echo formatLibyanCurrency($school['income']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php if (empty($schools_stats)): ?>
                                    <tr>
                                        <td colspan="5" class="empty-state">
                                            <i class="fas fa-school"></i>
                                            لا توجد مدارس مسجلة
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <!-- توزيع الطلاب حسب المراحل -->
                    <div class="report-card">
                        <div class="card-title">
                            <i class="fas fa-users"></i>
                            توزيع الطلاب حسب المراحل
                        </div>
                        <?php if (!empty($stage_distribution)): ?>
                        <div class="distribution-chart">
                            <?php 
                            $max_count = 0;
                            foreach ($stage_distribution as $sd) {
                                if ($sd['count'] > $max_count) $max_count = $sd['count'];
                            }
                            if ($max_count == 0) $max_count = 1;
                            foreach ($stage_distribution as $sd): 
                            ?>
                            <div class="distribution-item">
                                <span class="dist-label"><?php echo htmlspecialchars($sd['name']); ?></span>
                                <div class="dist-track">
                                    <div class="dist-fill" style="width: <?php echo ($sd['count'] / $max_count) * 100; ?>%;">
                                        <?php echo $sd['count']; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-users"></i>
                            <p>لا توجد مراحل مسجلة</p>
                        </div>
                        <?php endif; ?>
                    </div>

                    <!-- المعاملات الشهرية -->
                    <div class="report-card">
                        <div class="card-title">
                            <i class="fas fa-calendar-alt"></i>
                            المعاملات الشهرية (آخر 12 شهر)
                        </div>
                        <div class="bar-chart">
                            <?php 
                            $max_monthly = 0;
                            foreach ($monthly_data as $data) {
                                $max_monthly = max($max_monthly, $data['income'], $data['expenses']);
                            }
                            if ($max_monthly == 0) $max_monthly = 1;
                            foreach ($monthly_data as $data): 
                            ?>
                            <div class="bar-item">
                                <span class="bar-label"><?php echo $data['month']; ?></span>
                                <div class="bar-track">
                                    <div class="bar-fill income" style="width: <?php echo ($data['income'] / $max_monthly) * 100; ?>%;">
                                    </div>
                                </div>
                                <span class="bar-value amount-income">+<?php echo formatLibyanCurrency($data['income']); ?></span>
                            </div>
                            <div class="bar-item">
                                <span class="bar-label"></span>
                                <div class="bar-track">
                                    <div class="bar-fill expense" style="width: <?php echo ($data['expenses'] / $max_monthly) * 100; ?>%;">
                                    </div>
                                </div>
                                <span class="bar-value amount-expense">-<?php echo formatLibyanCurrency($data['expenses']); ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- أفضل الطلاب -->
                    <div class="report-card">
                        <div class="card-title">
                            <i class="fas fa-crown"></i>
                            أفضل الطلاب من حيث الإيرادات
                        </div>
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>#</th>
                                        <th>الطالب</th>
                                        <th>رقم الطالب</th>
                                        <th>الإيرادات</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (!empty($top_students)): ?>
                                    <?php foreach ($top_students as $index => $student): ?>
                                    <tr>
                                        <td><?php echo $index + 1; ?></td>
                                        <td><?php echo htmlspecialchars($student['name']); ?></td>
                                        <td><?php echo htmlspecialchars($student['student_code'] ?? '---'); ?></td>
                                        <td class="amount-income"><?php echo formatLibyanCurrency($student['total_income']); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                    <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="empty-state">
                                            <i class="fas fa-crown"></i>
                                            لا توجد بيانات
                                        </td>
                                    </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- أحدث المعاملات -->
                <div class="report-card full-width">
                    <div class="card-title">
                        <i class="fas fa-history"></i>
                        أحدث المعاملات
                        <span style="font-size: 14px; font-weight: 400; color: #718096;">
                            (آخر 10 معاملات)
                        </span>
                    </div>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>الطالب</th>
                                    <th>المدرسة</th>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recent_transactions)): ?>
                                <?php foreach ($recent_transactions as $index => $t): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td><?php echo htmlspecialchars($t['student_name']); ?></td>
                                    <td><?php echo htmlspecialchars($t['school_name']); ?></td>
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
                                    <td class="<?php echo $t['type'] == 'income' ? 'amount-income' : 'amount-expense'; ?>">
                                        <?php echo $t['type'] == 'income' ? '+' : '-'; ?>
                                        <?php echo formatLibyanCurrency($t['amount']); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($t['transaction_date'])); ?></td>
                                    <td>
                                        <span class="status-badge-sm <?php echo $t['status']; ?>">
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
                                </tr>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <tr>
                                    <td colspan="7" class="empty-state">
                                        <i class="fas fa-exchange-alt"></i>
                                        لا توجد معاملات مسجلة
                                    </td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // تأثير ظهور الأعمدة عند التحميل
        document.addEventListener('DOMContentLoaded', function() {
            const fills = document.querySelectorAll('.bar-fill, .dist-fill');
            fills.forEach(fill => {
                const width = fill.style.width;
                fill.style.width = '0%';
                setTimeout(() => {
                    fill.style.width = width;
                }, 300);
            });
        });

        // تطبيق الفلتر عند تغيير القائمة المنسدلة
        document.getElementById('filterSchool').addEventListener('change', function() {
            if (this.value) {
                document.getElementById('filterForm').submit();
            } else {
                window.location.href = '?clear_filter=1';
            }
        });

        // تحديث الإحصائيات كل دقيقة
        setInterval(function() {
            fetch('get_stats.php' + window.location.search)
                .then(response => response.json())
                .then(data => {
                    // تحديث الأرقام
                })
                .catch(error => console.log('خطأ في تحديث الإحصائيات:', error));
        }, 60000);
    </script>
</body>
</html>