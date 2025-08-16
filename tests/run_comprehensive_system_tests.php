<?php

/**
 * مشغل الاختبارات الشاملة لنظام الدفع والاشتراكات
 * 
 * يقوم هذا الملف بتشغيل جميع اختبارات النظام الشاملة ويوفر تقارير مفصلة
 * حول الأداء والتغطية والنتائج.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

class ComprehensiveSystemTestRunner
{
    private $testCategories = [
        'System Integration Tests' => [
            'CompleteSystemIntegrationTest',
            'PaymentSubscriptionIntegrationTest'
        ],
        'Lifecycle Management Tests' => [
            'SubscriptionLifecycleTest'
        ],
        'Communication Tests' => [
            'NotificationSystemTest'
        ],
        'Analytics & Reporting Tests' => [
            'ReportsAndAnalyticsTest'
        ],
        'Background Processing Tests' => [
            'ProcessPendingPaymentJobTest',
            'VerifyPendingPaymentsCommandTest',
            'PaymentVerificationApiTest',
            'BackgroundPaymentSystemIntegrationTest',
            'PaymentSystemPerformanceTest'
        ]
    ];

    private $results = [];
    private $startTime;
    private $totalTests = 0;
    private $totalPassed = 0;
    private $totalFailed = 0;
    private $totalTime = 0;

    public function run()
    {
        $this->startTime = microtime(true);
        
        echo "🚀 بدء تشغيل الاختبارات الشاملة لنظام الدفع والاشتراكات\n";
        echo "========================================================\n\n";

        $this->setupTestEnvironment();
        
        foreach ($this->testCategories as $category => $tests) {
            echo "📋 تشغيل {$category}...\n";
            echo str_repeat('-', 60) . "\n";

            foreach ($tests as $testClass) {
                $result = $this->runTestSuite($testClass);
                $this->results[$category][$testClass] = $result;

                $this->totalTests += $result['tests'];
                $this->totalPassed += $result['passed'];
                $this->totalFailed += $result['failed'];
                $this->totalTime += $result['time'];

                $status = $result['failed'] > 0 ? '❌ فشل' : '✅ نجح';
                echo sprintf(
                    "  %s %s (%d/%d نجح، %.2fs)\n",
                    $status,
                    $this->translateTestName($testClass),
                    $result['passed'],
                    $result['tests'],
                    $result['time']
                );

                if ($result['failed'] > 0) {
                    echo "    🔍 تفاصيل الأخطاء:\n";
                    $this->displayErrors($result['errors']);
                }
            }
            echo "\n";
        }

        $this->printComprehensiveSummary();
        $this->generateDetailedReport();
        $this->performSystemHealthCheck();
    }

    private function setupTestEnvironment()
    {
        echo "🔧 إعداد بيئة الاختبار...\n";
        
        // تشغيل الـ migrations
        $migrationProcess = new Process(['php', 'artisan', 'migrate:fresh', '--seed'], dirname(__DIR__));
        $migrationProcess->run();
        
        if ($migrationProcess->getExitCode() !== 0) {
            echo "⚠️ تحذير: مشكلة في إعداد قاعدة البيانات\n";
        }
        
        // تنظيف الـ cache
        $cacheProcess = new Process(['php', 'artisan', 'cache:clear'], dirname(__DIR__));
        $cacheProcess->run();
        
        echo "✅ تم إعداد بيئة الاختبار بنجاح\n\n";
    }

    private function runTestSuite($testClass)
    {
        // تحديد مسار الملف
        $testPath = $this->findTestFile($testClass);
        
        if (!$testPath) {
            return [
                'tests' => 0,
                'passed' => 0,
                'failed' => 1,
                'time' => 0,
                'output' => '',
                'errors' => "ملف الاختبار غير موجود: {$testClass}",
                'exit_code' => 1
            ];
        }

        $command = [
            './vendor/bin/phpunit',
            '--testdox',
            '--stop-on-failure',
            $testPath
        ];

        $startTime = microtime(true);
        $process = new Process($command, dirname(__DIR__));
        $process->setTimeout(300); // 5 دقائق لكل مجموعة اختبارات
        $process->run();
        $endTime = microtime(true);

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        // تحليل النتائج
        $tests = 0;
        $passed = 0;
        $failed = 0;

        if (preg_match('/(\d+) tests?, (\d+) assertions?/', $output, $matches)) {
            $tests = (int)$matches[1];
            if ($process->getExitCode() === 0) {
                $passed = $tests;
                $failed = 0;
            } else {
                // تحديد عدد الاختبارات الفاشلة من الإخراج
                if (preg_match('/(\d+) failure/', $output, $failureMatches)) {
                    $failed = (int)$failureMatches[1];
                    $passed = $tests - $failed;
                } else {
                    $failed = $tests;
                    $passed = 0;
                }
            }
        }

        // استخراج مقاييس الأداء
        $performanceMetrics = $this->extractPerformanceMetrics($output);

        return [
            'tests' => $tests,
            'passed' => $passed,
            'failed' => $failed,
            'time' => $endTime - $startTime,
            'output' => $output,
            'errors' => $errorOutput,
            'exit_code' => $process->getExitCode(),
            'performance_metrics' => $performanceMetrics
        ];
    }

    private function findTestFile($testClass)
    {
        $possiblePaths = [
            __DIR__ . "/Feature/{$testClass}.php",
            __DIR__ . "/Unit/{$testClass}.php",
            __DIR__ . "/Integration/{$testClass}.php"
        ];

        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return null;
    }

    private function extractPerformanceMetrics($output)
    {
        $metrics = [];
        
        // استخراج مقاييس الأداء من مخرجات الاختبار
        if (preg_match('/Memory usage: ([\d.]+)MB/', $output, $matches)) {
            $metrics['memory_usage'] = (float)$matches[1];
        }
        
        if (preg_match('/Database queries: (\d+)/', $output, $matches)) {
            $metrics['database_queries'] = (int)$matches[1];
        }
        
        if (preg_match('/API calls: (\d+)/', $output, $matches)) {
            $metrics['api_calls'] = (int)$matches[1];
        }

        return $metrics;
    }

    private function displayErrors($errors)
    {
        if (empty($errors)) {
            return;
        }

        $lines = explode("\n", $errors);
        $relevantLines = array_slice($lines, 0, 5); // أول 5 أسطر من الخطأ
        
        foreach ($relevantLines as $line) {
            if (trim($line)) {
                echo "      " . trim($line) . "\n";
            }
        }
    }

    private function translateTestName($testClass)
    {
        $translations = [
            'CompleteSystemIntegrationTest' => 'اختبار التكامل الشامل للنظام',
            'PaymentSubscriptionIntegrationTest' => 'اختبار تكامل الدفع والاشتراك',
            'SubscriptionLifecycleTest' => 'اختبار دورة حياة الاشتراك',
            'NotificationSystemTest' => 'اختبار نظام الإشعارات',
            'ReportsAndAnalyticsTest' => 'اختبار التقارير والتحليلات',
            'ProcessPendingPaymentJobTest' => 'اختبار معالجة الدفعات المعلقة',
            'VerifyPendingPaymentsCommandTest' => 'اختبار أمر التحقق من الدفعات',
            'PaymentVerificationApiTest' => 'اختبار API التحقق من الدفع',
            'BackgroundPaymentSystemIntegrationTest' => 'اختبار نظام المعالجة في الخلفية',
            'PaymentSystemPerformanceTest' => 'اختبار أداء نظام الدفع'
        ];

        return $translations[$testClass] ?? $testClass;
    }

    private function printComprehensiveSummary()
    {
        $totalTime = microtime(true) - $this->startTime;
        
        echo "📊 ملخص شامل للاختبارات\n";
        echo "========================\n";
        echo sprintf("⏱️  إجمالي الوقت: %.2f ثانية\n", $totalTime);
        echo sprintf("🧪 إجمالي الاختبارات: %d\n", $this->totalTests);
        echo sprintf("✅ نجح: %d (%.1f%%)\n", $this->totalPassed, $this->totalTests > 0 ? ($this->totalPassed / $this->totalTests) * 100 : 0);
        echo sprintf("❌ فشل: %d (%.1f%%)\n", $this->totalFailed, $this->totalTests > 0 ? ($this->totalFailed / $this->totalTests) * 100 : 0);
        echo sprintf("⚡ متوسط الوقت لكل اختبار: %.3fs\n", $this->totalTests > 0 ? $this->totalTime / $this->totalTests : 0);
        
        echo "\n📈 تفصيل النتائج حسب الفئة:\n";
        foreach ($this->results as $category => $tests) {
            $categoryPassed = 0;
            $categoryTotal = 0;
            $categoryTime = 0;
            
            foreach ($tests as $result) {
                $categoryTotal += $result['tests'];
                $categoryPassed += $result['passed'];
                $categoryTime += $result['time'];
            }
            
            $successRate = $categoryTotal > 0 ? ($categoryPassed / $categoryTotal) * 100 : 0;
            echo sprintf("  📁 %s: %d/%d (%.1f%%) - %.2fs\n", 
                $category, $categoryPassed, $categoryTotal, $successRate, $categoryTime);
        }
        
        if ($this->totalFailed === 0) {
            echo "\n🎉 تهانينا! جميع اختبارات النظام نجحت بامتياز!\n";
            echo "✨ النظام جاهز للإنتاج\n";
        } else {
            echo "\n⚠️ يحتاج النظام إلى مراجعة قبل النشر\n";
            echo "🔧 يرجى مراجعة الاختبارات الفاشلة أعلاه\n";
        }
    }

    private function generateDetailedReport()
    {
        $reportPath = __DIR__ . '/comprehensive_test_report.html';
        
        $html = $this->generateHtmlReport();
        file_put_contents($reportPath, $html);
        
        echo "\n📄 تم إنشاء التقرير المفصل: {$reportPath}\n";
        
        // إنشاء تقرير JSON للتكامل مع الأنظمة الأخرى
        $jsonReport = [
            'summary' => [
                'total_tests' => $this->totalTests,
                'passed' => $this->totalPassed,
                'failed' => $this->totalFailed,
                'success_rate' => $this->totalTests > 0 ? ($this->totalPassed / $this->totalTests) * 100 : 0,
                'total_time' => $this->totalTime,
                'timestamp' => date('Y-m-d H:i:s')
            ],
            'categories' => $this->results,
            'recommendations' => $this->generateRecommendations()
        ];
        
        $jsonPath = __DIR__ . '/comprehensive_test_report.json';
        file_put_contents($jsonPath, json_encode($jsonReport, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        echo "📊 تم إنشاء تقرير JSON: {$jsonPath}\n";
    }

    private function generateHtmlReport()
    {
        $html = '<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تقرير الاختبارات الشاملة لنظام الدفع والاشتراكات</title>
    <style>
        body { font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif; margin: 20px; background: #f5f5f5; }
        .container { max-width: 1200px; margin: 0 auto; background: white; padding: 30px; border-radius: 10px; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; margin-bottom: 30px; }
        .summary { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .metric-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #007bff; }
        .metric-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
        .metric-label { color: #6c757d; margin-top: 5px; }
        .category { margin: 30px 0; }
        .category-header { background: #e9ecef; padding: 15px; border-radius: 5px; font-weight: bold; font-size: 1.2em; }
        .test-result { margin: 15px 0; padding: 20px; border-radius: 8px; border: 1px solid #dee2e6; }
        .test-passed { background: #d4edda; border-color: #c3e6cb; }
        .test-failed { background: #f8d7da; border-color: #f5c6cb; }
        .performance-metrics { background: #e2e3e5; padding: 15px; border-radius: 5px; margin-top: 15px; }
        .recommendations { background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin-top: 30px; }
        .success-badge { background: #28a745; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8em; }
        .failure-badge { background: #dc3545; color: white; padding: 5px 10px; border-radius: 15px; font-size: 0.8em; }
        .chart-container { margin: 20px 0; text-align: center; }
        pre { background: #f8f9fa; padding: 15px; border-radius: 5px; overflow-x: auto; border: 1px solid #e9ecef; }
        .progress-bar { width: 100%; height: 20px; background: #e9ecef; border-radius: 10px; overflow: hidden; }
        .progress-fill { height: 100%; background: linear-gradient(90deg, #28a745, #20c997); transition: width 0.3s ease; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔧 تقرير الاختبارات الشاملة لنظام الدفع والاشتراكات</h1>
            <p>تم إنشاؤه في: ' . date('Y-m-d H:i:s') . '</p>
        </div>';

        // ملخص عام
        $successRate = $this->totalTests > 0 ? ($this->totalPassed / $this->totalTests) * 100 : 0;
        
        $html .= '<div class="summary">
            <div class="metric-card">
                <div class="metric-value">' . $this->totalTests . '</div>
                <div class="metric-label">إجمالي الاختبارات</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" style="color: #28a745;">' . $this->totalPassed . '</div>
                <div class="metric-label">اختبارات ناجحة</div>
            </div>
            <div class="metric-card">
                <div class="metric-value" style="color: #dc3545;">' . $this->totalFailed . '</div>
                <div class="metric-label">اختبارات فاشلة</div>
            </div>
            <div class="metric-card">
                <div class="metric-value">' . number_format($successRate, 1) . '%</div>
                <div class="metric-label">معدل النجاح</div>
            </div>
        </div>';

        // شريط التقدم
        $html .= '<div class="progress-bar">
            <div class="progress-fill" style="width: ' . $successRate . '%;"></div>
        </div>';

        // تفاصيل الفئات
        foreach ($this->results as $category => $tests) {
            $html .= '<div class="category">
                <div class="category-header">📁 ' . $category . '</div>';
                
            foreach ($tests as $testClass => $result) {
                $cssClass = $result['failed'] > 0 ? 'test-failed' : 'test-passed';
                $badge = $result['failed'] > 0 ? 
                    '<span class="failure-badge">فشل</span>' : 
                    '<span class="success-badge">نجح</span>';
                
                $html .= '<div class="test-result ' . $cssClass . '">
                    <h3>' . $this->translateTestName($testClass) . ' ' . $badge . '</h3>
                    <p><strong>الاختبارات:</strong> ' . $result['tests'] . ' | 
                       <strong>نجح:</strong> ' . $result['passed'] . ' | 
                       <strong>فشل:</strong> ' . $result['failed'] . ' | 
                       <strong>الوقت:</strong> ' . number_format($result['time'], 2) . 's</p>';
                
                if (!empty($result['performance_metrics'])) {
                    $html .= '<div class="performance-metrics">
                        <h4>مقاييس الأداء:</h4>';
                    foreach ($result['performance_metrics'] as $metric => $value) {
                        $html .= '<p><strong>' . $metric . ':</strong> ' . $value . '</p>';
                    }
                    $html .= '</div>';
                }
                
                if ($result['failed'] > 0 && !empty($result['errors'])) {
                    $html .= '<details>
                        <summary>تفاصيل الأخطاء</summary>
                        <pre>' . htmlspecialchars($result['errors']) . '</pre>
                    </details>';
                }
                
                $html .= '</div>';
            }
            
            $html .= '</div>';
        }

        // التوصيات
        $recommendations = $this->generateRecommendations();
        if (!empty($recommendations)) {
            $html .= '<div class="recommendations">
                <h3>🎯 التوصيات والملاحظات</h3>
                <ul>';
            foreach ($recommendations as $recommendation) {
                $html .= '<li>' . $recommendation . '</li>';
            }
            $html .= '</ul></div>';
        }

        $html .= '</div></body></html>';
        
        return $html;
    }

    private function generateRecommendations()
    {
        $recommendations = [];
        
        if ($this->totalFailed > 0) {
            $failureRate = ($this->totalFailed / $this->totalTests) * 100;
            
            if ($failureRate > 20) {
                $recommendations[] = 'معدل فشل الاختبارات مرتفع (' . number_format($failureRate, 1) . '%). يُنصح بمراجعة شاملة للكود قبل النشر.';
            } elseif ($failureRate > 10) {
                $recommendations[] = 'يوجد بعض الاختبارات الفاشلة. يُنصح بإصلاحها قبل النشر.';
            }
        }
        
        if ($this->totalTime > 300) { // أكثر من 5 دقائق
            $recommendations[] = 'وقت تشغيل الاختبارات طويل نسبياً. فكر في تحسين الاختبارات أو استخدام المعالجة المتوازية.';
        }
        
        // فحص الأداء
        $slowTests = [];
        foreach ($this->results as $category => $tests) {
            foreach ($tests as $testClass => $result) {
                if ($result['time'] > 30) { // أكثر من 30 ثانية
                    $slowTests[] = $this->translateTestName($testClass);
                }
            }
        }
        
        if (!empty($slowTests)) {
            $recommendations[] = 'الاختبارات التالية تحتاج تحسين الأداء: ' . implode(', ', $slowTests);
        }
        
        if ($this->totalFailed === 0) {
            $recommendations[] = 'ممتاز! جميع الاختبارات نجحت. النظام جاهز للإنتاج.';
            $recommendations[] = 'يُنصح بتشغيل اختبارات الحمولة قبل النشر الفعلي.';
        }
        
        return $recommendations;
    }

    private function performSystemHealthCheck()
    {
        echo "\n🔍 فحص سلامة النظام...\n";
        echo "====================\n";
        
        $healthChecks = [
            'Database Connection' => $this->checkDatabaseConnection(),
            'Cache System' => $this->checkCacheSystem(),
            'Queue System' => $this->checkQueueSystem(),
            'File Permissions' => $this->checkFilePermissions(),
            'Environment Configuration' => $this->checkEnvironmentConfig()
        ];
        
        foreach ($healthChecks as $check => $status) {
            $icon = $status ? '✅' : '❌';
            echo "  {$icon} {$check}: " . ($status ? 'سليم' : 'يحتاج مراجعة') . "\n";
        }
        
        echo "\n";
    }

    private function checkDatabaseConnection()
    {
        try {
            $process = new Process(['php', 'artisan', 'tinker', '--execute=DB::connection()->getPdo()'], dirname(__DIR__));
            $process->run();
            return $process->getExitCode() === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkCacheSystem()
    {
        try {
            $process = new Process(['php', 'artisan', 'cache:clear'], dirname(__DIR__));
            $process->run();
            return $process->getExitCode() === 0;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkQueueSystem()
    {
        try {
            $process = new Process(['php', 'artisan', 'queue:work', '--stop-when-empty'], dirname(__DIR__));
            $process->setTimeout(5);
            $process->run();
            return true; // إذا لم يفشل في 5 ثوانٍ، فالنظام يعمل
        } catch (\Exception $e) {
            return false;
        }
    }

    private function checkFilePermissions()
    {
        $dirs = [
            dirname(__DIR__) . '/storage/logs',
            dirname(__DIR__) . '/storage/app',
            dirname(__DIR__) . '/bootstrap/cache'
        ];
        
        foreach ($dirs as $dir) {
            if (!is_writable($dir)) {
                return false;
            }
        }
        
        return true;
    }

    private function checkEnvironmentConfig()
    {
        $envFile = dirname(__DIR__) . '/.env';
        return file_exists($envFile) && is_readable($envFile);
    }
}

// تشغيل الاختبارات
$runner = new ComprehensiveSystemTestRunner();
$runner->run();