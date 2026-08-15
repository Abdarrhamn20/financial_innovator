<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب معرف المدرسة من الرابط أو من الجلسة
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;

// إذا كان هناك طلب لإلغاء الفلتر
if (isset($_GET['clear_filter'])) {
    unset($_SESSION['stages_school_filter']);
    $school_id = null;
} 
// إذا تم تمرير school_id في الرابط، حفظه في الجلسة
elseif ($school_id) {
    $_SESSION['stages_school_filter'] = $school_id;
} 
// إذا لم يتم تمرير school_id في الرابط، جلب من الجلسة
elseif (isset($_SESSION['stages_school_filter']) && !isset($_GET['school_id'])) {
    $school_id = $_SESSION['stages_school_filter'];
}

$school_name = '';

// جلب المراحل
$stages = getStages($school_id);

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

// معالجة حذف مرحلة
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM stages WHERE id = ?");
        $stmt->execute([$id]);
        $success = "تم حذف المرحلة بنجاح";
        header("Location: index.php?deleted=1" . ($school_id ? "&school_id=$school_id" : ""));
        exit;
    } catch(PDOException $e) {
        $error = "لا يمكن حذف هذه المرحلة لوجود طلاب مرتبطين بها";
    }
}

// عرض رسالة نجاح الحذف
if (isset($_GET['deleted'])) {
    $success = "تم حذف المرحلة بنجاح";
}

// جلب إحصائيات عامة
$total_stages = count($stages);
$total_fees = 0;
$total_students_in_stages = 0;
$total_income_stages = 0;
$total_expenses_stages = 0;

foreach ($stages as $stage) {
    $total_fees += $stage['fee_amount'];
    
    // حساب عدد الطلاب في كل مرحلة
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE current_stage_id = ?");
    $stmt->execute([$stage['id']]);
    $stage['student_count'] = $stmt->fetchColumn();
    $total_students_in_stages += $stage['student_count'];
    
    // حساب إيرادات المرحلة (المدفوعة)
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE stage_id = ? AND type = 'income' AND status = 'paid'");
    $stmt->execute([$stage['id']]);
    $stage['total_income'] = $stmt->fetchColumn() ?: 0;
    $total_income_stages += $stage['total_income'];
    
    // حساب مصروفات المرحلة (المدفوعة)
    $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE stage_id = ? AND type = 'expense' AND status = 'paid'");
    $stmt->execute([$stage['id']]);
    $stage['total_expenses'] = $stmt->fetchColumn() ?: 0;
    $total_expenses_stages += $stage['total_expenses'];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المراحل الدراسية - المبتكر المالي</title>
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

        .btn-filter {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 12px 20px;
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

        .btn-filter:hover {
            border-color: #667eea;
            background: #f7fafc;
        }

        .btn-filter.active {
            border-color: #667eea;
            background: #ebf4ff;
            color: #667eea;
        }

        .btn-filter i {
            color: #667eea;
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
            color: #667eea;
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

        .stats-mini .stat-box .info .number.income {
            color: #38a169;
        }

        .stats-mini .stat-box .info .number.expense {
            color: #e53e3e;
        }

        .stats-mini .stat-box .info .number.balance {
            color: #667eea;
        }

        /* فلتر المدارس المحسّن */
        .filter-container {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 15px;
        }

        .filter-container .filter-group {
            display: flex;
            align-items: center;
            gap: 15px;
            flex-wrap: wrap;
        }

        .filter-container .filter-group label {
            font-weight: 600;
            color: #2d3748;
            font-size: 14px;
            display: flex;
            align-items: center;
            gap: 5px;
        }

        .filter-container .filter-group label i {
            color: #667eea;
        }

        .filter-container .filter-group select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            background: white;
            color: #2d3748;
            min-width: 220px;
            transition: all 0.3s;
        }

        .filter-container .filter-group select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }

        .filter-container .filter-actions {
            display: flex;
            gap: 10px;
        }

        .btn-clear-filter {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 10px 20px;
            background: #fff5f5;
            color: #e53e3e;
            border: 2px solid #fed7d7;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }

        .btn-clear-filter:hover {
            background: #fed7d7;
            border-color: #e53e3e;
        }

        .btn-clear-filter i {
            font-size: 14px;
        }

        .filter-badge {
            display: inline-flex;
            align-items: center;
            gap: 5px;
            padding: 4px 14px;
            background: #ebf4ff;
            color: #3182ce;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        /* شبكة المراحل */
        .stages-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
        }

        .stage-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            border: 1px solid #edf2f7;
            transition: all 0.3s;
            position: relative;
        }

        .stage-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border-color: #667eea;
        }

        .stage-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }

        .stage-card .stage-name {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .stage-card .stage-school {
            font-size: 13px;
            color: #667eea;
            font-weight: 500;
        }

        .stage-card .stage-school i {
            margin-left: 5px;
        }

        .stage-card .fee-amount {
            font-size: 22px;
            font-weight: 800;
            color: #2d3748;
            margin: 10px 0;
            padding: 10px 15px;
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 10px;
            text-align: center;
        }

        .stage-card .fee-amount .currency {
            font-size: 14px;
            color: #718096;
            font-weight: 500;
        }

        .stage-card .student-count {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 5px 15px;
            background: #ebf4ff;
            color: #3182ce;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
        }

        .stage-card .student-count i {
            font-size: 14px;
        }

        /* إحصائيات المرحلة - الإيرادات والمصروفات */
        .stage-stats {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr;
            gap: 8px;
            margin: 10px 0;
            padding: 10px;
            background: #f7fafc;
            border-radius: 10px;
        }

        .stage-stats .stat-item {
            text-align: center;
        }

        .stage-stats .stat-item .stat-number {
            font-size: 16px;
            font-weight: 700;
        }

        .stage-stats .stat-item .stat-number.income {
            color: #38a169;
        }

        .stage-stats .stat-item .stat-number.expense {
            color: #e53e3e;
        }

        .stage-stats .stat-item .stat-number.balance {
            color: #667eea;
        }

        .stage-stats .stat-item .stat-label {
            font-size: 11px;
            color: #718096;
            display: block;
        }

        .stage-card .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 15px;
            flex-wrap: wrap;
        }

        .stage-card .card-actions a {
            flex: 1;
            padding: 8px 12px;
            border-radius: 8px;
            text-decoration: none;
            font-size: 13px;
            font-weight: 500;
            text-align: center;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
        }

        .btn-edit-stage {
            background: #edf2f7;
            color: #2d3748;
        }

        .btn-edit-stage:hover {
            background: #e2e8f0;
        }

        .btn-students-stage {
            background: #f0fff4;
            color: #38a169;
        }

        .btn-students-stage:hover {
            background: #c6f6d5;
        }

        .btn-delete-stage {
            background: #fff5f5;
            color: #e53e3e;
        }

        .btn-delete-stage:hover {
            background: #fed7d7;
        }

        .btn-transactions-stage {
            background: #fefcbf;
            color: #975a16;
        }

        .btn-transactions-stage:hover {
            background: #f6e05e;
        }

        .empty-state {
            text-align: center;
            padding: 60px 20px;
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
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
        }

        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }

        .modal-box .modal-actions button {
            padding: 10px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
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
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .header-actions {
                flex-direction: column;
            }

            .btn-add, .btn-filter {
                justify-content: center;
            }

            .stats-mini {
                grid-template-columns: 1fr 1fr;
            }

            .stages-grid {
                grid-template-columns: 1fr;
            }

            .filter-container {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-container .filter-group {
                flex-direction: column;
                align-items: stretch;
            }

            .filter-container .filter-group select {
                width: 100%;
            }

            .filter-container .filter-actions {
                justify-content: stretch;
            }

            .btn-clear-filter {
                justify-content: center;
            }

            .stage-stats {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .stats-mini {
                grid-template-columns: 1fr;
            }

            .stage-stats {
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
                    <li class="nav-item active">
                        <a href="index.php">
                            <i class="fas fa-layer-group"></i>
                            <span>المراحل الدراسية</span>
                            <span class="badge"><?php echo $total_stages; ?></span>
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
                    <h1><?php echo $school_name ? "مراحل $school_name" : "المراحل الدراسية"; ?></h1>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="بحث في المراحل..." id="searchInput">
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
                        <i class="fas fa-layer-group" style="color: #667eea;"></i>
                        <?php echo $school_name ? "مراحل $school_name" : "قائمة المراحل الدراسية"; ?>
                        <span style="font-size: 14px; color: #718096; font-weight: 400;">
                            (<?php echo $total_stages; ?> مرحلة)
                        </span>
                        <?php if ($school_name): ?>
                        <span class="filter-badge">
                            <i class="fas fa-school"></i>
                            <?php echo htmlspecialchars($school_name); ?>
                        </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="add.php<?php echo $school_id ? "?school_id=$school_id" : ""; ?>" class="btn-add">
                            <i class="fas fa-plus"></i>
                            إضافة مرحلة
                        </a>
                    </div>
                </div>

                <!-- رسائل التنبيه -->
                <?php if (isset($success)): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i>
                    <?php echo $success; ?>
                </div>
                <?php endif; ?>

                <?php if (isset($error)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                </div>
                <?php endif; ?>

                <!-- إحصائيات سريعة -->
                <div class="stats-mini">
                    <div class="stat-box">
                        <i class="fas fa-layer-group"></i>
                        <div class="info">
                            <div class="number"><?php echo $total_stages; ?></div>
                            <div class="label">إجمالي المراحل</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-coins"></i>
                        <div class="info">
                            <div class="number"><?php echo formatLibyanCurrency($total_fees); ?></div>
                            <div class="label">إجمالي الرسوم</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-arrow-up" style="color: #38a169;"></i>
                        <div class="info">
                            <div class="number income"><?php echo formatLibyanCurrency($total_income_stages); ?></div>
                            <div class="label">إجمالي الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-arrow-down" style="color: #e53e3e;"></i>
                        <div class="info">
                            <div class="number expense"><?php echo formatLibyanCurrency($total_expenses_stages); ?></div>
                            <div class="label">إجمالي المصروفات</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-wallet" style="color: #667eea;"></i>
                        <div class="info">
                            <div class="number balance"><?php echo formatLibyanCurrency($total_income_stages - $total_expenses_stages); ?></div>
                            <div class="label">صافي الإيرادات</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-user-graduate"></i>
                        <div class="info">
                            <div class="number"><?php echo $total_students_in_stages; ?></div>
                            <div class="label">إجمالي الطلاب</div>
                        </div>
                    </div>
                </div>

                <!-- فلتر المدارس المحسّن -->
                <div class="filter-container">
                    <div class="filter-group">
                        <label for="filterSchool">
                            <i class="fas fa-school"></i>
                            المدرسة / المعهد:
                        </label>
                        <select id="filterSchool" onchange="filterBySchool(this.value)">
                            <option value="">جميع المدارس والمعاهد</option>
                            <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($school['name']); ?>
                                (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="filter-actions">
                        <?php if ($school_id): ?>
                        <a href="?clear_filter=1" class="btn-clear-filter">
                            <i class="fas fa-times"></i>
                            إلغاء الفلتر
                        </a>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- شبكة المراحل -->
                <?php if ($total_stages > 0): ?>
                <div class="stages-grid" id="stagesGrid">
                    <?php foreach ($stages as $stage): 
                        // حساب عدد الطلاب في هذه المرحلة
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE current_stage_id = ?");
                        $stmt->execute([$stage['id']]);
                        $student_count = $stmt->fetchColumn();
                        
                        // حساب الإيرادات والمصروفات لهذه المرحلة
                        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE stage_id = ? AND type = 'income' AND status = 'paid'");
                        $stmt->execute([$stage['id']]);
                        $stage_income = $stmt->fetchColumn() ?: 0;
                        
                        $stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE stage_id = ? AND type = 'expense' AND status = 'paid'");
                        $stmt->execute([$stage['id']]);
                        $stage_expenses = $stmt->fetchColumn() ?: 0;
                        
                        $stage_balance = $stage_income - $stage_expenses;
                    ?>
                    <div class="stage-card" data-name="<?php echo strtolower($stage['name']); ?>" data-school="<?php echo $stage['school_id']; ?>">
                        <div class="card-header">
                            <div>
                                <h3 class="stage-name"><?php echo htmlspecialchars($stage['name']); ?></h3>
                                <div class="stage-school">
                                    <i class="fas fa-school"></i>
                                    <?php echo htmlspecialchars($stage['school_name']); ?>
                                </div>
                            </div>
                            <div class="student-count">
                                <i class="fas fa-user-graduate"></i>
                                <?php echo $student_count; ?> طالب
                            </div>
                        </div>

                        <div class="fee-amount">
                            <?php echo formatLibyanCurrency($stage['fee_amount']); ?>
                            <span class="currency">رسوم</span>
                        </div>

                        <!-- إحصائيات المرحلة -->
                        <div class="stage-stats">
                            <div class="stat-item">
                                <span class="stat-number income"><?php echo formatLibyanCurrency($stage_income); ?></span>
                                <span class="stat-label">💰 إيرادات</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number expense"><?php echo formatLibyanCurrency($stage_expenses); ?></span>
                                <span class="stat-label">💳 مصروفات</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number balance"><?php echo formatLibyanCurrency($stage_balance); ?></span>
                                <span class="stat-label">📊 صافي</span>
                            </div>
                        </div>

                        <?php if ($stage['description']): ?>
                        <div style="color: #718096; font-size: 13px; margin: 10px 0;">
                            <i class="fas fa-info-circle"></i>
                            <?php echo htmlspecialchars($stage['description']); ?>
                        </div>
                        <?php endif; ?>

                        <div class="card-actions">
                            <a href="edit.php?id=<?php echo $stage['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?>" class="btn-edit-stage">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <a href="../students/index.php?stage_id=<?php echo $stage['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?>" class="btn-students-stage">
                                <i class="fas fa-users"></i> طلاب
                            </a>
                            <a href="../transactions/index.php?stage_id=<?php echo $stage['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?>" class="btn-transactions-stage">
                                <i class="fas fa-exchange-alt"></i> معاملات
                            </a>
                            <a href="#" class="btn-delete-stage" onclick="confirmDelete(<?php echo $stage['id']; ?>, '<?php echo addslashes($stage['name']); ?>')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-layer-group"></i>
                    <h3>لا توجد مراحل دراسية</h3>
                    <p><?php echo $school_name ? "لا توجد مراحل مسجلة في $school_name" : "قم بإضافة أول مرحلة دراسية في النظام"; ?></p>
                    <a href="add.php<?php echo $school_id ? "?school_id=$school_id" : ""; ?>" class="btn-add" style="display: inline-flex; margin-top: 10px;">
                        <i class="fas fa-plus"></i>
                        إضافة مرحلة
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- مودال تأكيد الحذف -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>تأكيد الحذف</h3>
            <p>هل أنت متأكد من حذف المرحلة <strong id="deleteStageName"></strong>؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                سيتم حذف جميع البيانات المرتبطة بهذه المرحلة
            </p>
            <div class="modal-actions">
                <button class="btn-cancel-modal" onclick="closeModal()">إلغاء</button>
                <a href="#" id="confirmDeleteBtn" class="btn-confirm-delete" style="text-decoration: none; padding: 10px 30px; border-radius: 10px; font-weight: 600; background: #e53e3e; color: white;">
                    نعم، حذف
                </a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // تأكيد الحذف
        function confirmDelete(id, name) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('deleteStageName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = 'index.php?delete=' + id + '<?php echo $school_id ? "&school_id=$school_id" : ""; ?>';
            modal.classList.add('active');
        }

        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }

        // إغلاق المودال عند الضغط على ESC
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeModal();
        });

        // إغلاق المودال عند الضغط خارجها
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });

        // البحث المباشر
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const value = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.stage-card');
            let visible = 0;

            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                if (name.includes(value)) {
                    card.style.display = '';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });

            // تحديث عدد النتائج
            const countSpan = document.querySelector('.page-header h2 span');
            if (countSpan) {
                countSpan.textContent = `(${visible} من ${cards.length} مرحلة)`;
            }
        });

        // فلتر المدارس - يحفظ الفلتر في الجلسة
        function filterBySchool(schoolId) {
            const url = new URL(window.location.href);
            if (schoolId) {
                url.searchParams.set('school_id', schoolId);
            } else {
                url.searchParams.delete('school_id');
            }
            window.location.href = url.toString();
        }
    </script>
</body>
</html>