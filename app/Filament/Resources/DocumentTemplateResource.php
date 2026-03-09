<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use App\Models\PdfLayoutTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;
use Illuminate\Support\HtmlString;

class DocumentTemplateResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = DocumentTemplate::class;
    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';
    protected static ?string $navigationGroup = 'Sistema';
    protected static ?string $modelLabel = 'Modelo de Documento';
    protected static ?string $pluralModelLabel = 'Modelos de Documentos';
    protected static ?int $navigationSort = 10;

    // Esconde da navegação — substituído pelos dois sub-resources
    protected static bool $shouldRegisterNavigation = false;

    public static function form(Form $form): Form
    {
        return $form->schema([

            // ═══ Basic Info ═══
            Forms\Components\Section::make('Informações do Modelo')
                ->schema([
                    Forms\Components\TextInput::make('name')
                        ->label('Nome do Modelo')
                        ->required()
                        ->maxLength(255),

                    Forms\Components\Select::make('template_category')
                        ->label('Categoria')
                        ->options(DocumentTemplate::CATEGORIES)
                        ->required()
                        ->default('custom')
                        ->live()
                        ->disabled(fn ($record) => $record && $record->isSystem())
                        ->dehydrated()
                        ->helperText('PDF do Sistema: seções/colunas configuráveis. Personalizado: editor livre.'),

                    Forms\Components\Select::make('type')
                        ->label('Tipo')
                        ->options(DocumentTemplate::TYPES)
                        ->required()
                        ->default('other'),

                    Forms\Components\Toggle::make('is_active')
                        ->label('Ativo')
                        ->default(true),

                    Forms\Components\Textarea::make('description')
                        ->label('Descrição')
                        ->rows(2)
                        ->columnSpanFull(),
                ])
                ->columns(4),

            // ═══ System Template: key selection ═══
            Forms\Components\Section::make('PDF do Sistema')
                ->schema([
                    Forms\Components\Select::make('system_template_key')
                        ->label('PDF do Sistema')
                        ->options(collect(DocumentTemplate::getSystemTemplateDefinitions())
                            ->mapWithKeys(fn ($def, $key) => [$key => $def['label']])
                            ->toArray()
                        )
                        ->required()
                        ->live()
                        ->disabled(fn ($record) => $record && $record->isSystem())
                        ->dehydrated()
                        ->helperText('Selecione qual PDF do sistema este modelo configurará.'),
                ])
                ->visible(fn (Get $get) => $get('template_category') === 'system')
                ->columns(1),

            // ═══ Layout: header, footer, cover & back-cover ═══
            Forms\Components\Section::make('Layout do Documento')
                ->description('Escolha layouts de cabeçalho, rodapé, capa e contracapa. Crie modelos em "Layouts de PDF" no menu.')
                ->schema([
                    Forms\Components\Select::make('header_layout_id')
                        ->label('Cabeçalho')
                        ->options(fn () => PdfLayoutTemplate::active()
                            ->headers()
                            ->pluck('name', 'id')
                            ->prepend('— Padrão do Sistema —', '')
                            ->toArray()
                        )
                        ->default('')
                        ->searchable()
                        ->placeholder('Padrão do Sistema'),

                    Forms\Components\Select::make('footer_layout_id')
                        ->label('Rodapé')
                        ->options(fn () => PdfLayoutTemplate::active()
                            ->footers()
                            ->pluck('name', 'id')
                            ->prepend('— Padrão do Sistema —', '')
                            ->toArray()
                        )
                        ->default('')
                        ->searchable()
                        ->placeholder('Padrão do Sistema'),

                    Forms\Components\Select::make('cover_layout_id')
                        ->label('Capa')
                        ->options(fn () => PdfLayoutTemplate::active()
                            ->covers()
                            ->pluck('name', 'id')
                            ->prepend('— Sem Capa —', '')
                            ->toArray()
                        )
                        ->default('')
                        ->searchable()
                        ->placeholder('Sem Capa'),

                    Forms\Components\Select::make('back_cover_layout_id')
                        ->label('Contracapa')
                        ->options(fn () => PdfLayoutTemplate::active()
                            ->backCovers()
                            ->pluck('name', 'id')
                            ->prepend('— Sem Contracapa —', '')
                            ->toArray()
                        )
                        ->default('')
                        ->searchable()
                        ->placeholder('Sem Contracapa'),

                    Forms\Components\Select::make('paper_size')
                        ->label('Tamanho do Papel')
                        ->options(DocumentTemplate::PAPER_SIZES)
                        ->default('a4'),

                    Forms\Components\Select::make('paper_orientation')
                        ->label('Orientação')
                        ->options(DocumentTemplate::PAPER_ORIENTATIONS)
                        ->default('portrait'),

                    Forms\Components\Select::make('color_theme')
                        ->label('Tema de Cor')
                        ->options(DocumentTemplate::COLOR_THEMES)
                        ->default('org')
                        ->helperText('Cores que serão usadas ao gerar o PDF.'),
                ])
                ->columns(4),

            // ═══ System: Sections visibility ═══
            Forms\Components\Section::make('Seções Visíveis')
                ->description('Marque as seções que devem aparecer no PDF gerado.')
                ->schema([
                    Forms\Components\CheckboxList::make('visible_sections')
                        ->label('')
                        ->options(fn (Get $get) => self::getSectionsForKey($get('system_template_key')))
                        ->columns(3)
                        ->bulkToggleable(),
                ])
                ->visible(fn (Get $get) => $get('template_category') === 'system' && !empty($get('system_template_key'))),

            // ═══ System: Columns visibility ═══
            Forms\Components\Section::make('Colunas Visíveis nas Tabelas')
                ->description('Marque as colunas que devem aparecer nas tabelas do PDF.')
                ->schema([
                    Forms\Components\CheckboxList::make('visible_columns')
                        ->label('')
                        ->options(fn (Get $get) => self::getColumnsForKey($get('system_template_key')))
                        ->columns(4)
                        ->bulkToggleable(),
                ])
                ->visible(fn (Get $get) =>
                    $get('template_category') === 'system'
                    && !empty($get('system_template_key'))
                    && count(self::getColumnsForKey($get('system_template_key'))) > 0
                ),

            // ═══ Custom: Content Editor ═══
            Forms\Components\Section::make('Conteúdo do Documento')
                ->schema([
                    Forms\Components\Placeholder::make('variables_help')
                        ->label('')
                        ->content(fn () => new HtmlString(self::buildVariablesHelpHtml()))
                        ->columnSpanFull(),

                    Forms\Components\RichEditor::make('content')
                        ->label('Conteúdo do Documento')
                        ->required()
                        ->toolbarButtons([
                            'bold', 'italic', 'underline', 'strike',
                            'h2', 'h3', 'bulletList', 'orderedList',
                            'redo', 'undo', 'link', 'table',
                        ])
                        ->placeholder('Digite o conteúdo. Use {{variavel}} para dados dinâmicos e {{custom.chave}} para campos personalizados.')
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => $get('template_category') === 'custom'),

            // ═══ Custom: Custom Fields ═══
            Forms\Components\Section::make('Campos Personalizados')
                ->description('Defina campos que serão solicitados ao usuário ao gerar o PDF. Use {{custom.chave}} no conteúdo acima.')
                ->schema([
                    Forms\Components\Repeater::make('custom_fields')
                        ->label('')
                        ->schema([
                            Forms\Components\Grid::make(5)->schema([
                                Forms\Components\TextInput::make('key')
                                    ->label('Chave (variável)')
                                    ->required()
                                    ->placeholder('meu_campo')
                                    ->helperText('Sem espaços. Use como {{custom.meu_campo}}')
                                    ->maxLength(50),

                                Forms\Components\TextInput::make('label')
                                    ->label('Nome do Campo')
                                    ->required()
                                    ->placeholder('Ex: Nome do Destinatário'),

                                Forms\Components\Select::make('type')
                                    ->label('Tipo')
                                    ->options(DocumentTemplate::FIELD_TYPES)
                                    ->default('text')
                                    ->required()
                                    ->live(),

                                Forms\Components\Toggle::make('required')
                                    ->label('Obrigatório')
                                    ->default(true),

                                Forms\Components\TextInput::make('default')
                                    ->label('Valor Padrão')
                                    ->placeholder('Opcional'),
                            ]),
                            Forms\Components\Textarea::make('options')
                                ->label('Opções (para tipo Seleção — uma por linha)')
                                ->placeholder("Opção 1\nOpção 2\nOpção 3")
                                ->rows(3)
                                ->visible(fn (Get $get) => $get('type') === 'select'),
                        ])
                        ->addActionLabel('+ Adicionar Campo Personalizado')
                        ->collapsible()
                        ->itemLabel(fn (array $state) => ($state['label'] ?? 'Campo') . ' [' . ($state['type'] ?? 'text') . ']' . (isset($state['key']) ? ' {{custom.' . $state['key'] . '}}' : ''))
                        ->columnSpanFull(),
                ])
                ->visible(fn (Get $get) => $get('template_category') === 'custom')
                ->collapsed(),

            // ═══ System variables reference (custom templates) ═══
            Forms\Components\Section::make('Variáveis do Sistema Utilizadas')
                ->schema([
                    Forms\Components\CheckboxList::make('available_variables')
                        ->label('Marque as variáveis utilizadas neste modelo (apenas referência)')
                        ->options(function () {
                            $opts = [];
                            foreach (DocumentTemplate::getAvailableVariables() as $group => $vars) {
                                foreach ($vars as $var => $label) {
                                    $opts[$var] = "{$label} ({$var})";
                                }
                            }
                            return $opts;
                        })
                        ->columns(3)
                        ->searchable(),
                ])
                ->visible(fn (Get $get) => $get('template_category') === 'custom')
                ->collapsed(),
        ]);
    }

    private static function getSectionsForKey(?string $key): array
    {
        if (!$key) {
            return [];
        }
        return DocumentTemplate::getSystemTemplateDefinitions()[$key]['sections'] ?? [];
    }

    private static function getColumnsForKey(?string $key): array
    {
        if (!$key) {
            return [];
        }
        return DocumentTemplate::getSystemTemplateDefinitions()[$key]['columns'] ?? [];
    }

    private static function buildVariablesHelpHtml(): string
    {
        $groups = DocumentTemplate::getAvailableVariables();
        $html = '<div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">';
        $html .= '<p class="font-bold text-blue-700 dark:text-blue-300 mb-2">��� Variáveis do Sistema disponíveis</p>';
        $html .= '<div class="grid grid-cols-3 gap-1 text-xs">';
        foreach ($groups as $group => $vars) {
            $html .= '<div class="col-span-3 font-semibold text-blue-600 dark:text-blue-400 mt-2">' . $group . '</div>';
            foreach ($vars as $var => $label) {
                $html .= '<div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">' . e($var) . '</code> ' . e($label) . '</div>';
            }
        }
        $html .= '</div>';
        $html .= '<p class="text-xs text-blue-500 mt-3 font-medium">Para campos personalizados use: <code>{{custom.chave_do_campo}}</code></p>';
        $html .= '</div>';
        return $html;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable()
                    ->limit(40),

                Tables\Columns\TextColumn::make('template_category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DocumentTemplate::CATEGORIES[$state] ?? $state)
                    ->color(fn ($state) => $state === 'system' ? 'warning' : 'primary'),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn ($state) => DocumentTemplate::TYPES[$state] ?? $state)
                    ->color(fn ($state) => match ($state) {
                        'contract'      => 'primary',
                        'declaration'   => 'info',
                        'receipt'       => 'success',
                        'authorization' => 'warning',
                        default         => 'gray',
                    }),

                Tables\Columns\TextColumn::make('system_template_key')
                    ->label('PDF Sistema')
                    ->formatStateUsing(fn ($state) => $state
                        ? (DocumentTemplate::getSystemTemplateDefinitions()[$state]['label'] ?? $state)
                        : '—'
                    )
                    ->toggleable()
                    ->limit(30),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('template_category')
                    ->label('Categoria')
                    ->options(DocumentTemplate::CATEGORIES),

                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(DocumentTemplate::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('toggle_active')
                    ->label(fn ($record) => $record->is_active ? 'Desativar' : 'Ativar')
                    ->icon(fn ($record) => $record->is_active ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn ($record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->hidden(fn ($record) => $record->trashed())
                    ->action(function ($record) {
                        if (!$record->is_active) {
                            // Deactivate others with same key when activating
                            if ($record->system_template_key) {
                                DocumentTemplate::where('system_template_key', $record->system_template_key)
                                    ->where('template_category', 'system')
                                    ->where('tenant_id', $record->tenant_id)
                                    ->where('id', '!=', $record->id)
                                    ->update(['is_active' => false]);
                            }
                        }
                        $record->update(['is_active' => !$record->is_active]);
                    }),

                Tables\Actions\Action::make('preview')
                    ->label('Pré-vis.')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Pré-visualização do Modelo')
                    ->modalContent(fn ($record) => new HtmlString(
                        '<div style="font-family:Arial,sans-serif;font-size:13px;padding:20px;border:1px solid #e5e7eb;border-radius:6px;background:#fff;color:#1f2937;">'
                        . ($record->content ?: '<p style="color:#9ca3af;"><em>Este modelo não possui conteúdo de editor (é um PDF do Sistema configurável).</em></p>')
                        . '</div>'
                    ))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Fechar'),

                Tables\Actions\Action::make('duplicate')
                    ->label('Duplicar')
                    ->icon('heroicon-o-document-duplicate')
                    ->color('gray')
                    ->requiresConfirmation()
                    ->action(function ($record) {
                        $new = $record->replicate();
                        $new->name = $record->name . ' (Cópia)';
                        $new->created_by = auth()->id();
                        $new->is_active = false;
                        $new->save();
                    }),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
                Tables\Actions\ForceDeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('updated_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index'  => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'view'   => Pages\ViewDocumentTemplate::route('/{record}'),
            'edit'   => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}
