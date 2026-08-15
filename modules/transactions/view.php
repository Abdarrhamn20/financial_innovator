<?php
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// التحقق من وجود معرف المعاملة
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header("Location: index.php");
    exit;
}

$id = (int)$_GET['id'];

// جلب بيانات المعاملة مع معلومات مرتبطة
$stmt = $pdo->prepare("
    SELECT t.*, 
           s.name as student_name, 
           s.student_code,
           s.phone as student_phone,
           sc.name as school_name,
           sc.type as school_type,
           sc.phone as school_phone,
           sc.email as school_email,
           sg.name as stage_name,
           sg.fee_amount as stage_fee
    FROM transactions t
    JOIN students s ON t.student_id = s.id
    JOIN schools sc ON s.school_id = sc.id
    LEFT JOIN stages sg ON t.stage_id = sg.id
    WHERE t.id = ?
");
$stmt->execute([$id]);
$transaction = $stmt->fetch();

if (!$transaction) {
    header("Location: index.php");
    exit;
}

// جلب المعاملات الأخرى لنفس الطالب
$stmt = $pdo->prepare("
    SELECT * FROM transactions 
    WHERE student_id = ? AND id != ? 
    ORDER BY transaction_date DESC LIMIT 5
");
$stmt->execute([$transaction['student_id'], $id]);
$other_transactions = $stmt->fetchAll();

// حساب إحصائيات الطالب
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN type = 'income' AND status = 'paid' THEN amount ELSE 0 END) as total_income,
        SUM(CASE WHEN type = 'expense' AND status = 'paid' THEN amount ELSE 0 END) as total_expenses
    FROM transactions 
    WHERE student_id = ?
");
$stmt->execute([$transaction['student_id']]);
$student_stats = $stmt->fetch();

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
    <title>تفاصيل المعاملة - المبتكر المالي</title>
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

        .btn-edit {
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

        .btn-edit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .btn-delete {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 20px;
            background: #e53e3e;
            color: white;
            border: none;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 500;
            text-decoration: none;
            transition: all 0.3s;
            font-family: 'Tajawal', sans-serif;
            cursor: pointer;
        }

        .btn-delete:hover {
            background: #c53030;
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(229, 62, 62, 0.3);
        }

        /* بطاقة المعاملة */
        .transaction-card {
            background: white;
            border-radius: 16px;
            padding: 30px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
            border-right: 5px solid <?php echo $transaction['type'] == 'income' ? '#38a169' : '#e53e3e'; ?>;
        }

        .transaction-card .card-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            flex-wrap: wrap;
            gap: 15px;
            margin-bottom: 20px;
        }

        .transaction-card .transaction-type-badge {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 10px 25px;
            border-radius: 12px;
            font-size: 18px;
            font-weight: 700;
        }

        .transaction-card .transaction-type-badge.income {
            background: #c6f6d5;
            color: #22543d;
        }

        .transaction-card .transaction-type-badge.expense {
            background: #fed7d7;
            color: #9b2c2c;
        }

        .transaction-card .transaction-type-badge i {
            font-size: 20px;
        }

        .transaction-card .transaction-amount {
            font-size: 36px;
            font-weight: 800;
        }

        .transaction-card .transaction-amount.income {
            color: #38a169;
        }

        .transaction-card .transaction-amount.expense {
            color: #e53e3e;
        }

        .transaction-card .transaction-amount .currency {
            font-size: 20px;
            font-weight: 500;
            color: #718096;
        }

        .transaction-card .transaction-meta {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 2px solid #edf2f7;
        }

        .transaction-card .transaction-meta .meta-item {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .transaction-card .transaction-meta .meta-item i {
            width: 35px;
            height: 35px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f7fafc;
            border-radius: 50%;
            color: #667eea;
        }

        .transaction-card .transaction-meta .meta-item .meta-label {
            font-size: 12px;
            color: #718096;
        }

        .transaction-card .transaction-meta .meta-item .meta-value {
            font-size: 14px;
            font-weight: 600;
            color: #2d3748;
        }

        .status-badge-large {
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge-large.paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge-large.pending {
            background: #fefcbf;
            color: #975a16;
        }

        .status-badge-large.cancelled {
            background: #fed7d7;
            color: #9b2c2c;
        }

        /* شبكة المعلومات */
        .info-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        }

        .info-card .card-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .info-card .card-title i {
            color: #667eea;
            margin-left: 8px;
        }

        .info-card .info-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f7fafc;
        }

        .info-card .info-row:last-child {
            border-bottom: none;
        }

        .info-card .info-row .label {
            color: #718096;
            font-size: 13px;
        }

        .info-card .info-row .value {
            color: #2d3748;
            font-weight: 500;
            font-size: 13px;
        }

        .info-card .info-row .value a {
            color: #667eea;
            text-decoration: none;
        }

        .info-card .info-row .value a:hover {
            text-decoration: underline;
        }

        /* جدول المعاملات الأخرى */
        .other-transactions {
            background: white;
            border-radius: 16px;
            padding: 25px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            margin-bottom: 25px;
        }

        .other-transactions .table-title {
            font-size: 16px;
            font-weight: 700;
            color: #2d3748;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 2px solid #edf2f7;
        }

        .other-transactions .table-title i {
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

        .amount-income {
            color: #38a169;
            font-weight: 600;
        }

        .amount-expense {
            color: #e53e3e;
            font-weight: 600;
        }

        .status-badge-sm {
            padding: 2px 10px;
            border-radius: 20px;
            font-size: 11px;
            font-weight: 600;
            display: inline-block;
        }

        .status-badge-sm.paid {
            background: #c6f6d5;
            color: #22543d;
        }

        .status-badge-sm.pending {
            background: #fefcbf;
            color: #975a16;
        }

        .status-badge-sm.cancelled {
            background: #fed7d7;
            color: #9b2c2c;
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

            .btn-back, .btn-edit, .btn-delete {
                justify-content: center;
            }

            .info-grid {
                grid-template-columns: 1fr;
            }

            .transaction-card .transaction-meta {
                grid-template-columns: 1fr;
            }

            .transaction-card .card-header {
                flex-direction: column;
                align-items: stretch;
            }

            .transaction-card .transaction-amount {
                font-size: 28px;
            }
        }

        @media (max-width: 480px) {
            .transaction-card {
                padding: 20px;
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
                    <h1>تفاصيل المعاملة</h1>
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
                        <i class="fas fa-file-invoice" style="color: #667eea;"></i>
                        تفاصيل المعاملة #<?php echo $transaction['id']; ?>
                    </h2>
                    <div class="header-actions">
                        <a href="index.php" class="btn-back">
                            <i class="fas fa-arrow-right"></i>
                            العودة للقائمة
                        </a>
                        <a href="edit.php?id=<?php echo $transaction['id']; ?>" class="btn-edit">
                            <i class="fas fa-edit"></i>
                            تعديل
                        </a>
                        <button onclick="confirmDelete(<?php echo $transaction['id']; ?>)" class="btn-delete">
                            <i class="fas fa-trash"></i>
                            حذف
                        </button>
                    </div>
                </div>

                <!-- بطاقة المعاملة الرئيسية -->
                <div class="transaction-card">
                    <div class="card-header">
                        <div>
                            <span class="transaction-type-badge <?php echo $transaction['type']; ?>">
                                <i class="fas fa-<?php echo $transaction['type'] == 'income' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                <?php echo $type_labels[$transaction['type']]; ?>
                            </span>
                            <div style="margin-top: 10px;">
                                <span class="status-badge-large <?php echo $transaction['status']; ?>">
                                    <?php echo $status_labels[$transaction['status']]; ?>
                                </span>
                            </div>
                        </div>
                        <div>
                            <div class="transaction-amount <?php echo $transaction['type']; ?>">
                                <?php echo $transaction['type'] == 'income' ? '+' : '-'; ?>
                                <?php echo number_format($transaction['amount'], 2); ?>
                                <span class="currency"><?php echo currencySymbol(); ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="transaction-meta">
                        <div class="meta-item">
                            <i class="fas fa-user-graduate"></i>
                            <div>
                                <div class="meta-label">الطالب</div>
                                <div class="meta-value">
                                    <a href="../students/view.php?id=<?php echo $transaction['student_id']; ?>">
                                        <?php echo htmlspecialchars($transaction['student_name']); ?>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-school"></i>
                            <div>
                                <div class="meta-label">المدرسة / المعهد</div>
                                <div class="meta-value"><?php echo htmlspecialchars($transaction['school_name']); ?></div>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-layer-group"></i>
                            <div>
                                <div class="meta-label">المرحلة</div>
                                <div class="meta-value"><?php echo htmlspecialchars($transaction['stage_name'] ?? 'غير محدد'); ?></div>
                            </div>
                        </div>
                        <div class="meta-item">
                            <i class="fas fa-calendar-alt"></i>
                            <div>
                                <div class="meta-label">تاريخ المعاملة</div>
                                <div class="meta-value"><?php echo date('Y-m-d', strtotime($transaction['transaction_date'])); ?></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- شبكة المعلومات -->
                <div class="info-grid">
                    <div class="info-card">
                        <div class="card-title">
                            <i class="fas fa-info-circle"></i>
                            تفاصيل المعاملة
                        </div>
                        <div class="info-row">
                            <span class="label">رقم المعاملة</span>
                            <span class="value">#<?php echo $transaction['id']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">النوع</span>
                            <span class="value"><?php echo $type_labels[$transaction['type']]; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">المبلغ</span>
                            <span class="value" style="color: <?php echo $transaction['type'] == 'income' ? '#38a169' : '#e53e3e'; ?>; font-weight: 700;">
                                <?php echo $transaction['type'] == 'income' ? '+' : '-'; ?>
                                <?php echo formatLibyanCurrency($transaction['amount']); ?>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">طريقة الدفع</span>
                            <span class="value"><?php echo $payment_methods[$transaction['payment_method']] ?? $transaction['payment_method']; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">الحالة</span>
                            <span class="value">
                                <span class="status-badge-sm <?php echo $transaction['status']; ?>">
                                    <?php echo $status_labels[$transaction['status']]; ?>
                                </span>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">تاريخ الإنشاء</span>
                            <span class="value"><?php echo date('Y-m-d H:i', strtotime($transaction['created_at'])); ?></span>
                        </div>
                        <?php if ($transaction['description']): ?>
                        <div class="info-row">
                            <span class="label">الوصف</span>
                            <span class="value"><?php echo htmlspecialchars($transaction['description']); ?></span>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="info-card">
                        <div class="card-title">
                            <i class="fas fa-user-graduate"></i>
                            معلومات الطالب
                        </div>
                        <div class="info-row">
                            <span class="label">الاسم</span>
                            <span class="value">
                                <a href="../students/view.php?id=<?php echo $transaction['student_id']; ?>">
                                    <?php echo htmlspecialchars($transaction['student_name']); ?>
                                </a>
                            </span>
                        </div>
                        <div class="info-row">
                            <span class="label">رقم الطالب</span>
                            <span class="value"><?php echo htmlspecialchars($transaction['student_code'] ?? '---'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">رقم الهاتف</span>
                            <span class="value"><?php echo htmlspecialchars($transaction['student_phone'] ?? '---'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">المدرسة</span>
                            <span class="value"><?php echo htmlspecialchars($transaction['school_name']); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">نوع المؤسسة</span>
                            <span class="value"><?php echo $transaction['school_type'] == 'school' ? 'مدرسة' : 'معهد'; ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">المرحلة</span>
                            <span class="value"><?php echo htmlspecialchars($transaction['stage_name'] ?? 'غير محدد'); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label">رسوم المرحلة</span>
                            <span class="value"><?php echo formatLibyanCurrency($transaction['stage_fee'] ?? 0); ?></span>
                        </div>
                        <div class="info-row" style="border-top: 2px solid #edf2f7; padding-top: 10px; margin-top: 5px;">
                            <span class="label" style="font-weight: 700;">إجمالي إيرادات الطالب</span>
                            <span class="value" style="color: #38a169; font-weight: 700;"><?php echo formatLibyanCurrency($student_stats['total_income'] ?? 0); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label" style="font-weight: 700;">إجمالي مصروفات الطالب</span>
                            <span class="value" style="color: #e53e3e; font-weight: 700;"><?php echo formatLibyanCurrency($student_stats['total_expenses'] ?? 0); ?></span>
                        </div>
                        <div class="info-row">
                            <span class="label" style="font-weight: 700;">عدد المعاملات</span>
                            <span class="value" style="font-weight: 700;"><?php echo $student_stats['total'] ?? 0; ?></span>
                        </div>
                    </div>
                </div>

                <!-- معاملات أخرى لنفس الطالب -->
                <div class="other-transactions">
                    <div class="table-title">
                        <i class="fas fa-history"></i>
                        معاملات أخرى لنفس الطالب
                        <span style="font-size: 14px; font-weight: 400; color: #718096;">
                            (آخر 5 معاملات)
                        </span>
                    </div>
                    <?php if (count($other_transactions) > 0): ?>
                    <div class="table-scroll">
                        <table>
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>النوع</th>
                                    <th>المبلغ</th>
                                    <th>التاريخ</th>
                                    <th>الحالة</th>
                                    <th>الإجراء</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($other_transactions as $index => $ot): ?>
                                <tr>
                                    <td><?php echo $index + 1; ?></td>
                                    <td>
                                        <span style="color: <?php echo $ot['type'] == 'income' ? '#38a169' : '#e53e3e'; ?>;">
                                            <i class="fas fa-<?php echo $ot['type'] == 'income' ? 'arrow-up' : 'arrow-down'; ?>"></i>
                                            <?php echo $type_labels[$ot['type']]; ?>
                                        </span>
                                    </td>
                                    <td class="<?php echo $ot['type'] == 'income' ? 'amount-income' : 'amount-expense'; ?>">
                                        <?php echo $ot['type'] == 'income' ? '+' : '-'; ?>
                                        <?php echo formatLibyanCurrency($ot['amount']); ?>
                                    </td>
                                    <td><?php echo date('Y-m-d', strtotime($ot['transaction_date'])); ?></td>
                                    <td>
                                        <span class="status-badge-sm <?php echo $ot['status']; ?>">
                                            <?php echo $status_labels[$ot['status']]; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?php echo $ot['id']; ?>" style="color: #667eea; text-decoration: none; font-size: 13px;">
                                            <i class="fas fa-eye"></i> عرض
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-exchange-alt"></i>
                        <p>لا توجد معاملات أخرى لهذا الطالب</p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <!-- مودال تأكيد الحذف -->
    <div class="modal-overlay" id="deleteModal">
        <div class="modal-box">
            <i class="fas fa-exclamation-triangle"></i>
            <h3>تأكيد الحذف</h3>
            <p>هل أنت متأكد من حذف هذه المعاملة؟</p>
            <p style="font-size: 13px; color: #e53e3e;">
                <i class="fas fa-warning"></i>
                لا يمكن استعادة المعاملة بعد الحذف
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
        function confirmDelete(id) {
            const modal = document.getElementById('deleteModal');
            document.getElementById('confirmDeleteBtn').href = 'delete.php?id=' + id;
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

        // طباعة المعاملة
        document.addEventListener('keydown', function(e) {
            if (e.ctrlKey && e.key === 'p') {
                // يمكن إضافة وظيفة طباعة محسنة
            }
        });
    </script>
</body>
</html>