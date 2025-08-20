<?php

namespace App\Filament\Resources\QueueJobResource\Pages;

use App\Filament\Resources\QueueJobResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\DB;

class ListQueueJobs extends ListRecords
{
    protected static string $resource = QueueJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('refresh')
                ->label('تحديث')
                ->icon('heroicon-o-arrow-path')
                ->action(function () {
                    // Refresh the page data
                    $this->resetTable();
                    
                    \Filament\Notifications\Notification::make()
                        ->title('تم التحديث')
                        ->body('تم تحديث قائمة المهام')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('queue_stats')
                ->label('إحصائيات القائمة')
                ->icon('heroicon-o-chart-bar')
                ->color('info')
                ->modalHeading('Queue Statistics')
                ->modalContent(function () {
                    $totalJobs = \App\Models\QueueJob::count();
                    $pendingJobs = \App\Models\QueueJob::whereNull('reserved_at')->count();
                    $processingJobs = \App\Models\QueueJob::whereNotNull('reserved_at')->count();
                    $failedAttempts = \App\Models\QueueJob::where('attempts', '>', 0)->count();
                    
                    $stats = [
                        'Total Jobs' => $totalJobs,
                        'Pending Jobs' => $pendingJobs,
                        'Processing Jobs' => $processingJobs,
                        'Jobs with Failed Attempts' => $failedAttempts,
                    ];
                    
                    return view('filament.modals.queue-stats', ['stats' => $stats]);
                }),
                
            Actions\Action::make('process_jobs')
                ->label('معالجة المهام')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    // Run queue worker for a short time
                    $command = 'php artisan queue:work --once --timeout=30';
                    $output = shell_exec("cd /home/payments/central-payment-system && $command 2>&1");
                    
                    \Filament\Notifications\Notification::make()
                        ->title('تم تشغيل معالج المهام')
                        ->body('تم معالجة مهمة واحدة من القائمة')
                        ->success()
                        ->send();
                })
                ->requiresConfirmation()
                ->modalHeading('معالجة المهام')
                ->modalDescription('هل تريد معالجة مهمة واحدة من القائمة الآن؟')
                ->modalSubmitActionLabel('نعم، معالج الآن'),
        ];
    }
    
    protected function getHeaderWidgets(): array
    {
        return [
            // We can add widgets here for queue statistics
        ];
    }
}