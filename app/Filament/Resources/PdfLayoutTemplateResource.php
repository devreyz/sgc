<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PdfLayoutTemplateResource\Pages;
use App\Models\PdfLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;
use Illuminate\Support\HtmlString;

class PdfLayoutTemplateResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = PdfLayoutTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $modelLabel = 'Layout de PDF';
    protected static ?string $pluralModelLabel = 'Layouts de PDF (Cabeçalho/Rodapé)';
    protected static ?int $navigationSort = 11;

    public static function form(Form $form): Form
    {
        $variablesHtml = collect(PdfLayoutTemplate::getAvailableVariables())
            ->map(fn ($label, $var) => "<div><code class=\"bg-blue-100 dark:bg-blue-800 px-1 rounded text-xs\"><button type=\"button\" onclick=\"navigator.clipboard.writeText('{$var}')\" title=\"Copiar {$var}\">{$var}</button></code> <span class=\"text-xs text-gray-500\">{$label}</span></div>")
            ->implode('');

        return $form->schema([
            Forms\Components\Section::make('Informações do Layout')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Layout')
                        ->required()
                        ->maxLength(255)
                        ->placeholder('Ex: Cabeçalho Padrão da Cooperativa'),

                    Forms\Components\Select::make('layout_type')
                        ->label('Tipo de Layout')
                        ->options(PdfLayoutTemplate::LAYOUT_TYPES)
                        ->required()
                        ->default('header'),

                    Forms\Components\Toggle::make('is_default')
                        ->label('Padrão para este tipo')
                        ->helperText('Será selecionado automaticamente nos novos templates'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),
                ])
                ->columns(4),

            Forms\Components\Section::make('Variáveis Disponíveis')
                ->schema([
                    Forms\Components\Placeholder::make('vars_info')
                        ->label('')
                        ->content(new HtmlString(
                            '<div class="p-3 bg-amber-50 dark:bg-amber-900/20 rounded-lg text-sm">'
                            . '<p class="font-bold text-amber-700 dark:text-amber-300 mb-2">📋 Variáveis disponíveis (clique para copiar)</p>'
                            . '<div class="grid grid-cols-2 gap-1">' . $variablesHtml . '</div>'
                            . '</div>'
                        ))
                        ->columnSpanFull(),
                ])
                ->collapsed(),

            Forms\Components\Section::make('Conteúdo HTML do Layout')
                ->schema([
                    Forms\Components\Textarea::make('content')
                        ->label('Conteúdo HTML')
                        ->required()
                        ->rows(20)
                        ->placeholder('<div style="border-bottom: 2px solid #1e40af; padding-bottom: 10px; margin-bottom: 15px;">&#10;  <strong>{{cooperativa.nome}}</strong> | CNPJ: {{cooperativa.cnpj}}&#10;</div>')
                        ->helperText('HTML puro. Use as variáveis listadas acima. Para DomPDF use estilos inline.')
                        ->columnSpanFull(),
                ]),

            Forms\Components\Section::make('Pré-visualização')
                ->schema([
                    Forms\Components\Placeholder::make('preview')
                        ->label('')
                        ->content(fn ($record) => $record
                            ? new HtmlString('<div class="border rounded p-4 bg-white text-gray-800" style="font-family: Arial, sans-serif; font-size: 12px;">' . ($record->content ?? '') . '</div>')
                            : new HtmlString('<p class="text-gray-400">Salve para ver a pré-visualização.</p>')
                        )
                        ->columnSpanFull(),
                ])
                ->collapsed()
                ->visibleOn('edit'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('layout_type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => PdfLayoutTemplate::LAYOUT_TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'header' => 'primary',
                        'footer' => 'success',
                        'both'   => 'warning',
                        default  => 'gray',
                    }),

                Tables\Columns\IconColumn::make('is_default')
                    ->label('Padrão')
                    ->boolean(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('layout_type')
                    ->label('Tipo')
                    ->options(PdfLayoutTemplate::LAYOUT_TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Pré-vis.')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Pré-visualização do Layout')
                    ->modalContent(fn ($record) => new HtmlString(
                        '<div style="font-family: Arial, sans-serif; font-size: 12px; padding: 20px; background: #fff; border: 1px solid #e5e7eb; border-radius: 6px;">'
                        . $record->content
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListPdfLayoutTemplates::route('/'),
            'create' => Pages\CreatePdfLayoutTemplate::route('/create'),
            'edit'   => Pages\EditPdfLayoutTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
