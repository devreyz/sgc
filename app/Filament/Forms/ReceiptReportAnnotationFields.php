<?php

namespace App\Filament\Forms;

use App\Services\ReceiptReportAnnotationService;
use Filament\Forms;
use Filament\Forms\Get;
use Filament\Forms\Set;

class ReceiptReportAnnotationFields
{
    public static function section(): Forms\Components\Section
    {
        return Forms\Components\Section::make('Observações')
            ->description('Anotações exclusivas deste PDF. Associe uma nota a produto/distribuição ou selecione uma observação da entrega para importá-la com marcador; notas gerais aparecem sem referência.')
            ->schema([
                Forms\Components\Select::make('report_annotations_position')
                    ->label('Posição das observações')
                    ->options(['before' => 'Antes da tabela', 'after' => 'Depois da tabela'])
                    ->default('after')->required(),
                Forms\Components\Repeater::make('report_annotations')
                    ->label('Notas do relatório')
                    ->schema([
                        Forms\Components\Select::make('target')
                            ->label('Aplicar a')
                            ->options(function (Get $get, $record): array {
                                $ids = (array) ($get('../../delivery_ids') ?: $record?->delivery_ids ?: []);

                                return app(ReceiptReportAnnotationService::class)
                                    ->targetOptions($ids, (int) ($record?->tenant_id ?: session('tenant_id')));
                            })
                            ->live()
                            ->afterStateUpdated(function (?string $state, Set $set, Get $get, $record): void {
                                if (! str_starts_with((string) $state, 'delivery-note:')) {
                                    return;
                                }
                                $ids = (array) ($get('../../delivery_ids') ?: $record?->delivery_ids ?: []);
                                $text = app(ReceiptReportAnnotationService::class)->deliveryNoteTexts(
                                    $ids,
                                    (int) ($record?->tenant_id ?: session('tenant_id')),
                                )[$state] ?? null;
                                if ($text !== null) {
                                    $set('text', $text);
                                }
                            })
                            ->default('global')->required()->searchable(),
                        Forms\Components\Textarea::make('text')
                            ->label('Texto da observação')->required()->maxLength(1000)->rows(2),
                    ])
                    ->columns(2)->defaultItems(0)->addActionLabel('Adicionar observação')
                    ->reorderable()->maxItems(30)->columnSpanFull(),
            ])
            ->collapsible();
    }
}
