<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Hash;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $modelLabel = 'Usuário';

    protected static ?string $pluralModelLabel = 'Usuários';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Informações Básicas')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Nome')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\TextInput::make('email')
                            ->label('E-mail')
                            ->email()
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(255),
                        Forms\Components\TextInput::make('password')
                            ->label('Senha')
                            ->password()
                            ->dehydrateStateUsing(fn ($state) => Hash::make($state))
                            ->dehydrated(fn ($state) => filled($state))
                            ->required(fn (string $context): bool => $context === 'create')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('status')
                            ->label('Ativo')
                            ->default(true)
                            ->required(),
                    ])->columns(2),

                Forms\Components\Section::make('Segurança')
                    ->schema([
                        Forms\Components\Select::make('roles')
                            ->label('Funções (Roles)')
                            ->relationship('roles', 'name')
                            ->multiple()
                            ->preload()
                            ->searchable()
                            ->disabled(fn ($record) => $record?->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin'))
                            ->options(function () {
                                $roles = \Spatie\Permission\Models\Role::all()->pluck('name', 'id');

                                // Se não é super_admin, remove a opção de atribuir super_admin
                                if (! auth()->user()->hasRole('super_admin')) {
                                    $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
                                    if ($superAdminRole) {
                                        $roles = $roles->except($superAdminRole->id);
                                    }
                                }

                                return $roles;
                            }),
                    ]),

                Forms\Components\Section::make('Integração Google')
                    ->schema([
                        Forms\Components\TextInput::make('google_id')
                            ->label('Google ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('avatar')
                            ->label('URL do Avatar')
                            ->disabled(),
                    ])->columns(2)->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular(),
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('email')
                    ->label('E-mail')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('roles.name')
                    ->label('Funções')
                    ->badge()
                    ->color('info'),
                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status'),
                Tables\Filters\SelectFilter::make('roles')
                    ->label('Função')
                    ->relationship('roles', 'name'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->hidden(fn ($record) => $record->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin')),
                Tables\Actions\DeleteAction::make()
                    ->hidden(fn ($record) => $record->hasRole('super_admin') && ! auth()->user()->hasRole('super_admin')),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                // Não permite deletar super_admin se usuário não for super_admin
                                if (! $record->hasRole('super_admin') || auth()->user()->hasRole('super_admin')) {
                                    $record->delete();
                                }
                            });
                        }),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
