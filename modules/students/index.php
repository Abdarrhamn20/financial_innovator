<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب معرف المدرسة والمرحلة من الرابط
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : null;
$stage_id = isset($_GET['stage_id']) ? (int)$_GET['stage_id'] : null;

// معالجة إلغاء الفلتر
if (isset($_GET['clear_filter'])) {
    // حذف الفلاتر من الجلسة
    unset($_SESSION['students_school_filter']);
    unset($_SESSION['students_stage_filter']);
    // إعادة تعيين المتغيرات
    $school_id = null;
    $stage_id = null;
    // إعادة التوجيه بدون معلمات الفلتر
    header("Location: index.php");
    exit;
}

// إذا تم تمرير school_id في الرابط (من الفلتر)، حفظه في الجلسة
if (isset($_GET['school_id']) && $_GET['school_id'] !== '') {
    $school_id = (int)$_GET['school_id'];
    $_SESSION['students_school_filter'] = $school_id;
} 
// إذا لم يتم تمرير school_id في الرابط، جلب من الجلسة
elseif (isset($_SESSION['students_school_filter']) && !isset($_GET['school_id'])) {
    $school_id = $_SESSION['students_school_filter'];
} 
// إذا كان school_id = 0 أو فارغ، إلغاء الفلتر
elseif (isset($_GET['school_id']) && $_GET['school_id'] === '0') {
    unset($_SESSION['students_school_filter']);
    $school_id = null;
}

// إذا تم تمرير stage_id في الرابط (من الفلتر)، حفظه في الجلسة
if (isset($_GET['stage_id']) && $_GET['stage_id'] !== '') {
    $stage_id = (int)$_GET['stage_id'];
    $_SESSION['students_stage_filter'] = $stage_id;
} 
// إذا لم يتم تمرير stage_id في الرابط، جلب من الجلسة
elseif (isset($_SESSION['students_stage_filter']) && !isset($_GET['stage_id'])) {
    $stage_id = $_SESSION['students_stage_filter'];
}
// إذا كان stage_id = 0 أو فارغ، إلغاء الفلتر
elseif (isset($_GET['stage_id']) && $_GET['stage_id'] === '0') {
    unset($_SESSION['students_stage_filter']);
    $stage_id = null;
}

$school_name = '';
$stage_name = '';

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

// إذا كان هناك فلتر بمرحلة معينة، جلب اسمها
if ($stage_id) {
    $stmt = $pdo->prepare("SELECT name FROM stages WHERE id = ?");
    $stmt->execute([$stage_id]);
    $stage_name = $stmt->fetchColumn();
}

// جلب المراحل للفلتر (بناءً على المدرسة المختارة)
$stages = [];
if ($school_id) {
    $stages = getStages($school_id);
} else {
    $stages = getStages();
}

// جلب الطلاب مع الفلاتر
$students = [];
$sql = "SELECT st.*, sc.name as school_name, sg.name as stage_name 
        FROM students st 
        JOIN schools sc ON st.school_id = sc.id 
        LEFT JOIN stages sg ON st.current_stage_id = sg.id
        WHERE 1=1";

$params = [];

if ($school_id) {
    $sql .= " AND st.school_id = ?";
    $params[] = $school_id;
}

if ($stage_id) {
    $sql .= " AND st.current_stage_id = ?";
    $params[] = $stage_id;
}

$sql .= " ORDER BY st.name";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$students = $stmt->fetchAll();

// معالجة حذف طالب
if (isset($_GET['delete']) && is_numeric($_GET['delete'])) {
    $id = $_GET['delete'];
    try {
        $stmt = $pdo->prepare("DELETE FROM students WHERE id = ?");
        $stmt->execute([$id]);
        $success = "تم حذف الطالب بنجاح";
        // الحفاظ على الفلتر عند الحذف
        $redirect_url = "index.php?deleted=1";
        if ($school_id) $redirect_url .= "&school_id=$school_id";
        if ($stage_id) $redirect_url .= "&stage_id=$stage_id";
        header("Location: $redirect_url");
        exit;
    } catch(PDOException $e) {
        $error = "لا يمكن حذف هذا الطالب لوجود بيانات مرتبطة به";
    }
}

// عرض رسالة نجاح الحذف
if (isset($_GET['deleted'])) {
    $success = "تم حذف الطالب بنجاح";
}

// جلب إحصائيات عامة
$total_students = count($students);
$active_students = 0;
foreach ($students as $s) {
    if ($s['status'] == 'active') $active_students++;
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>الطلاب - المبتكر المالي</title>
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

        /* فلتر متقدم محسّن */
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
            min-width: 180px;
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

        /* جدول الطلاب */
        .table-container {
            background: white;
            border-radius: 16px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            overflow: hidden;
        }

        .table-scroll {
            overflow-x: auto;
            padding: 0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
        }

        table thead {
            background: #f7fafc;
        }

        table th {
            padding: 15px 20px;
            text-align: right;
            font-size: 13px;
            font-weight: 700;
            color: #4a5568;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            border-bottom: 2px solid #edf2f7;
            white-space: nowrap;
        }

        table td {
            padding: 15px 20px;
            border-bottom: 1px solid #edf2f7;
            font-size: 14px;
            color: #2d3748;
            vertical-align: middle;
        }

        table tbody tr {
            transition: all 0.3s;
        }

        table tbody tr:hover {
            background: #f7fafc;
        }

        table tbody tr:last-child td {
            border-bottom: none;
        }

        .student-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 16px;
        }

        .student-info {
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .student-info .details .name {
            font-weight: 600;
            color: #2d3748;
        }

        .student-info .details .code {
            font-size: 12px;
            color: #718096;
        }

        .status-badge {
            padding: 4px 12px;
            border-radius: 20px;
            font-size: 12px;
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

        .table-actions {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }

        .table-actions a {
            padding: 5px 12px;
            border-radius: 6px;
            text-decoration: none;
            font-size: 12px;
            font-weight: 500;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }

        .btn-view {
            background: #ebf4ff;
            color: #3182ce;
        }

        .btn-view:hover {
            background: #bee3f8;
        }

        .btn-promote {
            background: #fefcbf;
            color: #975a16;
        }

        .btn-promote:hover {
            background: #f6e05e;
        }

        .btn-edit-student {
            background: #edf2f7;
            color: #2d3748;
        }

        .btn-edit-student:hover {
            background: #e2e8f0;
        }

        .btn-delete-student {
            background: #fff5f5;
            color: #e53e3e;
        }

        .btn-delete-student:hover {
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

            .filter-container .filter-row {
                flex-direction: column;
            }

            .filter-container .filter-row select {
                width: 100%;
                min-width: unset;
            }

            .filter-container .filter-actions {
                flex-direction: column;
            }

            .btn-clear-filter {
                justify-content: center;
            }

            table td, table th {
                padding: 10px 12px;
                font-size: 12px;
            }

            .table-actions a {
                font-size: 11px;
                padding: 4px 8px;
            }

            .student-info .details .name {
                font-size: 13px;
            }
        }

        @media (max-width: 480px) {
            .stats-mini {
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
                            <span class="badge"><?php echo $total_students; ?></span>
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
                    <h1>
                        <?php 
                        if ($school_name && $stage_name) {
                            echo "طلاب $stage_name - $school_name";
                        } elseif ($school_name) {
                            echo "طلاب $school_name";
                        } elseif ($stage_name) {
                            echo "طلاب $stage_name";
                        } else {
                            echo "جميع الطلاب";
                        }
                        ?>
                    </h1>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="بحث في الطلاب..." id="searchInput">
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
                        <?php 
                        if ($school_name && $stage_name) {
                            echo "طلاب $stage_name - $school_name";
                        } elseif ($school_name) {
                            echo "طلاب $school_name";
                        } elseif ($stage_name) {
                            echo "طلاب $stage_name";
                        } else {
                            echo "قائمة الطلاب";
                        }
                        ?>
                        <span style="font-size: 14px; color: #718096; font-weight: 400;">
                            (<?php echo $total_students; ?> طالب)
                        </span>
                        <?php if ($school_name): ?>
                        <span class="filter-badge">
                            <i class="fas fa-school"></i>
                            <?php echo htmlspecialchars($school_name); ?>
                        </span>
                        <?php endif; ?>
                        <?php if ($stage_name): ?>
                        <span class="filter-badge">
                            <i class="fas fa-layer-group"></i>
                            <?php echo htmlspecialchars($stage_name); ?>
                        </span>
                        <?php endif; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="add.php<?php echo $school_id ? '?school_id=' . $school_id : ''; ?>" class="btn-add">
                            <i class="fas fa-user-plus"></i>
                            إضافة طالب
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
                        <i class="fas fa-users"></i>
                        <div class="info">
                            <div class="number"><?php echo $total_students; ?></div>
                            <div class="label">إجمالي الطلاب</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-user-check"></i>
                        <div class="info">
                            <div class="number"><?php echo $active_students; ?></div>
                            <div class="label">طلاب نشطون</div>
                        </div>
                    </div>
                    <div class="stat-box">
                        <i class="fas fa-school"></i>
                        <div class="info">
                            <div class="number"><?php echo count($schools); ?></div>
                            <div class="label">المدارس</div>
                        </div>
                    </div>
                </div>

                <!-- فلتر متقدم محسّن -->
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-filter"></i>
                        فلترة الطلاب
                    </div>
                    <div class="filter-row">
                        <select id="filterSchool" onchange="filterBySchool(this.value)">
                            <option value="">جميع المدارس</option>
                            <?php foreach ($schools as $school): ?>
                            <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($school['name']); ?>
                                (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <select id="filterStage" onchange="filterByStage(this.value)">
                            <option value="">جميع المراحل</option>
                            <?php foreach ($stages as $stage): ?>
                            <option value="<?php echo $stage['id']; ?>" <?php echo $stage_id == $stage['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($stage['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>

                        <div class="filter-actions">
                            <?php if ($school_id || $stage_id): ?>
                            <a href="?clear_filter=1" class="btn-clear-filter">
                                <i class="fas fa-times"></i>
                                إلغاء الفلتر
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- جدول الطلاب -->
                <div class="table-container">
                    <div class="table-scroll">
                        <?php if ($total_students > 0): ?>
                        <table id="studentsTable">
                            <thead>
                                <tr>
                                    <th>الطالب</th>
                                    <th>المدرسة</th>
                                    <th>المرحلة</th>
                                    <th>رقم الطالب</th>
                                    <th>الحالة</th>
                                    <th>تاريخ التسجيل</th>
                                    <th>الإجراءات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $student): ?>
                                <tr>
                                    <td>
                                        <div class="student-info">
                                            <div class="student-avatar">
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
                                            <div class="details">
                                                <div class="name"><?php echo htmlspecialchars($student['name']); ?></div>
                                                <div class="code"><?php echo htmlspecialchars($student['student_code'] ?? '---'); ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?php echo htmlspecialchars($student['school_name'] ?? '---'); ?></td>
                                    <td><?php echo htmlspecialchars($student['stage_name'] ?? 'غير محدد'); ?></td>
                                    <td><?php echo htmlspecialchars($student['student_code'] ?? '---'); ?></td>
                                    <td>
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
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($student['enrollment_date'] ?? 'now')); ?></td>
                                    <td>
                                        <div class="table-actions">
                                            <a href="view.php?id=<?php echo $student['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?><?php echo $stage_id ? '&stage_id=' . $stage_id : ''; ?>" class="btn-view" title="عرض">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="promote.php?id=<?php echo $student['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?><?php echo $stage_id ? '&stage_id=' . $stage_id : ''; ?>" class="btn-promote" title="ترقية">
                                                <i class="fas fa-arrow-up"></i>
                                            </a>
                                            <a href="edit.php?id=<?php echo $student['id']; ?><?php echo $school_id ? '&school_id=' . $school_id : ''; ?><?php echo $stage_id ? '&stage_id=' . $stage_id : ''; ?>" class="btn-edit-student" title="تعديل">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="#" class="btn-delete-student" title="حذف" onclick="confirmDelete(<?php echo $student['id']; ?>, '<?php echo addslashes($student['name']); ?>')">
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
                            <i class="fas fa-user-graduate"></i>
                            <h3>لا يوجد طلاب</h3>
                            <p>
                                <?php 
                                if ($school_name && $stage_name) {
                                    echo "لا يوجد طلاب مسجلين في $stage_name - $school_name";
                                } elseif ($school_name) {
                                    echo "لا يوجد طلاب مسجلين في $school_name";
                                } elseif ($stage_name) {
                                    echo "لا يوجد طلاب مسجلين في $stage_name";
                                } else {
                                    echo "قم بإضافة أول طالب في النظام";
                                }
                                ?>
                            </p>
                            <a href="add.php<?php echo $school_id ? '?school_id=' . $school_id : ''; ?>" class="btn-add" style="display: inline-flex; margin-top: 10px;">
                                <i class="fas fa-user-plus"></i>
                                إضافة طالب
                            </a>
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
            <p>هل أنت متأكد من حذف الطالب <strong id="deleteStudentName"></strong>؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                سيتم حذف جميع البيانات المرتبطة بهذا الطالب
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
            document.getElementById('deleteStudentName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = 'index.php?delete=' + id + '<?php echo $school_id ? "&school_id=$school_id" : ""; ?><?php echo $stage_id ? "&stage_id=$stage_id" : ""; ?>';
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
            const rows = document.querySelectorAll('#studentsTable tbody tr');
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

        // فلتر المدارس
        function filterBySchool(schoolId) {
            if (schoolId === '') {
                // إلغاء الفلتر
                window.location.href = '?clear_filter=1';
            } else {
                const url = new URL(window.location.href);
                url.searchParams.set('school_id', schoolId);
                url.searchParams.delete('stage_id');
                window.location.href = url.toString();
            }
        }

        // فلتر المراحل
        function filterByStage(stageId) {
            if (stageId === '') {
                // إلغاء فلتر المرحلة فقط
                const url = new URL(window.location.href);
                url.searchParams.delete('stage_id');
                window.location.href = url.toString();
            } else {
                const url = new URL(window.location.href);
                url.searchParams.set('stage_id', stageId);
                window.location.href = url.toString();
            }
        }

        // تحديث قائمة المراحل عند تغيير المدرسة (AJAX)
        document.getElementById('filterSchool').addEventListener('change', function() {
            const schoolId = this.value;
            const stageSelect = document.getElementById('filterStage');
            
            // إعادة تعيين قائمة المراحل
            stageSelect.innerHTML = '<option value="">جميع المراحل</option>';
            
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
            }
        });
    </script>
</body>
</html>