// assets/js/dashboard.js

document.addEventListener('DOMContentLoaded', function() {
    // ===== تبديل الشريط الجانبي =====
    const toggleBtn = document.getElementById('toggleSidebar');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (toggleBtn) {
        toggleBtn.addEventListener('click', function() {
            sidebar.classList.toggle('open');
            
            // إضافة طبقة خلفية للشاشات الصغيرة
            if (window.innerWidth <= 768) {
                const overlay = document.querySelector('.sidebar-overlay');
                if (!overlay) {
                    const div = document.createElement('div');
                    div.className = 'sidebar-overlay';
                    div.style.cssText = `
                        position: fixed;
                        top: 0;
                        left: 0;
                        width: 100%;
                        height: 100%;
                        background: rgba(0,0,0,0.5);
                        z-index: 999;
                    `;
                    div.addEventListener('click', function() {
                        sidebar.classList.remove('open');
                        this.remove();
                    });
                    document.body.appendChild(div);
                } else {
                    overlay.remove();
                }
            }
        });
    }
    
    // ===== إغلاق الشريط الجانبي عند الضغط على ESC =====
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && sidebar.classList.contains('open')) {
            sidebar.classList.remove('open');
            const overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.remove();
        }
    });
    
    // ===== تأثير ظهور البطاقات =====
    const cards = document.querySelectorAll('.stat-card, .action-card, .chart-card');
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.style.opacity = '1';
                entry.target.style.transform = 'translateY(0)';
            }
        });
    }, { threshold: 0.1 });
    
    cards.forEach(card => {
        card.style.opacity = '0';
        card.style.transform = 'translateY(20px)';
        card.style.transition = 'all 0.6s cubic-bezier(0.4, 0, 0.2, 1)';
        observer.observe(card);
    });
    
    // ===== تأثير حركة الأعمدة =====
    const bars = document.querySelectorAll('.bar-fill');
    setTimeout(() => {
        bars.forEach(bar => {
            const width = bar.style.width;
            bar.style.width = '0%';
            setTimeout(() => {
                bar.style.width = width;
            }, 100);
        });
    }, 300);
    
    // ===== البحث المباشر =====
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            // يمكن إضافة منطق البحث هنا
            console.log('بحث عن:', query);
        });
    }
    
    // ===== تحديث الوقت =====
    function updateTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('ar-EG', {
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit'
        });
        const dateString = now.toLocaleDateString('ar-EG', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        // يمكن إضافة عرض الوقت في الهيدر
        const timeElement = document.querySelector('.header-time');
        if (timeElement) {
            timeElement.textContent = `${dateString} - ${timeString}`;
        }
    }
    
    setInterval(updateTime, 1000);
    updateTime();
    
    // ===== إشعارات تفاعلية =====
    const notificationBtn = document.querySelector('.icon-btn .fa-bell')?.parentElement;
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            // إظهار قائمة الإشعارات
            const notifications = [
                '📚 تم إضافة طالب جديد',
                '💰 تم تسجيل إيراد جديد',
                '📊 تم تحديث التقارير'
            ];
            
            // إنشاء قائمة منسدلة للإشعارات
            const dropdown = document.createElement('div');
            dropdown.className = 'notifications-dropdown';
            dropdown.style.cssText = `
                position: absolute;
                top: 50px;
                right: 0;
                background: white;
                border-radius: 12px;
                box-shadow: 0 10px 30px rgba(0,0,0,0.15);
                padding: 15px;
                min-width: 250px;
                z-index: 1000;
            `;
            
            notifications.forEach(msg => {
                const item = document.createElement('div');
                item.style.cssText = `
                    padding: 10px;
                    border-bottom: 1px solid #edf2f7;
                    font-size: 14px;
                    color: #2d3748;
                    cursor: pointer;
                `;
                item.textContent = msg;
                item.addEventListener('click', function() {
                    this.style.background = '#edf2f7';
                });
                dropdown.appendChild(item);
            });
            
            // إغلاق القائمة عند النقر خارجها
            const closeDropdown = (e) => {
                if (!dropdown.contains(e.target) && e.target !== notificationBtn) {
                    dropdown.remove();
                    document.removeEventListener('click', closeDropdown);
                }
            };
            
            // إزالة القائمة القديمة إذا وجدت
            const oldDropdown = document.querySelector('.notifications-dropdown');
            if (oldDropdown) oldDropdown.remove();
            
            notificationBtn.style.position = 'relative';
            notificationBtn.appendChild(dropdown);
            document.addEventListener('click', closeDropdown);
        });
    }
    
    // ===== تأثيرات تفاعلية إضافية =====
    console.log('🚀 المبتكر المالي - لوحة التحكم تم تحميلها بنجاح');
    console.log('📊 عدد البطاقات:', cards.length);
    console.log('📈 عدد الأعمدة:', bars.length);
    
    // ===== إعادة تحميل البيانات بشكل دوري =====
    function refreshData() {
        // تحديث البيانات كل 30 ثانية
        console.log('🔄 تحديث البيانات...');
        // هنا يمكن إضافة طلب AJAX لتحديث البيانات
    }
    
    // تفعيل التحديث التلقائي
    // setInterval(refreshData, 30000);
});