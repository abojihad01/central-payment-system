<?php

return [

    'single' => [

        'associate' => [
            'label' => 'ربط',
        ],

        'attach' => [
            'label' => 'إرفاق',
        ],

        'create' => [
            'label' => 'إنشاء :label',
        ],

        'delete' => [
            'label' => 'حذف',
        ],

        'detach' => [
            'label' => 'فصل',
        ],

        'dissociate' => [
            'label' => 'إلغاء الربط',
        ],

        'edit' => [
            'label' => 'تعديل',
        ],

        'export' => [
            'label' => 'تصدير',
        ],

        'import' => [
            'label' => 'استيراد',
        ],

        'replicate' => [
            'label' => 'تكرار',
        ],

        'restore' => [
            'label' => 'استعادة',
        ],

        'view' => [
            'label' => 'عرض',
        ],

    ],

    'multiple' => [

        'associate' => [
            'label' => 'ربط المحدد',
        ],

        'attach' => [
            'label' => 'إرفاق المحدد',
        ],

        'delete' => [
            'label' => 'حذف المحدد',
        ],

        'detach' => [
            'label' => 'فصل المحدد',
        ],

        'dissociate' => [
            'label' => 'إلغاء ربط المحدد',
        ],

        'export' => [
            'label' => 'تصدير المحدد',
        ],

        'replicate' => [
            'label' => 'تكرار المحدد',
        ],

        'restore' => [
            'label' => 'استعادة المحدد',
        ],

    ],

    'import' => [

        'completed' => [

            'actions' => [

                'download_failed_rows_csv' => [
                    'label' => 'تحميل معلومات الصفوف الفاشلة|تحميل معلومات الصفوف الفاشلة',
                ],

            ],

        ],

        'fields' => [

            'file' => [
                'label' => 'ملف',
                'placeholder' => 'رفع ملف CSV',
            ],

            'columns' => [
                'label' => 'أعمدة',
                'placeholder' => 'اختر عمود',
            ],

        ],

        'modal' => [

            'heading' => 'استيراد :label',

            'form' => [

                'heading' => 'استيراد :label',

            ],

            'table' => [

                'heading' => 'استيراد :label',

            ],

            'finished' => [

                'heading' => 'اكتمل الاستيراد',

            ],

        ],

        'notifications' => [

            'completed' => [

                'title' => 'اكتمل الاستيراد',

                'actions' => [

                    'download_failed_rows_csv' => [
                        'label' => 'تحميل معلومات الصفوف الفاشلة|تحميل معلومات الصفوف الفاشلة',
                    ],

                ],

            ],

            'max_rows' => [
                'title' => 'الملف كبير جداً',
                'body' => 'لا يمكنك استيراد أكثر من صف واحد في المرة.|لا يمكنك استيراد أكثر من :count صف في المرة.',
            ],

            'started' => [
                'title' => 'بدأ الاستيراد',
                'body' => 'بدأ الاستيراد وسيتم معالجة صف واحد في الخلفية.|بدأ الاستيراد وسيتم معالجة :count صف في الخلفية.',
            ],

        ],

        'example' => [
            'download' => 'تحميل ملف مثال',
        ],

    ],

    'export' => [

        'filename_prefix' => 'تصدير',

        'modal' => [

            'heading' => 'تصدير :label',

        ],

        'notifications' => [

            'completed' => [

                'title' => 'اكتمل التصدير',

                'actions' => [

                    'download' => [
                        'label' => 'تحميل',
                    ],

                ],

            ],

            'max_rows' => [
                'title' => 'التصدير كبير جداً',
                'body' => 'لا يمكنك تصدير أكثر من صف واحد في المرة.|لا يمكنك تصدير أكثر من :count صف في المرة.',
            ],

            'started' => [
                'title' => 'بدأ التصدير',
                'body' => 'بدأ التصدير وسيتم إعداد صف واحد للتحميل قريباً.|بدأ التصدير وسيتم إعداد :count صف للتحميل قريباً.',
            ],

        ],

    ],

];