<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف الطالب
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات الطالب
$stmt = $pdo->prepare("
    SELECT st.*, 
           sc.name as school_name,
           sg.name as current_stage_name,
           sg.fee_amount as current_fee,
           sg.order_number as current_order
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

// جلب جميع مراحل المدرسة مرتبة حسب order_number
$stmt = $pdo->prepare("
    SELECT * FROM stages 
    WHERE school_id = ?
    ORDER BY order_number ASC, fee_amount ASC
");
$stmt->execute([$student['school_id']]);
$all_stages = $stmt->fetchAll();

// تصفية المراحل: عرض فقط المراحل التي تأتي بعد المرحلة الحالية (أعلى منها)
$available_stages = [];
$found_current = false;
$current_order = $student['current_order'] ?? 0;

foreach ($all_stages as $stage) {
    // إذا كانت هذه هي المرحلة الحالية، نبدأ في إضافة المراحل التالية
    if ($stage['id'] == $student['current_stage_id']) {
        $found_current = true;
        continue;
    }
    // إذا وجدنا المرحلة الحالية، نضيف المراحل التالية فقط
    if ($found_current) {
        $available_stages[] = $stage;
    }
}

// إذا لم يتم العثور على المرحلة الحالية (مثلاً الطالب ليس لديه مرحلة)، نعرض جميع المراحل
if (!$found_current) {
    $available_stages = $all_stages;
}

// جلب سجل الترقيات السابقة
$stmt = $pdo->prepare("
    SELECT p.*, 
           fs.name as from_stage_name, 
           ts.name as to_stage_name 
    FROM promotions p
    LEFT JOIN stages fs ON p.from_stage_id = fs.id
    LEFT JOIN stages ts ON p.to_stage_id = ts.id
    WHERE p.student_id = ?
    ORDER BY p.promotion_date DESC
");
$stmt->execute([$id]);
$promotions = $stmt->fetchAll();

// معالجة الترقية
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $to_stage_id = (int)$_POST['to_stage_id'];
    $notes = trim($_POST['notes']);
    
    $errors = [];
    
    // التحقق من صحة البيانات
    if (empty($to_stage_id) || $to_stage_id <= 0) {
        $errors[] = "يرجى اختيار المرحلة الجديدة";
    }
    
    // التحقق من أن المرحلة المختارة مختلفة عن المرحلة الحالية
    if ($to_stage_id == $student['current_stage_id']) {
        $errors[] = "المرحلة المختارة هي نفس المرحلة الحالية";
    }
    
    if (empty($errors)) {
        try {
            // بدء المعاملة
            $pdo->beginTransaction();
            
            // جلب معلومات المرحلة الجديدة
            $stmt = $pdo->prepare("SELECT name, fee_amount, order_number FROM stages WHERE id = ?");
            $stmt->execute([$to_stage_id]);
            $new_stage = $stmt->fetch();
            
            // تسجيل الترقية في جدول promotions
            $stmt = $pdo->prepare("
                INSERT INTO promotions (student_id, from_stage_id, to_stage_id, promotion_date, notes) 
                VALUES (?, ?, ?, CURDATE(), ?)
            ");
            $stmt->execute([$id, $student['current_stage_id'], $to_stage_id, $notes]);
            
            // تحديث مرحلة الطالب
            $stmt = $pdo->prepare("UPDATE students SET current_stage_id = ? WHERE id = ?");
            $stmt->execute([$to_stage_id, $id]);
            
            $pdo->commit();
            
            $success = "تم ترقية الطالب بنجاح من " . $student['current_stage_name'] . " إلى " . $new_stage['name'];
            
            // تحديث بيانات الطالب
            $student['current_stage_id'] = $to_stage_id;
            $student['current_stage_name'] = $new_stage['name'];
            $student['current_fee'] = $new_stage['fee_amount'];
            $student['current_order'] = $new_stage['order_number'];
            
            // إعادة تحميل المراحل المتاحة (بعد الترقية)
            // جلب جميع مراحل المدرسة مرة أخرى
            $stmt = $pdo->prepare("
                SELECT * FROM stages 
                WHERE school_id = ?
                ORDER BY order_number ASC, fee_amount ASC
            ");
            $stmt->execute([$student['school_id']]);
            $all_stages = $stmt->fetchAll();
            
            // تصفية المراحل مرة أخرى
            $available_stages = [];
            $found_current = false;
            foreach ($all_stages as $stage) {
                if ($stage['id'] == $to_stage_id) {
                    $found_current = true;
                    continue;
                }
                if ($found_current) {
                    $available_stages[] = $stage;
                }
            }
            
            // إعادة تحميل سجل الترقيات
            $stmt = $pdo->prepare("
                SELECT p.*, 
                       fs.name as from_stage_name, 
                       ts.name as to_stage_name 
                FROM promotions p
                LEFT JOIN stages fs ON p.from_stage_id = fs.id
                LEFT JOIN stages ts ON p.to_stage_id = ts.id
                WHERE p.student_id = ?
                ORDER BY p.promotion_date DESC
            ");
            $stmt->execute([$id]);
            $promotions = $stmt->fetchAll();
            
        } catch(PDOException $e) {
            $pdo->rollBack();
            $errors[] = "حدث خطأ أثناء الترقية: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات الطالب
$stmt = $pdo->prepare("SELECT COUNT(*) as total FROM transactions WHERE student_id = ?");
$stmt->execute([$id]);
$transaction_count = $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT SUM(amount) FROM transactions WHERE student_id = ? AND type = 'income' AND status = 'paid'");
$stmt->execute([$id]);
$total_income = $stmt->fetchColumn() ?: 0;

// جلب جميع مراحل الطالب (للعرض)
$stmt = $pdo->prepare("
    SELECT sg.* 
    FROM stages sg
    JOIN students st ON st.school_id = sg.school_id
    WHERE st.id = ?
    ORDER BY sg.order_number ASC
");
$stmt->execute([$id]);
$student_stages = $stmt->fetchAll();

// تحديد موقع المرحلة الحالية بين المراحل
$current_index = -1;
foreach ($student_stages as $index => $stage) {
    if ($stage['id'] == $student['current_stage_id']) {
        $current_index = $index;
        break;
    }
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ترقية طالب - المبتكر المالي</title>
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

        .btn-view {
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

        .btn-view:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
            border-top: 5px solid #f6ad55;
        }

        .form-container .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .form-container .form-title i {
            color: #f6ad55;
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
            border-color: #f6ad55;
            box-shadow: 0 0 0 3px rgba(246, 173, 85, 0.1);
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
            background: linear-gradient(135deg, #f6ad55, #ed8936);
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
            box-shadow: 0 10px 20px rgba(237, 137, 54, 0.3);
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

        /* بطاقة معلومات الطالب */
        .student-info-card {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .student-info-card .info-item {
            text-align: center;
        }

        .student-info-card .info-item .number {
            font-size: 22px;
            font-weight: 700;
            color: #2d3748;
        }

        .student-info-card .info-item .label {
            font-size: 13px;
            color: #718096;
            display: block;
            margin-top: 3px;
        }

        .student-info-card .info-item .number.current-stage {
            color: #667eea;
        }

        .student-info-card .info-item .number.income {
            color: #38a169;
        }

        .stage-progress {
            background: white;
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .stage-progress .progress-title {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .stage-progress .progress-title i {
            color: #667eea;
            margin-left: 5px;
        }

        .stage-progress .stage-flow {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            padding: 10px;
            background: #f7fafc;
            border-radius: 8px;
        }

        .stage-progress .stage-step {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 6px 15px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 500;
        }

        .stage-progress .stage-step.completed {
            background: #c6f6d5;
            color: #22543d;
        }

        .stage-progress .stage-step.completed i {
            color: #38a169;
        }

        .stage-progress .stage-step.current {
            background: #bee3f8;
            color: #2a4365;
            border: 2px solid #3182ce;
        }

        .stage-progress .stage-step.current i {
            color: #3182ce;
        }

        .stage-progress .stage-step.upcoming {
            background: #edf2f7;
            color: #718096;
        }

        .stage-progress .stage-step.upcoming i {
            color: #a0aec0;
        }

        .stage-progress .stage-arrow {
            color: #cbd5e0;
            font-size: 18px;
        }

        /* سجل الترقيات */
        .promotions-history {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-top: 25px;
        }

        .promotions-history .history-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .promotions-history .history-title i {
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

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }

            .page-header .header-actions {
                flex-direction: column;
            }

            .btn-back, .btn-view {
                justify-content: center;
            }

            .form-container {
                padding: 20px;
            }

            .form-actions {
                flex-direction: column;
            }

            .student-info-card {
                grid-template-columns: 1fr 1fr;
            }

            .stage-progress .stage-flow {
                flex-direction: column;
                align-items: stretch;
            }

            .stage-progress .stage-arrow {
                transform: rotate(90deg);
                text-align: center;
            }
        }

        @media (max-width: 480px) {
            .student-info-card {
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
                    <h1>ترقية طالب</h1>
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
                        <i class="fas fa-arrow-up" style="color: #f6ad55;"></i>
                        ترقية: <?php echo htmlspecialchars($student['name']); ?>
                    </h2>
                    <div class="header-actions">
                        <a href="view.php?id=<?php echo $student['id']; ?>" class="btn-view">
                            <i class="fas fa-eye"></i>
                            عرض الطالب
                        </a>
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة للقائمة
                        </a>
                    </div>
                </div>

                <!-- رسائل التنبيه -->
                <?php if (isset($success)): ?>
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

                <!-- بطاقة معلومات الطالب -->
                <div class="student-info-card">
                    <div class="info-item">
                        <span class="number current-stage"><?php echo htmlspecialchars($student['current_stage_name'] ?? 'غير محدد'); ?></span>
                        <span class="label">📚 المرحلة الحالية</span>
                    </div>
                    <div class="info-item">
                        <span class="number"><?php echo formatLibyanCurrency($student['current_fee'] ?? 0); ?></span>
                        <span class="label">💰 رسوم المرحلة الحالية</span>
                    </div>
                    <div class="info-item">
                        <span class="number income"><?php echo formatLibyanCurrency($total_income); ?></span>
                        <span class="label">💰 إجمالي الإيرادات</span>
                    </div>
                    <div class="info-item">
                        <span class="number"><?php echo $transaction_count; ?></span>
                        <span class="label">📊 عدد المعاملات</span>
                    </div>
                </div>

                <!-- عرض تقدم الطالب في المراحل -->
                <?php if (!empty($student_stages)): ?>
                <div class="stage-progress">
                    <div class="progress-title">
                        <i class="fas fa-road"></i>
                        مسار الطالب الدراسي
                    </div>
                    <div class="stage-flow">
                        <?php foreach ($student_stages as $index => $stage): 
                            $is_current = $stage['id'] == $student['current_stage_id'];
                            $is_completed = $index < $current_index;
                            $is_upcoming = $index > $current_index;
                        ?>
                        <?php if ($index > 0): ?>
                        <span class="stage-arrow">
                            <i class="fas fa-arrow-left"></i>
                        </span>
                        <?php endif; ?>
                        <span class="stage-step <?php echo $is_completed ? 'completed' : ($is_current ? 'current' : 'upcoming'); ?>">
                            <i class="fas <?php echo $is_completed ? 'fa-check-circle' : ($is_current ? 'fa-circle' : 'fa-circle-o'); ?>"></i>
                            <?php echo htmlspecialchars($stage['name']); ?>
                            <?php if ($is_current): ?>
                            <span style="font-size: 11px;">(الحالية)</span>
                            <?php endif; ?>
                        </span>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- نموذج الترقية -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-arrow-up"></i>
                        ترقية الطالب
                    </div>
                    <div class="form-subtitle">
                        قم باختيار المرحلة الجديدة للطالب
                    </div>

                    <form method="POST" id="promoteForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label for="current_stage">
                                المرحلة الحالية
                            </label>
                            <input type="text" id="current_stage" 
                                   value="<?php echo htmlspecialchars($student['current_stage_name'] ?? 'غير محدد'); ?>" 
                                   disabled style="background: #f7fafc; cursor: not-allowed;">
                            <div class="help-text">المرحلة التي يدرس فيها الطالب حالياً</div>
                        </div>

                        <div class="form-group">
                            <label for="to_stage_id">
                                المرحلة الجديدة
                                <span class="required">*</span>
                            </label>
                            <select id="to_stage_id" name="to_stage_id" required>
                                <option value="">-- اختر المرحلة الجديدة --</option>
                                <?php foreach ($available_stages as $stage): ?>
                                <option value="<?php echo $stage['id']; ?>">
                                    <?php echo htmlspecialchars($stage['name']); ?>
                                    (<?php echo formatLibyanCurrency($stage['fee_amount']); ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">
                                <?php if (count($available_stages) > 0): ?>
                                المراحل المتاحة للترقية (المراحل الأعلى من المرحلة الحالية)
                                <?php else: ?>
                                <span style="color: #e53e3e;">لا توجد مراحل متاحة للترقية. الطالب في أعلى مرحلة.</span>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="notes">ملاحظات الترقية</label>
                            <textarea id="notes" name="notes" 
                                      placeholder="أدخل أي ملاحظات إضافية حول الترقية (اختياري)"></textarea>
                            <div class="help-text">ملاحظات إضافية حول عملية الترقية (اختياري)</div>
                        </div>

                        <?php if (count($available_stages) == 0): ?>
                        <div class="alert alert-error" style="margin-top: 15px;">
                            <i class="fas fa-exclamation-circle"></i>
                            لا توجد مراحل متاحة للترقية. الطالب في أعلى مرحلة دراسية.
                        </div>
                        <?php endif; ?>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn" <?php echo count($available_stages) == 0 ? 'disabled' : ''; ?>>
                                <i class="fas fa-arrow-up"></i>
                                ترقية الطالب
                            </button>
                            <a href="view.php?id=<?php echo $student['id']; ?>" class="btn-cancel">
                                <i class="fas fa-times"></i>
                                إلغاء
                            </a>
                        </div>
                    </form>
                </div>

                <!-- سجل الترقيات السابقة -->
                <div class="promotions-history">
                    <div class="history-title">
                        <i class="fas fa-history"></i>
                        سجل الترقيات السابقة
                        <span style="font-size: 14px; font-weight: 400; color: #718096;">
                            (<?php echo count($promotions); ?> ترقية)
                        </span>
                    </div>
                    <?php if (count($promotions) > 0): ?>
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
                                    <td><?php echo htmlspecialchars($promotion['from_stage_name'] ?? 'بداية'); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['to_stage_name']); ?></td>
                                    <td><?php echo date('Y-m-d', strtotime($promotion['promotion_date'])); ?></td>
                                    <td><?php echo htmlspecialchars($promotion['notes'] ?? '---'); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-history"></i>
                        <p>لا توجد سجلات ترقية سابقة لهذا الطالب</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // التحقق من صحة النموذج
        function validateForm() {
            const to_stage_id = document.getElementById('to_stage_id').value;
            
            // التحقق من اختيار المرحلة الجديدة
            if (to_stage_id === '') {
                alert('يرجى اختيار المرحلة الجديدة للطالب');
                document.getElementById('to_stage_id').focus();
                return false;
            }
            
            // تأكيد الترقية
            const currentStage = document.getElementById('current_stage').value;
            const newStage = document.getElementById('to_stage_id').options[document.getElementById('to_stage_id').selectedIndex].text;
            
            if (!confirm(`هل أنت متأكد من ترقية الطالب من "${currentStage}" إلى "${newStage}"؟`)) {
                return false;
            }
            
            // تعطيل الزر لمنع النقر المتكرر
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الترقية...';
            
            return true;
        }
        
        // إضافة تأثيرات فورية عند تغيير الحقول
        document.addEventListener('DOMContentLoaded', function() {
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
            
            // إعادة تعيين لون الحدود عند التركيز
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.style.borderColor = '#f6ad55';
                });
                input.addEventListener('blur', function() {
                    if (this.value.trim() === '') {
                        this.style.borderColor = '#e2e8f0';
                    }
                });
            });
        });
        
        // تأكيد الخروج إذا كانت هناك تغييرات غير محفوظة
        let formChanged = false;
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
        
        // إلغاء تأكيد الخروج عند إرسال النموذج
        document.getElementById('promoteForm').addEventListener('submit', function() {
            formChanged = false;
        });
        
        // إظهار رسائل نجاح تلقائية بعد 5 ثواني
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert-success');
            alerts.forEach(alert => {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 500);
                }, 5000);
            });
        }, 1000);
    </script>
</body>
</html>