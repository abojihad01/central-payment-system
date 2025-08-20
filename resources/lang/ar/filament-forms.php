<?php

return [

    'fields' => [

        'code_editor' => [

            'actions' => [

                'copy_to_clipboard' => [

                    'label' => 'نسخ إلى الحافظة',

                    'tooltip' => 'نسخ',

                ],

            ],

        ],

        'file_upload' => [

            'editor' => [

                'actions' => [

                    'cancel' => [
                        'label' => 'إلغاء',
                    ],

                    'drag_crop' => [
                        'label' => 'وضع السحب "اقتصاص"',
                    ],

                    'drag_move' => [
                        'label' => 'وضع السحب "نقل"',
                    ],

                    'flip_horizontal' => [
                        'label' => 'قلب الصورة أفقياً',
                    ],

                    'flip_vertical' => [
                        'label' => 'قلب الصورة عمودياً',
                    ],

                    'move_down' => [
                        'label' => 'نقل الصورة لأسفل',
                    ],

                    'move_left' => [
                        'label' => 'نقل الصورة لليسار',
                    ],

                    'move_right' => [
                        'label' => 'نقل الصورة لليمين',
                    ],

                    'move_up' => [
                        'label' => 'نقل الصورة لأعلى',
                    ],

                    'reset' => [
                        'label' => 'إعادة تعيين',
                    ],

                    'rotate_left' => [
                        'label' => 'تدوير الصورة لليسار',
                    ],

                    'rotate_right' => [
                        'label' => 'تدوير الصورة لليمين',
                    ],

                    'set_aspect_ratio' => [
                        'label' => 'تعيين نسبة العرض إلى الارتفاع إلى :ratio',
                    ],

                    'save' => [
                        'label' => 'حفظ',
                    ],

                    'zoom_100' => [
                        'label' => 'تكبير الصورة إلى 100%',
                    ],

                    'zoom_in' => [
                        'label' => 'تكبير',
                    ],

                    'zoom_out' => [
                        'label' => 'تصغير',
                    ],

                ],

                'fields' => [

                    'height' => [
                        'label' => 'الارتفاع',
                        'unit' => 'بكسل',
                    ],

                    'rotation' => [
                        'label' => 'التدوير',
                        'unit' => 'درجة',
                    ],

                    'width' => [
                        'label' => 'العرض',
                        'unit' => 'بكسل',
                    ],

                    'x_position' => [
                        'label' => 'X',
                        'unit' => 'بكسل',
                    ],

                    'y_position' => [
                        'label' => 'Y',
                        'unit' => 'بكسل',
                    ],

                ],

                'aspect_ratios' => [

                    'label' => 'نسب العرض إلى الارتفاع',

                    'no_fixed' => [
                        'label' => 'حر',
                    ],

                ],

            ],

        ],

        'key_value' => [

            'actions' => [

                'add' => [
                    'label' => 'إضافة صف',
                ],

                'delete' => [
                    'label' => 'حذف صف',
                ],

                'reorder' => [
                    'label' => 'إعادة ترتيب صف',
                ],

            ],

            'fields' => [

                'key' => [
                    'label' => 'مفتاح',
                ],

                'value' => [
                    'label' => 'قيمة',
                ],

            ],

        ],

        'markdown_editor' => [

            'toolbar_buttons' => [
                'attach_files' => 'إرفاق ملفات',
                'blockquote' => 'اقتباس',
                'bold' => 'عريض',
                'bullet_list' => 'قائمة نقطية',
                'code_block' => 'كتلة كود',
                'heading' => 'عنوان',
                'italic' => 'مائل',
                'link' => 'رابط',
                'ordered_list' => 'قائمة مرقمة',
                'redo' => 'إعادة',
                'strike' => 'يتوسطه خط',
                'table' => 'جدول',
                'undo' => 'تراجع',
            ],

        ],

        'repeater' => [

            'actions' => [

                'add' => [
                    'label' => 'إضافة إلى :label',
                ],

                'add_between' => [
                    'label' => 'إدراج بين',
                ],

                'delete' => [
                    'label' => 'حذف',
                ],

                'reorder' => [
                    'label' => 'نقل',
                ],

                'move_down' => [
                    'label' => 'نقل لأسفل',
                ],

                'move_up' => [
                    'label' => 'نقل لأعلى',
                ],

                'collapse' => [
                    'label' => 'طي',
                ],

                'expand' => [
                    'label' => 'توسيع',
                ],

                'collapse_all' => [
                    'label' => 'طي الكل',
                ],

                'expand_all' => [
                    'label' => 'توسيع الكل',
                ],

            ],

        ],

        'rich_editor' => [

            'dialogs' => [

                'link' => [

                    'actions' => [
                        'link' => 'رابط',
                        'unlink' => 'إلغاء الرابط',
                    ],

                    'label' => 'عنوان URL',

                    'placeholder' => 'أدخل عنوان URL',

                ],

            ],

            'toolbar_buttons' => [
                'attach_files' => 'إرفاق ملفات',
                'blockquote' => 'اقتباس',
                'bold' => 'عريض',
                'bullet_list' => 'قائمة نقطية',
                'code_block' => 'كتلة كود',
                'h1' => 'عنوان',
                'h2' => 'عنوان',
                'h3' => 'عنوان فرعي',
                'italic' => 'مائل',
                'link' => 'رابط',
                'ordered_list' => 'قائمة مرقمة',
                'redo' => 'إعادة',
                'strike' => 'يتوسطه خط',
                'underline' => 'تحته خط',
                'undo' => 'تراجع',
            ],

        ],

        'select' => [

            'actions' => [

                'create_option' => [

                    'modal' => [

                        'heading' => 'إنشاء',

                        'actions' => [

                            'create' => [
                                'label' => 'إنشاء',
                            ],

                            'create_another' => [
                                'label' => 'إنشاء وإنشاء آخر',
                            ],

                        ],

                    ],

                ],

                'edit_option' => [

                    'modal' => [

                        'heading' => 'تعديل',

                        'actions' => [

                            'save' => [
                                'label' => 'حفظ',
                            ],

                        ],

                    ],

                ],

            ],

            'boolean' => [
                'true' => 'نعم',
                'false' => 'لا',
            ],

            'loading_message' => 'جارٍ التحميل...',

            'max_items_message' => 'يمكن تحديد عنصر واحد فقط.|يمكن تحديد :count عناصر فقط.',

            'no_search_results_message' => 'لا توجد خيارات تطابق بحثك.',

            'placeholder' => 'اختر خياراً',

            'searching_message' => 'جارٍ البحث...',

            'search_prompt' => 'ابدأ الكتابة للبحث...',

        ],

        'tags_input' => [
            'placeholder' => 'علامة جديدة',
        ],

        'wizard' => [

            'actions' => [

                'previous_step' => [
                    'label' => 'السابق',
                ],

                'next_step' => [
                    'label' => 'التالي',
                ],

            ],

        ],

    ],

    'actions' => [

        'modal' => [

            'actions' => [

                'cancel' => [
                    'label' => 'إلغاء',
                ],

                'confirm' => [
                    'label' => 'تأكيد',
                ],

                'submit' => [
                    'label' => 'إرسال',
                ],

            ],

        ],

    ],

    'notifications' => [

        'database_notifications' => [

            'modal' => [

                'heading' => 'الإشعارات',

                'actions' => [

                    'clear' => [
                        'label' => 'مسح',
                    ],

                    'mark_all_as_read' => [
                        'label' => 'تعليم الكل كمقروء',
                    ],

                ],

                'empty' => [
                    'heading' => 'لا توجد إشعارات',
                    'description' => 'تحقق مرة أخرى لاحقاً.',
                ],

            ],

        ],

    ],

];