<?php

namespace App\Observers;

use App\Models\ServiceProvider;

class ServiceProviderObserver
{
    /**
     * Handle the ServiceProvider "created" event.
     */
    public function created(ServiceProvider $serviceProvider): void
    {
        // Sincroniza roles com o usuário quando o prestador é criado
        $serviceProvider->syncRolesToUser();
    }

    /**
     * Handle the ServiceProvider "updated" event.
     */
    public function updated(ServiceProvider $serviceProvider): void
    {
        // Sincroniza roles com o usuário quando os roles são atualizados
        if ($serviceProvider->wasChanged('provider_roles')) {
            $serviceProvider->syncRolesToUser();
        }
    }

    /**
     * Handle the ServiceProvider "deleted" event.
     */
    public function deleted(ServiceProvider $serviceProvider): void
    {
        //
    }

    /**
     * Handle the ServiceProvider "restored" event.
     */
    public function restored(ServiceProvider $serviceProvider): void
    {
        //
    }

    /**
     * Handle the ServiceProvider "force deleted" event.
     */
    public function forceDeleted(ServiceProvider $serviceProvider): void
    {
        //
    }
}
