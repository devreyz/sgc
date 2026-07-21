<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SalesProjectTypeResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\SalesProject;
use App\Models\SalesProjectType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class SalesProjectTypeResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = SalesProjectType::class;

    protected static ?string $navigationIcon = 'heroicon-o-tag';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $modelLabel = 'Tipo de Projeto';

    protected static ?string $pluralModelLabel = 'Tipos de Projeto';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\Section::make('Tipo de Projeto')
                ->description('Crie categorias proprias para organizar projetos e personalizar seus comprovantes.')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome')
                        ->required()
                        ->maxLength(80)
                        ->live(onBlur: true)
                        ->afterStateUpdated(fn (?string $state, Forms\Set $set) => $set('slug', Str::slug((string) $state, '_'))),
                    Forms\Components\TextInput::make('slug')
                        ->label('Identificador')
                        ->required()
                        ->maxLength(80)
                        ->alphaDash()
                        ->rules([Rule::notIn(array_keys(SalesProjectType::builtInOptions()))])
                        ->unique(
                            ignoreRecord: true,
                            modifyRuleUsing: fn ($rule) => $rule->where('tenant_id', session('tenant_id')),
                        )
                        ->disabled(fn (?SalesProjectType $record): bool => $record !== null)
                        ->dehydrated()
                        ->helperText('Usado internamente. Nao altere depois que o tipo estiver em uso.'),
                    Forms\Components\Select::make('color')
                        ->label('Cor')
                        ->options([
                            'success' => 'Verde',
                            'info' => 'Azul',
                            'warning' => 'Amarelo',
                            'danger' => 'Vermelho',
                            'primary' => 'Destaque',
                            'gray' => 'Cinza',
                        ])
                        ->default('gray')
                        ->required(),
                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                    Forms\Components\Textarea::make('description')
                        ->label('Descricao')
                        ->rows(3)
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                Tables\Columns\TextColumn::make('slug')->label('Identificador')->copyable(),
                Tables\Columns\TextColumn::make('color')->label('Cor')->badge()->color(fn (string $state) => $state),
                Tables\Columns\IconColumn::make('is_active')->label('Ativo')->boolean(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make()
                    ->disabled(fn (SalesProjectType $record): bool => SalesProject::query()
                        ->where('tenant_id', $record->tenant_id)
                        ->where('type', $record->slug)
                        ->exists())
                    ->tooltip(fn (SalesProjectType $record): ?string => SalesProject::query()
                        ->where('tenant_id', $record->tenant_id)
                        ->where('type', $record->slug)
                        ->exists()
                            ? 'Este tipo esta em uso e nao pode ser excluido.'
                            : null),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSalesProjectTypes::route('/'),
            'create' => Pages\CreateSalesProjectType::route('/create'),
            'edit' => Pages\EditSalesProjectType::route('/{record}/edit'),
        ];
    }
}
