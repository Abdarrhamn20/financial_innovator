<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

$errors = [];
$success = '';
$name = '';
$type = 'school';
$address = '';
$phone = '';
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name']);
    $type = $_POST['type'];
    $address = trim($_POST['address']);
    $phone = trim($_POST['phone']);
    $email = trim($_POST['email']);
    
    if (empty($name)) {
        $errors[] = "اسم المدرسة/المعهد مطلوب";
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "البريد الإلكتروني غير صحيح";
    }
    
    if (empty($errors)) {
        try {
            $stmt = $pdo->prepare("INSERT INTO schools (name, type, address, phone, email) VALUES (?, ?, ?, ?, ?)");
            $stmt->execute([$name, $type, $address, $phone, $email]);
            
            // ✅ إعادة التوجيه إلى صفحة المدارس مع رسالة نجاح
            header("Location: index.php?added=success");
            exit;
            
        } catch(PDOException $e) {
            $errors[] = "حدث خطأ أثناء الإضافة: " . $e->getMessage();
        }
    }
}

$schools = getSchools();
$total_schools = count($schools);
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>إضافة مدرسة - المبتكر المالي</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
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
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        .btn-back:hover { border-color: #667eea; background: #f7fafc; }
        .form-container {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            max-width: 700px;
            margin: 0 auto;
            border-top: 5px solid #667eea;
        }
        .form-group { margin-bottom: 20px; }
        .form-group label {
            display: block;
            font-weight: 600;
            margin-bottom: 6px;
            color: #2d3748;
            font-size: 14px;
        }
        .form-group label .required { color: #e53e3e; margin-right: 3px; }
        .form-group input, .form-group select, .form-group textarea {
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
        .form-group input:focus, .form-group select:focus, .form-group textarea:focus {
            outline: none;
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .form-group textarea { min-height: 80px; resize: vertical; }
        .form-group .help-text { font-size: 12px; color: #718096; margin-top: 5px; }
        .form-row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        .alert-success { background: #f0fff4; color: #22543d; border: 1px solid #c6f6d5; }
        .alert-error { background: #fff5f5; color: #9b2c2c; border: 1px solid #fed7d7; }
        .alert i { font-size: 20px; }
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
        }
        .btn-submit:hover { transform: translateY(-2px); box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3); }
        .btn-submit:disabled { opacity: 0.6; cursor: not-allowed; }
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
        }
        .btn-cancel:hover { background: #e2e8f0; }
        .stats-mini {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }
        .stats-mini .stat-box {
            background: white;
            padding: 15px 20px;
            border-radius: 12px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            text-align: center;
        }
        .stats-mini .stat-box .number { font-size: 24px; font-weight: 700; color: #2d3748; }
        .stats-mini .stat-box .label { font-size: 13px; color: #718096; }
        @media (max-width: 768px) {
            .form-row { grid-template-columns: 1fr; gap: 0; }
            .form-actions { flex-direction: column; }
        }
    </style>
</head>
<body>
    <div class="dashboard-wrapper">
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
                    <li class="nav-item"><a href="../../index.php"><i class="fas fa-th-large"></i> لوحة التحكم</a></li>
                    <li class="nav-section">الإدارة</li>
                    <li class="nav-item active"><a href="index.php"><i class="fas fa-school"></i> المدارس والمعاهد</a></li>
                    <li class="nav-item"><a href="../stages/index.php"><i class="fas fa-layer-group"></i> المراحل الدراسية</a></li>
                    <li class="nav-item"><a href="../students/index.php"><i class="fas fa-user-graduate"></i> الطلاب</a></li>
                    <li class="nav-section">المعاملات المالية</li>
                    <li class="nav-item"><a href="../transactions/index.php"><i class="fas fa-exchange-alt"></i> جميع المعاملات</a></li>
                    <li class="nav-section">التقارير</li>
                    <li class="nav-item"><a href="../reports/index.php"><i class="fas fa-chart-bar"></i> التقارير والإحصائيات</a></li>
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
                <a href="#" class="logout-btn"><i class="fas fa-sign-out-alt"></i> تسجيل الخروج</a>
            </div>
        </aside>

        <main class="main-content">
            <header class="top-header">
                <div class="header-left">
                    <button class="toggle-sidebar" id="toggleSidebar"><i class="fas fa-bars"></i></button>
                    <h1>إضافة مدرسة / معهد</h1>
                </div>
                <div class="header-right">
                    <a href="index.php" class="btn-back"><i class="fas fa-arrow-right"></i> العودة للقائمة</a>
                </div>
            </header>

            <div class="content-area">
                <div class="page-header">
                    <h2><i class="fas fa-plus-circle" style="color: #667eea;"></i> إضافة مدرسة أو معهد جديد</h2>
                </div>

                <?php if (!empty($errors)): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <div><?php foreach ($errors as $error) echo "• $error<br>"; ?></div>
                </div>
                <?php endif; ?>

                <div class="stats-mini">
                    <div class="stat-box">
                        <span class="number"><?php echo $total_schools; ?></span>
                        <span class="label">إجمالي المدارس</span>
                    </div>
                </div>

                <div class="form-container">
                    <div class="form-title" style="font-size: 18px; font-weight: 700; margin-bottom: 5px;">
                        <i class="fas fa-school" style="color: #667eea;"></i> بيانات المدرسة / المعهد
                    </div>
                    <div class="form-subtitle" style="color: #718096; margin-bottom: 25px;">
                        قم بإدخال المعلومات الأساسية للمدرسة أو المعهد الجديد
                    </div>

                    <form method="POST" id="addSchoolForm">
                        <div class="form-group">
                            <label for="name">اسم المدرسة / المعهد <span class="required">*</span></label>
                            <input type="text" id="name" name="name" value="<?php echo htmlspecialchars($name); ?>" placeholder="أدخل اسم المدرسة أو المعهد" required>
                            <div class="help-text">الاسم كما سيظهر في النظام</div>
                        </div>

                        <div class="form-group">
                            <label for="type">النوع <span class="required">*</span></label>
                            <select id="type" name="type" required>
                                <option value="school" <?php echo $type == 'school' ? 'selected' : ''; ?>>مدرسة</option>
                                <option value="institute" <?php echo $type == 'institute' ? 'selected' : ''; ?>>معهد</option>
                            </select>
                            <div class="help-text">اختر نوع المؤسسة التعليمية</div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label for="phone">رقم الهاتف</label>
                                <input type="tel" id="phone" name="phone" value="<?php echo htmlspecialchars($phone); ?>" placeholder="مثال: 0912345678">
                                <div class="help-text">رقم هاتف المدرسة أو المعهد</div>
                            </div>
                            <div class="form-group">
                                <label for="email">البريد الإلكتروني</label>
                                <input type="email" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>" placeholder="example@school.edu">
                                <div class="help-text">البريد الإلكتروني الرسمي</div>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="address">العنوان</label>
                            <textarea id="address" name="address" placeholder="أدخل العنوان الكامل للمدرسة أو المعهد"><?php echo htmlspecialchars($address); ?></textarea>
                            <div class="help-text">العنوان التفصيلي للمؤسسة</div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn-submit" id="submitBtn">
                                <i class="fas fa-save"></i> إضافة المدرسة
                            </button>
                            <a href="index.php" class="btn-cancel">
                                <i class="fas fa-times"></i> إلغاء
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // تحقق من صحة النموذج
        document.getElementById('addSchoolForm').addEventListener('submit', function(e) {
            const name = document.getElementById('name').value.trim();
            if (!name) {
                e.preventDefault();
                alert('يرجى إدخال اسم المدرسة أو المعهد');
                document.getElementById('name').focus();
                return false;
            }
            document.getElementById('submitBtn').disabled = true;
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> جاري الإضافة...';
        });
    </script>
</body>
</html>