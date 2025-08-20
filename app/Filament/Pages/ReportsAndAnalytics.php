<?php

namespace App\Filament\Pages;

use App\Models\Payment;
use App\Models\Website;
use App\Models\GeneratedLink;
use Filament\Pages\Page;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ReportsAndAnalytics extends Page implements HasTable, HasForms
{
    use InteractsWithTable, InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationGroup = 'التقارير والإحصائيات';
    protected static ?string $navigationLabel = 'تقارير الأرباح والإحصائيات';
    protected static ?string $title = 'تقارير الأرباح والإحصائيات التفصيلية';
    protected static string $view = 'filament.pages.reports-and-analytics';
    protected static ?int $navigationSort = 1;

    public ?array $data = [];
    public $selectedPeriod = '30';
    public $selectedWebsite = null;
    public $selectedStatus = 'completed';

    public function mount(): void
    {
        $this->form->fill([
            'period' => '30',
            'website_id' => null,
            'status' => 'completed'
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('فلاتر التقرير')
                    ->schema([
                        Forms\Components\Grid::make(3)
                            ->schema([
                                Forms\Components\Select::make('period')
                                    ->label('فترة التقرير')
                                    ->options([
                                        '7' => 'آخر 7 أيام',
                                        '30' => 'آخر 30 يوم',
                                        '90' => 'آخر 3 أشهر',
                                        '365' => 'آخر سنة',
                                        'all' => 'جميع الفترات'
                                    ])
                                    ->default('30')
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->selectedPeriod = $state;
                                    }),

                                Forms\Components\Select::make('website_id')
                                    ->label('الموقع')
                                    ->options(Website::pluck('name', 'id')->prepend('جميع المواقع', null))
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->selectedWebsite = $state;
                                    }),

                                Forms\Components\Select::make('status')
                                    ->label('حالة المدفوعات')
                                    ->options([
                                        'all' => 'جميع الحالات',
                                        'completed' => 'مكتملة',
                                        'pending' => 'معلقة',
                                        'failed' => 'فاشلة',
                                        'cancelled' => 'ملغاة'
                                    ])
                                    ->default('completed')
                                    ->live()
                                    ->afterStateUpdated(function ($state) {
                                        $this->selectedStatus = $state;
                                    }),
                            ])
                    ])
            ])
            ->statePath('data');
    }

    public function table(Table $table): Table
    {
        return $table
            ->query($this->getTableQuery())
            ->columns([
                Tables\Columns\TextColumn::make('website_name')
                    ->label('اسم الموقع')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('total_revenue')
                    ->label('إجمالي الإيرادات')
                    ->money('USD')
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->money('USD')
                            ->label('المجموع الكلي')
                    ]),

                Tables\Columns\TextColumn::make('payments_count')
                    ->label('عدد المدفوعات')
                    ->numeric()
                    ->sortable()
                    ->summarize([
                        Tables\Columns\Summarizers\Sum::make()
                            ->label('إجمالي المدفوعات')
                    ]),

                Tables\Columns\TextColumn::make('average_payment')
                    ->label('متوسط قيمة الدفعة')
                    ->money('USD')
                    ->sortable(),

                Tables\Columns\TextColumn::make('generated_links_count')
                    ->label('عدد الروابط المُولدة')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('conversion_rate')
                    ->label('معدل التحويل')
                    ->suffix('%')
                    ->sortable(),

                Tables\Columns\TextColumn::make('last_payment')
                    ->label('آخر دفعة')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('website_id')
                    ->label('الموقع')
                    ->options(Website::pluck('name', 'id')),
            ])
            ->defaultSort('total_revenue', 'desc')
            ->striped()
            ->paginated(false);
    }

    protected function getTableQuery(): Builder
    {
        $query = Website::query()
            ->leftJoin('generated_links', 'websites.id', '=', 'generated_links.website_id')
            ->leftJoin('payments', 'generated_links.id', '=', 'payments.generated_link_id')
            ->select([
                'websites.id as website_id',
                'websites.name as website_name',
                DB::raw('COALESCE(SUM(CASE WHEN payments.status = "completed" THEN payments.amount ELSE 0 END), 0) as total_revenue'),
                DB::raw('COUNT(CASE WHEN payments.status = "completed" THEN payments.id END) as payments_count'),
                DB::raw('COALESCE(AVG(CASE WHEN payments.status = "completed" THEN payments.amount END), 0) as average_payment'),
                DB::raw('COUNT(DISTINCT generated_links.id) as generated_links_count'),
                DB::raw('CASE 
                    WHEN COUNT(DISTINCT generated_links.id) > 0 
                    THEN ROUND((COUNT(CASE WHEN payments.status = "completed" THEN payments.id END) * 100.0 / COUNT(DISTINCT generated_links.id)), 2)
                    ELSE 0 
                END as conversion_rate'),
                DB::raw('MAX(CASE WHEN payments.status = "completed" THEN payments.created_at END) as last_payment'),
            ])
            ->groupBy('websites.id', 'websites.name');

        // Apply period filter
        if ($this->selectedPeriod !== 'all') {
            $days = (int) $this->selectedPeriod;
            $query->where('payments.created_at', '>=', Carbon::now()->subDays($days));
        }

        // Apply website filter
        if ($this->selectedWebsite) {
            $query->where('websites.id', $this->selectedWebsite);
        }

        // Apply status filter
        if ($this->selectedStatus !== 'all') {
            $query->where('payments.status', $this->selectedStatus);
        }

        return $query;
    }

    public function getTableRecordKey($record): string
    {
        return (string) $record->website_id;
    }

    public function getOverviewStats(): array
    {
        $query = Payment::query();

        // Apply filters
        if ($this->selectedPeriod !== 'all') {
            $days = (int) $this->selectedPeriod;
            $query->where('created_at', '>=', Carbon::now()->subDays($days));
        }

        if ($this->selectedWebsite) {
            $query->whereHas('generatedLink', function ($q) {
                $q->where('website_id', $this->selectedWebsite);
            });
        }

        if ($this->selectedStatus !== 'all') {
            $query->where('status', $this->selectedStatus);
        }

        $completedPayments = (clone $query)->where('status', 'completed');

        return [
            'total_revenue' => $completedPayments->sum('amount'),
            'total_payments' => $completedPayments->count(),
            'average_payment' => $completedPayments->avg('amount') ?: 0,
            'pending_payments' => (clone $query)->where('status', 'pending')->count(),
            'failed_payments' => (clone $query)->where('status', 'failed')->count(),
            'success_rate' => $query->count() > 0 ? 
                round(($completedPayments->count() / $query->count()) * 100, 2) : 0,
        ];
    }

    public function getTopGeneratedLinks(): array
    {
        $query = GeneratedLink::query()
            ->leftJoin('payments', 'generated_links.id', '=', 'payments.generated_link_id')
            ->leftJoin('websites', 'generated_links.website_id', '=', 'websites.id')
            ->select([
                'generated_links.id',
                'generated_links.token',
                'websites.name as website_name',
                'generated_links.price',
                'generated_links.currency',
                DB::raw('COALESCE(SUM(CASE WHEN payments.status = "completed" THEN payments.amount ELSE 0 END), 0) as total_revenue'),
                DB::raw('COUNT(CASE WHEN payments.status = "completed" THEN payments.id END) as successful_payments'),
                DB::raw('COUNT(payments.id) as total_payments'),
                'generated_links.created_at',
            ])
            ->groupBy([
                'generated_links.id', 
                'generated_links.token', 
                'websites.name', 
                'generated_links.price', 
                'generated_links.currency',
                'generated_links.created_at'
            ]);

        // Apply filters
        if ($this->selectedPeriod !== 'all') {
            $days = (int) $this->selectedPeriod;
            $query->where('payments.created_at', '>=', Carbon::now()->subDays($days));
        }

        if ($this->selectedWebsite) {
            $query->where('generated_links.website_id', $this->selectedWebsite);
        }

        if ($this->selectedStatus !== 'all') {
            $query->where('payments.status', $this->selectedStatus);
        }

        return $query->orderBy('total_revenue', 'desc')
            ->limit(10)
            ->get()
            ->toArray();
    }

    public function getRevenueByMonth(): array
    {
        $query = Payment::query()
            ->where('status', 'completed')
            ->select([
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as revenue'),
                DB::raw('COUNT(*) as payments_count')
            ])
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(12);

        // Apply website filter if selected
        if ($this->selectedWebsite) {
            $query->whereHas('generatedLink', function ($q) {
                $q->where('website_id', $this->selectedWebsite);
            });
        }

        return $query->get()->toArray();
    }
}