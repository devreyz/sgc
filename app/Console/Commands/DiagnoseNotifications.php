<?php

namespace App\Console\Commands;

use App\Models\PushDevice;
use App\Models\Tenant;
use App\Models\TenantUser;
use App\Services\FcmHttpV1Client;
use App\Services\QueueTaskInspector;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DiagnoseNotifications extends Command
{
    protected $signature = 'notifications:diagnose {--tenant= : ID ou slug da organização}';

    protected $description = 'Diagnostica a configuração Firebase, os dispositivos Android e a fila notifications';

    public function handle(FcmHttpV1Client $fcm, QueueTaskInspector $queues): int
    {
        $tenantValue = trim((string) $this->option('tenant'));
        $tenant = $tenantValue === '' ? null : Tenant::query()
            ->where(fn ($query) => ctype_digit($tenantValue)
                ? $query->whereKey((int) $tenantValue)
                : $query->where('slug', $tenantValue))
            ->first();

        if ($tenantValue !== '' && ! $tenant) {
            $this->error('Organização não encontrada.');

            return self::FAILURE;
        }

        $this->info('Firebase e worker');
        $this->table(['Item', 'Valor'], [
            ['Firebase HTTP v1', $fcm->configured() ? 'configurado' : 'NÃO configurado'],
            ['FCM_ENABLED', config('notifications.fcm.enabled') ? 'true' : 'false'],
            ['Project ID', filled(config('notifications.fcm.project_id')) ? 'informado' : 'ausente'],
            ['Credencial legível', $fcm->configured() ? 'sim' : 'não/indeterminada'],
            ['Pacote Android', (string) config('notifications.fcm.android_package')],
            ['Conexão padrão da fila', (string) config('queue.default')],
        ]);

        if (! $tenant) {
            $this->newLine();
            $this->warn('Informe --tenant=ID_OU_SLUG para ver aparelhos, tentativas e entregas da organização.');

            return $fcm->configured() ? self::SUCCESS : self::FAILURE;
        }

        $pending = collect($queues->pendingForTenant((int) $tenant->id, 500))
            ->where('queue', 'notifications');
        $failed = collect($queues->failedForTenant((int) $tenant->id, 500))
            ->where('queue', 'notifications');
        $userIds = TenantUser::query()->forTenant($tenant->id)->active()->pluck('user_id');
        $devices = PushDevice::query()->whereIn('user_id', $userIds);
        $activeDevices = (clone $devices)->where('notifications_enabled', true)->whereNull('revoked_at');
        $receipts = collect();

        if (Schema::hasTable('push_delivery_receipts')) {
            $receipts = DB::table('push_delivery_receipts')
                ->join('push_devices', 'push_devices.id', '=', 'push_delivery_receipts.push_device_id')
                ->whereIn('push_devices.user_id', $userIds)
                ->where('push_delivery_receipts.created_at', '>=', now()->subDay())
                ->select('push_delivery_receipts.status')
                ->get();
        }

        $this->newLine();
        $this->info("{$tenant->name} (ID {$tenant->id})");
        $this->table(['Item', 'Valor'], [
            ['Livres para executar', $pending->where('status', 'waiting')->count()],
            ['Aguardando nova tentativa', $pending->where('status', 'scheduled')->count()],
            ['Reservadas/processando', $pending->where('status', 'processing')->count()],
            ['Maior número de tentativas', $pending->max('attempts') ?? 0],
            ['Falhas definitivas da fila', $failed->count()],
            ['Aparelhos Android ativos', (clone $activeDevices)->count()],
            ['Aparelhos vistos nos últimos 7 dias', (clone $activeDevices)->where('last_seen_at', '>=', now()->subDays(7))->count()],
            ['Último vínculo de aparelho', (clone $devices)->max('bound_at') ?? 'nunca'],
            ['Entregas FCM nas últimas 24h', $receipts->where('status', 'sent')->count()],
            ['Rejeições FCM nas últimas 24h', $receipts->whereIn('status', ['failed', 'invalid_token'])->count()],
        ]);

        if ($pending->where('status', 'processing')->isNotEmpty()) {
            $this->warn('Há tarefas reservadas. Se permanecerem assim por mais de 3 minutos, execute queue:restart e aguarde o próximo cron.');
        }
        if ((clone $activeDevices)->count() === 0) {
            $this->warn('Nenhum aparelho ativo: abra a nova versão do app, entre na conta e aceite as notificações.');
        }

        return $fcm->configured() ? self::SUCCESS : self::FAILURE;
    }
}
