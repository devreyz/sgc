<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Console\Command;

class ManageTenants extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'tenant:manage 
                            {action : Action to perform (create, list, assign, remove)}
                            {--tenant= : Tenant ID or slug}
                            {--user= : User ID or email}
                            {--name= : Tenant name (for create)}
                            {--slug= : Tenant slug (for create)}
                            {--admin : Make user admin of the tenant}';

    /**
     * The console command description.
     */
    protected $description = 'Manage tenants and user assignments';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $action = $this->argument('action');

        match ($action) {
            'create' => $this->createTenant(),
            'list' => $this->listTenants(),
            'assign' => $this->assignUserToTenant(),
            'remove' => $this->removeUserFromTenant(),
            default => $this->error("Invalid action. Use: create, list, assign, or remove"),
        };
    }

    /**
     * Create a new tenant.
     */
    protected function createTenant(): void
    {
        $name = $this->option('name') ?? $this->ask('Tenant name');
        $slug = $this->option('slug') ?? \Illuminate\Support\Str::slug($name);

        if (Tenant::where('slug', $slug)->exists()) {
            $this->error("Tenant with slug '{$slug}' already exists.");
            return;
        }

        $tenant = Tenant::create([
            'name' => $name,
            'slug' => $slug,
            'active' => true,
        ]);

        $this->info("✓ Tenant created successfully!");
        $this->table(
            ['ID', 'Name', 'Slug', 'Active'],
            [[$tenant->id, $tenant->name, $tenant->slug, $tenant->active ? 'Yes' : 'No']]
        );
    }

    /**
     * List all tenants.
     */
    protected function listTenants(): void
    {
        $tenants = Tenant::withCount('users')->get();

        if ($tenants->isEmpty()) {
            $this->warn('No tenants found.');
            return;
        }

        $this->table(
            ['ID', 'Name', 'Slug', 'Users', 'Active', 'Created At'],
            $tenants->map(fn($t) => [
                $t->id,
                $t->name,
                $t->slug,
                $t->users_count,
                $t->active ? 'Yes' : 'No',
                $t->created_at->format('Y-m-d H:i'),
            ])->toArray()
        );
    }

    /**
     * Assign user to tenant.
     */
    protected function assignUserToTenant(): void
    {
        $tenantId = $this->option('tenant') ?? $this->ask('Tenant ID or slug');
        $userId = $this->option('user') ?? $this->ask('User ID or email');
        $isAdmin = $this->option('admin');

        // Find tenant
        $tenant = is_numeric($tenantId)
            ? Tenant::find($tenantId)
            : Tenant::where('slug', $tenantId)->first();

        if (!$tenant) {
            $this->error('Tenant not found.');
            return;
        }

        // Find user
        $user = is_numeric($userId)
            ? User::find($userId)
            : User::where('email', $userId)->first();

        if (!$user) {
            $this->error('User not found.');
            return;
        }

        // Check if already assigned
        if ($tenant->users()->where('user_id', $user->id)->exists()) {
            $this->warn('User is already assigned to this tenant.');
            
            // Update admin status if flag is provided
            if ($isAdmin) {
                $tenant->makeAdmin($user);
                $this->info('✓ User admin status updated.');
            }
            
            return;
        }

        // Assign user
        $tenant->addUser($user, $isAdmin);

        $this->info('✓ User assigned to tenant successfully!');
        $this->line("User: {$user->name} ({$user->email})");
        $this->line("Tenant: {$tenant->name} ({$tenant->slug})");
        $this->line("Admin: " . ($isAdmin ? 'Yes' : 'No'));
    }

    /**
     * Remove user from tenant.
     */
    protected function removeUserFromTenant(): void
    {
        $tenantId = $this->option('tenant') ?? $this->ask('Tenant ID or slug');
        $userId = $this->option('user') ?? $this->ask('User ID or email');

        // Find tenant
        $tenant = is_numeric($tenantId)
            ? Tenant::find($tenantId)
            : Tenant::where('slug', $tenantId)->first();

        if (!$tenant) {
            $this->error('Tenant not found.');
            return;
        }

        // Find user
        $user = is_numeric($userId)
            ? User::find($userId)
            : User::where('email', $userId)->first();

        if (!$user) {
            $this->error('User not found.');
            return;
        }

        // Check if assigned
        if (!$tenant->users()->where('user_id', $user->id)->exists()) {
            $this->warn('User is not assigned to this tenant.');
            return;
        }

        // Remove user
        $tenant->removeUser($user);

        $this->info('✓ User removed from tenant successfully!');
        $this->line("User: {$user->name} ({$user->email})");
        $this->line("Tenant: {$tenant->name} ({$tenant->slug})");
    }
}
