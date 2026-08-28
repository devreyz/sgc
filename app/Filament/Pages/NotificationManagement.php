<?php

namespace App\Filament\Pages;

use App\Models\NotificationEventPreference;
use App\Models\PushDevice;
use App\Models\TenantUser;
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
use Illuminate\Support\Facades\Validator;

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
            'url' => $validator['url'] ?: '/',
            'icon' => 'message-square',
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

        Notification::make()
            ->title('Notificacao enviada')
            ->body('A mensagem ja esta na central do destinatario. O push seguira pela fila.')
            ->success()
            ->send();
    }

    public function getPushConfiguredProperty(): bool
    {
        return app(\App\Services\FcmHttpV1Client::class)->configured();
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
                            Forms\Components\CheckboxList::make($prefix.'.roles')
                                ->label('Destinatarios')
                                ->options(NotificationEventCatalog::roles())
                                ->columns(2)
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
