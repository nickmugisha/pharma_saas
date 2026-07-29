<?php

namespace App\Filament\Resources\Users\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')
                    ->label('User')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                TextColumn::make('roles.name')
                    ->label('Role')
                    ->badge()
                    ->separator(', ')
                    ->placeholder('No role'),

                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),

                TextColumn::make('email_verified_at')
                    ->label('Verified')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Not verified')
                    ->sortable(),

                TextColumn::make('last_login_at')
                    ->label('Last login')
                    ->dateTime('d M Y, H:i')
                    ->placeholder('Never')
                    ->sortable(),

                TextColumn::make('last_login_ip')
                    ->label('Last IP')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->label('Created')
                    ->dateTime('d M Y, H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Account status')
                    ->trueLabel('Active accounts')
                    ->falseLabel('Blocked accounts')
                    ->placeholder('All accounts'),

                SelectFilter::make('roles')
                    ->label('Role')
                    ->relationship('roles', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}