<meta name="locale" content="{{ app()->getLocale() }}">
<meta name="direction" content="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}">

@if(app()->getLocale() === 'ar')
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700&family=Tajawal:wght@300;400;500;700&display=swap" rel="stylesheet">
    <style>
        body, .fi-body {
            font-family: 'Cairo', 'Tajawal', Arial, sans-serif !important;
        }
        
        html {
            direction: rtl !important;
        }
    </style>
@endif