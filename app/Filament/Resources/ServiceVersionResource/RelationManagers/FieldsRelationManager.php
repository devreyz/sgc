<?php

namespace App\Filament\Resources\ServiceVersionResource\RelationManagers;

use App\Models\ServiceVersionField;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;

class FieldsRelationManager extends RelationManager
{
    protected static string $relationship = 'fields';

    protected static ?string $title = 'Campos do fluxo operacional';

    public function form(Form $form): Form
    {
        return $form->schema([
            Forms\Components\TextInput::make('key')->label('Chave')->required()->alphaDash()->maxLength(80),
            Forms\Components\TextInput::make('label')->label('Nome exibido')->required()->maxLength(191),
            Forms\Components\Select::make('type')->label('Tipo')->options(array_combine(ServiceVersionField::TYPES, ServiceVersionField::TYPES))->required(),
            Forms\Components\Select::make('phase')->label('Etapa')->options(['order' => 'Criação da ordem', 'start' => 'Início', 'execution' => 'Execução', 'finish' => 'Conclusão', 'review' => 'Conferência'])->required(),
            Forms\Components\TextInput::make('section')->label('Seção')->maxLength(80),
            Forms\Components\TextInput::make('unit')->label('Unidade')->maxLength(30),
            Forms\Components\Toggle::make('required')->label('Obrigatório'),
            Forms\Components\TextInput::make('minimum')->label('Valor mínimo')->numeric()->minValue(0),
            Forms\Components\TextInput::make('maximum')->label('Valor máximo')->numeric()->minValue(0),
            Forms\Components\Toggle::make('reportable')->label('Exibir na prestação de contas')->default(true),
            Forms\Components\KeyValue::make('options')->label('Opções de seleção')->keyLabel('Valor')->valueLabel('Nome exibido')->columnSpanFull(),
            Forms\Components\Toggle::make('visible_to_provider')->label('Visível ao prestador')->default(true),
            Forms\Components\Toggle::make('editable_by_provider')->label('Prestador pode preencher')->default(true),
            Forms\Components\Toggle::make('include_in_documents')->label('Incluir em documentos'),
            Forms\Components\TextInput::make('sort_order')->label('Ordem')->numeric()->default(0),
            Forms\Components\Textarea::make('help')->label('Ajuda')->columnSpanFull(),
        ])->columns(2);
    }

    public function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('label')->label('Campo')->searchable(),
            Tables\Columns\TextColumn::make('type')->label('Tipo')->badge(),
            Tables\Columns\TextColumn::make('phase')->label('Etapa')->badge(),
            Tables\Columns\IconColumn::make('required')->label('Obrigatório')->boolean(),
        ])->headerActions([
            Tables\Actions\CreateAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
        ])->actions([
            Tables\Actions\EditAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
            Tables\Actions\DeleteAction::make()->visible(fn () => $this->getOwnerRecord()->status === 'draft'),
        ]);
    }
}
