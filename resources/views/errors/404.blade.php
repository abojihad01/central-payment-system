<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>غير موجود</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 0; 
            padding: 40px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .error-container { 
            max-width: 500px; 
            text-align: center;
            background: rgba(255,255,255,0.1);
            padding: 40px;
            border-radius: 20px;
            backdrop-filter: blur(10px);
        }
        .error-icon { font-size: 80px; margin: 20px 0; }
        .error-title { font-size: 24px; margin: 20px 0; }
        .error-message { margin: 20px 0; opacity: 0.8; }
        .btn { 
            background: #007bff; 
            color: white; 
            padding: 12px 24px; 
            text-decoration: none; 
            border-radius: 10px; 
            display: inline-block;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="error-container">
        <div class="error-icon">🔍</div>
        <h1 class="error-title">غير موجود</h1>
        <div class="error-message">
            @if(isset($message))
                {{ $message }}
            @else
                الصفحة أو المورد المطلوب غير موجود.
            @endif
        </div>
        <a href="/" class="btn">العودة للرئيسية</a>
        <a href="javascript:history.back()" class="btn">الرجوع</a>
    </div>
</body>
</html>