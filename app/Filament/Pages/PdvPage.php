<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;

class PdvPage extends Page
{
    protected static string $view = 'filament.pages.pdv';

    protected static ?string $navigationIcon = 'heroicon-o-computer-desktop';

    // Ocultamos da navegação — o link "Abrir PDV" no AdminPanelProvider já cobre isso
    protected static bool $shouldRegisterNavigation = false;

    public static function canAccess(): bool
    {
        if (! auth()->check()) {
            return false;
        }

        $user = auth()->user();
        return $user->hasAnyRole(['super_admin', 'admin', 'operador_caixa', 'financeiro']);
    }
}

