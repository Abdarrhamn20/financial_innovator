<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// جلب الإحصائيات
$schools = getSchools();
$total_schools = count($schools);
$total_students = 0;
$total_income = 0;
$total_expenses = 0;

foreach ($schools as $school) {
    $stats = getSchoolStats($school['id']);
    $total_students += intval($stats['total_students']);
    $total_income += floatval($stats['total_income']);
    $total_expenses += floatval($stats['total_expenses']);
}

// جلب آخر المعاملات
$recent_transactions = [];
$stmt = $pdo->query("SELECT t.*, s.name as student_name, sc.name as school_name 
                     FROM transactions t 
                     JOIN students s ON t.student_id = s.id 
                     JOIN schools sc ON s.school_id = sc.id 
                     ORDER BY t.created_at DESC LIMIT 5");
$recent_transactions = $stmt->fetchAll();

// جلب توزيع الطلاب حسب المدارس
$student_distribution = [];
foreach ($schools as $school) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM students WHERE school_id = ?");
    $stmt->execute([$school['id']]);
    $count = $stmt->fetchColumn();
    $student_distribution[] = [
        'name' => $school['name'],
        'count' => intval($count)
    ];
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المبتكر المالي - لوحة التحكم</title>
    <link rel="stylesheet" href="assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
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
                    <li class="nav-item active">
                        <a href="index.php">
                            <i class="fas fa-th-large"></i>
                            <span>لوحة التحكم</span>
                        </a>
                    </li>
                    <li class="nav-section">الإدارة</li>
                    <li class="nav-item">
                        <a href="modules/schools/index.php">
                            <i class="fas fa-school"></i>
                            <span>المدارس والمعاهد</span>
                            <span class="badge"><?php echo $total_schools; ?></span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="modules/stages/index.php">
                            <i class="fas fa-layer-group"></i>
                            <span>المراحل الدراسية</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="modules/students/index.php">
                            <i class="fas fa-user-graduate"></i>
                            <span>الطلاب</span>
                            <span class="badge"><?php echo $total_students; ?></span>
                        </a>
                    </li>
                    
                    <li class="nav-section">المعاملات المالية</li>
                    <li class="nav-item">
                        <a href="modules/transactions/index.php">
                            <i class="fas fa-exchange-alt"></i>
                            <span>جميع المعاملات</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="modules/transactions/income.php">
                            <i class="fas fa-arrow-up text-success"></i>
                            <span>الإيرادات</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="modules/transactions/expense.php">
                            <i class="fas fa-arrow-down text-danger"></i>
                            <span>المصروفات</span>
                        </a>
                    </li>
                    
                    <li class="nav-section">التقارير</li>
                    <li class="nav-item">
                        <a href="modules/reports/index.php">
                            <i class="fas fa-chart-bar"></i>
                            <span>التقارير والإحصائيات</span>
                        </a>
                    </li>
                    <li class="nav-item">
                        <a href="modules/reports/students.php">
                            <i class="fas fa-users"></i>
                            <span>تقرير الطلاب</span>
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
                    <h1>لوحة التحكم</h1>
                </div>
                <div class="header-right">
                    <div class="header-time" style="font-size: 13px; color: #718096; font-weight: 500; margin-left: 15px;">
                        <i class="far fa-clock"></i>
                        <span id="currentTime">--:--:--</span>
                    </div>
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

            <!-- محتوى لوحة التحكم -->
            <div class="content-area">
                <!-- بطاقات الإحصائيات -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-icon blue">
                            <i class="fas fa-school"></i>
                        </div>
                        <div class="stat-content">
                            <h3>المدارس والمعاهد</h3>
                            <p class="stat-number"><?php echo $total_schools; ?></p>
                            <span class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> 12%
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon green">
                            <i class="fas fa-user-graduate"></i>
                        </div>
                        <div class="stat-content">
                            <h3>إجمالي الطلاب</h3>
                            <p class="stat-number"><?php echo $total_students; ?></p>
                            <span class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> 8%
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon gold">
                            <i class="fas fa-coins"></i>
                        </div>
                        <div class="stat-content">
                            <h3>إجمالي الإيرادات</h3>
                            <p class="stat-number"><?php echo number_format($total_income, 2); ?> د.ل</p>
                            <span class="stat-change positive">
                                <i class="fas fa-arrow-up"></i> 15%
                            </span>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-icon red">
                            <i class="fas fa-credit-card"></i>
                        </div>
                        <div class="stat-content">
                            <h3>إجمالي المصروفات</h3>
                            <p class="stat-number"><?php echo number_format($total_expenses, 2); ?> د.ل</p>
                            <span class="stat-change negative">
                                <i class="fas fa-arrow-down"></i> 5%
                            </span>
                        </div>
                    </div>
                </div>

                <!-- المخططات والرسوم البيانية -->
                <div class="charts-grid">
                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>توزيع الطلاب حسب المدارس</h3>
                            <i class="fas fa-ellipsis-v"></i>
                        </div>
                        <div class="chart-body">
                            <div class="distribution-bars">
                                <?php foreach($student_distribution as $item): ?>
                                <div class="bar-item">
                                    <div class="bar-label"><?php echo htmlspecialchars($item['name']); ?></div>
                                    <div class="bar-track">
                                        <div class="bar-fill" style="width: <?php echo $total_students > 0 ? ($item['count'] / $total_students) * 100 : 0; ?>%">
                                            <span><?php echo $item['count']; ?></span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div class="chart-card">
                        <div class="chart-header">
                            <h3>آخر المعاملات</h3>
                            <a href="modules/transactions/index.php" class="view-all">عرض الكل</a>
                        </div>
                        <div class="chart-body">
                            <div class="transactions-list">
                                <?php if(count($recent_transactions) > 0): ?>
                                <?php foreach($recent_transactions as $transaction): ?>
                                <div class="transaction-item">
                                    <div class="transaction-icon <?php echo $transaction['type']; ?>">
                                        <i class="fas fa-<?php echo $transaction['type'] == 'income' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                    </div>
                                    <div class="transaction-info">
                                        <h4><?php echo htmlspecialchars($transaction['student_name']); ?></h4>
                                        <span><?php echo htmlspecialchars($transaction['school_name']); ?></span>
                                    </div>
                                    <div class="transaction-amount <?php echo $transaction['type']; ?>">
                                        <?php echo $transaction['type'] == 'income' ? '+' : '-'; ?>
                                        <?php echo number_format($transaction['amount'], 2); ?> د.ل
                                    </div>
                                </div>
                                <?php endforeach; ?>
                                <?php else: ?>
                                <div class="empty-state">
                                    <i class="fas fa-exchange-alt"></i>
                                    <p>لا توجد معاملات مسجلة</p>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- الإجراءات السريعة -->
                <div class="quick-actions">
                    <h2>الإجراءات السريعة</h2>
                    <div class="actions-grid">
                        <a href="modules/schools/add.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #667eea, #764ba2);">
                                <i class="fas fa-plus"></i>
                            </div>
                            <div class="action-info">
                                <h4>إضافة مدرسة</h4>
                                <p>إضافة مدرسة أو معهد جديد</p>
                            </div>
                        </a>
                        <a href="modules/stages/add.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #f093fb, #f5576c);">
                                <i class="fas fa-layer-group"></i>
                            </div>
                            <div class="action-info">
                                <h4>إضافة مرحلة</h4>
                                <p>إضافة مرحلة دراسية جديدة</p>
                            </div>
                        </a>
                        <a href="modules/students/add.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #4facfe, #00f2fe);">
                                <i class="fas fa-user-plus"></i>
                            </div>
                            <div class="action-info">
                                <h4>إضافة طالب</h4>
                                <p>تسجيل طالب جديد</p>
                            </div>
                        </a>
                        <a href="modules/transactions/income.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #43e97b, #38f9d7);">
                                <i class="fas fa-arrow-up"></i>
                            </div>
                            <div class="action-info">
                                <h4>إضافة إيراد</h4>
                                <p>تسجيل إيراد جديد بالدينار الليبي</p>
                            </div>
                        </a>
                        <a href="modules/transactions/expense.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #fa709a, #fee140);">
                                <i class="fas fa-arrow-down"></i>
                            </div>
                            <div class="action-info">
                                <h4>إضافة مصروف</h4>
                                <p>تسجيل مصروف جديد بالدينار الليبي</p>
                            </div>
                        </a>
                        <a href="modules/reports/index.php" class="action-card">
                            <div class="action-icon" style="background: linear-gradient(135deg, #a8edea, #fed6e3);">
                                <i class="fas fa-chart-pie"></i>
                            </div>
                            <div class="action-info">
                                <h4>التقارير</h4>
                                <p>عرض التقارير والإحصائيات</p>
                            </div>
                        </a>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <script src="assets/js/dashboard.js"></script>
    <script>
        // عرض الوقت الحالي
        function updateTime() {
            const now = new Date();
            const timeString = now.toLocaleTimeString('ar-EG', {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            });
            document.getElementById('currentTime').textContent = timeString;
        }
        updateTime();
        setInterval(updateTime, 1000);
        
        // تأثير ظهور البطاقات
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card, .action-card, .chart-card');
            cards.forEach((card, index) => {
                setTimeout(() => {
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 100 * index);
            });
        });
    </script>
</body>
</html>