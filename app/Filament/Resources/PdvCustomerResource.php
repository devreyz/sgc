<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdvCustomerResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\PdvCustomer;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PdvCustomerResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = PdvCustomer::class;
    protected static ?string $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationGroup = 'Estoque';
    protected static ?string $modelLabel = 'Cliente PDV';
    protected static ?string $pluralModelLabel = 'Clientes PDV';
    protected static ?int $navigationSort = 98;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('name')->label('Nome')->required()->maxLength(255),
            Forms\Components\TextInput::make('cpf_cnpj')->label('CPF/CNPJ')->maxLength(20),
            Forms\Components\TextInput::make('phone')->label('Telefone')->maxLength(20),
            Forms\Components\TextInput::make('email')->label('E-mail')->email()->maxLength(255),
            Forms\Components\Textarea::make('address')->label('Endereço')->maxLength(500)->columnSpanFull(),
            Forms\Components\TextInput::make('credit_limit')->label('Limite de Crédito')->numeric()->prefix('R$')->default(0),
            Forms\Components\Textarea::make('notes')->label('Observações')->columnSpanFull(),
            Forms\Components\Toggle::make('status')->label('Ativo')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('cpf_cnpj')->label('CPF/CNPJ')->searchable(),
                Tables\Columns\TextColumn::make('phone')->label('Telefone'),
                Tables\Columns\TextColumn::make('credit_balance')
                    ->label('Saldo Devedor')
                    ->money('BRL')
                    ->color(fn ($state) => $state > 0 ? 'danger' : 'success'),
                Tables\Columns\IconColumn::make('status')->label('Ativo')->boolean(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')->label('Ativo'),
            ])
            ->defaultSort('name');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPdvCustomers::route('/'),
            'create' => Pages\CreatePdvCustomer::route('/create'),
            'edit' => Pages\EditPdvCustomer::route('/{record}/edit'),
        ];
    }
}
