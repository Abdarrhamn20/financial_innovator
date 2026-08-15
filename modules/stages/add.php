<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب قائمة المدارس للاختيار
$schools = getSchools();

// متغيرات لتخزين قيم النموذج
$school_id = isset($_GET['school_id']) ? (int)$_GET['school_id'] : '';
$name = '';
$fee_amount = '';
$description = '';
$errors = [];
$success = '';

// معالجة إضافة المرحلة
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $school_id = (int)$_POST['school_id'];
    $name = trim($_POST['name']);
    $fee_amount = (float)$_POST['fee_amount'];
    $description = trim($_POST['description']);
    
    // التحقق من صحة البيانات
    if (empty($school_id) || $school_id <= 0) {
        $errors[] = "يرجى اختيار المدرسة/المعهد";
    }
    
    if (empty($name)) {
        $errors[] = "اسم المرحلة مطلوب";
    }
    
    if ($fee_amount < 0) {
        $errors[] = "قيمة الرسوم يجب أن تكون أكبر من أو تساوي صفر";
    }
    
    // التحقق من عدم وجود مرحلة بنفس الاسم في نفس المدرسة
    if (empty($errors)) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM stages WHERE school_id = ? AND name = ?");
        $stmt->execute([$school_id, $name]);
        $count = $stmt->fetchColumn();
        
        if ($count > 0) {
            $errors[] = "يوجد مرحلة بنفس الاسم في هذه المدرسة بالفعل";
        }
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO stages (school_id, name, fee_amount, description) VALUES (?, ?, ?, ?)");
            $stmt->execute([$school_id, $name, $fee_amount, $description]);
            
            $success = "تم إضافة المرحلة الدراسية بنجاح";
            
            // إعادة تعيين الحقول
            $name = '';
            $fee_amount = '';
            $description = '';
            
            // إعادة تعيين اختيار المدرسة إذا كان من GET
            if (isset($_GET['school_id'])) {
                $school_id = (int)$_GET['school_id'];
            }
            
        } catch(PDOException $e) {
            $errors[] = "حدث خطأ أثناء الإضافة: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات عامة
$total_stages = 0;
$stmt = $pdo->query("SELECT COUNT(*) FROM stages");
$total_stages = $stmt->fetchColumn();

$total_schools = count($schools);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مرحلة دراسية - المبتكر المالي</title>
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
            max-width: 700px;
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

        /* تأثيرات إضافية */
        .input-with-icon {
            position: relative;
        }

        .input-with-icon i {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #a0aec0;
        }

        .input-with-icon input {
            padding-left: 40px;
        }

        /* رسوم متحركة للنجاح */
        @keyframes successPulse {
            0%, 100% { transform: scale(1); }
            50% { transform: scale(1.02); }
        }

        .alert-success {
            animation: successPulse 0.5s ease;
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
                    <h1>إضافة مرحلة دراسية</h1>
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
                        <i class="fas fa-plus-circle" style="color: #667eea;"></i>
                        إضافة مرحلة دراسية جديدة
                    </h2>
                    <div class="header-actions">
                        <a href="index.php<?php echo isset($_GET['school_id']) ? '?school_id=' . $_GET['school_id'] : ''; ?>" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة إلى القائمة
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
                        <i class="fas fa-layer-group"></i>
                        <div class="info">
                            <div class="number"><?php echo $total_stages; ?></div>
                            <div class="label">إجمالي المراحل</div>
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

                <!-- نموذج الإضافة -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-layer-group" style="color: #667eea;"></i>
                        بيانات المرحلة الدراسية
                    </div>
                    <div class="form-subtitle">
                        قم بإدخال المعلومات الأساسية للمرحلة الدراسية الجديدة
                    </div>

                    <form method="POST" id="addStageForm" onsubmit="return validateForm()">
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
                                    (<?php echo $school['type'] == 'school' ? '🏫 مدرسة' : '🏛️ معهد'; ?>)
                                </option>
                                <?php endforeach; ?>
                            </select>
                            <div class="help-text">اختر المدرسة أو المعهد التابع له هذه المرحلة</div>
                        </div>

                        <div class="form-group">
                            <label for="name">
                                اسم المرحلة
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($name); ?>" 
                                   placeholder="مثال: الصف الأول الابتدائي" required>
                            <div class="help-text">الاسم الرسمي للمرحلة الدراسية</div>
                        </div>

                        <div class="form-group">
                            <label for="fee_amount">
                                قيمة الرسوم (<?php echo currencySymbol(); ?>)
                                <span class="required">*</span>
                            </label>
                            <div class="input-with-icon">
                                <input type="number" id="fee_amount" name="fee_amount" 
                                       value="<?php echo $fee_amount; ?>" 
                                       step="0.01" min="0" 
                                       placeholder="0.00" required>
                                <i class="fas fa-coins"></i>
                            </div>
                            <div class="help-text">قيمة الرسوم الدراسية لهذه المرحلة بالدينار الليبي</div>
                        </div>

                        <div class="form-group">
                            <label for="description">الوصف</label>
                            <textarea id="description" name="description" 
                                      placeholder="أدخل وصفاً إضافياً للمرحلة (اختياري)"><?php echo htmlspecialchars($description); ?></textarea>
                            <div class="help-text">وصف إضافي للمرحلة الدراسية (اختياري)</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-plus"></i>
                                إضافة المرحلة
                            </button>
                            <a href="index.php<?php echo isset($_GET['school_id']) ? '?school_id=' . $_GET['school_id'] : ''; ?>" class="btn-cancel">
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
            const school_id = document.getElementById('school_id').value;
            const name = document.getElementById('name').value.trim();
            const fee_amount = document.getElementById('fee_amount').value.trim();
            
            // التحقق من اختيار المدرسة
            if (school_id === '') {
                alert('يرجى اختيار المدرسة أو المعهد');
                document.getElementById('school_id').focus();
                return false;
            }
            
            // التحقق من اسم المرحلة
            if (name === '') {
                alert('يرجى إدخال اسم المرحلة');
                document.getElementById('name').focus();
                return false;
            }
            
            // التحقق من طول اسم المرحلة
            if (name.length < 3) {
                alert('اسم المرحلة قصير جداً (يجب أن يكون 3 أحرف على الأقل)');
                document.getElementById('name').focus();
                return false;
            }
            
            // التحقق من قيمة الرسوم
            if (fee_amount === '' || isNaN(fee_amount) || parseFloat(fee_amount) < 0) {
                alert('يرجى إدخال قيمة رسوم صحيحة');
                document.getElementById('fee_amount').focus();
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
            
            // تحويل اسم المرحلة إلى تنسيق مناسب
            const nameInput = document.getElementById('name');
            nameInput.addEventListener('input', function() {
                // يمكن إضافة تنسيق تلقائي هنا
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
        document.getElementById('addStageForm').addEventListener('submit', function() {
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
                document.getElementById('addStageForm').submit();
            }
        });
    </script>
</body>
</html>