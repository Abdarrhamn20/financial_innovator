<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المعاملة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات المعاملة
$stmt = $pdo->prepare("
    SELECT t.*, 
           s.name as student_name,
           s.student_code,
           s.school_id,
           sc.name as school_name
    FROM transactions t
    JOIN students s ON t.student_id = s.id
    JOIN schools sc ON s.school_id = sc.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header("Location: index.php");
    exit;
}

// جلب قائمة الطلاب للمدرسة نفسها
$students = getStudents($transaction['school_id']);

// جلب قائمة المراحل للمدرسة نفسها
$stages = getStages($transaction['school_id']);

// جلب قائمة المدارس
$schools = getSchools();

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_id = (int)$_POST['student_id'];
    $stage_id = !empty($_POST['stage_id']) ? (int)$_POST['stage_id'] : null;
    $type = $_POST['type'];
    $amount = (float)$_POST['amount'];
    $description = trim($_POST['description']);
    $transaction_date = $_POST['transaction_date'];
    $payment_method = $_POST['payment_method'];
    $status = $_POST['status'];
    
    $errors = [];
    
    // التحقق من صحة البيانات
    if (empty($student_id) || $student_id <= 0) {
        $errors[] = "يرجى اختيار الطالب";
    }
    
    if (empty($type)) {
        $errors[] = "نوع المعاملة مطلوب";
    }
    
    if ($amount <= 0) {
        $errors[] = "المبلغ يجب أن يكون أكبر من صفر";
    }
    
    if (empty($transaction_date)) {
        $errors[] = "تاريخ المعاملة مطلوب";
    }
    
    if (empty($payment_method)) {
        $errors[] = "طريقة الدفع مطلوبة";
    }
    
    if (empty($status)) {
        $errors[] = "حالة المعاملة مطلوبة";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE transactions SET 
                student_id = ?, 
                stage_id = ?, 
                type = ?, 
                amount = ?, 
                description = ?, 
                transaction_date = ?, 
                payment_method = ?, 
                status = ? 
                WHERE id = ?");
            
            $stmt->execute([
                $student_id, 
                $stage_id, 
                $type, 
                $amount, 
                $description, 
                $transaction_date, 
                $payment_method, 
                $status,
                $id
            ]);
            
            $success = "تم تحديث المعاملة بنجاح";
            
            // تحديث البيانات في المتغير
            $transaction['student_id'] = $student_id;
            $transaction['stage_id'] = $stage_id;
            $transaction['type'] = $type;
            $transaction['amount'] = $amount;
            $transaction['description'] = $description;
            $transaction['transaction_date'] = $transaction_date;
            $transaction['payment_method'] = $payment_method;
            $transaction['status'] = $status;
            
            // تحديث اسم الطالب
            foreach ($students as $student) {
                if ($student['id'] == $student_id) {
                    $transaction['student_name'] = $student['name'];
                    break;
                }
            }
            
        } catch(PDOException $e) {
            $errors[] = "حدث خطأ أثناء التحديث: " . $e->getMessage();
        }
    }
}

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

// أنواع المعاملة
$type_labels = [
    'income' => 'إيراد',
    'expense' => 'مصروف'
];
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل معاملة - المبتكر المالي</title>
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
        }

        .form-container .form-title {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 5px;
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

        /* معلومات المعاملة */
        .transaction-info {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 12px;
            padding: 15px 20px;
            margin-bottom: 25px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 10px;
        }

        .transaction-info .info-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            color: #2d3748;
        }

        .transaction-info .info-item i {
            color: #667eea;
        }

        .transaction-info .info-item .label {
            color: #718096;
        }

        .transaction-info .info-item .value {
            font-weight: 600;
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

            .transaction-info {
                flex-direction: column;
                align-items: stretch;
                text-align: center;
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
                    <h1>تعديل معاملة</h1>
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
                        <i class="fas fa-edit" style="color: #667eea;"></i>
                        تعديل المعاملة #<?php echo $transaction['id']; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="view.php?id=<?php echo $transaction['id']; ?>" class="btn-back">
                            <i class="fas fa-eye"></i>
                            عرض المعاملة
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

                <!-- معلومات المعاملة -->
                <div class="transaction-info">
                    <div class="info-item">
                        <i class="fas fa-hashtag"></i>
                        <span class="label">رقم المعاملة:</span>
                        <span class="value">#<?php echo $transaction['id']; ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-user-graduate"></i>
                        <span class="label">الطالب:</span>
                        <span class="value"><?php echo htmlspecialchars($transaction['student_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-school"></i>
                        <span class="label">المدرسة:</span>
                        <span class="value"><?php echo htmlspecialchars($transaction['school_name']); ?></span>
                    </div>
                    <div class="info-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="label">تاريخ الإنشاء:</span>
                        <span class="value"><?php echo date('Y-m-d', strtotime($transaction['created_at'])); ?></span>
                    </div>
                </div>

                <!-- نموذج التعديل -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-exchange-alt" style="color: #667eea;"></i>
                        بيانات المعاملة
                    </div>
                    <div class="form-subtitle">
                        قم بتحديث المعلومات الأساسية للمعاملة المالية
                    </div>

                    <form method="POST" id="editTransactionForm" onsubmit="return validateForm()">
                        <div class="form-row">
                            <div class="form-group">
                                <label for="student_id">
                                    الطالب
                                    <span class="required">*</span>
                                </label>
                                <select id="student_id" name="student_id" required>
                                    <option value="">-- اختر الطالب --</option>
                                    <?php foreach ($students as $student): ?>
                                    <option value="<?php echo $student['id']; ?>" 
                                        <?php echo $transaction['student_id'] == $student['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($student['name']); ?>
                                        (<?php echo htmlspecialchars($student['student_code'] ?? '---'); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">الطالب المرتبط بهذه المعاملة</div>
                            </div>

                            <div class="form-group">
                                <label for="stage_id">المرحلة الدراسية</label>
                                <select id="stage_id" name="stage_id">
                                    <option value="">-- اختر المرحلة --</option>
                                    <?php foreach ($stages as $stage): ?>
                                    <option value="<?php echo $stage['id']; ?>" 
                                        <?php echo $transaction['stage_id'] == $stage['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($stage['name']); ?>
                                        (<?php echo formatLibyanCurrency($stage['fee_amount']); ?>)
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">المرحلة المرتبطة بالمعاملة (اختياري)</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="type">
                                    نوع المعاملة
                                    <span class="required">*</span>
                                </label>
                                <select id="type" name="type" required>
                                    <option value="">-- اختر النوع --</option>
                                    <option value="income" <?php echo $transaction['type'] == 'income' ? 'selected' : ''; ?>>
                                        <i class="fas fa-arrow-up"></i> إيراد
                                    </option>
                                    <option value="expense" <?php echo $transaction['type'] == 'expense' ? 'selected' : ''; ?>>
                                        <i class="fas fa-arrow-down"></i> مصروف
                                    </option>
                                </select>
                                <div class="help-text">نوع المعاملة المالية</div>
                            </div>

                            <div class="form-group">
                                <label for="amount">
                                    المبلغ (<?php echo currencySymbol(); ?>)
                                    <span class="required">*</span>
                                </label>
                                <input type="number" id="amount" name="amount" 
                                       value="<?php echo $transaction['amount']; ?>" 
                                       step="0.01" min="0.01" 
                                       placeholder="0.00" required>
                                <div class="help-text">قيمة المعاملة بالدينار الليبي</div>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="transaction_date">
                                    تاريخ المعاملة
                                    <span class="required">*</span>
                                </label>
                                <input type="date" id="transaction_date" name="transaction_date" 
                                       value="<?php echo $transaction['transaction_date']; ?>" required>
                                <div class="help-text">تاريخ حدوث المعاملة</div>
                            </div>

                            <div class="form-group">
                                <label for="payment_method">
                                    طريقة الدفع
                                    <span class="required">*</span>
                                </label>
                                <select id="payment_method" name="payment_method" required>
                                    <option value="">-- اختر طريقة الدفع --</option>
                                    <?php foreach ($payment_methods as $key => $label): ?>
                                    <option value="<?php echo $key; ?>" 
                                        <?php echo $transaction['payment_method'] == $key ? 'selected' : ''; ?>>
                                        <?php echo $label; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <div class="help-text">طريقة دفع المعاملة</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="status">
                                حالة المعاملة
                                <span class="required">*</span>
                            </label>
                            <select id="status" name="status" required>
                                <option value="">-- اختر الحالة --</option>
                                <?php foreach ($status_labels as $key => $label): ?>
                                <option value="<?php echo $key; ?>" 
                                    <?php echo $transaction['status'] == $key ? 'selected' : ''; ?>>
                                    <?php echo $label; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">الحالة الحالية للمعاملة</div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea id="description" name="description" 
                                      placeholder="أدخل وصفاً للمعاملة (اختياري)"><?php echo htmlspecialchars($transaction['description']); ?></textarea>
                            <div class="help-text">وصف تفصيلي للمعاملة (اختياري)</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-save"></i>
                                حفظ التغييرات
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
        // التحقق من صحة النموذج
        function validateForm() {
            const student_id = document.getElementById('student_id').value;
            const type = document.getElementById('type').value;
            const amount = document.getElementById('amount').value.trim();
            const transaction_date = document.getElementById('transaction_date').value;
            const payment_method = document.getElementById('payment_method').value;
            const status = document.getElementById('status').value;
            
            // التحقق من اختيار الطالب
            if (student_id === '') {
                alert('يرجى اختيار الطالب');
                document.getElementById('student_id').focus();
                return false;
            }
            
            // التحقق من نوع المعاملة
            if (type === '') {
                alert('يرجى اختيار نوع المعاملة');
                document.getElementById('type').focus();
                return false;
            }
            
            // التحقق من المبلغ
            if (amount === '' || isNaN(amount) || parseFloat(amount) <= 0) {
                alert('يرجى إدخال مبلغ صحيح أكبر من صفر');
                document.getElementById('amount').focus();
                return false;
            }
            
            // التحقق من التاريخ
            if (transaction_date === '') {
                alert('يرجى اختيار تاريخ المعاملة');
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
                alert('يرجى اختيار حالة المعاملة');
                document.getElementById('status').focus();
                return false;
            }
            
            // تعطيل الزر لمنع النقر المتكرر
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            
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
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'لديك تغييرات غير محفوظة. هل أنت متأكد من المغادرة؟';
            }
        });
        
        // إلغاء تأكيد الخروج عند حفظ النموذج
        document.getElementById('editTransactionForm').addEventListener('submit', function() {
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
        
        // تحديث عنوان الصفحة عند تغيير النوع
        document.getElementById('type').addEventListener('change', function() {
            const type = this.value;
            const title = document.querySelector('.form-title');
            if (type === 'income') {
                title.innerHTML = '<i class="fas fa-arrow-up" style="color: #38a169;"></i> تعديل إيراد';
            } else if (type === 'expense') {
                title.innerHTML = '<i class="fas fa-arrow-down" style="color: #e53e3e;"></i> تعديل مصروف';
            } else {
                title.innerHTML = '<i class="fas fa-exchange-alt" style="color: #667eea;"></i> بيانات المعاملة';
            }
        });
    </script>
</body>
</html>