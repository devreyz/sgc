<?php

namespace App\Filament\Resources\UserResource\Pages;

use App\Filament\Resources\UserResource;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Components\Wizard;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Hash;

class CreateUser extends CreateRecord
{
    use CreateRecord\Concerns\HasWizard;

    protected static string $resource = UserResource::class;

    /**
     * Variável para armazenar o usuário existente encontrado
     */
    protected ?User $existingUser = null;

    protected function getSteps(): array
    {
        return [
            Wizard\Step::make('E-mail')
                ->description('Digite o e-mail do usuário')
                ->schema([
                    Forms\Components\TextInput::make('email')
                        ->label('E-mail')
                        ->email()
                        ->required()
                        ->maxLength(255)
                        ->live(onBlur: true)
                        ->afterStateUpdated(function ($state, Forms\Set $set) {
                            if (filled($state)) {
                                // Buscar usuário existente por email
                                $user = User::where('email', $state)->first();
                                
                                if ($user) {
                                    // Verificar se já está nesta organização
                                    $tenantId = session('tenant_id');
                                    $alreadyInOrg = $user->tenants()->where('tenant_id', $tenantId)->exists();
                                    
                                    if ($alreadyInOrg) {
                                        Notification::make()
                                            ->title('Usuário já pertence a esta organização')
                                            ->warning()
                                            ->send();
                                        
                                        $set('user_exists', false);
                                        $set('already_in_org', true);
                                    } else {
                                        // Não expor o nome global do usuário aqui. Cada organização deve fornecer
                                        // seu próprio nome e senha que serão armazenados na pivot.
                                        $set('user_exists', true);
                                        $set('already_in_org', false);
                                        $set('existing_user_id', $user->id);
                                    }
                                } else {
                                    $set('user_exists', false);
                                    $set('already_in_org', false);
                                    $set('existing_user_id', null);
                                }
                            }
                        })
                        ->helperText('Digite o e-mail e aguarde a verificação'),
                    
                    Forms\Components\Hidden::make('user_exists')
                        ->default(false),
                    
                    Forms\Components\Hidden::make('already_in_org')
                        ->default(false),
                    
                    Forms\Components\Hidden::make('existing_user_name'),
                    
                    Forms\Components\Hidden::make('existing_user_id'),
                ]),

            Wizard\Step::make('Dados do Usuário')
                ->description('Complete os dados do usuário')
                ->schema([
                    Forms\Components\Placeholder::make('existing_user_info')
                        ->label('Usuário Encontrado')
                        ->content(fn (Get $get): string =>
                            "Usuário encontrado no sistema. Forneça um nome e senha exclusivos para vinculá-lo a esta organização.")
                        ->visible(fn (Get $get): bool => $get('user_exists') && !$get('already_in_org')),

                    Forms\Components\TextInput::make('name')
                        ->label('Nome Completo')
                        ->required(fn (Get $get): bool => !$get('already_in_org'))
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => !$get('already_in_org')),

                    Forms\Components\TextInput::make('password')
                        ->label('Senha')
                        ->password()
                        ->required(fn (Get $get): bool => !$get('already_in_org'))
                        ->minLength(8)
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => !$get('already_in_org'))
                        ->helperText('Mínimo de 8 caracteres'),

                    Forms\Components\Toggle::make('status')
                        ->label('Usuário Ativo')
                        ->default(true)
                        ->required()
                        ->visible(fn (Get $get): bool => !$get('already_in_org')),

                    Forms\Components\Select::make('roles')
                        ->label('Funções (Roles)')
                        ->relationship('roles', 'name')
                        ->multiple()
                        ->preload()
                        ->searchable()
                        ->options(function () {
                            return \Spatie\Permission\Models\Role::where('name', '!=', 'super_admin')
                                ->pluck('name', 'id');
                        })
                        ->helperText('Selecione as funções do usuário nesta organização')
                        ->visible(fn (Get $get): bool => !$get('already_in_org')),
                ]),
        ];
    }

    /**
     * Customiza o processo de criação/adição do usuário
     */
    protected function handleRecordCreation(array $data): User
    {
        $tenantId = session('tenant_id');

        // Se o usuário já existe no sistema
        if (!empty($data['user_exists']) && !empty($data['existing_user_id'])) {
            $user = User::find($data['existing_user_id']);
            
            // Adicionar à organização atual
            if ($user && $tenantId && !$user->tenants()->where('tenant_id', $tenantId)->exists()) {
                $attachData = [
                    'is_admin' => false,
                    'roles' => json_encode([]),
                    'created_at' => now(),
                    'updated_at' => now(),
                ];

                // Armazenar nome/senha específicos da organização na pivot
                if (!empty($data['name'])) {
                    $attachData['tenant_name'] = $data['name'];
                }
                if (!empty($data['password'])) {
                    $attachData['tenant_password'] = Hash::make($data['password']);
                }

                $user->tenants()->attach($tenantId, $attachData);

                // Associar roles se fornecidas
                if (!empty($data['roles'])) {
                    $user->roles()->syncWithoutDetaching($data['roles']);
                }

                Notification::make()
                    ->title('Usuário adicionado à organização com sucesso')
                    ->success()
                    ->send();
            }

            return $user;
        }


        // Se o usuário não existe, criar novo
        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? true,
        ]);

        // Vincular à organização atual e armazenar nome/senha por tenant
        if ($tenantId) {
            $attachData = [
                'is_admin' => false,
                'roles' => json_encode([]),
                'created_at' => now(),
                'updated_at' => now(),
            ];
            if (!empty($data['name'])) {
                $attachData['tenant_name'] = $data['name'];
            }
            if (!empty($data['password'])) {
                $attachData['tenant_password'] = Hash::make($data['password']);
            }

            $user->tenants()->attach($tenantId, $attachData);
        }

        // Associar roles se fornecidas
        if (!empty($data['roles'])) {
            $user->roles()->sync($data['roles']);
        }

        return $user;
    }

    /**
     * Desabilita o hook afterCreate padrão pois a lógica foi movida para handleRecordCreation
     */
    protected function afterCreate(): void
    {
        // Lógica movida para handleRecordCreation
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}

