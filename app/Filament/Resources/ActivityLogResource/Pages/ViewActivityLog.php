<?php

namespace App\Filament\Resources\ActivityLogResource\Pages;

use App\Filament\Resources\ActivityLogResource;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Resources\Pages\ViewRecord;

class ViewActivityLog extends ViewRecord
{
    protected static string $resource = ActivityLogResource::class;

    public function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Infolists\Components\Section::make('Informações Gerais')
                    ->schema([
                        Infolists\Components\TextEntry::make('created_at')
                            ->label('Data e Hora')
                            ->dateTime('d/m/Y H:i:s')
                            ->icon('heroicon-o-clock'),

                        Infolists\Components\TextEntry::make('causer.name')
                            ->label('Usuário Responsável')
                            ->default('Sistema')
                            ->icon('heroicon-o-user'),

                        Infolists\Components\TextEntry::make('causer.email')
                            ->label('E-mail do Usuário')
                            ->copyable()
                            ->visible(fn ($record) => ! empty($record->causer?->email)),

                        Infolists\Components\TextEntry::make('description')
                            ->label('Tipo de Ação')
                            ->badge()
                            ->color(fn ($state): string => match ($state) {
                                'created' => 'success',
                                'updated' => 'warning',
                                'deleted' => 'danger',
                                'restored' => 'info',
                                default => 'gray',
                            })
                            ->formatStateUsing(fn ($state): string => match ($state) {
                                'created' => '✓ Criado',
                                'updated' => '✎ Atualizado',
                                'deleted' => '⨯ Excluído',
                                'restored' => '↺ Restaurado',
                                default => ucfirst($state),
                            }),

                        Infolists\Components\TextEntry::make('log_name')
                            ->label('Categoria')
                            ->badge()
                            ->color('info'),
                    ])
                    ->columns(4),

                Infolists\Components\Section::make('Entidade Afetada')
                    ->schema([
                        Infolists\Components\TextEntry::make('subject_type')
                            ->label('Tipo de Entidade')
                            ->icon('heroicon-o-cube')
                            ->formatStateUsing(function ($state, $record) {
                                $basename = class_basename($state);
                                $translations = [
                                    'User' => 'Usuário',
                                    'Tenant' => 'Organização',
                                    'TenantUser' => 'Membro',
                                    'Associate' => 'Associado',
                                    'ServiceProvider' => 'Prestador',
                                    'ServiceOrder' => 'Ordem de Serviço',
                                    'Expense' => 'Despesa',
                                    'BankAccount' => 'Conta Bancária',
                                    'Product' => 'Produto',
                                    'CashMovement' => 'Movimento de Caixa',
                                ];

                                return $translations[$basename] ?? $basename;
                            }),

                        Infolists\Components\TextEntry::make('subject_id')
                            ->label('Registro')
                            ->badge()
                            ->color('gray')
                            ->formatStateUsing(function ($state, $record) {
                                if (! empty($record->subject_type) && ! empty($record->subject_id) && class_exists($record->subject_type)) {
                                    try {
                                        $model = $record->subject_type::find($record->subject_id);
                                        if ($model) {
                                            $display = $model->name ?? $model->title ?? $model->display_name ?? $model->getKey();

                                            return $display;
                                        }
                                    } catch (\Throwable $e) {
                                        // ignore
                                    }
                                }

                                return $state;
                            }),

                        Infolists\Components\TextEntry::make('tenant.name')
                            ->label('Organização')
                            ->icon('heroicon-o-building-office')
                            ->visible(fn ($record) => $record->tenant_id && auth()->user()?->hasRole('super_admin')),
                    ])
                    ->columns(3),

                Infolists\Components\Section::make('Detalhes das Alterações')
                    ->schema([
                        Infolists\Components\ViewEntry::make('properties')
                            ->label('')
                            ->view('filament.infolists.activity-changes')
                            ->visible(fn ($record) => ! empty($record->properties)),
                    ])
                    ->visible(fn ($record) => ! empty($record->properties))
                    ->collapsible(),

                Infolists\Components\Section::make('Dados Técnicos')
                    ->schema([
                        Infolists\Components\TextEntry::make('batch_uuid')
                            ->label('UUID do Batch')
                            ->copyable()
                            ->visible(fn ($record) => ! empty($record->batch_uuid)),

                        Infolists\Components\ViewEntry::make('properties')
                            ->label('Propriedades JSON')
                            ->view('filament.infolists.activity-json'),
                    ])
                    ->collapsed(),
            ]);
    }
}
