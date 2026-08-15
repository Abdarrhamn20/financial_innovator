<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب قائمة المدارس للاختيار
$schools = getSchools();

// متغيرات لتخزين قيم النموذج
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : '';
$stage_id = '';
$name = '';
$student_code = '';
$birth_date = '';
$phone = '';
$address = '';
$enrollment_date = date('Y-m-d');
$status = 'active';

$errors = [];
$success = '';

// جلب المراحل حسب المدرسة المختارة
$stages = [];
if ($school_id) {
    $stages = getStages($school_id);
}

// معالجة إضافة الطالب
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = (int)$_POST['school_id'];
    $stage_id = !empty($_POST['stage_id']) ? (int)$_POST['stage_id'] : null;
    $name = trim($_POST['name']);
    $student_code = trim($_POST['student_code']);
    $birth_date = $_POST['birth_date'] ?: null;
    $phone = trim($_POST['phone']);
    $address = trim($_POST['address']);
    $enrollment_date = $_POST['enrollment_date'] ?: date('Y-m-d');
    $status = $_POST['status'];
    
    // التحقق من صحة البيانات
    if (empty($school_id) || $school_id <= 0) {
        $errors[] = "يرجى اختيار المدرسة/المعهد";
    }
    
    if (empty($name)) {
        $errors[] = "اسم الطالب مطلوب";
    }
    
    if (empty($student_code)) {
        $errors[] = "رقم الطالب مطلوب";
    }
    
    // التحقق من عدم تكرار رقم الطالب
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM students WHERE student_code = ?");
        $stmt->execute([$student_code]);
        $count = $stmt->fetchColumn();
        if ($count > 0) {
            $errors[] = "رقم الطالب موجود بالفعل";
        }
    }
    
    // التحقق من صحة التاريخ
    if ($birth_date && !strtotime($birth_date)) {
        $errors[] = "تاريخ الميلاد غير صحيح";
    }
    
    if ($enrollment_date && !strtotime($enrollment_date)) {
        $errors[] = "تاريخ التسجيل غير صحيح";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO students 
                (school_id, current_stage_id, name, student_code, birth_date, phone, address, enrollment_date, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            
            $stmt->execute([
                $school_id, 
                $stage_id, 
                $name, 
                $student_code, 
                $birth_date, 
                $phone, 
                $address, 
                $enrollment_date, 
                $status
            ]);
            
            $success = "تم إضافة الطالب بنجاح";
            
            // إعادة تعيين الحقول
            $name = '';
            $student_code = '';
            $birth_date = '';
            $phone = '';
            $address = '';
            $enrollment_date = date('Y-m-d');
            $status = 'active';
            $stage_id = '';
            
            // إعادة تحميل المراحل
            if ($school_id) {
                $stages = getStages($school_id);
            }
            
        } catch(PDOException $e) {
            $errors[] = "حدث خطأ أثناء الإضافة: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات عامة
$stmt = $pdo->query("SELECT COUNT(*) FROM students");
$total_students = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM students WHERE status = 'active'");
$active_students = $stmt->fetchColumn();

$total_schools = count($schools);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة طالب - المبتكر المالي</title>
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

        .form-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 800px;
            margin: 0 auto;
            border-top: 5px solid #667eea;
        }

        .form-container .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
        }

        .form-container .form-title i {
            color: #667eea;
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
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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
            background: linear-gradient(135deg, #667eea, #764ba2);
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
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
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

        /* فلتر المدرسة */
        .filter-container {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 20px;
        }

        .filter-container .filter-title {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
            margin-bottom: 10px;
        }

        .filter-container .filter-title i {
            color: #667eea;
        }

        .filter-container select {
            padding: 10px 15px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-family: 'Tajawal', sans-serif;
            font-size: 14px;
            background: white;
            color: #2d3748;
            width: 100%;
            max-width: 300px;
            transition: all 0.3s;
        }

        .filter-container select:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
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

            .form-actions {
                flex-direction: column;
            }

            .stats-mini {
                grid-template-columns: 1fr 1fr;
            }

            .filter-container select {
                max-width: 100%;
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
                    <h1>إضافة طالب جديد</h1>
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
                        <i class="fas fa-user-plus" style="color: #667eea;"></i>
                        إضافة طالب جديد
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
                            <div class="number"><?php echo $total_schools; ?></div>
                            <div class="label">المدارس والمعاهد</div>
                        </div>
                    </div>
                </div>

                <!-- فلتر المدرسة -->
                <div class="filter-container">
                    <div class="filter-title">
                        <i class="fas fa-school"></i>
                        اختيار المدرسة / المعهد:
                    </div>
                    <select id="filterSchool" onchange="filterBySchool(this.value)">
                        <option value="">اختر المدرسة أو المعهد</option>
                        <?php foreach ($schools as $school): ?>
                        <option value="<?php echo $school['id']; ?>" <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($school['name']); ?>
                            (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                        </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- نموذج الإضافة -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-user-graduate"></i>
                        بيانات الطالب
                    </div>
                    <div class="form-subtitle">
                        قم بإدخال المعلومات الأساسية للطالب الجديد
                    </div>

                    <form method="POST" id="addStudentForm" onsubmit="return validateForm()">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="school_id">
                                    المدرسة / المعهد
                                    <span class="required">*</span>
                                </label>
                                <select id="school_id" name="school_id" required>
                                    <option value="">-- اختر المدرسة أو المعهد --</option>
                                    <?php foreach ($schools as $school): ?>
                                    <option value="<?php echo $school['id']; ?>" 
                                        <?php echo $school_id == $school['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($school['name']); ?>
                                        (<?php echo $school['type'] == 'school' ? 'مدرسة' : 'معهد'; ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">المدرسة أو المعهد التابع له الطالب</div>
                            </div>

                            <div class="form-group">
                                <label for="stage_id">المرحلة الدراسية</label>
                                <select id="stage_id" name="stage_id">
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>" 
                                        <?php echo $stage_id == $stage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                        (<?php echo formatLibyanCurrency($stage['fee_amount']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">المرحلة الدراسية للطالب (اختياري)</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="name">
                                اسم الطالب
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($name); ?>" 
                                   placeholder="أدخل اسم الطالب كاملاً" required>
                            <div class="help-text">الاسم الكامل للطالب</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_code">
                                    رقم الطالب
                                    <span class="required">*</span>
                                </label>
                                <input type="text" id="student_code" name="student_code" 
                                       value="<?php echo htmlspecialchars($student_code); ?>" 
                                       placeholder="مثال: S2024001" required>
                                <div class="help-text">رقم الطالب الفريد في النظام</div>
                            </div>

                            <div class="form-group">
                                <label for="birth_date">تاريخ الميلاد</label>
                                <input type="date" id="birth_date" name="birth_date" 
                                       value="<?php echo $birth_date; ?>">
                                <div class="help-text">تاريخ ميلاد الطالب</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">رقم الهاتف</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($phone); ?>" 
                                       placeholder="مثال: 0912345678">
                                <div class="help-text">رقم هاتف ولي الأمر أو الطالب</div>
                            </div>

                            <div class="form-group">
                                <label for="enrollment_date">تاريخ التسجيل</label>
                                <input type="date" id="enrollment_date" name="enrollment_date" 
                                       value="<?php echo $enrollment_date; ?>">
                                <div class="help-text">تاريخ تسجيل الطالب في النظام</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">العنوان</label>
                            <textarea id="address" name="address" 
                                      placeholder="أدخل عنوان الطالب"><?php echo htmlspecialchars($address); ?></textarea>
                            <div class="help-text">العنوان التفصيلي للطالب</div>
                        </div>

                        <div class="form-group">
                            <label for="status">
                                الحالة
                                <span class="required">*</span>
                            </label>
                            <select id="status" name="status" required>
                                <option value="active" <?php echo $status == 'active' ? 'selected' : ''; ?>>نشط</option>
                                <option value="inactive" <?php echo $status == 'inactive' ? 'selected' : ''; ?>>غير نشط</option>
                                <option value="graduated" <?php echo $status == 'graduated' ? 'selected' : ''; ?>>متخرج</option>
                            </select>
                            <div class="help-text">حالة الطالب الحالية</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-user-plus"></i>
                                إضافة الطالب
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
        // تحديث قائمة المراحل عند تغيير المدرسة
        document.getElementById('school_id').addEventListener('change', function() {
            const schoolId = this.value;
            const stageSelect = document.getElementById('stage_id');
            
            // إعادة تعيين قائمة المراحل
            stageSelect.innerHTML = '<option value="">-- اختر المرحلة --</option>';
            
            if (schoolId) {
                // جلب المراحل عبر AJAX
                fetch(`../../api/get_stages.php?school_id=${schoolId}`)
                    .then(response => response.json())
                    .then(data => {
                        data.forEach(stage => {
                            const option = document.createElement('option');
                            option.value = stage.id;
                            option.textContent = stage.name + ' (' + formatCurrency(stage.fee_amount) + ')';
                            stageSelect.appendChild(option);
                        });
                    })
                    .catch(error => console.error('خطأ في جلب المراحل:', error));
            }
        });

        // دالة تنسيق العملة للعرض في القائمة
        function formatCurrency(amount) {
            return amount.toFixed(2) + ' د.ل';
        }

        // فلتر المدرسة
        function filterBySchool(schoolId) {
            const url = new URL(window.location.href);
            if (schoolId) {
                url.searchParams.set('school_id', schoolId);
            } else {
                url.searchParams.delete('school_id');
            }
            window.location.href = url.toString();
        }

        // التحقق من صحة النموذج
        function validateForm() {
            const school_id = document.getElementById('school_id').value;
            const name = document.getElementById('name').value.trim();
            const student_code = document.getElementById('student_code').value.trim();
            
            // التحقق من اختيار المدرسة
            if (school_id === '') {
                alert('يرجى اختيار المدرسة أو المعهد');
                document.getElementById('school_id').focus();
                return false;
            }
            
            // التحقق من اسم الطالب
            if (name === '') {
                alert('يرجى إدخال اسم الطالب');
                document.getElementById('name').focus();
                return false;
            }
            
            // التحقق من طول الاسم
            if (name.length < 3) {
                alert('اسم الطالب قصير جداً (يجب أن يكون 3 أحرف على الأقل)');
                document.getElementById('name').focus();
                return false;
            }
            
            // التحقق من رقم الطالب
            if (student_code === '') {
                alert('يرجى إدخال رقم الطالب');
                document.getElementById('student_code').focus();
                return false;
            }
            
            // تعطيل الزر لمنع النقر المتكرر
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإضافة...';
            
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
                    this.style.borderColor = '#667eea';
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
        document.getElementById('addStudentForm').addEventListener('submit', function() {
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
        
        // اختصار لوحة المفاتيح: Ctrl+Enter لإرسال النموذج
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'Enter') {
                e.preventDefault();
                document.getElementById('addStudentForm').submit();
            }
        });
    </script>
</body>
</html>