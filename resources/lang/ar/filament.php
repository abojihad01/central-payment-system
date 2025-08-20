<?php

return [
    // Dashboard
    'dashboard' => 'لوحة التحكم',
    
    // Navigation
    'navigation' => [
        'payments' => 'المدفوعات',
        'subscriptions' => 'الاشتراكات',
        'customers' => 'العملاء',
        'plans' => 'الخطط',
        'websites' => 'المواقع',
        'payment_accounts' => 'حسابات الدفع',
        'payment_gateways' => 'بوابات الدفع',
        'cron_jobs' => 'المهام المجدولة',
        'generated_links' => 'الروابط المولدة',
        'reports' => 'التقارير',
        'analytics' => 'التحليلات',
        'system' => 'النظام',
        'settings' => 'الإعدادات',
        'users' => 'المستخدمين',
    ],
    
    // Common actions
    'actions' => [
        'create' => 'إنشاء',
        'edit' => 'تعديل',
        'view' => 'عرض',
        'delete' => 'حذف',
        'save' => 'حفظ',
        'cancel' => 'إلغاء',
        'submit' => 'إرسال',
        'search' => 'بحث',
        'filter' => 'تصفية',
        'export' => 'تصدير',
        'import' => 'استيراد',
        'refresh' => 'تحديث',
        'run_now' => 'تشغيل الآن',
        'enable' => 'تمكين',
        'disable' => 'تعطيل',
        'activate' => 'تفعيل',
        'deactivate' => 'إلغاء التفعيل',
    ],
    
    // Table headers
    'table' => [
        'id' => 'المعرف',
        'name' => 'الاسم',
        'email' => 'البريد الإلكتروني',
        'status' => 'الحالة',
        'amount' => 'المبلغ',
        'currency' => 'العملة',
        'gateway' => 'البوابة',
        'created_at' => 'تاريخ الإنشاء',
        'updated_at' => 'تاريخ التحديث',
        'actions' => 'الإجراءات',
        'description' => 'الوصف',
        'command' => 'الأمر',
        'schedule' => 'الجدولة',
        'environment' => 'البيئة',
        'runs' => 'التشغيلات',
        'failures' => 'الأخطاء',
        'success_rate' => 'معدل النجاح',
        'last_run' => 'آخر تشغيل',
        'next_run' => 'التشغيل التالي',
        'active' => 'نشط',
    ],
    
    // Status labels
    'status' => [
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'pending' => 'معلق',
        'completed' => 'مكتمل',
        'failed' => 'فاشل',
        'cancelled' => 'ملغى',
        'expired' => 'منتهي الصلاحية',
        'processing' => 'قيد المعالجة',
        'overdue' => 'متأخر',
        'warning' => 'تحذير',
    ],
    
    // Messages
    'messages' => [
        'no_records' => 'لا توجد سجلات',
        'loading' => 'جارٍ التحميل...',
        'saved_successfully' => 'تم الحفظ بنجاح',
        'deleted_successfully' => 'تم الحذف بنجاح',
        'updated_successfully' => 'تم التحديث بنجاح',
        'created_successfully' => 'تم الإنشاء بنجاح',
        'action_completed' => 'تم إنجاز العملية بنجاح',
        'error_occurred' => 'حدث خطأ',
        'confirm_delete' => 'هل أنت متأكد من حذف هذا العنصر؟',
        'never_run' => 'لم يتم التشغيل مطلقاً',
    ],
    
    // Pagination
    'pagination' => [
        'showing' => 'عرض',
        'to' => 'إلى', 
        'of' => 'من',
        'results' => 'نتيجة',
        'per_page' => 'لكل صفحة',
    ],
    
    // Time periods
    'time' => [
        'minute' => 'دقيقة',
        'minutes' => 'دقائق',
        'hour' => 'ساعة',
        'hours' => 'ساعات',
        'day' => 'يوم',
        'days' => 'أيام',
        'week' => 'أسبوع',
        'weeks' => 'أسابيع',
        'month' => 'شهر',
        'months' => 'أشهر',
        'year' => 'سنة',
        'years' => 'سنوات',
        'ago' => 'منذ',
        'from_now' => 'من الآن',
    ],
    
    // Dashboard specific
    'dashboard' => [
        'widgets' => [
            'analytics_overview' => 'نظرة عامة على التحليلات',
            'revenue_chart' => 'مخطط الإيرادات',
            'subscription_metrics' => 'مقاييس الاشتراكات',
            'customer_analytics' => 'تحليلات العملاء',
            'fraud_detection_stats' => 'إحصائيات كشف الاحتيال',
        ],
        'stats' => [
            'daily_revenue' => 'الإيرادات اليومية',
            'monthly_revenue' => 'الإيرادات الشهرية',
            'mrr' => 'الإيرادات الشهرية المتكررة',
            'active_subscriptions' => 'الاشتراكات النشطة',
            'total_customers' => 'إجمالي العملاء',
            'fraud_alerts' => 'تنبيهات الاحتيال',
        ],
        'charts' => [
            'revenue_trend' => 'اتجاه الإيرادات',
            'subscription_growth' => 'نمو الاشتراكات',
            'customer_acquisition' => 'اكتساب العملاء',
            'payment_methods' => 'طرق الدفع',
        ],
    ],
    
    // Widgets
    'widgets' => [
        'overview' => 'نظرة عامة',
        'statistics' => 'الإحصائيات',
        'charts' => 'المخططات',
        'recent_activity' => 'النشاط الأخير',
        'quick_actions' => 'إجراءات سريعة',
        'health_check' => 'فحص الصحة',
        'system_status' => 'حالة النظام',
        'performance' => 'الأداء',
    ],
    
    // Reports
    'reports' => [
        'daily' => 'يومي',
        'weekly' => 'أسبوعي', 
        'monthly' => 'شهري',
        'quarterly' => 'ربع سنوي',
        'yearly' => 'سنوي',
        'custom' => 'مخصص',
        'revenue' => 'الإيرادات',
        'customers' => 'العملاء',
        'subscriptions' => 'الاشتراكات',
        'payments' => 'المدفوعات',
        'fraud' => 'الاحتيال',
        'performance' => 'الأداء',
    ],
    
    // System Dashboard
    'system' => [
        'dashboard' => 'لوحة تحكم النظام',
        'health' => 'صحة النظام',
        'monitoring' => 'المراقبة',
        'alerts' => 'التنبيهات',
        'logs' => 'السجلات',
        'backup' => 'النسخ الاحتياطي',
        'maintenance' => 'الصيانة',
        'security' => 'الأمان',
    ],
];