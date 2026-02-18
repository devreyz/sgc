<?php

namespace App\Filament\Resources;

use App\Filament\Resources\DocumentTemplateResource\Pages;
use App\Models\DocumentTemplate;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Traits\TenantScoped;

class DocumentTemplateResource extends Resource
{
    use TenantScoped;
    protected static ?string $model = DocumentTemplate::class;

    protected static ?string $navigationIcon = 'heroicon-o-document-duplicate';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Modelo de Documento';

    protected static ?string $pluralModelLabel = 'Modelos de Documentos';

    protected static ?int $navigationSort = 10;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações do Modelo')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome do Modelo')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Contrato de Fornecimento de Produtos'),

                        Forms\Components\Select::make('type')
                            ->label('Tipo')
                            ->options(DocumentTemplate::TYPES)
                            ->required()
                            ->default('contract'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Ativo')
                            ->default(true),

                        Forms\Components\Textarea::make('description')
                            ->label('Descrição')
                            ->rows(2)
                            ->placeholder('Breve descrição do modelo e quando utilizar')
                            ->columnSpanFull(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('Conteúdo do Documento')
                    ->schema([
                        Forms\Components\Placeholder::make('variables_help')
                            ->label('')
                            ->content(new \Illuminate\Support\HtmlString('
                                <div class="p-4 bg-blue-50 dark:bg-blue-900/20 rounded-lg text-sm">
                                    <p class="font-bold text-blue-700 dark:text-blue-300 mb-2">📝 Variáveis Disponíveis</p>
                                    <p class="text-blue-600 dark:text-blue-400 mb-2">
                                        Use as variáveis abaixo no texto. Elas serão substituídas automaticamente ao gerar o documento.
                                    </p>
                                    <div class="grid grid-cols-2 md:grid-cols-3 gap-2 text-xs">
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{associado.nome}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{associado.cpf}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{associado.endereco}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{projeto.titulo}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{projeto.valor_total}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{data.hoje}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{financeiro.valor}}</code></div>
                                        <div><code class="bg-blue-100 dark:bg-blue-800 px-1 rounded">{{cooperativa.nome}}</code></div>
                                    </div>
                                </div>
                            '))
                            ->columnSpanFull(),

                        Forms\Components\RichEditor::make('content')
                            ->label('Conteúdo do Documento')
                            ->required()
                            ->toolbarButtons([
                                'bold',
                                'italic',
                                'underline',
                                'strike',
                                'h2',
                                'h3',
                                'bulletList',
                                'orderedList',
                                'redo',
                                'undo',
                                'link',
                                'table',
                            ])
                            ->placeholder('Digite o conteúdo do documento aqui. Use as variáveis {{variavel}} para dados dinâmicos.')
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('Variáveis Utilizadas')
                    ->schema([
                        Forms\Components\CheckboxList::make('available_variables')
                            ->label('Marque as variáveis que este modelo utiliza (para referência)')
                            ->options(function () {
                                $options = [];
                                foreach (DocumentTemplate::getAvailableVariables() as $group => $variables) {
                                    foreach ($variables as $var => $label) {
                                        $options[$var] = "{$label} ({$var})";
                                    }
                                }
                                return $options;
                            })
                            ->columns(3)
                            ->searchable(),
                    ])
                    ->collapsed(),
            ]);
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

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => DocumentTemplate::TYPES[$state] ?? $state)
                    ->color(fn (string $state): string => match($state) {
                        'contract' => 'primary',
                        'declaration' => 'info',
                        'receipt' => 'success',
                        'authorization' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(50)
                    ->toggleable(),

                Tables\Columns\IconColumn::make('is_active')
                    ->label('Ativo')
                    ->boolean(),

                Tables\Columns\TextColumn::make('generatedDocuments_count')
                    ->label('Docs Gerados')
                    ->counts('generatedDocuments')
                    ->badge()
                    ->color('gray'),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Criado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(DocumentTemplate::TYPES),

                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Ativo'),

                Tables\Filters\TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\Action::make('preview')
                    ->label('Pré-visualizar')
                    ->icon('heroicon-o-eye')
                    ->color('info')
                    ->modalHeading('Pré-visualização do Modelo')
                    ->modalContent(fn ($record) => view('filament.modals.template-preview', ['template' => $record]))
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
                        $new->save();
                    })
                    ->successNotificationTitle('Modelo duplicado!'),

                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
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
            'index' => Pages\ListDocumentTemplates::route('/'),
            'create' => Pages\CreateDocumentTemplate::route('/create'),
            'view' => Pages\ViewDocumentTemplate::route('/{record}'),
            'edit' => Pages\EditDocumentTemplate::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::active()->count() ?: null;
    }
}
