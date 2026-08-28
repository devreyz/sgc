<?php

namespace App\Console\Commands;

use App\Models\NotificationEventPreference;
use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Models\User;
use App\Services\FcmHttpV1Client;
use App\Services\TenantNotificationDispatcher;
use App\Support\NotificationEventCatalog;
use Illuminate\Console\Command;

class SendTestPushNotification extends Command
{
    protected $signature = 'notifications:test-push {--user= : ID ou e-mail do usuario} {--tenant= : ID ou slug da organizacao}';

    protected $description = 'Cria uma notificacao de teste no banco e a entrega aos dispositivos do usuario';

    public function handle(TenantNotificationDispatcher $notifications, FcmHttpV1Client $fcm): int
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

        $definition = NotificationEventCatalog::get('manual.message');
        $preference = NotificationEventPreference::query()
            ->where('tenant_id', $tenant->id)
            ->where('event_key', 'manual.message')
            ->first();
        $pushEnabled = (bool) ($preference?->push_enabled ?? $definition['pushDefault']);
        $devices = PushDevice::query()
            ->where('user_id', $user->id)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at');
        $deviceCount = (clone $devices)->count();
        $lastBoundAt = (clone $devices)->max('bound_at');

        $notifications->dispatch('manual.message', $tenant->id, [$user], [
            'title' => 'Teste de notificacao',
            'body' => 'Esta notificacao confirma a integracao segura com o aplicativo.',
            'url' => route('notifications.index', ['tenant' => $tenant->slug], false),
        ]);

        $this->info('Notificacao registrada na central do usuario.');
        $this->line('Firebase HTTP v1: '.($fcm->configured() ? 'configurado' : 'NAO configurado'));
        $this->line('Push do evento manual.message: '.($pushEnabled ? 'ativado' : 'desativado'));
        $this->line("Dispositivos Android ativos do usuario: {$deviceCount}");
        if ($lastBoundAt) {
            $this->line("Ultimo vinculo de dispositivo: {$lastBoundAt}");
        }

        if (! $fcm->configured() || ! $pushEnabled || $deviceCount < 1) {
            $this->error('O push nao pode ser entregue. Abra o app autenticado para registrar o aparelho e confira os indicadores acima.');

            return self::FAILURE;
        }

        $this->info('Push colocado na fila notifications.');

        return self::SUCCESS;
    }
}
