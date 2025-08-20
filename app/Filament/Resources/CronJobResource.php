<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CronJobResource\Pages;
use App\Models\CronJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CronJobResource extends Resource
{
    protected static ?string $model = CronJob::class;
    protected static ?string $navigationIcon = 'heroicon-o-clock';
    protected static ?string $navigationGroup = 'إدارة النظام';
    protected static ?string $navigationLabel = 'المهام المجدولة';
    protected static ?string $label = 'مهمة مجدولة';
    protected static ?string $pluralLabel = 'المهام المجدولة';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('المعلومات الأساسية')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('اسم المهمة')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->helperText('معرف فريد لهذه المهمة المجدولة'),
                            
                        Forms\Components\Textarea::make('description')
                            ->label('الوصف')
                            ->helperText('ما وظيفة هذه المهمة؟')
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('إعدادات الأمر')
                    ->schema([
                        Forms\Components\Textarea::make('command')
                            ->label('الأمر')
                            ->required()
                            ->helperText('الأمر الكامل للتنفيذ (مثال: php artisan payments:verify-pending)')
                            ->columnSpanFull(),
                            
                        Forms\Components\TextInput::make('cron_expression')
                            ->label('تعبير الجدولة')
                            ->required()
                            ->helperText('الجدولة بصيغة cron (مثال: */5 * * * * لكل 5 دقائق)')
                            ->placeholder('*/30 * * * *'),
                            
                        Forms\Components\Select::make('environment')
                            ->label('البيئة')
                            ->options([
                                'local' => 'محلي',
                                'testing' => 'اختبار',
                                'staging' => 'تجريبي',
                                'production' => 'إنتاج',
                            ])
                            ->default('production')
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('إعدادات التنفيذ')
                    ->schema([
                        Forms\Components\TextInput::make('timeout_seconds')
                            ->label('مهلة التنفيذ (ثواني)')
                            ->numeric()
                            ->default(300)
                            ->minValue(1)
                            ->maxValue(3600)
                            ->helperText('أقصى وقت للتنفيذ بالثواني'),
                            
                        Forms\Components\TextInput::make('max_attempts')
                            ->label('أقصى عدد محاولات')
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->maxValue(10)
                            ->helperText('أقصى عدد محاولات إعادة التشغيل'),
                            
                        Forms\Components\Toggle::make('is_active')
                            ->label('نشط')
                            ->default(true)
                            ->helperText('تمكين/تعطيل هذه المهمة المجدولة'),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('الاسم')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                    
                Tables\Columns\BadgeColumn::make('status')
                    ->label('الحالة')
                    ->getStateUsing(fn (CronJob $record) => match($record->status) {
                        'active' => 'نشط',
                        'inactive' => 'غير نشط',
                        'overdue' => 'متأخر',
                        'warning' => 'تحذير',
                        default => $record->status
                    })
                    ->colors([
                        'gray' => 'inactive',
                        'danger' => 'overdue',
                        'warning' => 'warning',
                        'success' => 'active',
                    ]),
                    
                Tables\Columns\TextColumn::make('cron_expression')
                    ->label('الجدولة')
                    ->badge()
                    ->color('info'),
                    
                Tables\Columns\TextColumn::make('environment')
                    ->label('البيئة')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'production' => 'إنتاج',
                        'staging' => 'تجريبي',
                        'testing' => 'اختبار',
                        'local' => 'محلي',
                        default => $state
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'production' => 'danger',
                        'staging' => 'warning',
                        'testing' => 'info',
                        'local' => 'gray',
                        default => 'gray',
                    }),
                    
                Tables\Columns\TextColumn::make('run_count')
                    ->label('التشغيلات')
                    ->sortable()
                    ->alignCenter(),
                    
                Tables\Columns\TextColumn::make('failure_count')
                    ->label('الأخطاء')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn (int $state): string => $state > 0 ? 'danger' : 'success'),
                    
                Tables\Columns\TextColumn::make('success_rate')
                    ->label('معدل النجاح')
                    ->formatStateUsing(fn (float $state) => number_format($state, 1) . '%')
                    ->sortable()
                    ->alignCenter()
                    ->color(fn (float $state): string => match (true) {
                        $state >= 95 => 'success',
                        $state >= 80 => 'warning',
                        default => 'danger',
                    }),
                    
                Tables\Columns\TextColumn::make('last_run_formatted')
                    ->label('آخر تشغيل')
                    ->sortable('last_run_at'),
                    
                Tables\Columns\TextColumn::make('next_run_formatted')
                    ->label('التشغيل التالي')
                    ->sortable('next_run_at'),
                    
                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('نشط')
                    ->afterStateUpdated(function (CronJob $record, bool $state) {
                        if ($state) {
                            $record->calculateNextRun();
                        }
                    }),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('is_active')
                    ->label('الحالة')
                    ->options([
                        true => 'نشط',
                        false => 'غير نشط',
                    ]),
                    
                Tables\Filters\SelectFilter::make('environment')
                    ->label('البيئة')
                    ->options([
                        'local' => 'محلي',
                        'testing' => 'اختبار',
                        'staging' => 'تجريبي',
                        'production' => 'إنتاج',
                    ]),
                    
                Tables\Filters\Filter::make('overdue')
                    ->label('المهام المتأخرة')
                    ->query(fn (Builder $query): Builder => $query->overdue()),
                    
                Tables\Filters\Filter::make('failed')
                    ->label('المهام التي بها أخطاء')
                    ->query(fn (Builder $query): Builder => $query->where('failure_count', '>', 0)),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                
                Tables\Actions\Action::make('run_now')
                    ->label('تشغيل الآن')
                    ->icon('heroicon-o-play')
                    ->color('success')
                    ->action(function (CronJob $record) {
                        $result = $record->execute();
                        
                        if ($result['success']) {
                            \Filament\Notifications\Notification::make()
                                ->title('تم تنفيذ المهمة بنجاح')
                                ->body("وقت التنفيذ: " . number_format($result['execution_time'], 2) . " ثانية")
                                ->success()
                                ->send();
                        } else {
                            \Filament\Notifications\Notification::make()
                                ->title('فشل في تنفيذ المهمة')
                                ->body($result['error'])
                                ->danger()
                                ->send();
                        }
                    })
                    ->requiresConfirmation()
                    ->modalHeading('تنفيذ المهمة المجدولة')
                    ->modalDescription('تشغيل هذه المهمة فوراً؟')
                    ->modalSubmitActionLabel('تنفيذ'),
                    
                Tables\Actions\Action::make('view_logs')
                    ->label('عرض السجلات')
                    ->icon('heroicon-o-document-text')
                    ->color('info')
                    ->modalHeading('سجلات تنفيذ المهمة')
                    ->modalContent(function (CronJob $record) {
                        return view('filament.modals.cron-job-logs', [
                            'record' => $record
                        ]);
                    }),
                    
                Tables\Actions\Action::make('toggle_status')
                    ->label(fn (CronJob $record) => $record->is_active ? 'تعطيل' : 'تمكين')
                    ->icon(fn (CronJob $record) => $record->is_active ? 'heroicon-o-pause' : 'heroicon-o-play')
                    ->color(fn (CronJob $record) => $record->is_active ? 'warning' : 'success')
                    ->action(function (CronJob $record) {
                        $record->toggle();
                        
                        \Filament\Notifications\Notification::make()
                            ->title($record->is_active ? 'Job enabled' : 'Job disabled')
                            ->success()
                            ->send();
                    }),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    
                    Tables\Actions\BulkAction::make('enable_selected')
                        ->label('Enable Selected')
                        ->icon('heroicon-o-play')
                        ->color('success')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if (!$record->is_active) {
                                    $record->update(['is_active' => true]);
                                    $record->calculateNextRun();
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Jobs enabled')
                                ->success()
                                ->send();
                        }),
                        
                    Tables\Actions\BulkAction::make('disable_selected')
                        ->label('Disable Selected')
                        ->icon('heroicon-o-pause')
                        ->color('warning')
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                if ($record->is_active) {
                                    $record->update(['is_active' => false]);
                                }
                            }
                            
                            \Filament\Notifications\Notification::make()
                                ->title('Jobs disabled')
                                ->success()
                                ->send();
                        }),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListCronJobs::route('/'),
            'create' => Pages\CreateCronJob::route('/create'),
            'edit' => Pages\EditCronJob::route('/{record}/edit'),
        ];
    }
}