<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Filament\Actions;
use Illuminate\Support\Facades\Artisan;

class SystemScheduler extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'النظام';
    protected static ?string $navigationLabel = 'مجدول Laravel (إرث)';
    protected static bool $shouldRegisterNavigation = false;
    protected static string $view = 'filament.pages.system-scheduler';

    public $scheduledTasks = [];
    public $lastRun = null;
    public $nextRun = null;
    public $isRunning = false;

    public function mount()
    {
        $this->loadScheduledTasks();
        $this->checkSchedulerStatus();
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('run_scheduler')
                ->label('تشغيل المجدول الآن')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    $this->runScheduler();
                })
                ->requiresConfirmation()
                ->modalHeading('تشغيل مجدول Laravel')
                ->modalDescription('سيتم تشغيل جميع المهام المجدولة فوراً.')
                ->modalSubmitActionLabel('تشغيل الآن'),

            Actions\Action::make('view_schedule_list')
                ->label('عرض جميع الجداول')
                ->icon('heroicon-o-list-bullet')
                ->color('info')
                ->action(function () {
                    $output = shell_exec('cd ' . base_path() . ' && php artisan schedule:list 2>&1');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Scheduled Tasks')
                        ->body('Check the modal for full list')
                        ->info()
                        ->send();
                })
                ->modalHeading('All Scheduled Tasks')
                ->modalContent(function () {
                    $output = shell_exec('cd ' . base_path() . ' && php artisan schedule:list 2>&1');
                    return view('filament.modals.schedule-list', ['output' => $output]);
                }),

            Actions\Action::make('test_schedule')
                ->label('Test Schedule')
                ->icon('heroicon-o-beaker')
                ->color('warning')
                ->action(function () {
                    $this->testSchedule();
                }),

            Actions\Action::make('clear_schedule_cache')
                ->label('Clear Schedule Cache')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->action(function () {
                    Artisan::call('schedule:clear-cache');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Schedule cache cleared')
                        ->success()
                        ->send();
                }),
        ];
    }

    public function loadScheduledTasks()
    {
        try {
            // Get scheduled tasks from Laravel
            $schedule = app(\Illuminate\Console\Scheduling\Schedule::class);
            $events = $schedule->events();

            $this->scheduledTasks = collect($events)->map(function ($event) {
                return [
                    'command' => $event->command ?? $event->getSummaryForDisplay(),
                    'expression' => $event->getExpression(),
                    'description' => $event->description ?? 'No description',
                    'next_due' => $event->nextRunDate()->format('Y-m-d H:i:s'),
                    'timezone' => $event->timezone ?? config('app.timezone'),
                    'environments' => $event->environments ?? ['*'],
                    'without_overlapping' => $event->withoutOverlapping ?? false,
                    'on_one_server' => $event->onOneServer ?? false,
                ];
            })->toArray();

        } catch (\Exception $e) {
            $this->scheduledTasks = [];
            \Filament\Notifications\Notification::make()
                ->title('Error loading scheduled tasks')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function checkSchedulerStatus()
    {
        // Check if schedule:run is currently running
        $processes = shell_exec('ps aux | grep "schedule:run" | grep -v grep');
        $this->isRunning = !empty($processes);

        // Get last run time from cache or logs
        $this->lastRun = cache('scheduler_last_run', 'Never');
        
        // Calculate next run (should be every minute if cron is set up)
        $this->nextRun = now()->addMinute()->startOfMinute()->format('Y-m-d H:i:s');
    }

    public function runScheduler()
    {
        try {
            $startTime = microtime(true);
            
            // Run the scheduler
            Artisan::call('schedule:run');
            $output = Artisan::output();
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            // Cache the last run time
            cache(['scheduler_last_run' => now()->format('Y-m-d H:i:s')], 3600);
            
            $this->lastRun = now()->format('Y-m-d H:i:s');
            
            \Filament\Notifications\Notification::make()
                ->title('Scheduler executed successfully')
                ->body("Execution time: {$executionTime}s")
                ->success()
                ->send();

            // Refresh the data
            $this->loadScheduledTasks();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Scheduler execution failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function testSchedule()
    {
        try {
            // Test individual schedule commands
            $testResults = [];
            
            foreach ($this->scheduledTasks as $task) {
                if (str_contains($task['command'], 'artisan')) {
                    // Extract the artisan command
                    preg_match('/artisan\s+(.+)/', $task['command'], $matches);
                    if (isset($matches[1])) {
                        $command = trim($matches[1]);
                        
                        try {
                            $startTime = microtime(true);
                            Artisan::call($command);
                            $executionTime = round(microtime(true) - $startTime, 2);
                            
                            $testResults[] = [
                                'command' => $command,
                                'status' => 'success',
                                'time' => $executionTime,
                                'output' => Artisan::output()
                            ];
                        } catch (\Exception $e) {
                            $testResults[] = [
                                'command' => $command,
                                'status' => 'failed',
                                'time' => 0,
                                'output' => $e->getMessage()
                            ];
                        }
                    }
                }
            }

            $successCount = collect($testResults)->where('status', 'success')->count();
            $totalCount = count($testResults);

            \Filament\Notifications\Notification::make()
                ->title('Schedule test completed')
                ->body("Tested {$totalCount} commands, {$successCount} successful")
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Schedule test failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    public function refresh()
    {
        $this->loadScheduledTasks();
        $this->checkSchedulerStatus();
        
        \Filament\Notifications\Notification::make()
            ->title('Data refreshed')
            ->success()
            ->send();
    }
}