<?php

namespace App\Filament\Resources;

use App\Filament\Resources\WebsiteResource\Pages;
use App\Filament\Resources\WebsiteResource\RelationManagers;
use App\Models\Website;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class WebsiteResource extends Resource
{
    protected static ?string $model = Website::class;

    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    
    protected static ?string $navigationGroup = 'إدارة المواقع';
    
    protected static ?string $navigationLabel = 'المواقع';
    
    protected static ?string $modelLabel = 'موقع';
    
    protected static ?string $pluralModelLabel = 'المواقع';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('name')
                    ->required()
                    ->label('اسم الموقع'),
                Forms\Components\TextInput::make('domain')
                    ->required()
                    ->label('النطاق'),
                Forms\Components\Select::make('language')
                    ->required()
                    ->label('لغة صفحة الدفع')
                    ->options([
                        'en' => 'English',
                        'sv' => 'Svenska (Swedish)',
                        'fr' => 'Français (French)',
                        'de' => 'Deutsch (German)',
                        'es' => 'Español (Spanish)',
                        'ar' => 'العربية (Arabic)',
                    ])
                    ->default('en')
                    ->helperText('اللغة المستخدمة في صفحة الدفع للعملاء'),
                Forms\Components\TextInput::make('logo')
                    ->label('شعار الموقع (رابط)')
                    ->url(),
                Forms\Components\TextInput::make('success_url')
                    ->required()
                    ->label('رابط النجاح')
                    ->url()
                    ->helperText('الرابط الذي سيتم التحويل إليه عند نجاح الدفع'),
                Forms\Components\TextInput::make('failure_url')
                    ->required()
                    ->label('رابط الفشل')
                    ->url()
                    ->helperText('الرابط الذي سيتم التحويل إليه عند فشل الدفع'),
                Forms\Components\Toggle::make('is_active')
                    ->required()
                    ->label('نشط')
                    ->default(true),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->label('اسم الموقع'),
                Tables\Columns\TextColumn::make('domain')
                    ->searchable()
                    ->label('النطاق'),
                Tables\Columns\ImageColumn::make('logo')
                    ->label('الشعار')
                    ->circular()
                    ->size(40)
                    ->default('https://via.placeholder.com/40x40/e5e7eb/9ca3af?text=Logo'),
                Tables\Columns\TextColumn::make('language')
                    ->badge()
                    ->label('اللغة')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'en' => 'English',
                        'sv' => 'Svenska',
                        'fr' => 'Français',
                        'de' => 'Deutsch',
                        'es' => 'Español',
                        'ar' => 'العربية',
                        default => $state
                    })
                    ->color(fn (string $state): string => match ($state) {
                        'en' => 'primary',
                        'sv' => 'warning',
                        'fr' => 'info',
                        'de' => 'success',
                        'es' => 'danger',
                        'ar' => 'gray',
                        default => 'secondary'
                    }),
                Tables\Columns\TextColumn::make('success_url')
                    ->searchable()
                    ->label('رابط النجاح')
                    ->limit(50),
                Tables\Columns\TextColumn::make('failure_url')
                    ->searchable()
                    ->label('رابط الفشل')
                    ->limit(50),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->label('نشط'),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->label('تاريخ الإنشاء')
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->label('تاريخ التحديث')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListWebsites::route('/'),
            'create' => Pages\CreateWebsite::route('/create'),
            'edit' => Pages\EditWebsite::route('/{record}/edit'),
        ];
    }
}
