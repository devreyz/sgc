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

        // Detectar as roles disponíveis
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
                'url' => route('provider.dashboard'),
                'color' => 'success',
            ];
        }

        if ($user->hasRole('associado')) {
            $roles[] = [
                'name' => 'Associado',
                'description' => 'Projetos e entregas',
                'icon' => 'users',
                'url' => route('associate.dashboard'),
                'color' => 'info',
            ];
        }

        if ($user->hasRole('registrador_entregas')) {
            $roles[] = [
                'name' => 'Registrador de Entregas',
                'description' => 'Registrar entregas de produção',
                'icon' => 'package',
                'url' => route('delivery.dashboard'),
                'color' => 'warning',
            ];
        }

        // Se só tem uma role, redirecionar automaticamente
        if (count($roles) === 1) {
            return redirect($roles[0]['url']);
        }

        // Se não tem nenhuma role conhecida, mostrar hub com mensagem em vez de redirecionar
        if (count($roles) === 0) {
            // evita redirecionamento automático para /admin que causa loops/UX indesejado
            $notice = 'Seu usuário não possui painéis atribuídos. Contate o administrador para atribuir permissões.';
            return view('hub', compact('roles', 'user'))->with('error', $notice);
        }

        // Mostrar hub de seleção
        return view('hub', compact('roles', 'user'));
    }
}
