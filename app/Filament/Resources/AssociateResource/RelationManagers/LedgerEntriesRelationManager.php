<?php

namespace App\Filament\Resources\AssociateResource\RelationManagers;

use App\Enums\LedgerCategory;
use App\Enums\LedgerType;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Response;
use Filament\Tables\Actions\Action;

class LedgerEntriesRelationManager extends RelationManager
{
    protected static string $relationship = 'ledgerEntries';

    protected static ?string $title = 'Conta Corrente';

    protected static ?string $modelLabel = 'Lançamento';

    protected static ?string $pluralModelLabel = 'Lançamentos';

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make()
                    ->schema([
                        Forms\Components\Select::make('type')
                            ->label('Tipo de Lançamento')
                            ->options(LedgerType::class)
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(fn (callable $set, $state) => 
                                $set('category', $state === 'credit' ? 'producao' : 'servico')
                            ),

                        Forms\Components\TextInput::make('amount')
                            ->label('Valor')
                            ->numeric()
                            ->prefix('R$')
                            ->required()
                            ->minValue(0.01),

                        Forms\Components\Select::make('category')
                            ->label('Categoria')
                            ->options(LedgerCategory::class)
                            ->required(),

                        Forms\Components\DatePicker::make('transaction_date')
                            ->label('Data do Lançamento')
                            ->required()
                            ->default(now())
                            ->maxDate(now()),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Detalhes')
                    ->schema([
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Ex: Pagamento produção projeto X')
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('notes')
                            ->label('Observações (Interno)')
                            ->rows(2)
                            ->placeholder('Notas adicionais (opcional)')
                            ->columnSpanFull(),
                    ])
                    ->collapsed(),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('description')
            ->defaultSort('transaction_date', 'desc')
            ->poll('30s')
            ->columns([
                Tables\Columns\TextColumn::make('transaction_date')
                    ->label('Data')
                    ->date('d/m/Y')
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->label('Tipo')
                    ->badge()
                    ->formatStateUsing(fn (LedgerType $state): string => $state->label())
                    ->color(fn (LedgerType $state): string => $state->color())
                    ->icon(fn (LedgerType $state): string => 
                        $state === LedgerType::CREDIT ? 'heroicon-o-arrow-up' : 'heroicon-o-arrow-down'
                    ),

                Tables\Columns\TextColumn::make('category')
                    ->label('Categoria')
                    ->badge()
                    ->formatStateUsing(fn (LedgerCategory $state): string => $state->label())
                    ->color(fn (LedgerCategory $state): string => $state->color())
                    ->toggleable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Descrição')
                    ->limit(35)
                    ->searchable()
                    ->tooltip(fn ($record) => $record->description),

                Tables\Columns\TextColumn::make('amount')
                    ->label('Valor')
                    ->formatStateUsing(fn ($state, $record): string => 
                        ($record->type === LedgerType::CREDIT ? '+' : '-') . 
                        ' R$ ' . number_format($state, 2, ',', '.')
                    )
                    ->color(fn ($record): string => 
                        $record->type === LedgerType::CREDIT ? 'success' : 'danger'
                    )
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('balance_after')
                    ->label('Saldo')
                    ->formatStateUsing(fn ($state): string => 
                        'R$ ' . number_format($state, 2, ',', '.')
                    )
                    ->color(fn ($state): string => $state >= 0 ? 'success' : 'danger')
                    ->weight('bold')
                    ->alignEnd(),

                Tables\Columns\TextColumn::make('creator.name')
                    ->label('Por')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('Tipo')
                    ->options(LedgerType::class),

                Tables\Filters\SelectFilter::make('category')
                    ->label('Categoria')
                    ->options(LedgerCategory::class),

                Tables\Filters\Filter::make('date_range')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '>=', $date),
                            )
                            ->when(
                                $data['until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('transaction_date', '<=', $date),
                            );
                    })
                    ->indicateUsing(function (array $data): array {
                        $indicators = [];
                        if ($data['from'] ?? null) {
                            $indicators['from'] = 'De: ' . \Carbon\Carbon::parse($data['from'])->format('d/m/Y');
                        }
                        if ($data['until'] ?? null) {
                            $indicators['until'] = 'Até: ' . \Carbon\Carbon::parse($data['until'])->format('d/m/Y');
                        }
                        return $indicators;
                    }),
            ])
            ->headerActions([
                // Saldo atual
                Action::make('current_balance')
                    ->label(function () {
                        $balance = $this->ownerRecord->current_balance ?? 0;
                        $formatted = 'R$ ' . number_format(abs($balance), 2, ',', '.');
                        return 'Saldo: ' . ($balance >= 0 ? $formatted : '- ' . $formatted);
                    })
                    ->icon('heroicon-o-banknotes')
                    ->color(fn () => ($this->ownerRecord->current_balance ?? 0) >= 0 ? 'success' : 'danger')
                    ->disabled(),

                Tables\Actions\CreateAction::make()
                    ->label('Novo Lançamento')
                    ->icon('heroicon-o-plus-circle')
                    ->mutateFormDataUsing(function (array $data): array {
                        $associate = $this->ownerRecord;
                        $currentBalance = $associate->current_balance ?? 0;
                        
                        if ($data['type'] === 'credit') {
                            $data['balance_after'] = $currentBalance + floatval($data['amount']);
                        } else {
                            $data['balance_after'] = $currentBalance - floatval($data['amount']);
                        }
                        
                        $data['created_by'] = auth()->id();
                        
                        return $data;
                    })
                    ->after(function () {
                        $this->ownerRecord->refresh();
                    }),

                // Exportar PDF
                Action::make('export_pdf')
                    ->label('PDF')
                    ->icon('heroicon-o-document-arrow-down')
                    ->color('danger')
                    ->form([
                        Forms\Components\Grid::make(2)
                            ->schema([
                                Forms\Components\DatePicker::make('from')
                                    ->label('De')
                                    ->default(now()->startOfMonth()),
                                Forms\Components\DatePicker::make('until')
                                    ->label('Até')
                                    ->default(now()),
                            ]),
                    ])
                    ->action(function (array $data) {
                        $entries = $this->ownerRecord->ledgerEntries()
                            ->when($data['from'], fn ($q) => $q->whereDate('transaction_date', '>=', $data['from']))
                            ->when($data['until'], fn ($q) => $q->whereDate('transaction_date', '<=', $data['until']))
                            ->orderBy('transaction_date', 'asc')
                            ->get();

                        $totals = [
                            'credits' => $entries->where('type', LedgerType::CREDIT)->sum('amount'),
                            'debits' => $entries->where('type', LedgerType::DEBIT)->sum('amount'),
                            'balance' => $this->ownerRecord->current_balance,
                        ];

                        $pdf = Pdf::loadView('pdf.associate-statement', [
                            'associate' => $this->ownerRecord,
                            'entries' => $entries,
                            'totals' => $totals,
                            'period' => [
                                'from' => $data['from'] ? \Carbon\Carbon::parse($data['from'])->format('d/m/Y') : 'Início',
                                'until' => $data['until'] ? \Carbon\Carbon::parse($data['until'])->format('d/m/Y') : 'Hoje',
                            ],
                            'generated_at' => now()->format('d/m/Y H:i'),
                        ]);

                        return Response::streamDownload(function () use ($pdf) {
                            echo $pdf->output();
                        }, 'extrato-' . $this->ownerRecord->id . '-' . now()->format('Y-m-d') . '.pdf');
                    }),

                // Exportar Excel
                Action::make('export_excel')
                    ->label('Excel')
                    ->icon('heroicon-o-table-cells')
                    ->color('success')
                    ->action(function () {
                        return \Maatwebsite\Excel\Facades\Excel::download(
                            new \App\Exports\AssociateLedgerExport($this->ownerRecord->id),
                            'extrato-' . $this->ownerRecord->id . '-' . now()->format('Y-m-d') . '.xlsx'
                        );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make()
                    ->modalHeading(fn ($record) => 'Lançamento - ' . $record->transaction_date->format('d/m/Y')),
            ])
            ->bulkActions([])
            ->emptyStateHeading('Nenhum lançamento')
            ->emptyStateDescription('A conta corrente deste associado está vazia.')
            ->emptyStateIcon('heroicon-o-banknotes');
    }
}
