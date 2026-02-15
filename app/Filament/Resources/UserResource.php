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
                            ->options(function () {
                                // Admins de cooperativa não podem atribuir a role super_admin
                                // Super admin é gerenciado apenas no painel super-admin
                                $roles = \Spatie\Permission\Models\Role::where('name', '!=', 'super_admin')
                                    ->pluck('name', 'id');
                                return $roles;
                            })
                            ->helperText('Apenas funções de gestão da cooperativa. Super Admins são gerenciados no painel de sistema.'),
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
            ->modifyQueryUsing(function ($query) {
                // Super admin vê todos os usuários
                if (! \Illuminate\Support\Facades\Auth::user()?->hasRole('super_admin')) {
                    // Filtrar apenas usuários do tenant atual
                    $tenantId = session('tenant_id');
                    if ($tenantId) {
                        $query->whereHas('tenants', function ($q) use ($tenantId) {
                            $q->where('tenant_id', $tenantId);
                        });
                    } else {
                        // Se não houver tenant na sessão, não mostrar nenhum usuário
                        $query->whereRaw('1 = 0');
                    }
                }
                
                // Admins de cooperativa não podem ver ou gerenciar super admins
                // Super admins são gerenciados apenas no painel super-admin
                $superAdminRole = \Spatie\Permission\Models\Role::where('name', 'super_admin')->first();
                if ($superAdminRole) {
                    $query->whereDoesntHave('roles', function ($q) use ($superAdminRole) {
                        $q->where('roles.id', $superAdminRole->id);
                    });
                }
                return $query;
            })
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('')
                    ->circular(),
                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nome')
                    ->searchable(['name'])
                    ->sortable(['name']),
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
                    ->relationship('roles', 'name', function ($query) {
                        // Não mostrar super_admin nos filtros do painel admin
                        if (!\Illuminate\Support\Facades\Auth::user()?->hasRole('super_admin')) {
                            $query->where('name', '!=', 'super_admin');
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
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
