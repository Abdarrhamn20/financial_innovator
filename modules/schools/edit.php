<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المدرسة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات المدرسة
$stmt = $pdo->prepare("SELECT * FROM schools WHERE id = ?");
$stmt->execute([$id]);
$school = $stmt->fetch();

if (!$school) {
    header("Location: index.php");
    exit;
}

// معالجة تحديث البيانات
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    $errors = [];
    
    // التحقق من صحة البيانات
    if (empty($name)) {
        $errors[] = "اسم المدرسة/المعهد مطلوب";
    }
    
    if (empty($type)) {
        $errors[] = "نوع المدرسة/المعهد مطلوب";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صحيح";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("UPDATE schools SET 
                name = ?, 
                type = ?, 
                address = ?, 
                phone = ?, 
                email = ? 
                WHERE id = ?");
            
            $stmt->execute([$name, $type, $address, $phone, $email, $id]);
            
            $success = "تم تحديث بيانات المدرسة/المعهد بنجاح";
            
            // تحديث البيانات في المتغير
            $school['name'] = $name;
            $school['type'] = $type;
            $school['address'] = $address;
            $school['phone'] = $phone;
            $school['email'] = $email;
            
        } catch(PDOException $e) {
            $errors[] = "حدث خطأ أثناء التحديث: " . $e->getMessage();
        }
    }
}

// جلب إحصائيات المدرسة
$stats = getSchoolStats($id);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تعديل مدرسة/معهد - المبتكر المالي</title>
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

        /* بطاقة معلومات المدرسة */
        .school-info-card {
            background: linear-gradient(135deg, #f7fafc, #edf2f7);
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
        }

        .school-info-card .info-item {
            text-align: center;
        }

        .school-info-card .info-item .number {
            font-size: 24px;
            font-weight: 700;
            color: #2d3748;
        }

        .school-info-card .info-item .label {
            font-size: 13px;
            color: #718096;
            display: block;
            margin-top: 3px;
        }

        .school-info-card .info-item .number.income {
            color: #38a169;
        }

        .school-info-card .info-item .number.students {
            color: #3182ce;
        }

        .school-info-card .info-item .number.stages {
            color: #805ad5;
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

            .school-info-card {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 480px) {
            .school-info-card {
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
                    <li class="nav-item active">
                        <a href="index.php">
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
                    <h1>تعديل مدرسة / معهد</h1>
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
                        تعديل: <?php echo htmlspecialchars($school['name']); ?>
                    </h2>
                    <div class="header-actions">
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة إلى القائمة
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
                        <div><?php echo $error; ?></div>
                        <?php endforeach; ?>
                    </div>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <!-- بطاقة معلومات المدرسة -->
                <div class="school-info-card">
                    <div class="info-item">
                        <span class="number students"><?php echo $stats['total_students'] ?? 0; ?></span>
                        <span class="label">👨‍🎓 إجمالي الطلاب</span>
                    </div>
                    <div class="info-item">
                        <span class="number stages"><?php echo $stats['total_stages'] ?? 0; ?></span>
                        <span class="label">📚 عدد المراحل</span>
                    </div>
                    <div class="info-item">
                        <span class="number income"><?php echo formatLibyanCurrency($stats['total_income'] ?? 0); ?></span>
                        <span class="label">💰 إجمالي الإيرادات</span>
                    </div>
                    <div class="info-item">
                        <span class="number" style="color: #e53e3e;"><?php echo formatLibyanCurrency($stats['total_expenses'] ?? 0); ?></span>
                        <span class="label">💳 إجمالي المصروفات</span>
                    </div>
                </div>

                <!-- نموذج التعديل -->
                <div class="form-container">
                    <div class="form-title">
                        <i class="fas fa-school" style="color: #667eea;"></i>
                        بيانات المدرسة / المعهد
                    </div>
                    <div class="form-subtitle">
                        قم بتحديث المعلومات الأساسية للمدرسة أو المعهد
                    </div>

                    <form method="POST" id="editSchoolForm" onsubmit="return validateForm()">
                        <div class="form-group">
                            <label for="name">
                                اسم المدرسة / المعهد
                                <span class="required">*</span>
                            </label>
                            <input type="text" id="name" name="name" 
                                   value="<?php echo htmlspecialchars($school['name']); ?>" 
                                   placeholder="أدخل اسم المدرسة أو المعهد" required>
                            <div class="help-text">الاسم كما سيظهر في النظام</div>
                        </div>

                        <div class="form-group">
                            <label for="type">
                                النوع
                                <span class="required">*</span>
                            </label>
                            <select id="type" name="type" required>
                                <option value="school" <?php echo $school['type'] == 'school' ? 'selected' : ''; ?>>مدرسة</option>
                                <option value="institute" <?php echo $school['type'] == 'institute' ? 'selected' : ''; ?>>معهد</option>
                            </select>
                            <div class="help-text">اختر نوع المؤسسة التعليمية</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">رقم الهاتف</label>
                                <input type="tel" id="phone" name="phone" 
                                       value="<?php echo htmlspecialchars($school['phone']); ?>" 
                                       placeholder="مثال: 0912345678">
                                <div class="help-text">رقم هاتف المدرسة أو المعهد</div>
                            </div>

                            <div class="form-group">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="email" id="email" name="email" 
                                       value="<?php echo htmlspecialchars($school['email']); ?>" 
                                       placeholder="example@school.edu">
                                <div class="help-text">البريد الإلكتروني الرسمي</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">العنوان</label>
                            <textarea id="address" name="address" 
                                      placeholder="أدخل العنوان الكامل للمدرسة أو المعهد"><?php echo htmlspecialchars($school['address']); ?></textarea>
                            <div class="help-text">العنوان التفصيلي للمؤسسة</div>
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
            const name = document.getElementById('name').value.trim();
            const type = document.getElementById('type').value;
            const email = document.getElementById('email').value.trim();
            
            // التحقق من الاسم
            if (name === '') {
                alert('يرجى إدخال اسم المدرسة أو المعهد');
                document.getElementById('name').focus();
                return false;
            }
            
            // التحقق من النوع
            if (type === '') {
                alert('يرجى اختيار نوع المدرسة أو المعهد');
                document.getElementById('type').focus();
                return false;
            }
            
            // التحقق من البريد الإلكتروني (إذا كان موجوداً)
            if (email !== '' && !isValidEmail(email)) {
                alert('يرجى إدخال بريد إلكتروني صحيح');
                document.getElementById('email').focus();
                return false;
            }
            
            // تعطيل الزر لمنع النقر المتكرر
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الحفظ...';
            
            return true;
        }
        
        // التحقق من صحة البريد الإلكتروني
        function isValidEmail(email) {
            const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return re.test(email);
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
        document.getElementById('editSchoolForm').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
</body>
</html>