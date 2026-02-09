<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DirectPurchaseResource\Pages;
use App\Filament\Resources\DirectPurchaseResource\RelationManagers;
use App\Models\DirectPurchase;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class DirectPurchaseResource extends Resource
{
    protected static ?string $model = DirectPurchase::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('supplier_id')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('total_value')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('discount')
                    ->required()
                    ->numeric()
                    ->default(0.00),
                Forms\Components\TextInput::make('final_value')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('status')
                    ->required(),
                Forms\Components\TextInput::make('payment_status')
                    ->required(),
                Forms\Components\TextInput::make('bank_account_id')
                    ->numeric(),
                Forms\Components\TextInput::make('payment_method'),
                Forms\Components\DatePicker::make('purchase_date')
                    ->required(),
                Forms\Components\DatePicker::make('expected_delivery_date'),
                Forms\Components\DatePicker::make('actual_delivery_date'),
                Forms\Components\DatePicker::make('payment_date'),
                Forms\Components\TextInput::make('invoice_number')
                    ->maxLength(191),
                Forms\Components\TextInput::make('invoice_path')
                    ->maxLength(191),
                Forms\Components\Textarea::make('description')
                    ->columnSpanFull(),
                Forms\Components\Textarea::make('notes')
                    ->columnSpanFull(),
                Forms\Components\TextInput::make('created_by')
                    ->required()
                    ->numeric(),
                Forms\Components\TextInput::make('approved_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('approved_at'),
                Forms\Components\TextInput::make('received_by')
                    ->numeric(),
                Forms\Components\DateTimePicker::make('received_at'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('supplier_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('discount')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('final_value')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('status'),
                Tables\Columns\TextColumn::make('payment_status'),
                Tables\Columns\TextColumn::make('bank_account_id')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_method'),
                Tables\Columns\TextColumn::make('purchase_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expected_delivery_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('actual_delivery_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('payment_date')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('invoice_number')
                    ->searchable(),
                Tables\Columns\TextColumn::make('invoice_path')
                    ->searchable(),
                Tables\Columns\TextColumn::make('created_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('approved_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_by')
                    ->numeric()
                    ->sortable(),
                Tables\Columns\TextColumn::make('received_at')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
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
            'index' => Pages\ListDirectPurchases::route('/'),
            'create' => Pages\CreateDirectPurchase::route('/create'),
            'edit' => Pages\EditDirectPurchase::route('/{record}/edit'),
        ];
    }
}
