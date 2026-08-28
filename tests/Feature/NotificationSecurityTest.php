<?php

namespace Tests\Feature;

use App\Jobs\SendFcmNotification;
use App\Models\ProductionDelivery;
use App\Models\PushDevice;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\FcmHttpV1Client;
use App\Services\QueueTaskInspector;
use App\Services\TenantNotificationDispatcher;
use App\Support\NotificationEventCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Http\Client\Response;
use Mockery;
use Tests\TestCase;

class NotificationSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach ([
            'failed_jobs',
            'jobs',
            'production_deliveries',
            'sales_projects',
            'products',
            'associates',
            'push_subscriptions',
            'push_devices',
            'push_delivery_receipts',
            'activity_log',
            'notification_event_preferences',
            'notifications',
            'tenant_user',
            'tenants',
            'users',
        ] as $table) {
            Schema::dropIfExists($table);
        }

        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('email')->nullable();
            $table->boolean('status')->default(true);
            $table->string('webauthn_user_handle')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenants', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('tenant_user', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->boolean('is_admin')->default(false);
            $table->json('roles')->nullable();
            $table->boolean('status')->default(true);
            $table->string('tenant_name')->nullable();
            $table->timestamps();
        });
        Schema::create('notifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });
        Schema::create('notification_event_preferences', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('event_key');
            $table->boolean('database_enabled')->default(true);
            $table->boolean('push_enabled')->default(false);
            $table->string('priority')->default('normal');
            $table->json('recipient_roles')->nullable();
            $table->unsignedBigInteger('updated_by')->nullable();
            $table->timestamps();
        });
        Schema::create('push_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->char('session_hash', 64)->nullable();
            $table->char('endpoint_hash', 64)->unique();
            $table->text('endpoint');
            $table->text('public_key');
            $table->text('auth_token');
            $table->string('content_encoding')->default('aes128gcm');
            $table->string('user_agent_summary')->nullable();
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('push_devices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('platform', 20)->default('android');
            $table->char('installation_hash', 64)->unique();
            $table->char('token_hash', 64)->unique();
            $table->text('token');
            $table->char('session_hash', 64)->index();
            $table->string('device_name', 120)->nullable();
            $table->string('app_version', 40)->nullable();
            $table->string('os_version', 40)->nullable();
            $table->boolean('notifications_enabled')->default(true);
            $table->unsignedSmallInteger('failure_count')->default(0);
            $table->timestamp('bound_at')->nullable();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
        });
        Schema::create('push_delivery_receipts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('push_device_id');
            $table->uuid('notification_id');
            $table->string('status', 20);
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['push_device_id', 'notification_id']);
        });
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable();
            $table->text('description');
            $table->string('subject_type')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->string('causer_type')->nullable();
            $table->unsignedBigInteger('causer_id')->nullable();
            $table->json('properties')->nullable();
            $table->string('event')->nullable();
            $table->uuid('batch_uuid')->nullable();
            $table->unsignedBigInteger('tenant_id')->nullable();
            $table->timestamps();
        });
        Schema::create('associates', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('user_id');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('name');
            $table->string('unit');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('sales_projects', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->string('title');
            $table->softDeletes();
            $table->timestamps();
        });
        Schema::create('production_deliveries', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('tenant_id');
            $table->unsignedBigInteger('sales_project_id');
            $table->unsignedBigInteger('associate_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('parent_delivery_id')->nullable();
            $table->decimal('quantity', 12, 3);
            $table->timestamps();
        });
        Schema::create('jobs', function (Blueprint $table) {
            $table->id();
            $table->string('queue');
            $table->longText('payload');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->unsignedInteger('reserved_at')->nullable();
            $table->unsignedInteger('available_at');
            $table->unsignedInteger('created_at');
        });
        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id();
            $table->string('uuid')->unique();
            $table->text('connection');
            $table->text('queue');
            $table->longText('payload');
            $table->longText('exception');
            $table->timestamp('failed_at');
        });

    }

    public function test_role_recipients_are_strictly_scoped_to_the_tenant(): void
    {
        DB::table('tenants')->insert([
            ['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()],
            ['id' => 2, 'name' => 'Tenant B', 'slug' => 'tenant-b', 'created_at' => now(), 'updated_at' => now()],
        ]);
        $first = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        $second = User::withoutEvents(fn () => User::query()->create(['name' => 'B', 'email' => 'b@example.test', 'status' => true]));
        DB::table('tenant_user')->insert([
            ['tenant_id' => 1, 'user_id' => $first->id, 'roles' => json_encode(['admin']), 'status' => true, 'created_at' => now(), 'updated_at' => now()],
            ['tenant_id' => 2, 'user_id' => $second->id, 'roles' => json_encode(['admin']), 'status' => true, 'created_at' => now(), 'updated_at' => now()],
        ]);

        $dispatcher = app(TenantNotificationDispatcher::class);
        $dispatcher->dispatchToConfiguredRoles('stock.low', 1, ['title' => 'Estoque', 'body' => 'Teste', 'url' => '//evil.test']);

        $this->assertCount(1, $first->notifications);
        $this->assertCount(0, $second->notifications);
        $this->assertSame(1, $first->notifications->first()->data['tenant_id']);
        $this->assertSame('/', $first->notifications->first()->data['url']);
    }

    public function test_distribution_push_cannot_be_enabled_by_database_preference(): void
    {
        DB::table('tenants')->insert(['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        DB::table('notification_event_preferences')->insert([
            'tenant_id' => 1,
            'event_key' => 'distribution.changed',
            'database_enabled' => false,
            'push_enabled' => true,
            'priority' => 'critical',
            'recipient_roles' => json_encode(['admin']),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $sent = app(TenantNotificationDispatcher::class)->dispatch('distribution.changed', 1, [$user], [
            'title' => 'Distribuicao',
            'body' => 'Editavel',
            'url' => '/',
        ]);

        $this->assertSame(0, $sent);
        $this->assertDatabaseCount('notifications', 0);
    }

    public function test_registered_delivery_notifies_tenant_registrar_without_invalid_columns(): void
    {
        session(['tenant_id' => 1, 'tenant_slug' => 'tenant-a']);
        DB::table('tenants')->insert([
            'id' => 1,
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $registrar = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Global registrar',
            'email' => 'registrar@example.test',
            'status' => true,
        ]));
        $associateUser = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Global associate',
            'email' => 'associate@example.test',
            'status' => true,
        ]));

        DB::table('tenant_user')->insert([
            [
                'tenant_id' => 1,
                'user_id' => $registrar->id,
                'tenant_name' => 'Registrador Local',
                'roles' => json_encode(['registrador_entregas']),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'tenant_id' => 1,
                'user_id' => $associateUser->id,
                'tenant_name' => 'Produtora Local',
                'roles' => json_encode(['associado']),
                'status' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
        DB::table('associates')->insert([
            'id' => 10,
            'tenant_id' => 1,
            'user_id' => $associateUser->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('products')->insert([
            'id' => 20,
            'tenant_id' => 1,
            'name' => 'Banana',
            'unit' => 'kg',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('sales_projects')->insert([
            'id' => 30,
            'tenant_id' => 1,
            'title' => 'Projeto 2026',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $delivery = new ProductionDelivery;
        $delivery->forceFill([
            'id' => 40,
            'tenant_id' => 1,
            'sales_project_id' => 30,
            'associate_id' => 10,
            'product_id' => 20,
            'parent_delivery_id' => null,
            'quantity' => 12.5,
        ]);
        $delivery->exists = true;

        app(NotificationService::class)->notifyDelivery($delivery);

        $this->assertCount(1, $registrar->fresh()->notifications);
        $this->assertSame('Entrega registrada', $registrar->fresh()->notifications->first()->data['title']);
        $this->assertStringContainsString('Produtora Local', $registrar->fresh()->notifications->first()->data['body']);
        $this->assertTrue(NotificationEventCatalog::get('delivery.registered')['pushDefault']);
    }

    public function test_queue_inspector_never_returns_jobs_from_another_tenant(): void
    {
        foreach ([1, 2] as $tenantId) {
            $job = new SendFcmNotification(10 + $tenantId, $tenantId, "00000000-0000-4000-8000-00000000000{$tenantId}", [
                'title' => 'Teste',
                'body' => 'Mensagem',
                'priority' => 'normal',
                'url' => '/',
                'links' => [],
            ]);

            DB::table('jobs')->insert([
                'queue' => 'notifications',
                'payload' => json_encode([
                    'displayName' => SendFcmNotification::class,
                    'data' => ['command' => serialize($job)],
                ]),
                'attempts' => 0,
                'reserved_at' => null,
                'available_at' => now()->timestamp,
                'created_at' => now()->timestamp,
            ]);
        }

        $jobs = app(QueueTaskInspector::class)->pendingForTenant(1);

        $this->assertCount(1, $jobs);
        $this->assertSame(1, $jobs[0]['tenant_id']);
        $this->assertSame('Enviar notificacao Android', $jobs[0]['name']);
    }

    public function test_android_device_is_rebound_to_the_new_account_without_leaking_the_old_account(): void
    {
        $first = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        $second = User::withoutEvents(fn () => User::query()->create(['name' => 'B', 'email' => 'b@example.test', 'status' => true]));
        $payload = [
            'installation_id' => '80ea10e6-699d-4bc1-b6d2-d075746fe878',
            'token' => str_repeat('first-fcm-token-', 8),
            'device_name' => 'Android Test',
        ];

        $this->actingAs($first)->postJson(route('notifications.push.devices.store'), $payload)->assertOk();
        $firstSession = PushDevice::query()->firstOrFail()->session_hash;

        auth()->logout();
        $this->app['session']->invalidate();
        $payload['token'] = str_repeat('rotated-fcm-token-', 8);
        $this->actingAs($second)->postJson(route('notifications.push.devices.store'), $payload)->assertOk();

        $device = PushDevice::query()->firstOrFail();
        $this->assertDatabaseCount('push_devices', 1);
        $this->assertSame($second->id, $device->user_id);
        $this->assertNotSame($firstSession, $device->session_hash);
        $this->assertNull($device->revoked_at);
        $this->assertTrue($device->notifications_enabled);
    }

    public function test_logout_revokes_only_android_device_from_the_current_session(): void
    {
        $user = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        $this->actingAs($user)->postJson(route('notifications.push.devices.store'), [
            'installation_id' => 'a2b40d33-c30a-4c38-a22a-57a2448d8d2f',
            'token' => str_repeat('current-token-', 8),
        ])->assertOk();
        $currentSession = PushDevice::query()->firstOrFail()->session_hash;

        PushDevice::query()->create([
            'user_id' => $user->id,
            'platform' => 'android',
            'installation_hash' => str_repeat('a', 64),
            'token_hash' => str_repeat('b', 64),
            'token' => str_repeat('other-token-', 8),
            'session_hash' => str_repeat('c', 64),
        ]);

        $this->post(route('logout'))->assertRedirect(route('login'));

        $this->assertNotNull(PushDevice::query()->where('session_hash', $currentSession)->firstOrFail()->revoked_at);
        $this->assertNull(PushDevice::query()->where('session_hash', str_repeat('c', 64))->firstOrFail()->revoked_at);
    }

    public function test_push_notification_remains_in_database_and_fcm_delivery_is_queued(): void
    {
        Queue::fake();
        DB::table('tenants')->insert(['id' => 1, 'name' => 'Tenant A', 'slug' => 'tenant-a', 'created_at' => now(), 'updated_at' => now()]);
        $user = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        DB::table('tenant_user')->insert([
            'tenant_id' => 1, 'user_id' => $user->id, 'roles' => json_encode(['admin']), 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        app(TenantNotificationDispatcher::class)->dispatch('manual.message', 1, [$user], [
            'title' => 'Teste', 'body' => 'Conteudo privado apenas na central.', 'url' => '/tenant-a/notifications',
        ]);

        $this->assertCount(1, $user->fresh()->notifications);
        Queue::assertPushed(SendFcmNotification::class, fn (SendFcmNotification $job) =>
            $job->userId === $user->id && $job->tenantId === 1
        );
    }

    public function test_fcm_job_rechecks_active_tenant_membership_before_delivery(): void
    {
        $user = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        $client = Mockery::mock(FcmHttpV1Client::class);
        $client->shouldReceive('configured')->once()->andReturnTrue();
        $client->shouldNotReceive('send');

        (new SendFcmNotification($user->id, 99, '3e4bc4fb-0829-457f-9089-df56e882a855', [
            'event_key' => 'manual.message', 'priority' => 'normal', 'url' => '/',
        ]))->handle($client);
    }

    public function test_fcm_delivery_is_idempotent_and_records_no_sensitive_response_body(): void
    {
        $user = User::withoutEvents(fn () => User::query()->create(['name' => 'A', 'email' => 'a@example.test', 'status' => true]));
        DB::table('tenant_user')->insert([
            'tenant_id' => 1, 'user_id' => $user->id, 'roles' => '[]', 'status' => true,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        PushDevice::query()->create([
            'user_id' => $user->id, 'platform' => 'android',
            'installation_hash' => str_repeat('d', 64), 'token_hash' => str_repeat('e', 64),
            'token' => str_repeat('valid-token-', 8), 'session_hash' => str_repeat('f', 64),
        ]);
        $response = Mockery::mock(Response::class);
        $response->shouldReceive('successful')->once()->andReturnTrue();
        $response->shouldReceive('status')->once()->andReturn(200);
        $client = Mockery::mock(FcmHttpV1Client::class);
        $client->shouldReceive('configured')->twice()->andReturnTrue();
        $client->shouldReceive('send')->once()->andReturn($response);
        $job = new SendFcmNotification($user->id, 1, '8813e6d2-eb54-4634-8165-a32f951cf675', [
            'event_key' => 'ledger.credit', 'priority' => 'high', 'url' => '/tenant-a/notifications/id/open',
        ]);

        $job->handle($client);
        $job->handle($client);

        $this->assertDatabaseHas('push_delivery_receipts', [
            'notification_id' => $job->notificationId, 'status' => 'sent', 'response_code' => 200,
        ]);
        $this->assertDatabaseCount('push_delivery_receipts', 1);
    }
}
