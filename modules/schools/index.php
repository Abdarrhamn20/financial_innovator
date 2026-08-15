<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// جلب جميع المدارس والمعاهد
$schools = getSchools();

// عرض رسائل النجاح أو الخطأ
$success = '';
$error = '';

if (isset($_GET['deleted']) && $_GET['deleted'] == 'success') {
    $success = "تم حذف المدرسة/المعهد وجميع البيانات المرتبطة به بنجاح";
}

if (isset($_GET['added']) && $_GET['added'] == 'success') {
    $success = "تم إضافة المدرسة/المعهد بنجاح";
}

if (isset($_GET['error'])) {
    $error = urldecode($_GET['error']);
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>المدارس والمعاهد - المبتكر المالي</title>
    <link rel="stylesheet" href="../../assets/css/dashboard.css">
    <link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@300;400;500;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        /* ===== تنسيقات الصفحة ===== */
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
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .page-header .header-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .btn-add {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: linear-gradient(135deg, #667eea, #764ba2);
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
        }
        
        .btn-add:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        }
        
        .btn-delete-all {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 22px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 10px;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
        }
        
        .btn-delete-all:hover {
            background: #c53030;
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(229, 62, 62, 0.3);
        }
        
        /* ===== التنبيهات ===== */
        .alert {
            padding: 15px 20px;
            border-radius: 12px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 12px;
            animation: slideDown 0.5s ease;
        }
        
        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
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
            transition: transform 0.3s;
        }
        
        .alert .close-alert:hover {
            transform: rotate(90deg);
        }
        
        /* ===== شبكة المدارس ===== */
        .schools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }
        
        .school-card {
            background: white;
            border-radius: 16px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: all 0.3s;
            border: 1px solid #edf2f7;
            position: relative;
            animation: fadeIn 0.5s ease;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: scale(0.95);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }
        
        .school-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            border-color: #667eea;
        }
        
        .school-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 12px;
        }
        
        .school-card .school-name {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
            line-height: 1.4;
        }
        
        .school-card .school-type {
            padding: 4px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
            flex-shrink: 0;
        }
        
        .school-type.school {
            background: #ebf4ff;
            color: #3182ce;
        }
        
        .school-type.institute {
            background: #faf5ff;
            color: #805ad5;
        }
        
        .school-card .card-body {
            margin: 12px 0;
        }
        
        .school-card .info-row {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            color: #4a5568;
            font-size: 14px;
        }
        
        .school-card .info-row i {
            width: 18px;
            color: #667eea;
            font-size: 14px;
        }
        
        .school-card .info-row .text-muted {
            color: #a0aec0;
            font-size: 13px;
        }
        
        .school-card .stats-row {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 8px;
            margin: 12px 0;
            padding: 12px;
            background: #f7fafc;
            border-radius: 10px;
        }
        
        .school-card .stat-item {
            text-align: center;
        }
        
        .school-card .stat-item .stat-number {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
        }
        
        .school-card .stat-item .stat-number.income {
            color: #38a169;
        }
        
        .school-card .stat-item .stat-number.students {
            color: #3182ce;
        }
        
        .school-card .stat-item .stat-number.stages {
            color: #805ad5;
        }
        
        .school-card .stat-item .stat-label {
            font-size: 11px;
            color: #718096;
            display: block;
            margin-top: 2px;
        }
        
        .school-card .card-actions {
            display: flex;
            gap: 8px;
            margin-top: 12px;
            flex-wrap: wrap;
        }
        
        .school-card .card-actions a {
            flex: 1;
            padding: 7px 12px;
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
            min-width: 60px;
        }
        
        .btn-edit {
            background: #edf2f7;
            color: #2d3748;
        }
        
        .btn-edit:hover {
            background: #e2e8f0;
        }
        
        .btn-stages {
            background: #ebf4ff;
            color: #3182ce;
        }
        
        .btn-stages:hover {
            background: #bee3f8;
        }
        
        .btn-students {
            background: #f0fff4;
            color: #38a169;
        }
        
        .btn-students:hover {
            background: #c6f6d5;
        }
        
        .btn-delete {
            background: #fff5f5;
            color: #e53e3e;
        }
        
        .btn-delete:hover {
            background: #fed7d7;
        }
        
        /* ===== الحالة الفارغة ===== */
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
        
        .empty-state .btn-add {
            display: inline-flex;
            margin-top: 10px;
        }
        
        /* ===== مودال تأكيد الحذف ===== */
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
            backdrop-filter: blur(4px);
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
                transform: scale(0.9) translateY(-20px);
            }
            to {
                opacity: 1;
                transform: scale(1) translateY(0);
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
            line-height: 1.8;
        }
        
        .modal-box .modal-actions {
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        
        .modal-box .modal-actions button,
        .modal-box .modal-actions a {
            padding: 10px 30px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            text-decoration: none;
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
            transform: scale(1.02);
        }
        
        /* ===== استجابة للشاشات ===== */
        @media (max-width: 992px) {
            .schools-grid {
                grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            }
        }
        
        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                align-items: stretch;
            }
            
            .page-header .header-actions {
                flex-direction: column;
            }
            
            .btn-add, .btn-delete-all {
                justify-content: center;
                width: 100%;
            }
            
            .schools-grid {
                grid-template-columns: 1fr;
            }
            
            .school-card .stats-row {
                grid-template-columns: repeat(3, 1fr);
            }
            
            .school-card .card-actions a {
                flex: 1 1 45%;
            }
        }
        
        @media (max-width: 480px) {
            .school-card .stats-row {
                grid-template-columns: 1fr 1fr;
            }
            
            .school-card .card-actions a {
                flex: 1 1 100%;
            }
            
            .modal-box {
                padding: 20px;
                width: 95%;
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
                            <span class="badge"><?php echo count($schools); ?></span>
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
                    <h1>المدارس والمعاهد</h1>
                </div>
                <div class="header-right">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" placeholder="بحث في المدارس..." id="searchInput">
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
                        <i class="fas fa-school" style="color: #667eea;"></i>
                        قائمة المدارس والمعاهد
                        <span style="font-size: 14px; color: #718096; font-weight: 400;">
                            (<?php echo count($schools); ?>)
                        </span>
                    </h2>
                    <div class="header-actions">
                        <?php if (count($schools) > 0): ?>
                        <button onclick="confirmDeleteAll()" class="btn-delete-all">
                            <i class="fas fa-trash-alt"></i>
                            حذف جميع المدارس
                        </button>
                        <?php endif; ?>
                        <a href="add.php" class="btn-add">
                            <i class="fas fa-plus"></i>
                            إضافة مدرسة / معهد
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
                
                <?php if ($error): ?>
                <div class="alert alert-error">
                    <i class="fas fa-exclamation-circle"></i>
                    <?php echo $error; ?>
                    <button class="close-alert" onclick="this.parentElement.style.display='none'">&times;</button>
                </div>
                <?php endif; ?>

                <!-- عرض المدارس -->
                <?php if (count($schools) > 0): ?>
                <div class="schools-grid" id="schoolsGrid">
                    <?php foreach($schools as $school): 
                        $stats = getSchoolStats($school['id']);
                    ?>
                    <div class="school-card" data-name="<?php echo strtolower($school['name']); ?>">
                        <div class="card-header">
                            <h3 class="school-name"><?php echo htmlspecialchars($school['name']); ?></h3>
                            <span class="school-type <?php echo $school['type']; ?>">
                                <?php echo $school['type'] == 'school' ? '🏫 مدرسة' : '🏛️ معهد'; ?>
                            </span>
                        </div>
                        
                        <div class="card-body">
                            <?php if ($school['address']): ?>
                            <div class="info-row">
                                <i class="fas fa-map-marker-alt"></i>
                                <span><?php echo htmlspecialchars($school['address']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($school['phone']): ?>
                            <div class="info-row">
                                <i class="fas fa-phone"></i>
                                <span><?php echo htmlspecialchars($school['phone']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if ($school['email']): ?>
                            <div class="info-row">
                                <i class="fas fa-envelope"></i>
                                <span><?php echo htmlspecialchars($school['email']); ?></span>
                            </div>
                            <?php endif; ?>
                            
                            <?php if (!$school['address'] && !$school['phone'] && !$school['email']): ?>
                            <div class="info-row">
                                <i class="fas fa-info-circle"></i>
                                <span class="text-muted">لا توجد معلومات إضافية</span>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="stats-row">
                            <div class="stat-item">
                                <span class="stat-number students"><?php echo $stats['total_students'] ?? 0; ?></span>
                                <span class="stat-label">👨‍🎓 طلاب</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number stages"><?php echo $stats['total_stages'] ?? 0; ?></span>
                                <span class="stat-label">📚 مراحل</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number income"><?php echo number_format($stats['total_income'] ?? 0, 2); ?></span>
                                <span class="stat-label">💰 إيرادات</span>
                            </div>
                        </div>
                        
                        <div class="card-actions">
                            <a href="edit.php?id=<?php echo $school['id']; ?>" class="btn-edit">
                                <i class="fas fa-edit"></i> تعديل
                            </a>
                            <a href="../stages/index.php?school_id=<?php echo $school['id']; ?>" class="btn-stages">
                                <i class="fas fa-layer-group"></i> مراحل
                            </a>
                            <a href="../students/index.php?school_id=<?php echo $school['id']; ?>" class="btn-students">
                                <i class="fas fa-users"></i> طلاب
                            </a>
                            <a href="#" class="btn-delete" onclick="confirmDelete(<?php echo $school['id']; ?>, '<?php echo addslashes($school['name']); ?>')">
                                <i class="fas fa-trash"></i> حذف
                            </a>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-school"></i>
                    <h3>لا توجد مدارس أو معاهد</h3>
                    <p>قم بإضافة أول مدرسة أو معهد في نظامك</p>
                    <a href="add.php" class="btn-add">
                        <i class="fas fa-plus"></i>
                        إضافة مدرسة / معهد
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </main>
    </div>

    <!-- مودال تأكيد الحذف (مدرسة واحدة) -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>تأكيد الحذف</h3>
            <p>هل أنت متأكد من حذف <strong id="deleteSchoolName"></strong>؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                سيتم حذف جميع البيانات المرتبطة بهذه المدرسة:
                <br>
                • جميع الطلاب
                <br>
                • جميع المراحل
                <br>
                • جميع المعاملات المالية
            </p>
            <div class="modal-actions">
                <button class="btn-cancel-modal" onclick="closeModal()">إلغاء</button>
                <a href="#" id="confirmDeleteBtn" class="btn-confirm-delete">
                    نعم، حذف الكل
                </a>
            </div>
        </div>
    </div>

    <!-- مودال تأكيد حذف جميع المدارس -->
    <div class="modal-overlay" id="deleteAllModal">
        <div class="modal-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>⚠️ تحذير: حذف جميع المدارس</h3>
            <p>هل أنت متأكد من حذف جميع المدارس والمعاهد؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                سيتم حذف جميع البيانات التالية:
                <br>
                • جميع المدارس والمعاهد
                <br>
                • جميع المراحل الدراسية
                <br>
                • جميع الطلاب
                <br>
                • جميع المعاملات المالية
                <br>
                • جميع سجلات الترقيات
                <br><br>
                <strong>هذا الإجراء لا يمكن التراجع عنه!</strong>
            </p>
            <div class="modal-actions">
                <button class="btn-cancel-modal" onclick="closeDeleteAllModal()">إلغاء</button>
                <a href="delete_all.php" class="btn-confirm-delete">
                    نعم، حذف الكل
                </a>
            </div>
        </div>
    </div>

    <script src="../../assets/js/dashboard.js"></script>
    <script>
        // ===== تأكيد حذف مدرسة واحدة =====
        function confirmDelete(id, name) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('deleteSchoolName').textContent = name;
            document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
            modal.classList.add('active');
        }
        
        function closeModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        
        // ===== تأكيد حذف جميع المدارس =====
        function confirmDeleteAll() {
            document.getElementById('deleteAllModal').classList.add('active');
        }
        
        function closeDeleteAllModal() {
            document.getElementById('deleteAllModal').classList.remove('active');
        }
        
        // ===== إغلاق المودال عند الضغط على ESC =====
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeDeleteAllModal();
            }
        });
        
        // ===== إغلاق المودال عند الضغط خارجها =====
        document.getElementById('deleteModal').addEventListener('click', function(e) {
            if (e.target === this) closeModal();
        });
        
        document.getElementById('deleteAllModal').addEventListener('click', function(e) {
            if (e.target === this) closeDeleteAllModal();
        });
        
        // ===== البحث المباشر =====
        document.getElementById('searchInput').addEventListener('keyup', function() {
            const value = this.value.toLowerCase().trim();
            const cards = document.querySelectorAll('.school-card');
            let visible = 0;
            
            cards.forEach(card => {
                const name = card.getAttribute('data-name');
                if (name.includes(value)) {
                    card.style.display = 'block';
                    visible++;
                } else {
                    card.style.display = 'none';
                }
            });
            
            // تحديث عدد النتائج
            const countSpan = document.querySelector('.page-header h2 span');
            if (countSpan) {
                countSpan.textContent = `(${visible})`;
            }
        });
        
        // ===== إخفاء رسائل التنبيه تلقائياً =====
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(alert => {
                setTimeout(() => {
                    alert.style.opacity = '0';
                    alert.style.transition = 'opacity 0.5s ease';
                    setTimeout(() => {
                        alert.style.display = 'none';
                    }, 500);
                }, 4000);
            });
        }, 1000);
    </script>
</body>
</html>