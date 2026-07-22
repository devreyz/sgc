<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\TenantNotificationDispatcher;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class NotificationSecurityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['notification_event_preferences', 'notifications', 'tenant_user', 'tenants', 'users'] as $table) {
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
}
