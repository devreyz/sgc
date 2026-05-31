<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationResource\Pages;
use App\Filament\Resources\OrganizationResource\RelationManagers;
use App\Filament\Traits\TenantScoped;
use App\Models\Organization;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = Organization::class;

    protected static ?string $navigationIcon = 'heroicon-o-building-office-2';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Organização';

    protected static ?string $pluralModelLabel = 'Organizações';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Identificação')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->placeholder('Ex: Município de Itacarambi, CONAB, Estado de Minas Gerais')
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('short_name')
                        ->label('Nome Curto')
                        ->placeholder('Ex: Itacarambi, CONAB')
                        ->helperText('Usado em relatórios e listagens'),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(Organization::typeOptions())
                        ->required()
                        ->default('outro'),

                    Forms\Components\TextInput::make('cnpj')
                        ->label('CNPJ')
                        ->placeholder('00.000.000/0000-00')
                        ->mask('99.999.999/9999-99'),
                ])
                ->columns(2),

            Forms\Components\Section::make('Contato e Localização')
                ->schema([
                    Forms\Components\TextInput::make('responsible_name')
                        ->label('Responsável'),

                    Forms\Components\TextInput::make('responsible_role')
                        ->label('Cargo'),

                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email(),

                    Forms\Components\TextInput::make('phone')
                        ->label('Telefone'),

                    Forms\Components\TextInput::make('address')
                        ->label('Endereço')
                        ->columnSpan(2),

                    Forms\Components\TextInput::make('city')
                        ->label('Cidade'),

                    Forms\Components\TextInput::make('state')
                        ->label('Estado')
                        ->maxLength(2)
                        ->placeholder('MG'),
                ])
                ->columns(2)
                ->collapsed(),

            Forms\Components\Section::make('Observações')
                ->schema([
                    Forms\Components\Textarea::make('notes')
                        ->label('Observações')
                        ->rows(3)
                        ->columnSpanFull(),

                    Forms\Components\Toggle::make('active')
                        ->label('Ativa')
                        ->default(true),
                ])
                ->collapsed(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Organização')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),

                Tables\Columns\TextColumn::make('short_name')
                    ->label('Nome Curto')
                    ->placeholder('—')
                    ->toggleable(),

                Tables\Columns\BadgeColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state) => Organization::typeOptions()[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'municipio'   => 'info',
                        'estado'      => 'primary',
                        'federal'     => 'danger',
                        'conab'       => 'warning',
                        'hospital'    => 'success',
                        'cooperativa' => 'gray',
                        default       => 'gray',
                    }),

                Tables\Columns\TextColumn::make('clients_count')
                    ->label('Clientes')
                    ->counts('clients')
                    ->sortable(),

                Tables\Columns\TextColumn::make('city')
                    ->label('Cidade')
                    ->placeholder('—'),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativa')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->since()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(Organization::typeOptions()),

                Tables\Filters\TernaryFilter::make('active')
                    ->label('Ativa'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('name');
    }

    public static function getRelations(): array
    {
        return [
            RelationManagers\ClientsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListOrganizations::route('/'),
            'create' => Pages\CreateOrganization::route('/create'),
            'edit'   => Pages\EditOrganization::route('/{record}/edit'),
        ];
    }
}
