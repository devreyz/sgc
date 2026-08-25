<?php

namespace App\Filament\Resources;

use App\Enums\DeliveryConferenceStatus;
use App\Filament\Resources\DeliveryConferenceSheetResource\Pages;
use App\Filament\Traits\TenantScoped;
use App\Models\DeliveryConferenceSheet;
use App\Services\DeliveryConferenceSheetService;
use Filament\Forms;
use Filament\Infolists;
use Filament\Infolists\Infolist;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DeliveryConferenceSheetResource extends Resource
{
    use TenantScoped;

    protected static ?string $model = DeliveryConferenceSheet::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static ?string $navigationGroup = 'Projetos de Venda';

    protected static ?string $navigationLabel = 'Folhas de Conferência';

    protected static ?string $modelLabel = 'folha de conferência';

    protected static ?string $pluralModelLabel = 'folhas de conferência';

    protected static ?int $navigationSort = 34;

    public static function table(Table $table): Table
    {
        return $table->columns([
            Tables\Columns\TextColumn::make('formatted_number')->label('Folha')->weight('bold')->searchable(['number']),
            Tables\Columns\TextColumn::make('project.title')->label('Projeto')->searchable()->limit(35),
            Tables\Columns\TextColumn::make('recipient_name')->label('Destinatário')->searchable(['number'])->limit(40),
            Tables\Columns\TextColumn::make('period_start')->label('Período')->formatStateUsing(fn ($state, DeliveryConferenceSheet $record) => $record->period_start->format('d/m/Y').'–'.$record->period_end->format('d/m/Y')),
            Tables\Columns\TextColumn::make('grouping_mode')->label('Modo')->formatStateUsing(fn ($state) => $state->label())->toggleable(),
            Tables\Columns\TextColumn::make('distributions_count')->label('Entregas')->counts('distributions')->alignCenter(),
            Tables\Columns\TextColumn::make('status')->label('Situação')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
            Tables\Columns\IconColumn::make('current_validity')->label('Válida')->state(fn (DeliveryConferenceSheet $record) => $record->snapshot_hash ? ! $record->invalidated_at : null)->boolean(),
        ])->filters([
            Tables\Filters\SelectFilter::make('status')->label('Situação')->options(collect(DeliveryConferenceStatus::cases())->mapWithKeys(fn ($status) => [$status->value => $status->label()])),
            Tables\Filters\SelectFilter::make('sales_project_id')->label('Projeto')->relationship('project', 'title')->searchable()->preload(),
            Tables\Filters\Filter::make('period')->form([Forms\Components\DatePicker::make('from')->label('De'), Forms\Components\DatePicker::make('until')->label('Até')])
                ->query(fn ($query, array $data) => $query->when($data['from'] ?? null, fn ($q, $date) => $q->whereDate('period_end', '>=', $date))->when($data['until'] ?? null, fn ($q, $date) => $q->whereDate('period_start', '<=', $date))),
        ])->actions([
            Tables\Actions\ViewAction::make(),
            Tables\Actions\Action::make('portal')->label('Abrir no portal')->icon('heroicon-o-arrow-top-right-on-square')
                ->url(fn (DeliveryConferenceSheet $record) => route('delivery.conference-sheets.show', [session('tenant_slug'), $record]))->openUrlInNewTab(),
            Tables\Actions\Action::make('issue')->label('Emitir')->icon('heroicon-o-check-badge')->color('success')
                ->visible(fn (DeliveryConferenceSheet $record) => $record->isDraft() && auth()->user()->can('issue', $record))->requiresConfirmation()
                ->action(function (DeliveryConferenceSheet $record): void {
                    app(DeliveryConferenceSheetService::class)->issue($record, auth()->user());
                    Notification::make()->title('Folha emitida')->success()->send();
                }),
            Tables\Actions\Action::make('review')->label('Registrar conferência')->icon('heroicon-o-clipboard-document-check')
                ->visible(fn (DeliveryConferenceSheet $record) => $record->status === DeliveryConferenceStatus::ISSUED && auth()->user()->can('review', $record))
                ->form([
                    Forms\Components\Radio::make('decision')->label('Resultado')->options(['approved' => 'Aprovado', 'correction_requested' => 'Correção necessária', 'rejected' => 'Rejeitado'])->required(),
                    Forms\Components\Textarea::make('review_note')->label('Observação / motivo')->maxLength(2000),
                ])->action(function (DeliveryConferenceSheet $record, array $data): void {
                    app(DeliveryConferenceSheetService::class)->review($record, $data['decision'], $data['review_note'] ?? null, auth()->user());
                    Notification::make()->title('Conferência registrada')->success()->send();
                }),
        ])->bulkActions([])->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist->schema([
            Infolists\Components\Section::make('Identificação')->schema([
                Infolists\Components\TextEntry::make('formatted_number')->label('Folha'),
                Infolists\Components\TextEntry::make('project.title')->label('Projeto'),
                Infolists\Components\TextEntry::make('recipient_name')->label('Destinatário'),
                Infolists\Components\TextEntry::make('status')->label('Situação')->badge()->formatStateUsing(fn ($state) => $state->label())->color(fn ($state) => $state->color()),
                Infolists\Components\TextEntry::make('period_start')->label('Período')->formatStateUsing(fn ($state, DeliveryConferenceSheet $record) => $record->period_start->format('d/m/Y').' a '.$record->period_end->format('d/m/Y')),
                Infolists\Components\TextEntry::make('grouping_mode')->label('Apresentação')->formatStateUsing(fn ($state) => $state->label()),
                Infolists\Components\TextEntry::make('revision')->label('Revisão'),
                Infolists\Components\TextEntry::make('snapshot_hash')->label('Hash SHA-256')->copyable()->columnSpanFull(),
            ])->columns(3),
            Infolists\Components\Section::make('Retorno')->schema([
                Infolists\Components\TextEntry::make('reviewed_at')->label('Registrado em')->dateTime('d/m/Y H:i')->placeholder('Aguardando'),
                Infolists\Components\TextEntry::make('reviewer.name')->label('Registrado por')->placeholder('—'),
                Infolists\Components\TextEntry::make('review_note')->label('Observação')->placeholder('Sem observação')->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => Pages\ListDeliveryConferenceSheets::route('/'), 'view' => Pages\ViewDeliveryConferenceSheet::route('/{record}')];
    }
}
