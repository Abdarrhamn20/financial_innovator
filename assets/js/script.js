// assets/js/script.js

document.addEventListener('DOMContentLoaded', function() {
    // رسائل الترحيب والتفاعلات
    console.log('🚀 المبتكر المالي - نظام إدارة الطلاب والمصروفات');
    
    // إضافة تأثيرات حركية للبطاقات
    const cards = document.querySelectorAll('.stat-card, .school-card, .action-btn');
    cards.forEach(card => {
        card.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.02)';
        });
        card.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
    
    // تأكيد الحذف
    const deleteButtons = document.querySelectorAll('.delete-btn');
    deleteButtons.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('هل أنت متأكد من حذف هذا العنصر؟')) {
                e.preventDefault();
            }
        });
    });
    
    // تحميل ديناميكي للمراحل بناءً على المدرسة
    const schoolSelect = document.getElementById('school_id');
    const stageSelect = document.getElementById('stage_id');
    
    if (schoolSelect && stageSelect) {
        schoolSelect.addEventListener('change', function() {
            const schoolId = this.value;
            if (schoolId) {
                fetch(`/api/get_stages.php?school_id=${schoolId}`)
                    .then(response => response.json())
                    .then(data => {
                        stageSelect.innerHTML = '<option value="">اختر المرحلة</option>';
                        data.forEach(stage => {
                            const option = document.createElement('option');
                            option.value = stage.id;
                            option.textContent = stage.name;
                            stageSelect.appendChild(option);
                        });
                    });
            }
        });
    }
    
    // ترقية الطالب
    const promoteForm = document.getElementById('promote-form');
    if (promoteForm) {
        promoteForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            fetch('/api/promote_student.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert('تم ترقية الطالب بنجاح!');
                    location.reload();
                } else {
                    alert('حدث خطأ: ' + data.message);
                }
            });
        });
    }
    
    // تحديث الإحصائيات بشكل ديناميكي
    function updateStats() {
        fetch('/api/get_stats.php')
            .then(response => response.json())
            .then(data => {
                document.querySelector('.stat-number').textContent = data.total_students;
                // تحديث باقي الإحصائيات
            });
    }
    
    // تحديث الإحصائيات كل 30 ثانية
    setInterval(updateStats, 30000);
});