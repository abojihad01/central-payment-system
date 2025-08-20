<?php

namespace App\Filament\Resources;

use App\Filament\Resources\QueueJobResource\Pages;
use App\Models\QueueJob;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class QueueJobResource extends Resource
{
    protected static ?string $model = QueueJob::class;
    protected static ?string $navigationIcon = 'heroicon-o-queue-list';
    protected static ?string $navigationGroup = 'System';
    protected static ?string $navigationLabel = 'Queue Jobs';
    protected static ?string $label = 'Queue Job';
    protected static ?string $pluralLabel = 'Queue Jobs';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('Job ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('queue')
                    ->label('Queue')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('job_type')
                    ->label('Job Type')
                    ->searchable(),
                Tables\Columns\TextColumn::make('attempts')
                    ->label('Attempts')
                    ->sortable()
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state <= 2 => 'warning',
                        default => 'danger',
                    }),
                Tables\Columns\TextColumn::make('reserved_at')
                    ->label('Reserved At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Not Reserved'),
                Tables\Columns\TextColumn::make('available_at_formatted')
                    ->label('Available At')
                    ->sortable('available_at'),
                Tables\Columns\TextColumn::make('created_at_formatted')
                    ->label('Created At')
                    ->sortable('created_at'),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('queue')
                    ->options([
                        'default' => 'Default',
                        'payments' => 'Payments',
                        'high' => 'High Priority',
                        'low' => 'Low Priority',
                    ]),
                Tables\Filters\Filter::make('failed')
                    ->label('Failed Jobs')
                    ->query(fn (Builder $query): Builder => $query->where('attempts', '>', 0)),
                Tables\Filters\Filter::make('pending')
                    ->label('Pending Jobs')
                    ->query(fn (Builder $query): Builder => $query->whereNull('reserved_at')),
            ])
            ->actions([
                Tables\Actions\Action::make('view_payload')
                    ->label('عرض التفاصيل')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Job Payload Details')
                    ->modalContent(function (QueueJob $record) {
                        $payload = is_array($record->payload) ? $record->payload : json_decode($record->payload, true);
                        $formatted = json_encode($payload, JSON_PRETTY_PRINT);
                        return view('filament.modals.json-viewer', ['content' => $formatted]);
                    }),
                Tables\Actions\Action::make('delete_job')
                    ->label('حذف المهمة')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(function (QueueJob $record) {
                        $record->delete();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('تم حذف المهمة')
                            ->body("Job ID: {$record->id} تم حذفها من القائمة")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('حذف المهمة')
                    ->modalDescription('هل تريد حذف هذه المهمة من القائمة؟')
                    ->modalSubmitActionLabel('نعم، احذف'),
            ])
            ->bulkActions([
                Tables\Actions\BulkAction::make('delete_selected')
                    ->label('حذف المحدد')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(function (\Illuminate\Support\Collection $records) {
                        $ids = $records->pluck('id');
                        DB::table('jobs')->whereIn('id', $ids)->delete();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('تم حذف المهام')
                            ->body("تم حذف " . count($ids) . " مهمة من القائمة")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation(),
                Tables\Actions\BulkAction::make('clear_all')
                    ->label('مسح جميع المهام')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->action(function () {
                        $count = DB::table('jobs')->count();
                        DB::table('jobs')->truncate();
                        
                        \Filament\Notifications\Notification::make()
                            ->title('تم مسح جميع المهام')
                            ->body("تم حذف {$count} مهمة من القائمة")
                            ->success()
                            ->send();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('مسح جميع المهام')
                    ->modalDescription('هل تريد حذف جميع المهام من القائمة؟ هذا الإجراء لا يمكن التراجع عنه!')
                    ->modalSubmitActionLabel('نعم، امسح الكل'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListQueueJobs::route('/'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}