<?php

return [

    'column_toggle' => [

        'heading' => 'الأعمدة',

    ],

    'columns' => [

        'text' => [
            'more_list_items' => 'و :count آخرين',
        ],

    ],

    'fields' => [

        'bulk_select_page' => [
            'label' => 'تحديد/إلغاء تحديد جميع العناصر للإجراءات الجماعية.',
        ],

        'bulk_select_record' => [
            'label' => 'تحديد/إلغاء تحديد العنصر :key للإجراءات الجماعية.',
        ],

        'search' => [
            'label' => 'بحث',
            'placeholder' => 'بحث',
            'indicator' => 'بحث',
        ],

    ],

    'summary' => [

        'heading' => 'ملخص',

        'subheadings' => [
            'all' => 'جميع :label',
            'group' => 'ملخص :group',
            'page' => 'هذه الصفحة',
        ],

        'summarizers' => [

            'average' => [
                'label' => 'المتوسط',
            ],

            'count' => [
                'label' => 'العدد',
            ],

            'sum' => [
                'label' => 'المجموع',
            ],

        ],

    ],

    'actions' => [

        'disable_reordering' => [
            'label' => 'إنهاء إعادة ترتيب السجلات',
        ],

        'enable_reordering' => [
            'label' => 'إعادة ترتيب السجلات',
        ],

        'filter' => [
            'label' => 'تصفية',
        ],

        'group' => [
            'label' => 'مجموعة',
        ],

        'open_bulk_actions' => [
            'label' => 'فتح الإجراءات',
        ],

        'toggle_columns' => [
            'label' => 'تبديل الأعمدة',
        ],

    ],

    'empty' => [

        'heading' => 'لا توجد :model',

        'description' => 'أنشئ :model للبدء.',

    ],

    'filters' => [

        'actions' => [

            'remove' => [
                'label' => 'إزالة المرشح',
            ],

            'remove_all' => [
                'label' => 'إزالة جميع المرشحات',
                'tooltip' => 'إزالة جميع المرشحات',
            ],

            'reset' => [
                'label' => 'إعادة تعيين',
            ],

        ],

        'heading' => 'مرشحات',

        'indicator' => 'المرشحات النشطة',

        'multi_select' => [
            'placeholder' => 'الكل',
        ],

        'select' => [
            'placeholder' => 'الكل',
        ],

        'trashed' => [

            'label' => 'السجلات المحذوفة',

            'only_trashed' => 'السجلات المحذوفة فقط',

            'with_trashed' => 'مع السجلات المحذوفة',

            'without_trashed' => 'بدون السجلات المحذوفة',

        ],

    ],

    'grouping' => [

        'fields' => [

            'group' => [
                'label' => 'مجموعة بواسطة',
                'placeholder' => 'مجموعة بواسطة',
            ],

            'direction' => [

                'label' => 'اتجاه المجموعة',

                'options' => [
                    'asc' => 'تصاعدي',
                    'desc' => 'تنازلي',
                ],

            ],

        ],

    ],

    'reorder_indicator' => 'اسحب وأفلت السجلات بالترتيب.',

    'selection_indicator' => [

        'selected_count' => 'تم تحديد سجل واحد|تم تحديد :count سجلات',

        'actions' => [

            'select_all' => [
                'label' => 'تحديد جميع :count',
            ],

            'deselect_all' => [
                'label' => 'إلغاء تحديد الكل',
            ],

        ],

    ],

    'sorting' => [

        'fields' => [

            'column' => [
                'label' => 'ترتيب بواسطة',
            ],

            'direction' => [

                'label' => 'اتجاه الترتيب',

                'options' => [
                    'asc' => 'تصاعدي',
                    'desc' => 'تنازلي',
                ],

            ],

        ],

    ],

];