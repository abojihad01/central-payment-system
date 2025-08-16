<?php

/**
 * Payment System Test Suite Runner
 * 
 * This script runs comprehensive tests for the background payment verification system
 * and provides detailed reporting on test results and performance metrics.
 */

require_once __DIR__ . '/../vendor/autoload.php';

use Symfony\Component\Process\Process;

class PaymentTestRunner
{
    private $testCategories = [
        'Unit Tests' => [
            'ProcessPendingPaymentJobTest',
            'VerifyPendingPaymentsCommandTest'
        ],
        'Feature Tests' => [
            'PaymentVerificationApiTest',
            'BackgroundPaymentSystemIntegrationTest'
        ],
        'Performance Tests' => [
            'PaymentSystemPerformanceTest'
        ]
    ];

    private $results = [];

    public function run()
    {
        echo "🚀 Starting Payment System Test Suite\n";
        echo "=====================================\n\n";

        $totalTests = 0;
        $totalPassed = 0;
        $totalFailed = 0;

        foreach ($this->testCategories as $category => $tests) {
            echo "📋 Running {$category}...\n";
            echo str_repeat('-', 40) . "\n";

            foreach ($tests as $testClass) {
                $result = $this->runTest($testClass);
                $this->results[$category][$testClass] = $result;

                $totalTests += $result['tests'];
                $totalPassed += $result['passed'];
                $totalFailed += $result['failed'];

                $status = $result['failed'] > 0 ? '❌ FAIL' : '✅ PASS';
                echo sprintf(
                    "  %s %s (%d/%d passed, %.2fs)\n",
                    $status,
                    $testClass,
                    $result['passed'],
                    $result['tests'],
                    $result['time']
                );
            }
            echo "\n";
        }

        $this->printSummary($totalTests, $totalPassed, $totalFailed);
        $this->generateReport();
    }

    private function runTest($testClass)
    {
        $command = ['./vendor/bin/phpunit', '--testdox', "tests/Unit/{$testClass}.php", "tests/Feature/{$testClass}.php"];
        
        // Try both Unit and Feature directories
        if (file_exists(__DIR__ . "/Unit/{$testClass}.php")) {
            $command = ['./vendor/bin/phpunit', '--testdox', "tests/Unit/{$testClass}.php"];
        } elseif (file_exists(__DIR__ . "/Feature/{$testClass}.php")) {
            $command = ['./vendor/bin/phpunit', '--testdox', "tests/Feature/{$testClass}.php"];
        }

        $startTime = microtime(true);
        $process = new Process($command, dirname(__DIR__));
        $process->run();
        $endTime = microtime(true);

        $output = $process->getOutput();
        $errorOutput = $process->getErrorOutput();

        // Parse results
        $tests = 0;
        $passed = 0;
        $failed = 0;

        if (preg_match('/(\d+) tests?, (\d+) assertions?/', $output, $matches)) {
            $tests = (int)$matches[1];
            $passed = $process->getExitCode() === 0 ? $tests : 0;
            $failed = $tests - $passed;
        }

        // Extract any performance metrics from output
        $metrics = $this->extractPerformanceMetrics($output);

        return [
            'tests' => $tests,
            'passed' => $passed,
            'failed' => $failed,
            'time' => $endTime - $startTime,
            'output' => $output,
            'errors' => $errorOutput,
            'metrics' => $metrics,
            'exit_code' => $process->getExitCode()
        ];
    }

    private function extractPerformanceMetrics($output)
    {
        $metrics = [];
        
        // Extract performance data from test output
        if (preg_match('/Payment Creation: ([\d.]+)s/', $output, $matches)) {
            $metrics['creation_time'] = (float)$matches[1];
        }
        
        if (preg_match('/Throughput: ([\d.]+) payments\/sec/', $output, $matches)) {
            $metrics['throughput'] = (float)$matches[1];
        }
        
        if (preg_match('/Memory Usage:.*?Peak Usage: ([\d.]+)MB/s', $output, $matches)) {
            $metrics['peak_memory'] = (float)$matches[1];
        }

        return $metrics;
    }

    private function printSummary($totalTests, $totalPassed, $totalFailed)
    {
        echo "📊 Test Summary\n";
        echo "===============\n";
        echo sprintf("Total Tests: %d\n", $totalTests);
        echo sprintf("Passed: %d (%.1f%%)\n", $totalPassed, $totalTests > 0 ? ($totalPassed / $totalTests) * 100 : 0);
        echo sprintf("Failed: %d (%.1f%%)\n", $totalFailed, $totalTests > 0 ? ($totalFailed / $totalTests) * 100 : 0);
        
        if ($totalFailed === 0) {
            echo "\n🎉 All tests passed! The payment system is working correctly.\n";
        } else {
            echo "\n⚠️  Some tests failed. Please review the detailed results above.\n";
        }
    }

    private function generateReport()
    {
        $reportPath = __DIR__ . '/payment_test_report.html';
        
        $html = $this->generateHtmlReport();
        file_put_contents($reportPath, $html);
        
        echo "\n📄 Detailed report generated: {$reportPath}\n";
    }

    private function generateHtmlReport()
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <title>Payment System Test Report</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .header { background: #f8f9fa; padding: 20px; border-radius: 5px; }
        .category { margin: 20px 0; }
        .test-result { margin: 10px 0; padding: 15px; border-radius: 5px; }
        .test-passed { background: #d4edda; border: 1px solid #c3e6cb; }
        .test-failed { background: #f8d7da; border: 1px solid #f5c6cb; }
        .metrics { background: #e2e3e5; padding: 10px; border-radius: 3px; margin-top: 10px; }
        pre { background: #f8f9fa; padding: 10px; border-radius: 3px; overflow-x: auto; }
    </style>
</head>
<body>';

        $html .= '<div class="header">';
        $html .= '<h1>🔧 Payment System Test Report</h1>';
        $html .= '<p>Generated on: ' . date('Y-m-d H:i:s') . '</p>';
        $html .= '</div>';

        foreach ($this->results as $category => $tests) {
            $html .= "<div class='category'>";
            $html .= "<h2>{$category}</h2>";
            
            foreach ($tests as $testClass => $result) {
                $cssClass = $result['failed'] > 0 ? 'test-failed' : 'test-passed';
                $status = $result['failed'] > 0 ? 'FAILED' : 'PASSED';
                
                $html .= "<div class='test-result {$cssClass}'>";
                $html .= "<h3>{$testClass} - {$status}</h3>";
                $html .= "<p>Tests: {$result['tests']}, Passed: {$result['passed']}, Failed: {$result['failed']}</p>";
                $html .= "<p>Execution Time: " . number_format($result['time'], 2) . "s</p>";
                
                if (!empty($result['metrics'])) {
                    $html .= "<div class='metrics'>";
                    $html .= "<h4>Performance Metrics:</h4>";
                    foreach ($result['metrics'] as $metric => $value) {
                        $html .= "<p>{$metric}: {$value}</p>";
                    }
                    $html .= "</div>";
                }
                
                if (!empty($result['output'])) {
                    $html .= "<details><summary>Test Output</summary>";
                    $html .= "<pre>" . htmlspecialchars($result['output']) . "</pre>";
                    $html .= "</details>";
                }
                
                if (!empty($result['errors']) && $result['failed'] > 0) {
                    $html .= "<details><summary>Errors</summary>";
                    $html .= "<pre style='color: red;'>" . htmlspecialchars($result['errors']) . "</pre>";
                    $html .= "</details>";
                }
                
                $html .= "</div>";
            }
            
            $html .= "</div>";
        }

        $html .= '</body></html>';
        
        return $html;
    }
}

// Run the test suite
$runner = new PaymentTestRunner();
$runner->run();