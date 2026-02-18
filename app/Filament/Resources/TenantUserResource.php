<?php

namespace App\Filament\Resources;

use App\Filament\Resources\TenantUserResource\Pages;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\EmailSwapService;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * TenantUserResource — Gerencia vínculos de membros da organização.
 *
 * SUBSTITUI o antigo UserResource no painel Admin (tenant).
 *
 * O que o Admin pode fazer:
 * - Ver todos os membros da sua organização
 * - Ativar/desativar vínculos (NUNCA deletar)
 * - Alterar role do vínculo
 * - Alterar nome de exibição no tenant
 * - Trocar email (via fluxo seguro EmailSwapService)
 * - Convidar novos membros (vincular user existente ou criar novo)
 *
 * O que o Admin NÃO pode fazer:
 * - Deletar vínculos
 * - Editar dados globais do User (nome global, senha global)
 * - Ver membros de outras organizações
 * - Ver super_admins
 */
class TenantUserResource extends Resource
{
    protected static ?string $model = TenantUser::class;

    protected static ?string $navigationIcon = 'heroicon-o-user-group';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Membros';

    protected static ?string $modelLabel = 'Membro';

    protected static ?string $pluralModelLabel = 'Membros';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                // ─── Seção: Dados do Membro ──────────────────────────
                Forms\Components\Section::make('Dados do Membro')
                    ->description('Informações do vínculo deste membro com a organização')
                    ->schema([
                        // Na criação: permite buscar user existente ou criar novo
                        Forms\Components\Select::make('user_id')
                            ->label('Usuário (Email)')
                            ->relationship('user', 'email')
                            ->getOptionLabelFromRecordUsing(fn (User $record) => "{$record->getRawOriginal('name')} ({$record->email})")
                            ->searchable(['name', 'email'])
                            ->preload()
                            ->required()
                            ->visible(fn (string $operation): bool => $operation === 'create')
                            ->helperText('Selecione um usuário existente ou crie um novo abaixo.')
                            ->createOptionForm([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome Completo')
                                    ->required()
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('email')
                                    ->label('E-mail')
                                    ->email()
                                    ->required()
                                    ->unique('users', 'email')
                                    ->maxLength(255),
                                Forms\Components\TextInput::make('password')
                                    ->label('Senha')
                                    ->password()
                                    ->required()
                                    ->minLength(8)
                                    ->maxLength(255)
                                    ->dehydrateStateUsing(fn ($state) => Hash::make($state)),
                            ])
                            ->createOptionUsing(function (array $data): int {
                                $user = User::create([
                                    'name' => $data['name'],
                                    'email' => $data['email'],
                                    'password' => $data['password'],
                                    'status' => true,
                                ]);

                                return $user->id;
                            }),

                        // Na edição: mostra email como read-only (troca é via ação dedicada)
                        Forms\Components\TextInput::make('user_email_display')
                            ->label('Email do Usuário')
                            ->disabled()
                            ->dehydrated(false)
                            ->formatStateUsing(fn (?TenantUser $record) => $record?->user?->email ?? '')
                            ->visible(fn (string $operation): bool => $operation === 'edit')
                            ->helperText('Para trocar o email, use a ação "Trocar Email" na listagem.'),

                        Forms\Components\TextInput::make('tenant_name')
                            ->label('Nome de Exibição')
                            ->maxLength(255)
                            ->helperText('Nome usado nesta organização. Se vazio, usa o nome global do usuário.')
                            ->placeholder('Deixe vazio para usar o nome global'),

                        Forms\Components\Toggle::make('is_admin')
                            ->label('Administrador da Organização')
                            ->helperText('Administradores podem gerenciar todos os dados da organização.')
                            ->default(false),

                        Forms\Components\Select::make('roles')
                            ->label('Funções (Roles)')
                            ->multiple()
                            ->options(function () {
                                return \Spatie\Permission\Models\Role::query()
                                    ->where('name', '!=', 'super_admin')
                                    ->pluck('name', 'name');
                            })
                            ->searchable()
                            ->helperText('Funções do membro nesta organização.'),

                        Forms\Components\Toggle::make('status')
                            ->label('Vínculo Ativo')
                            ->default(true)
                            ->helperText('Membros desativados perdem acesso à organização, mas o histórico é mantido.'),
                    ])->columns(2),

                // ─── Seção: Observações ──────────────────────────────
                Forms\Components\Section::make('Observações')
                    ->schema([
                        Forms\Components\Textarea::make('notes')
                            ->label('Notas Administrativas')
                            ->rows(3)
                            ->maxLength(1000)
                            ->helperText('Observações internas sobre este membro.')
                            ->columnSpanFull(),
                    ])->collapsed(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $tenantId = session('tenant_id');

                if ($tenantId) {
                    $query->where('tenant_user.tenant_id', $tenantId);
                } else {
                    $query->whereRaw('1 = 0');
                }

                // Excluir super_admins da listagem
                $query->whereHas('user', function ($q) {
                    $q->whereDoesntHave('roles', function ($rq) {
                        $rq->where('name', 'super_admin');
                    });
                });

                // Eager load user para evitar N+1
                $query->with('user');
            })
            ->columns([
                Tables\Columns\ImageColumn::make('user.avatar')
                    ->label('')
                    ->circular()
                    ->defaultImageUrl(fn ($record) => 'https://ui-avatars.com/api/?name=' . urlencode($record->display_name)),

                Tables\Columns\TextColumn::make('display_name')
                    ->label('Nome')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function ($q) use ($search) {
                            $q->where('tenant_name', 'like', "%{$search}%")
                              ->orWhereHas('user', fn ($uq) => $uq->where('name', 'like', "%{$search}%"));
                        });
                    })
                    ->sortable(query: function (Builder $query, string $direction): Builder {
                        return $query->orderByRaw("COALESCE(tenant_name, (SELECT name FROM users WHERE users.id = tenant_user.user_id)) {$direction}");
                    }),

                Tables\Columns\TextColumn::make('user.email')
                    ->label('Email')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('user', fn ($q) => $q->where('email', 'like', "%{$search}%"));
                    })
                    ->copyable(),

                Tables\Columns\TextColumn::make('roles')
                    ->label('Funções')
                    ->badge()
                    ->color('info')
                    ->formatStateUsing(function ($state) {
                        if (is_array($state)) return implode(', ', $state);
                        if (is_string($state)) {
                            $decoded = json_decode($state, true);
                            return is_array($decoded) ? implode(', ', $decoded) : $state;
                        }
                        return '';
                    }),

                Tables\Columns\IconColumn::make('is_admin')
                    ->label('Admin')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\IconColumn::make('status')
                    ->label('Ativo')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Vinculado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('deactivated_at')
                    ->label('Desativado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('status')
                    ->label('Status do Vínculo')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Ativos')
                    ->falseLabel('Apenas Desativados'),

                Tables\Filters\TernaryFilter::make('is_admin')
                    ->label('Tipo')
                    ->placeholder('Todos')
                    ->trueLabel('Apenas Admins')
                    ->falseLabel('Apenas Membros'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),

                // Ação: Ativar/Desativar vínculo
                Tables\Actions\Action::make('toggleStatus')
                    ->label(fn (TenantUser $record) => $record->status ? 'Desativar' : 'Ativar')
                    ->icon(fn (TenantUser $record) => $record->status ? 'heroicon-o-x-circle' : 'heroicon-o-check-circle')
                    ->color(fn (TenantUser $record) => $record->status ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (TenantUser $record) => $record->status
                        ? 'Desativar membro?'
                        : 'Reativar membro?')
                    ->modalDescription(fn (TenantUser $record) => $record->status
                        ? 'O membro perderá acesso à organização, mas todo o histórico será preservado. Esta ação pode ser revertida.'
                        : 'O membro recuperará acesso à organização com as mesmas permissões anteriores.')
                    ->action(function (TenantUser $record) {
                        if ($record->status) {
                            $record->deactivate(auth()->id());
                            Notification::make()->success()->title('Membro desativado')->body('O acesso foi removido, mas o histórico foi preservado.')->send();
                        } else {
                            $record->activate();
                            Notification::make()->success()->title('Membro reativado')->body('O acesso à organização foi restaurado.')->send();
                        }
                    }),

                // Ação: Trocar Email (fluxo seguro)
                Tables\Actions\Action::make('swapEmail')
                    ->label('Trocar Email')
                    ->icon('heroicon-o-envelope')
                    ->color('warning')
                    ->form([
                        Forms\Components\TextInput::make('new_email')
                            ->label('Novo Email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->helperText('Se já existir um usuário com este email, o vínculo será transferido. Se não, um novo usuário será criado.'),
                    ])
                    ->requiresConfirmation()
                    ->modalHeading('Trocar Email do Membro')
                    ->modalDescription('ATENÇÃO: O email anterior perderá acesso a esta organização. O novo email receberá o acesso com todas as permissões atuais. Todo o histórico será preservado.')
                    ->action(function (TenantUser $record, array $data) {
                        $service = new EmailSwapService();
                        $result = $service->swap($record, $data['new_email'], auth()->id());

                        if ($result['success']) {
                            Notification::make()
                                ->success()
                                ->title('Email alterado com sucesso')
                                ->body($result['message'])
                                ->send();
                        } else {
                            Notification::make()
                                ->danger()
                                ->title('Erro ao trocar email')
                                ->body($result['message'])
                                ->send();
                        }
                    }),
            ])
            ->bulkActions([
                // Nenhuma bulk action de delete - vínculos nunca são deletados
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListTenantUsers::route('/'),
            'create' => Pages\CreateTenantUser::route('/create'),
            'edit' => Pages\EditTenantUser::route('/{record}/edit'),
        ];
    }

    /**
     * Garante que ao criar um novo vínculo, o tenant_id é injetado automaticamente.
     */
    public static function mutateFormDataBeforeCreate(array $data): array
    {
        $data['tenant_id'] = session('tenant_id');
        return $data;
    }
}
