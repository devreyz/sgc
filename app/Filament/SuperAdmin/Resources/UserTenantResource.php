<?php

namespace App\Filament\SuperAdmin\Resources;

use App\Filament\SuperAdmin\Resources\UserTenantResource\Pages;
use App\Models\User;
use App\Models\Tenant;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserTenantResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Gestão de Organizações';

    protected static ?string $navigationLabel = 'Usuários e Vínculos';

    protected static ?string $modelLabel = 'Vínculo de Usuário';

    protected static ?string $pluralModelLabel = 'Vínculos de Usuários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Usuário')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true),
                        
                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->required(fn (string $context): bool => $context === 'create')
                            ->dehydrated(fn ($state) => filled($state))
                            ->minLength(8),
                        
                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('Vínculos com Organizações')
                    ->schema([
                        Forms\Components\Repeater::make('tenantRelations')
                            ->label('Organizações')
                            ->relationship('tenants')
                            ->schema([
                                Forms\Components\Select::make('id')
                                    ->label('Organização')
                                    ->options(Tenant::query()->active()->pluck('name', 'id'))
                                    ->required()
                                    ->searchable()
                                    ->disableOptionWhen(function ($value, $state, Forms\Get $get) {
                                        return collect($get('../*.id'))
                                            ->reject(fn($id) => $id === null)
                                            ->filter()
                                            ->contains($value);
                                    }),
                                
                                Forms\Components\Toggle::make('is_admin')
                                    ->label('Admin da Organização')
                                    ->default(false)
                                    ->helperText('Administradores podem gerenciar todos os dados da organização'),
                            ])
                            ->itemLabel(fn (array $state): ?string => Tenant::find($state['id'])?->name ?? 'Nova organização')
                            ->columns(2)
                            ->defaultItems(0)
                            ->addActionLabel('Adicionar Vínculo')
                            ->reorderable(false)
                            ->collapsible()
                            ->cloneable(false)
                            // Prevent Filament from attempting to create Tenant models from empty state
                            // and sync pivot data manually instead.
                            // This closure will receive the parent user record, the repeater state,
                            // and the relationship name; we build a sync array for the pivot.
                            ->saveRelationshipsUsing(function ($record, $state) {
                                if (! is_array($state)) {
                                    return;
                                }

                                $sync = [];
                                foreach ($state as $item) {
                                    if (isset($item['id']) && filled($item['id'])) {
                                        $sync[$item['id']] = [
                                            'is_admin' => isset($item['is_admin']) ? (bool) $item['is_admin'] : false,
                                        ];
                                    }
                                }

                                // Sync the pivot directly on the `tenants` relationship
                                $record->tenants()->sync($sync);
                            }),
                    ]),
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
                
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable()
                    ->copyable(),
                
                Tables\Columns\IconColumn::make('status')
                    ->label('Status')
                    ->boolean()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tenants_count')
                    ->label('Organizações')
                    ->counts('tenants')
                    ->badge()
                    ->sortable(),
                
                Tables\Columns\TextColumn::make('tenants.name')
                    ->label('Pertence a')
                    ->badge()
                    ->separator(',')
                    ->limit(3)
                    ->searchable(),
                
                Tables\Columns\IconColumn::make('isSuperAdmin')
                    ->label('Super Admin')
                    ->boolean()
                    ->getStateUsing(fn ($record) => $record->isSuperAdmin()),
                
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Inativos'),
                
                Tables\Filters\SelectFilter::make('tenant')
                    ->label('Organização')
                    ->relationship('tenants', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
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
            'index' => Pages\ListUserTenants::route('/'),
            'create' => Pages\CreateUserTenant::route('/create'),
            'edit' => Pages\EditUserTenant::route('/{record}/edit'),
        ];
    }
}
