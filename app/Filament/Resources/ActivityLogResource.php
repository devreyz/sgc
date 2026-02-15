<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ActivityLogResource\Pages;
use Spatie\Activitylog\Models\Activity;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ActivityLogResource extends Resource
{
    protected static ?string $model = Activity::class;

    protected static ?string $navigationIcon = 'heroicon-o-clipboard-document-list';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Log de Atividade';

    protected static ?string $pluralModelLabel = 'Logs de Atividade';

    protected static ?int $navigationSort = 99;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function shouldRegisterNavigation(): bool
    {
        return auth()->user()?->hasRole('super_admin') ?? false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Detalhes do Log')
                    ->schema([
                        Forms\Components\TextInput::make('log_name')
                            ->label('Nome do Log'),
                        Forms\Components\TextInput::make('description')
                            ->label('Descrição'),
                        Forms\Components\TextInput::make('subject_type')
                            ->label('Tipo do Sujeito'),
                        Forms\Components\TextInput::make('subject_id')
                            ->label('ID do Sujeito'),
                        Forms\Components\TextInput::make('causer_type')
                            ->label('Tipo do Causador'),
                        Forms\Components\TextInput::make('causer_id')
                            ->label('ID do Causador'),
                        Forms\Components\KeyValue::make('properties')
                            ->label('Propriedades')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Data/Hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                Tables\Columns\TextColumn::make('log_name')
                    ->label('Log')
                    ->badge()
                    ->searchable(),

                Tables\Columns\TextColumn::make('description')
                    ->label('Ação')
                    ->badge()
                    ->color(fn ($state): string => match ($state) {
                        'created' => 'success',
                        'updated' => 'warning',
                        'deleted' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                        default => $state,
                    }),

                Tables\Columns\TextColumn::make('subject_type')
                    ->label('Entidade')
                    ->formatStateUsing(fn ($state): string => class_basename($state))
                    ->searchable(),

                Tables\Columns\TextColumn::make('subject_id')
                    ->label('ID'),

                Tables\Columns\TextColumn::make('causer.display_name')
                    ->label('Usuário')
                    ->searchable(['users.name']),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('log_name')
                    ->label('Log')
                    ->options(fn () => Activity::distinct()->pluck('log_name', 'log_name')->toArray()),
                Tables\Filters\SelectFilter::make('description')
                    ->label('Ação')
                    ->options([
                        'created' => 'Criado',
                        'updated' => 'Atualizado',
                        'deleted' => 'Excluído',
                    ]),
                Tables\Filters\Filter::make('created_at')
                    ->form([
                        Forms\Components\DatePicker::make('from')
                            ->label('De'),
                        Forms\Components\DatePicker::make('until')
                            ->label('Até'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['from'], fn ($q, $date) => $q->whereDate('created_at', '>=', $date))
                            ->when($data['until'], fn ($q, $date) => $q->whereDate('created_at', '<=', $date));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListActivityLogs::route('/'),
            'view' => Pages\ViewActivityLog::route('/{record}'),
        ];
    }
}
