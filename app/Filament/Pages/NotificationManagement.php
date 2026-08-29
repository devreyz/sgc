<?php

namespace App\Filament\Pages;

use App\Jobs\SendFcmNotification;
use App\Models\NotificationEventPreference;
use App\Models\PushDevice;
use App\Models\TenantUser;
use App\Services\FcmHttpV1Client;
use App\Services\NotificationPreferenceDefaults;
use App\Services\QueueTaskInspector;
use App\Services\TenantNotificationDispatcher;
use App\Support\NotificationEventCatalog;
use Filament\Facades\Filament;
use Filament\Forms;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Notifications\DatabaseNotification;

class NotificationManagement extends Page implements HasForms
{
    use InteractsWithForms;

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationGroup = 'Sistema';

    protected static ?string $navigationLabel = 'Notificacoes';

    protected static ?string $title = 'Notificacoes';

    protected static ?int $navigationSort = 20;

    protected static string $view = 'filament.pages.notification-management';

    public ?array $data = [];

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

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (int) session('tenant_id');
        $stored = NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->get()
            ->keyBy('event_key');

        $events = [];
        foreach (NotificationEventCatalog::all() as $key => $definition) {
            $preference = $stored->get($key);
            $events[$this->stateKey($key)] = [
                'event_key' => $key,
                'database_enabled' => $preference?->database_enabled ?? $definition['databaseDefault'],
                'push_enabled' => $definition['pushAllowed']
                    && ($preference?->push_enabled ?? $definition['pushDefault']),
                'priority' => $preference?->priority ?? $definition['priority'],
                'roles' => $preference?->recipient_roles ?? $definition['roles'],
            ];
        }

        $this->form->fill([
            'events' => $events,
            'manual' => [
                'membership_id' => null,
                'title' => null,
                'body' => null,
                'url' => null,
            ],
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Tabs::make('Gestao de notificacoes')
                    ->tabs([
                        Forms\Components\Tabs\Tab::make('Preferencias')
                            ->icon('heroicon-o-adjustments-horizontal')
                            ->schema($this->preferenceSchema()),
                        Forms\Components\Tabs\Tab::make('Enviar mensagem')
                            ->icon('heroicon-o-paper-airplane')
                            ->schema([
                                Forms\Components\Section::make('Nova notificacao')
                                    ->description('Envie uma mensagem para um membro desta organizacao.')
                                    ->schema([
                                        Forms\Components\Select::make('manual.membership_id')
                                            ->label('Destinatario')
                                            ->options(fn (): array => $this->memberOptions())
                                            ->searchable()
                                            ->native(false),
                                        Forms\Components\TextInput::make('manual.title')
                                            ->label('Titulo')
                                            ->maxLength(120),
                                        Forms\Components\Textarea::make('manual.body')
                                            ->label('Mensagem')
                                            ->rows(4)
                                            ->maxLength(320)
                                            ->columnSpanFull(),
                                        Forms\Components\TextInput::make('manual.url')
                                            ->label('Link interno (opcional)')
                                            ->placeholder('/minha-organizacao/delivery')
                                            ->helperText('Use somente um caminho interno iniciado por /.')
                                            ->maxLength(500)
                                            ->columnSpanFull(),
                                    ])
                                    ->columns(2),
                            ]),
                    ])
                    ->persistTabInQueryString(),
            ])
            ->statePath('data');
    }

    public function savePreferences(): void
    {
        abort_unless(static::canAccess(), 403);

        $state = $this->form->getState();
        $tenantId = (int) session('tenant_id');
        $userId = (int) Filament::auth()->id();

        DB::transaction(function () use ($state, $tenantId, $userId): void {
            foreach ($state['events'] ?? [] as $values) {
                $eventKey = (string) ($values['event_key'] ?? '');
                $definition = NotificationEventCatalog::get($eventKey);
                if (! $definition) {
                    continue;
                }

                $roles = array_values(array_intersect(
                    (array) ($values['roles'] ?? []),
                    array_keys(NotificationEventCatalog::roles()),
                ));

                NotificationEventPreference::query()->updateOrCreate(
                    ['tenant_id' => $tenantId, 'event_key' => $eventKey],
                    [
                        'database_enabled' => (bool) ($values['database_enabled'] ?? false),
                        'push_enabled' => $definition['pushAllowed']
                            && (bool) ($values['push_enabled'] ?? false),
                        'priority' => in_array($values['priority'] ?? null, NotificationEventCatalog::PRIORITIES, true)
                            ? $values['priority']
                            : $definition['priority'],
                        'recipient_roles' => $roles,
                        'updated_by' => $userId,
                    ],
                );
            }
        });

        activity('notification_settings')
            ->causedBy(Filament::auth()->user())
            ->withProperties(['tenant_id' => $tenantId])
            ->log('Preferencias de notificacao atualizadas');

        Notification::make()
            ->title('Preferencias salvas')
            ->success()
            ->send();
    }

    public function applyRecommendedDefaults(NotificationPreferenceDefaults $defaults): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (int) session('tenant_id');
        $defaults->applyForTenant($tenantId, (int) Filament::auth()->id(), true);
        $this->mount();

        activity('notification_settings')
            ->causedBy(Filament::auth()->user())
            ->withProperties(['tenant_id' => $tenantId])
            ->log('Padrões recomendados de notificações aplicados');

        Notification::make()
            ->title('Padrões recomendados aplicados')
            ->body('Central interna, Android, prioridades e destinatários foram reconfigurados.')
            ->success()
            ->send();
    }

    public function sendManual(TenantNotificationDispatcher $dispatcher): void
    {
        abort_unless(static::canAccess(), 403);

        $input = (array) ($this->data['manual'] ?? []);
        $validator = Validator::make($input, [
            'membership_id' => ['required', 'integer'],
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:320'],
            'url' => [
                'nullable',
                'string',
                'max:500',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    if (filled($value) && (! str_starts_with($value, '/') || str_starts_with($value, '//'))) {
                        $fail('Informe apenas um link interno iniciado por /.');
                    }
                },
            ],
        ])->validate();

        $tenantId = (int) session('tenant_id');
        $membership = TenantUser::query()
            ->forTenant($tenantId)
            ->active()
            ->with(['user:id,status'])
            ->whereKey($validator['membership_id'])
            ->first();

        abort_unless($membership?->user?->status, 422, 'O destinatario nao esta disponivel.');

        $sent = $dispatcher->dispatch('manual.message', $tenantId, [$membership->user], [
            'title' => $validator['title'],
            'body' => $validator['body'],
            'url' => $validator['url'] ?: route('notifications.index', [
                'tenant' => (string) session('tenant_slug'),
            ], false),
            'icon' => 'message-square',
            'action_label' => 'Abrir mensagem',
            'action_icon' => 'message-square',
        ]);

        if ($sent !== 1) {
            Notification::make()
                ->title('Envio desativado')
                ->body('Ative ao menos a notificacao interna ou push para mensagens administrativas.')
                ->warning()
                ->send();

            return;
        }

        activity('notifications')
            ->causedBy(Filament::auth()->user())
            ->performedOn($membership)
            ->withProperties([
                'tenant_id' => $tenantId,
                'target_user_id' => $membership->user_id,
                'title' => $validator['title'],
            ])
            ->log('Notificacao administrativa enviada');

        $this->data['manual'] = [
            'membership_id' => null,
            'title' => null,
            'body' => null,
            'url' => null,
        ];

        $definition = NotificationEventCatalog::get('manual.message');
        $preference = NotificationEventPreference::query()
            ->where('tenant_id', $tenantId)
            ->where('event_key', 'manual.message')
            ->first();
        $pushEnabled = $this->pushConfigured
            && PushDevice::query()->where('user_id', $membership->user_id)
                ->where('notifications_enabled', true)->whereNull('revoked_at')->exists()
            && (bool) ($preference?->push_enabled ?? $definition['pushDefault']);

        Notification::make()
            ->title('Notificacao enviada')
            ->body($pushEnabled
                ? 'A mensagem está na central e o envio Android foi colocado na fila.'
                : 'A mensagem está na central. Não há envio Android ativo para este destinatário/organização.')
            ->success()
            ->send();
    }

    public function sendTestToMe(TenantNotificationDispatcher $dispatcher): void
    {
        abort_unless(static::canAccess(), 403);

        $tenantId = (int) session('tenant_id');
        $user = Filament::auth()->user();
        $hasDevice = PushDevice::query()->where('user_id', $user->id)
            ->where('notifications_enabled', true)->whereNull('revoked_at')->exists();

        $dispatcher->dispatch('manual.message', $tenantId, [$user], [
            'title' => 'Teste de notificações do SGC',
            'body' => 'A central interna e a entrega Android estão sendo verificadas.',
            'url' => route('notifications.index', ['tenant' => (string) session('tenant_slug')], false),
            'icon' => 'bell-ring',
            'action_label' => 'Abrir central',
            'action_icon' => 'bell',
        ]);

        Notification::make()
            ->title('Teste registrado na central')
            ->body($this->pushConfigured && $hasDevice
                ? 'O envio Android foi colocado na fila. Verifique o aparelho e Tarefas do sistema.'
                : 'Este usuário ainda não possui um dispositivo Android ativo ou o Firebase não está configurado.')
            ->color($this->pushConfigured && $hasDevice ? 'success' : 'warning')
            ->send();
    }

    public function getPushConfiguredProperty(): bool
    {
        return app(FcmHttpV1Client::class)->configured();
    }

    public function getActiveDevicesProperty(): int
    {
        $tenantId = (int) session('tenant_id');

        return PushDevice::query()
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->whereIn('user_id', TenantUser::query()
                ->forTenant($tenantId)
                ->active()
                ->select('user_id'))
            ->count();
    }

    public function getPushDiagnosticsProperty(): array
    {
        $tenantId = (int) session('tenant_id');
        $userIds = TenantUser::query()->forTenant($tenantId)->active()->pluck('user_id');
        $devices = PushDevice::query()->whereIn('user_id', $userIds);
        $active = (clone $devices)->where('notifications_enabled', true)->whereNull('revoked_at');
        $pending = collect(app(QueueTaskInspector::class)->pendingForTenant($tenantId))
            ->where('queue', 'notifications')->count();
        $failures = 0;
        $sent = 0;
        $lastDeliveryAt = null;

        if (Schema::hasTable('push_delivery_receipts')) {
            $failures = DB::table('push_delivery_receipts')
                ->join('push_devices', 'push_devices.id', '=', 'push_delivery_receipts.push_device_id')
                ->whereIn('push_devices.user_id', $userIds)
                ->whereIn('push_delivery_receipts.status', ['failed', 'invalid_token'])
                ->where('push_delivery_receipts.created_at', '>=', now()->subDay())
                ->count();
            $sentQuery = DB::table('push_delivery_receipts')
                ->join('push_devices', 'push_devices.id', '=', 'push_delivery_receipts.push_device_id')
                ->whereIn('push_devices.user_id', $userIds)
                ->where('push_delivery_receipts.status', 'sent');
            $sent = (clone $sentQuery)->where('push_delivery_receipts.created_at', '>=', now()->subDay())->count();
            $lastDeliveryAt = (clone $sentQuery)->max('push_delivery_receipts.delivered_at');
        }

        $lastWorkerCheck = Cache::get('system:notifications:last_worker_check');
        $healthy = $this->pushConfigured
            && ($pending === 0 || ($lastWorkerCheck && now()->diffInMinutes($lastWorkerCheck) <= 3));

        return [
            'configured' => $this->pushConfigured,
            'active' => (clone $active)->count(),
            'recent' => (clone $active)->where('last_seen_at', '>=', now()->subDays(7))->count(),
            'revoked' => (clone $devices)->whereNotNull('revoked_at')->count(),
            'pending' => $pending,
            'failures_24h' => $failures,
            'sent_24h' => $sent,
            'last_delivery_at' => $lastDeliveryAt,
            'last_worker_check' => $lastWorkerCheck,
            'healthy' => $healthy,
            'last_bound_at' => (clone $devices)->max('bound_at'),
        ];
    }

    public function getProblemDeliveriesProperty(): array
    {
        $tenantId = (int) session('tenant_id');
        if (! Schema::hasTable('push_delivery_receipts')) {
            return [];
        }

        return DB::table('push_delivery_receipts')
            ->join('push_devices', 'push_devices.id', '=', 'push_delivery_receipts.push_device_id')
            ->join('notifications', 'notifications.id', '=', 'push_delivery_receipts.notification_id')
            ->join('users', 'users.id', '=', 'push_devices.user_id')
            ->where('notifications.data->tenant_id', $tenantId)
            ->whereIn('push_delivery_receipts.status', ['failed', 'invalid_token'])
            ->select([
                'notifications.id',
                'notifications.data',
                'users.name as user_name',
                DB::raw('COUNT(*) as failed_devices'),
                DB::raw('MAX(push_delivery_receipts.updated_at) as last_attempt_at'),
            ])
            ->groupBy('notifications.id', 'notifications.data', 'users.name')
            ->orderByDesc('last_attempt_at')
            ->limit(25)
            ->get()
            ->map(function (object $row): array {
                $data = json_decode((string) $row->data, true) ?: [];

                return [
                    'id' => (string) $row->id,
                    'user' => (string) ($row->user_name ?: 'Usuário'),
                    'title' => (string) ($data['title'] ?? 'Notificação'),
                    'failed_devices' => (int) $row->failed_devices,
                    'last_attempt_at' => $row->last_attempt_at,
                ];
            })->all();
    }

    public function retryPushDelivery(string $notificationId): void
    {
        abort_unless(static::canAccess(), 403);
        $tenantId = (int) session('tenant_id');
        /** @var DatabaseNotification|null $notification */
        $notification = DatabaseNotification::query()
            ->whereKey($notificationId)
            ->where('notifiable_type', (new \App\Models\User())->getMorphClass())
            ->where('data->tenant_id', $tenantId)
            ->first();

        abort_unless($notification, 404);
        $userId = (int) $notification->notifiable_id;
        abort_unless(TenantUser::query()->forTenant($tenantId)->active()->where('user_id', $userId)->exists(), 422);

        $hasDevice = PushDevice::query()
            ->where('user_id', $userId)
            ->where('platform', 'android')
            ->where('notifications_enabled', true)
            ->whereNull('revoked_at')
            ->exists();

        if (! $hasDevice) {
            Notification::make()->warning()->title('Nenhum dispositivo ativo para tentar novamente')->send();

            return;
        }

        SendFcmNotification::dispatch($userId, $tenantId, (string) $notification->id)->afterCommit();

        activity('notifications')->causedBy(Filament::auth()->user())->withProperties([
            'tenant_id' => $tenantId,
            'notification_id' => (string) $notification->id,
            'target_user_id' => $userId,
        ])->log('Nova tentativa de entrega Android solicitada');

        Notification::make()->success()->title('Nova tentativa agendada')->send();
    }

    private function preferenceSchema(): array
    {
        return collect(NotificationEventCatalog::all())
            ->groupBy('group')
            ->map(function ($events, string $group): Forms\Components\Section {
                $schema = [];

                foreach ($events as $eventKey => $definition) {
                    $prefix = 'events.'.$this->stateKey($eventKey);
                    $schema[] = Forms\Components\Section::make($definition['label'])
                        ->description($definition['description'])
                        ->compact()
                        ->schema([
                            Forms\Components\Hidden::make($prefix.'.event_key')
                                ->default($eventKey),
                            Forms\Components\Toggle::make($prefix.'.database_enabled')
                                ->label('Central interna'),
                            Forms\Components\Toggle::make($prefix.'.push_enabled')
                                ->label('Notificacao Android')
                                ->disabled(! $definition['pushAllowed'])
                                ->helperText($definition['pushAllowed']
                                    ? null
                                    : 'Notificacao Android desativada para este evento editavel.'),
                            Forms\Components\Select::make($prefix.'.priority')
                                ->label('Prioridade')
                                ->options([
                                    'info' => 'Informativa',
                                    'normal' => 'Normal',
                                    'high' => 'Alta',
                                    'critical' => 'Critica',
                                ])
                                ->native(false),
                            Forms\Components\Select::make($prefix.'.roles')
                                ->label('Destinatarios')
                                ->options(NotificationEventCatalog::roles())
                                ->multiple()
                                ->native(false)
                                ->columnSpanFull(),
                        ])
                        ->columns(3);
                }

                return Forms\Components\Section::make($group)
                    ->schema($schema)
                    ->collapsible();
            })
            ->values()
            ->all();
    }

    private function memberOptions(): array
    {
        return TenantUser::query()
            ->forTenant((int) session('tenant_id'))
            ->active()
            ->whereHas('user', fn ($query) => $query->where('status', true))
            ->orderBy('tenant_name')
            ->get(['id', 'tenant_name'])
            ->mapWithKeys(fn (TenantUser $member): array => [
                $member->id => $member->display_name,
            ])
            ->all();
    }

    private function stateKey(string $eventKey): string
    {
        return str_replace('.', '__', $eventKey);
    }
}
