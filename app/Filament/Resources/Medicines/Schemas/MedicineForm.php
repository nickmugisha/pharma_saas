<?php

namespace App\Filament\Resources\Medicines\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class MedicineForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Medicine identity')
                    ->description(
                        'General information shared across all partner pharmacies.'
                    )
                    ->columns(2)
                    ->schema([
                        TextInput::make('brand_name')
                            ->label('Brand name')
                            ->required()
                            ->maxLength(255),

                        TextInput::make('generic_name')
                            ->label('Generic name')
                            ->maxLength(255),

                        Select::make('medicine_category_id')
                            ->label('Category')
                            ->relationship(
                                name: 'category',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query->where('is_active', true),
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('dosage_form_id')
                            ->label('Dosage form')
                            ->relationship(
                                name: 'dosageForm',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query->where('is_active', true),
                            )
                            ->searchable()
                            ->preload(),

                        Select::make('manufacturer_id')
                            ->label('Manufacturer')
                            ->relationship(
                                name: 'manufacturer',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder =>
                                    $query->where('is_active', true),
                            )
                            ->searchable()
                            ->preload(),

                        TextInput::make('strength')
                            ->placeholder('Example: 500 mg')
                            ->maxLength(255),

                        TextInput::make('package_size')
                            ->label('Package size')
                            ->placeholder('Example: Box of 20 tablets')
                            ->maxLength(255),

                        TextInput::make('barcode')
                            ->maxLength(100)
                            ->unique(ignoreRecord: true),

                        TextInput::make('regulatory_code')
                            ->label('Regulatory code')
                            ->maxLength(100),

                        Select::make('prescription_status')
                            ->label('Prescription status')
                            ->options([
                                'otc' => 'Over the counter',
                                'prescription' => 'Prescription required',
                                'controlled' => 'Controlled medicine',
                            ])
                            ->default('otc')
                            ->required(),

                        Select::make('online_sale_mode')
                            ->label('Online sale rule')
                            ->options([
                                'otc' => 'Order online without prescription',
                                'prescription_required' => 'Prescription required before approval',
                                'pharmacist_review' => 'Pharmacist review required',
                                'in_store_only' => 'Visible online — in-store purchase only',
                            ])
                            ->default('otc')
                            ->helperText(
                                'Configure the legal and clinical online-order rule. Do not rely on medicine names.'
                            )
                            ->required(),

                        Toggle::make('is_marketplace_featured')
                            ->label('Feature on marketplace home page')
                            ->default(false),

                        Toggle::make('is_active')
                            ->label('Active in central catalogue')
                            ->default(true),
                    ]),

                Section::make('Active ingredients')
                    ->description(
                        'Add every molecule contained in the medicine.'
                    )
                    ->schema([
                        Repeater::make('ingredients')
                            ->relationship('ingredients')
                            ->label('')
                            ->schema([
                                Select::make('molecule_id')
                                    ->label('Molecule')
                                    ->relationship(
                                        name: 'molecule',
                                        titleAttribute: 'name',
                                        modifyQueryUsing:
                                            fn (Builder $query): Builder =>
                                                $query->where('is_active', true),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->required(),

                                TextInput::make('strength')
                                    ->placeholder('Example: 500 mg')
                                    ->maxLength(255),

                                Toggle::make('is_primary')
                                    ->label('Primary ingredient'),

                                TextInput::make('sort_order')
                                    ->label('Order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ])
                            ->columns(4)
                            ->defaultItems(1)
                            ->minItems(1)
                            ->reorderable()
                            ->addActionLabel('Add ingredient')
                            ->columnSpanFull(),
                    ]),

                Section::make('Medicine pictures')
                    ->description(
                        'Upload clear pictures of the medicine and its packaging.'
                    )
                    ->schema([
                        Repeater::make('images')
                            ->relationship('images')
                            ->label('')
                            ->schema([
                                FileUpload::make('path')
                                    ->label('Image')
                                    ->disk('public')
                                    ->directory('medicines')
                                    ->visibility('public')
                                    ->image()
                                    ->acceptedFileTypes([
                                        'image/jpeg',
                                        'image/png',
                                        'image/webp',
                                    ])
                                    ->maxSize(5120)
                                    ->imageEditor()
                                    ->required(),

                                TextInput::make('alt_text')
                                    ->label('Image description')
                                    ->placeholder(
                                        'Example: Front of Panadol 500 mg box'
                                    )
                                    ->maxLength(255),

                                Toggle::make('is_primary')
                                    ->label('Primary picture'),

                                TextInput::make('sort_order')
                                    ->label('Order')
                                    ->numeric()
                                    ->default(0)
                                    ->minValue(0),
                            ])
                            ->columns(4)
                            ->addActionLabel('Add picture')
                            ->reorderable()
                            ->collapsible()
                            ->columnSpanFull(),
                    ]),

                Section::make('Clinical information')
                    ->columns(2)
                    ->collapsed()
                    ->schema([
                        Textarea::make('description')
                            ->rows(4)
                            ->columnSpanFull(),


                        Textarea::make('marketplace_summary')
                            ->label('Marketplace summary')
                            ->helperText('A clear, client-friendly summary shown on the public storefront.')
                            ->rows(3)
                            ->maxLength(1000)
                            ->columnSpanFull(),

                        Textarea::make('indications')
                            ->rows(4),

                        Textarea::make('contraindications')
                            ->rows(4),

                        Textarea::make('side_effects')
                            ->label('Possible side effects')
                            ->rows(4),

                        Textarea::make('storage_instructions')
                            ->label('Storage instructions')
                            ->rows(4),
                    ]),

                Section::make('Catalogue review')
                    ->description(
                        'Approval is controlled using the actions at the top of the edit page.'
                    )
                    ->columns(2)
                    ->schema([
                        Select::make('approval_status')
                            ->label('Approval status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_review' => 'Pending review',
                                'approved' => 'Approved',
                                'changes_requested' => 'Changes requested',
                                'rejected' => 'Rejected',
                                'suspended' => 'Suspended',
                            ])
                            ->default('draft')
                            ->disabled()
                            ->dehydrated(false),

                        Textarea::make('review_notes')
                            ->label('Latest review notes')
                            ->rows(3)
                            ->disabled()
                            ->dehydrated(false),
                    ]),
            ]);
    }
}