<?php

namespace App\Filament\Pharmacy\Pages;

use App\Actions\Reports\BuildSalesReport;
use App\Models\PharmacyBranch;
use App\Models\User;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use UnitEnum;

class SalesAnalytics extends Page implements HasSchemas
{
    use InteractsWithSchemas;

    protected static string | BackedEnum | null $navigationIcon =
        'heroicon-o-chart-bar';

    protected static string | UnitEnum | null $navigationGroup =
        'Reports';

    protected static ?string $navigationLabel =
        'Sales Analytics';

    protected static ?string $title =
        'Sales Analytics';

    protected static ?string $slug =
        'sales-analytics';

    protected static ?int $navigationSort = 1;

    protected string $view =
        'filament.pharmacy.pages.sales-analytics';

    public ?array $data = [];

    public array $report = [];

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $this->form->fill(
            $this->defaultFilters(),
        );

        $this->loadReport();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Report filters')
                    ->description(
                        'Choose a date range and optionally limit the report to one branch.'
                    )
                    ->icon('heroicon-o-funnel')
                    ->columns([
                        'default' => 1,
                        'md' => 3,
                    ])
                    ->schema([
                        DatePicker::make('start_date')
                            ->label('Start date')
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y'),

                        DatePicker::make('end_date')
                            ->label('End date')
                            ->required()
                            ->native(false)
                            ->displayFormat('d M Y'),

                        Select::make('pharmacy_branch_id')
                            ->label('Branch')
                            ->placeholder('All branches')
                            ->options(
                                fn (): array =>
                                    PharmacyBranch::query()
                                        ->where(
                                            'pharmacy_id',
                                            auth()->user()
                                                ?->pharmacy_id
                                                ?? 0,
                                        )
                                        ->where(
                                            'status',
                                            'active',
                                        )
                                        ->orderBy('name')
                                        ->pluck('name', 'id')
                                        ->all(),
                            )
                            ->searchable()
                            ->preload(),
                    ]),
            ])
            ->statePath('data');
    }

    public function applyFilters(): void
    {
        $this->loadReport();

        Notification::make()
            ->title('Sales report updated')
            ->success()
            ->send();
    }

    public function resetFilters(): void
    {
        $this->form->fill(
            $this->defaultFilters(),
        );

        $this->loadReport();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return Filament::getCurrentPanel()?->getId()
                === 'pharmacy'
            && filled($user?->pharmacy_id)
            && ($user?->can('reports.view') ?? false);
    }

    public function getMaxContentWidth(): Width
    {
        return Width::Full;
    }

    private function loadReport(): void
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        $filters = $this->form->getState();

        $this->report = app(BuildSalesReport::class)
            ->handle(
                user: $user,
                filters: $filters,
            );
    }

    private function defaultFilters(): array
    {
        return [
            'start_date' =>
                now()->subDays(29)->toDateString(),

            'end_date' =>
                now()->toDateString(),

            'pharmacy_branch_id' =>
                null,
        ];
    }
}