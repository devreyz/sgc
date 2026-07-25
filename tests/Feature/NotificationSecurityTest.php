<?php

namespace Tests\Feature;

use App\Jobs\SendWebPushNotification;
use App\Models\ProductionDelivery;
use App\Models\User;
use App\Services\NotificationService;
use App\Services\QueueTaskInspector;
use App\Services\TenantNotificationDispatcher;
use App\Support\NotificationEventCatalog;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
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

        config()->set('notifications.vapid.public_key', null);
        config()->set('notifications.vapid.private_key', null);
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
            $job = new SendWebPushNotification(10 + $tenantId, $tenantId, "notification-{$tenantId}", [
                'title' => 'Teste',
                'body' => 'Mensagem',
                'priority' => 'normal',
                'url' => '/',
                'links' => [],
            ]);

            DB::table('jobs')->insert([
                'queue' => 'notifications',
                'payload' => json_encode([
                    'displayName' => SendWebPushNotification::class,
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
        $this->assertSame('Enviar notificacao push', $jobs[0]['name']);
    }

    public function test_delivery_push_is_queued_when_vapid_is_configured(): void
    {
        Queue::fake();
        config()->set('notifications.vapid.subject', 'mailto:admin@example.test');
        config()->set('notifications.vapid.public_key', 'public-key');
        config()->set('notifications.vapid.private_key', 'private-key');

        DB::table('tenants')->insert([
            'id' => 1,
            'name' => 'Tenant A',
            'slug' => 'tenant-a',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $registrar = User::withoutEvents(fn () => User::query()->create([
            'name' => 'Registrar',
            'email' => 'registrar@example.test',
            'status' => true,
        ]));
        DB::table('tenant_user')->insert([
            'tenant_id' => 1,
            'user_id' => $registrar->id,
            'roles' => json_encode(['registrador_entregas']),
            'status' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        app(TenantNotificationDispatcher::class)->dispatchToConfiguredRoles(
            'delivery.registered',
            1,
            ['title' => 'Entrega', 'body' => 'Nova entrega', 'url' => '/tenant-a/delivery'],
        );

        Queue::assertPushed(SendWebPushNotification::class, fn (SendWebPushNotification $job): bool => (
            $job->tenantId === 1 && $job->userId === $registrar->id
        ));
        $this->assertCount(1, $registrar->fresh()->notifications);
    }
}
