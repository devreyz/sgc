<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class HubController extends Controller
{
    public function index()
    {
        if (!Auth::check()) {
            return view('welcome');
        }

        $user = Auth::user();

        // Verificar se usuário tem tenants antes de mostrar hub
        $userTenants = $user->tenants;
        
        // Se não tem nenhum tenant, mostrar erro
        if ($userTenants->isEmpty()) {
            return view('hub', [
                'user' => $user,
                'roles' => [],
                'tenants' => collect(),
            ])->with('error', 'Você não está vinculado a nenhuma organização. Contate o administrador.');
        }

        // Se não há tenant na sessão, mostrar seleção de tenant
        if (!session('tenant_id')) {
            return view('tenant.select', [
                'tenants' => $userTenants,
                'user' => $user,
            ]);
        }

        // Verificar se o tenant da sessão ainda é válido para o usuário
        $currentTenantId = session('tenant_id');
        if (!$userTenants->contains('id', $currentTenantId)) {
            session()->forget('tenant_id');
            return view('tenant.select', [
                'tenants' => $userTenants,
                'user' => $user,
            ])->with('warning', 'Você não tem mais acesso à organização anterior. Selecione outra.');
        }

        $currentTenant = $userTenants->firstWhere('id', $currentTenantId);

        // Detectar as roles disponíveis do usuário no tenant atual
        $roles = [];

        if ($user->hasAnyRole(['super_admin', 'admin', 'financeiro'])) {
            $roles[] = [
                'name' => 'Administração',
                'description' => 'Gerenciar sistema completo',
                'icon' => 'shield',
                'url' => '/admin',
                'color' => 'primary',
            ];
        }

        if ($user->hasAnyRole(['service_provider', 'tratorista', 'motorista', 'diarista', 'tecnico'])) {
            $roles[] = [
                'name' => 'Prestador de Serviço',
                'description' => 'Gerenciar ordens e recebimentos',
                'icon' => 'briefcase',
                'url' => route('provider.dashboard', ['tenant' => $currentTenant->slug]),
                'color' => 'success',
            ];
        }

        if ($user->hasRole('associado')) {
            $roles[] = [
                'name' => 'Associado',
                'description' => 'Projetos e entregas',
                'icon' => 'users',
                'url' => route('associate.dashboard', ['tenant' => $currentTenant->slug]),
                'color' => 'info',
            ];
        }

        if ($user->hasRole('registrador_entregas')) {
            $roles[] = [
                'name' => 'Registrador de Entregas',
                'description' => 'Registrar entregas de produção',
                'icon' => 'package',
                'url' => route('delivery.dashboard', ['tenant' => $currentTenant->slug]),
                'color' => 'warning',
            ];
        }

        // Se só tem uma role, redirecionar automaticamente
        if (count($roles) === 1) {
            return redirect($roles[0]['url']);
        }

        // Se não tem nenhuma role conhecida, mostrar hub com mensagem em vez de redirecionar
        if (count($roles) === 0) {
            $notice = 'Seu usuário não possui painéis atribuídos nesta organização. Contate o administrador para atribuir permissões.';
            return view('hub', compact('roles', 'user'))->with('error', $notice);
        }

        // Mostrar hub de seleção com tenant context
        return view('hub', compact('roles', 'user', 'currentTenant'));
    }
}
