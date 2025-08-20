<?php

return [

    'components' => [

        'account' => [

            'breadcrumb' => 'الحساب',

            'navigation_label' => 'الحساب',

            'title' => 'الحساب',

        ],

        'pages' => [

            'health_check' => [

                'navigation_label' => 'فحص حالة النظام',

                'title' => 'فحص حالة النظام',

            ],

        ],

    ],

    'fabricator' => [

        'page_title' => 'منشئ التخطيط',

    ],

    'pages' => [

        'auth' => [

            'login' => [

                'actions' => [

                    'register' => [
                        'before' => 'أو',
                        'label' => 'سجل للحصول على حساب',
                    ],

                    'request_password_reset' => [
                        'label' => 'نسيت كلمة المرور؟',
                    ],

                ],

                'form' => [

                    'email' => [
                        'label' => 'عنوان البريد الإلكتروني',
                    ],

                    'password' => [
                        'label' => 'كلمة المرور',
                    ],

                    'remember' => [
                        'label' => 'تذكرني',
                    ],

                    'actions' => [

                        'authenticate' => [
                            'label' => 'تسجيل الدخول',
                        ],

                    ],

                ],

                'heading' => 'تسجيل الدخول',

                'notification' => [

                    'title' => 'نجح تسجيل الدخول',

                ],

            ],

            'password_reset' => [

                'request' => [

                    'actions' => [

                        'login' => [
                            'label' => 'العودة إلى تسجيل الدخول',
                        ],

                    ],

                    'form' => [

                        'email' => [
                            'label' => 'عنوان البريد الإلكتروني',
                        ],

                        'actions' => [

                            'request' => [
                                'label' => 'إرسال بريد إلكتروني',
                            ],

                        ],

                    ],

                    'heading' => 'إعادة تعيين كلمة المرور',

                    'notification' => [

                        'title' => 'تم إرسال بريد إلكتروني',

                    ],

                ],

                'reset' => [

                    'actions' => [

                        'login' => [
                            'label' => 'العودة إلى تسجيل الدخول',
                        ],

                    ],

                    'form' => [

                        'email' => [
                            'label' => 'عنوان البريد الإلكتروني',
                        ],

                        'password' => [
                            'label' => 'كلمة المرور',
                            'validation_attribute' => 'كلمة المرور',
                        ],

                        'password_confirmation' => [
                            'label' => 'تأكيد كلمة المرور',
                        ],

                        'actions' => [

                            'reset' => [
                                'label' => 'إعادة تعيين كلمة المرور',
                            ],

                        ],

                    ],

                    'heading' => 'إعادة تعيين كلمة المرور',

                    'notification' => [

                        'title' => 'تم إعادة تعيين كلمة المرور',

                    ],

                ],

            ],

            'register' => [

                'actions' => [

                    'login' => [
                        'before' => 'أو',
                        'label' => 'سجل الدخول إلى حسابك',
                    ],

                ],

                'form' => [

                    'email' => [
                        'label' => 'عنوان البريد الإلكتروني',
                    ],

                    'name' => [
                        'label' => 'الاسم',
                    ],

                    'password' => [
                        'label' => 'كلمة المرور',
                        'validation_attribute' => 'كلمة المرور',
                    ],

                    'password_confirmation' => [
                        'label' => 'تأكيد كلمة المرور',
                    ],

                    'actions' => [

                        'register' => [
                            'label' => 'التسجيل',
                        ],

                    ],

                ],

                'heading' => 'التسجيل',

                'notification' => [

                    'title' => 'تم التسجيل بنجاح',

                ],

            ],

            'email_verification' => [

                'actions' => [

                    'resend_notification' => [
                        'label' => 'إعادة الإرسال',
                    ],

                ],

                'heading' => 'تحقق من بريدك الإلكتروني',

                'notification' => [

                    'title' => 'تم إعادة الإرسال',

                ],

            ],

        ],

        'dashboard' => [

            'title' => 'لوحة التحكم',

            'heading' => 'لوحة التحكم',

            'description' => 'مرحباً بك في نظام إدارة المدفوعات',

            'actions' => [

                'filter' => [

                    'period' => [
                        'label' => 'الفترة',
                    ],

                ],

            ],

        ],

    ],

    'resources' => [

        'label' => 'تسمية',

        'plural_label' => 'التسميات',

        'navigation_label' => 'تسمية التنقل',

        'navigation_group' => 'مجموعة التنقل',

        'pages' => [

            'create_record' => [

                'title' => 'إنشاء :label',

                'breadcrumb' => 'إنشاء',

                'form' => [

                    'actions' => [

                        'cancel' => [
                            'label' => 'إلغاء',
                        ],

                        'create' => [
                            'label' => 'إنشاء',
                        ],

                        'create_another' => [
                            'label' => 'إنشاء وإنشاء آخر',
                        ],

                    ],

                ],

                'notifications' => [

                    'created' => [
                        'title' => 'تم الإنشاء',
                    ],

                ],

            ],

            'edit_record' => [

                'title' => 'تعديل :label',

                'breadcrumb' => 'تعديل',

                'form' => [

                    'actions' => [

                        'cancel' => [
                            'label' => 'إلغاء',
                        ],

                        'save' => [
                            'label' => 'حفظ التغييرات',
                        ],

                    ],

                ],

                'content' => [

                    'tab' => [
                        'label' => 'تعديل',
                    ],

                ],

                'notifications' => [

                    'saved' => [
                        'title' => 'محفوظ',
                    ],

                ],

            ],

            'view_record' => [

                'title' => 'عرض :label',

                'breadcrumb' => 'عرض',

            ],

            'list_records' => [

                'title' => ':label',

                'navigation_label' => 'قائمة :label',

                'breadcrumb' => 'قائمة',

                'page' => [

                    'actions' => [

                        'create' => [
                            'label' => 'إنشاء :label',
                        ],

                    ],

                ],

                'table' => [

                    'heading' => ':label',

                ],

            ],

        ],

    ],

    'layout' => [

        'actions' => [

            'logout' => [
                'label' => 'تسجيل الخروج',
            ],

            'open_database_notifications' => [
                'label' => 'فتح الإشعارات',
            ],

            'open_user_menu' => [
                'label' => 'قائمة المستخدم',
            ],

            'sidebar' => [

                'collapse' => [
                    'label' => 'طي الشريط الجانبي',
                ],

                'expand' => [
                    'label' => 'توسيع الشريط الجانبي',
                ],

            ],

            'theme_switcher' => [

                'label' => 'مبدل السمة',

                'light' => [
                    'label' => 'فاتح',
                ],

                'dark' => [
                    'label' => 'غامق',
                ],

                'system' => [
                    'label' => 'نظام',
                ],

            ],

        ],

    ],

    'widgets' => [

        'account' => [

            'widget' => [

                'actions' => [

                    'edit' => [
                        'label' => 'تعديل الحساب',
                    ],

                ],

            ],

        ],

        'filament_info' => [

            'actions' => [

                'open_documentation' => [
                    'label' => 'فتح الوثائق',
                ],

                'open_github' => [
                    'label' => 'فتح GitHub',
                ],

            ],

        ],

    ],

];