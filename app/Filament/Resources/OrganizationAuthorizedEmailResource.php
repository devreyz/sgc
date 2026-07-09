<?php

namespace App\Filament\Resources;

use App\Filament\Resources\OrganizationAuthorizedEmailResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\Organization;
use App\Models\OrganizationAuthorizedEmail;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class OrganizationAuthorizedEmailResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = OrganizationAuthorizedEmail::class;

    protected static ?string $navigationIcon = 'heroicon-o-key';

    protected static ?string $navigationGroup = 'Cadastros';

    protected static ?string $modelLabel = 'Acesso do Comprador';

    protected static ?string $pluralModelLabel = 'Acessos dos Compradores';

    protected static ?int $navigationSort = 3;

    public static function canViewAny(): bool
    {
        $user = auth()->user();

        if (! $user) {
            return false;
        }

        if ($user->isSuperAdmin() || $user->hasRole('admin')) {
            return true;
        }

        return parent::canViewAny();
    }

    public static function shouldRegisterNavigation(): bool
    {
        return static::canViewAny();
    }

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Select::make('organization_id')
                ->label('Organizacao compradora')
                ->options(fn () => Organization::query()->orderBy('name')->pluck('name', 'id'))
                ->searchable()
                ->preload()
                ->required(),

            Forms\Components\TextInput::make('name')
                ->label('Nome')
                ->maxLength(255),

            Forms\Components\TextInput::make('email')
                ->label('E-mail autorizado')
                ->email()
                ->required()
                ->maxLength(255)
                ->dehydrateStateUsing(fn (?string $state) => $state ? mb_strtolower(trim($state)) : $state),

            Forms\Components\Toggle::make('active')
                ->label('Ativo')
                ->default(true),
        ])->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('organization.name')
                    ->label('Organizacao')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->toggleable(),

                Tables\Columns\IconColumn::make('active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Ultimo login')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('-')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('active')->label('Ativo'),
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

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListOrganizationAuthorizedEmails::route('/'),
            'create' => Pages\CreateOrganizationAuthorizedEmail::route('/create'),
            'edit' => Pages\EditOrganizationAuthorizedEmail::route('/{record}/edit'),
        ];
    }
}
