<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'notifications:test-push {--user= : ID ou e-mail do usuario} {--tenant= : ID ou slug da organizacao}';
    protected $description = 'Cria uma notificacao de teste no banco e a entrega aos dispositivos do usuario';

    public function handle(TenantNotificationDispatcher $notifications): int
    {
        $userValue = trim((string) $this->option('user'));
        $tenantValue = trim((string) $this->option('tenant'));
        if ($userValue === '' || $tenantValue === '') {
            $this->error('Informe --user e --tenant.');
            return self::INVALID;
        }

        $user = User::query()
            ->where('status', true)
            ->where(fn ($query) => ctype_digit($userValue)
                ? $query->whereKey((int) $userValue)
                : $query->where('email', mb_strtolower($userValue)))
            ->first();
        $tenant = Tenant::query()
            ->where('active', true)
            ->where(fn ($query) => ctype_digit($tenantValue)
                ? $query->whereKey((int) $tenantValue)
                : $query->where('slug', $tenantValue))
            ->first();

        if (! $user || ! $tenant || ! TenantUser::query()
            ->forTenant($tenant->id)->active()->where('user_id', $user->id)->exists()) {
            $this->error('Usuario/organizacao invalidos ou vinculo inativo.');
            return self::FAILURE;
        }

        $notifications->dispatch('manual.message', $tenant->id, [$user], [
            'title' => 'Teste de notificacao',
            'body' => 'Esta notificacao confirma a integracao segura com o aplicativo.',
            'url' => route('notifications.index', ['tenant' => $tenant->slug], false),
        ]);

        $this->info('Notificacao registrada. Processe a fila notifications para entrega push.');
        return self::SUCCESS;
    }
}
