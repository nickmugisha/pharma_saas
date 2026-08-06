<?php

namespace App\Filament\Pharmacy\Resources\MarketplaceOffers\Schemas;

use App\Models\PharmacyMedicine;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MarketplaceOfferForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Public pharmacy offer')
                ->description(
                    'Configure how one branch presents and fulfils this medicine on the public marketplace.'
                )
                ->columnSpanFull()
                ->columns([
                    'default' => 1,
                    'md' => 2,
                ])
                ->schema([
                    Select::make('pharmacy_branch_id')
                        ->label('Offering branch')
                        ->relationship(
                            name: 'branch',
                            titleAttribute: 'name',
                            modifyQueryUsing: function (
                                Builder $query,
                            ): Builder {
                                $user = auth()->user();

                                return $query
                                    ->where(
                                        'pharmacy_id',
                                        $user?->pharmacy_id ?? 0,
                                    )
                                    ->when(
                                        $user
                                        && ! $user->hasRole(
                                            'pharmacy_owner',
                                        ),
                                        fn (Builder $branchQuery): Builder =>
                                            $branchQuery->whereKey(
                                                $user->pharmacy_branch_id
                                                ?? 0,
                                            ),
                                    )
                                    ->where('status', 'active');
                            },
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),

                    Select::make('pharmacy_medicine_id')
                        ->label('Medicine listing')
                        ->relationship(
                            name: 'pharmacyMedicine',
                            titleAttribute: 'id',
                            modifyQueryUsing: fn (Builder $query): Builder =>
                                $query
                                    ->with('medicine')
                                    ->where('pharmacy_id', auth()->user()?->pharmacy_id ?? 0)
                                    ->where('status', 'active')
                                    ->where('is_available', true)
                                    ->where('is_visible_online', true),
                        )
                        ->getOptionLabelFromRecordUsing(
                            fn (PharmacyMedicine $record): string => collect([
                                $record->medicine?->brand_name,
                                $record->medicine?->strength,
                                $record->internal_sku,
                            ])->filter()->implode(' — '),
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->disabledOn('edit'),

                    TextInput::make('online_price')
                        ->label('Branch online price')
                        ->helperText('Leave empty to use the pharmacy listing online price or selling price.')
                        ->numeric()
                        ->minValue(0.01)
                        ->step(0.01)
                        ->suffix('BIF'),

                    Select::make('status')
                        ->options([
                            'active' => 'Active online',
                            'inactive' => 'Hidden from marketplace',
                        ])
                        ->default('active')
                        ->required(),

                    Toggle::make('pickup_enabled')
                        ->label('Pickup available')
                        ->default(true),

                    Toggle::make('delivery_enabled')
                        ->label('Delivery available')
                        ->default(false),

                    TextInput::make('delivery_fee')
                        ->label('Delivery fee')
                        ->numeric()
                        ->minValue(0)
                        ->step(0.01)
                        ->default(0)
                        ->suffix('BIF'),

                    TextInput::make('max_order_quantity')
                        ->label('Maximum quantity per order')
                        ->helperText('Leave empty to allow up to the available stock quantity.')
                        ->numeric()
                        ->minValue(0.001)
                        ->step(0.001),

                    TextInput::make('preparation_minutes')
                        ->label('Estimated preparation time')
                        ->numeric()
                        ->integer()
                        ->minValue(5)
                        ->maxValue(1440)
                        ->default(30)
                        ->suffix('minutes'),

                    Textarea::make('marketplace_description')
                        ->label('Offer description')
                        ->rows(4)
                        ->maxLength(1500)
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
