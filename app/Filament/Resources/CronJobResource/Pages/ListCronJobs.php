<?php

namespace App\Filament\Resources\CronJobResource\Pages;

use App\Filament\Resources\CronJobResource;
use App\Models\CronJob;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListCronJobs extends ListRecords
{
    protected static string $resource = CronJobResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            
            Actions\Action::make('sync_with_crontab')
                ->label('Sync with System Crontab')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('info')
                ->action(function () {
                    $this->syncWithSystemCrontab();
                })
                ->requiresConfirmation()
                ->modalHeading('Sync with System Crontab')
                ->modalDescription('This will read the current system crontab and create/update cron jobs in the database.')
                ->modalSubmitActionLabel('Sync Now'),
                
            Actions\Action::make('export_to_crontab')
                ->label('Export to System Crontab')
                ->icon('heroicon-o-arrow-up-tray')
                ->color('warning')
                ->action(function () {
                    $this->exportToSystemCrontab();
                })
                ->requiresConfirmation()
                ->modalHeading('Export to System Crontab')
                ->modalDescription('This will overwrite the current system crontab with active jobs from the database. Make sure to backup your current crontab first!')
                ->modalSubmitActionLabel('Export Now'),
                
            Actions\Action::make('run_scheduler')
                ->label('Run Scheduler Now')
                ->icon('heroicon-o-play')
                ->color('success')
                ->action(function () {
                    $output = shell_exec('cd ' . base_path() . ' && php artisan schedule:run 2>&1');
                    
                    \Filament\Notifications\Notification::make()
                        ->title('Laravel Scheduler executed')
                        ->body('Check the logs for detailed output')
                        ->success()
                        ->send();
                }),
                
            Actions\Action::make('system_health')
                ->label('System Health')
                ->icon('heroicon-o-heart')
                ->color('info')
                ->modalHeading('System Health Check')
                ->modalContent(function () {
                    return view('filament.modals.system-health');
                }),
        ];
    }

    protected function syncWithSystemCrontab(): void
    {
        try {
            // Read current crontab
            $crontab = shell_exec('crontab -l 2>/dev/null');
            
            if (!$crontab) {
                \Filament\Notifications\Notification::make()
                    ->title('No crontab found')
                    ->body('The system crontab is empty or not accessible')
                    ->warning()
                    ->send();
                return;
            }

            $lines = explode("\n", trim($crontab));
            $syncedCount = 0;

            foreach ($lines as $line) {
                $line = trim($line);
                
                // Skip empty lines and comments
                if (empty($line) || str_starts_with($line, '#')) {
                    continue;
                }

                // Parse cron line
                $parts = preg_split('/\s+/', $line, 6);
                
                if (count($parts) >= 6) {
                    $cronExpression = implode(' ', array_slice($parts, 0, 5));
                    $command = $parts[5];
                    
                    // Create or update cron job
                    CronJob::updateOrCreate(
                        ['command' => $command],
                        [
                            'name' => 'System Cron: ' . substr($command, 0, 50),
                            'cron_expression' => $cronExpression,
                            'description' => 'Imported from system crontab',
                            'is_active' => true,
                            'environment' => app()->environment(),
                        ]
                    );
                    
                    $syncedCount++;
                }
            }

            \Filament\Notifications\Notification::make()
                ->title('Crontab synced successfully')
                ->body("Imported/updated {$syncedCount} cron jobs")
                ->success()
                ->send();

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Sync failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }

    protected function exportToSystemCrontab(): void
    {
        try {
            $activeJobs = CronJob::active()
                ->where('environment', app()->environment())
                ->get();

            $crontabLines = [];
            $crontabLines[] = '# Generated by Central Payment System - ' . now()->format('Y-m-d H:i:s');
            $crontabLines[] = '';

            foreach ($activeJobs as $job) {
                $crontabLines[] = "# {$job->name}";
                $crontabLines[] = "{$job->cron_expression} {$job->command}";
                $crontabLines[] = '';
            }

            // Write to temporary file
            $tempFile = tempnam(sys_get_temp_dir(), 'crontab_');
            file_put_contents($tempFile, implode("\n", $crontabLines));

            // Install new crontab
            $result = shell_exec("crontab {$tempFile} 2>&1");
            unlink($tempFile);

            if ($result === null) {
                \Filament\Notifications\Notification::make()
                    ->title('Crontab exported successfully')
                    ->body("Exported {$activeJobs->count()} active jobs to system crontab")
                    ->success()
                    ->send();
            } else {
                throw new \Exception($result);
            }

        } catch (\Exception $e) {
            \Filament\Notifications\Notification::make()
                ->title('Export failed')
                ->body($e->getMessage())
                ->danger()
                ->send();
        }
    }
}