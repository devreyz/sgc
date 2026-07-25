<?php

namespace App\Filament\Pages;

use App\Models\TenantUser;
use App\Services\QueueTaskInspector;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Artisan;

class SystemJobs extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-server-stack';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Tarefas do sistema';

    protected static ?string $title = 'Tarefas do sistema';

    protected static ?int $navigationSort = 21;

    protected static string $view = 'filament.pages.system-jobs';

    public static function canAccess(array $parameters = []): bool
    {
        $user = Filament::auth()->user();
        $tenantId = (int) session('tenant_id');

        if (! $user || ! $tenantId) {
            return false;
        }

        return TenantUser::query()
            ->forTenant($tenantId)
            ->active()
            ->where('user_id', $user->id)
            ->where(function ($query): void {
                $query->where('is_admin', true)
                    ->orWhereJsonContains('roles', 'admin');
            })
            ->exists();
    }

    public function getPendingJobsProperty(): array
    {
        return app(QueueTaskInspector::class)->pendingForTenant((int) session('tenant_id'));
    }

    public function getFailedJobsProperty(): array
    {
        return app(QueueTaskInspector::class)->failedForTenant((int) session('tenant_id'));
    }

    public function retryFailed(string $uuid): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (int) session('tenant_id');
        $inspector = app(QueueTaskInspector::class);
        abort_unless($inspector->failedBelongsToTenant($uuid, $tenantId), 404);

        $exitCode = Artisan::call('queue:retry', ['id' => [$uuid]]);
        if ($exitCode !== 0) {
            Notification::make()
                ->title('Nao foi possivel reagendar a tarefa')
                ->danger()
                ->send();

            return;
        }

        activity('queue')
            ->causedBy(Filament::auth()->user())
            ->withProperties(['tenant_id' => $tenantId, 'failed_job_uuid' => $uuid])
            ->log('Tarefa com falha reagendada');

        Notification::make()
            ->title('Tarefa reagendada')
            ->success()
            ->send();
    }
}
