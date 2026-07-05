<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecione uma Organização</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom subtle animation */
        .tenant-card {
            transition: all 0.2s ease;
        }
        .tenant-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 25px rgba(16, 185, 129, 0.15);
        }
        .logo-placeholder {
            background: linear-gradient(135deg, #059669 0%, #10b981 100%);
        }
    </style>
</head>
<body class="bg-gradient-to-br from-gray-50 to-emerald-50 min-h-dvh">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-xl w-full">
            {{-- Header --}}
            <div class="text-center mb-8">
                <div class="inline-flex items-center justify-center w-16 h-16 rounded-full bg-emerald-100 mb-4">
                    <svg class="w-8 h-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                    </svg>
                </div>
                <h2 class="text-2xl font-bold text-gray-900 sm:text-3xl">Selecione uma Organização</h2>
                <p class="mt-3 text-sm text-gray-600 max-w-md mx-auto">
                    Escolha qual organização você deseja acessar. Seus dados e configurações são específicos para cada uma.
                </p>
            </div>

            {{-- Error message --}}
            @if(session('error'))
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-700 rounded-lg flex items-start gap-3">
                    <svg class="w-5 h-5 text-red-500 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span class="text-sm">{{ session('error') }}</span>
                </div>
            @endif

            {{-- Tenant list --}}
            <div class="space-y-3">
                @forelse($tenants as $tenant)
                    <form action="{{ route('tenant.switch') }}" method="POST">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <button type="submit" class="tenant-card w-full flex items-center gap-4 p-4 bg-white border border-gray-200 rounded-xl hover:border-emerald-400 transition-colors text-left group">
                            {{-- Logo / Icon --}}
                            <div class="shrink-0">
                                @if($tenant->logo)
                                    <img src="{{ asset('storage/' . $tenant->logo) }}" 
                                         alt="{{ $tenant->name }}" 
                                         class="w-12 h-12 rounded-xl object-cover border border-gray-100"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';">
                                    <div class="w-12 h-12 rounded-xl logo-placeholder items-center justify-center text-white font-bold text-lg" style="display:none;">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </div>
                                @else
                                    <div class="w-12 h-12 rounded-xl logo-placeholder flex items-center justify-center text-white font-bold text-lg">
                                        {{ strtoupper(substr($tenant->name, 0, 2)) }}
                                    </div>
                                @endif
                            </div>

                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="font-semibold text-gray-900 group-hover:text-emerald-700 transition-colors text-base">
                                    {{ $tenant->name }}
                                </p>
                                @if($tenant->slug)
                                    <p class="text-sm text-gray-500 mt-0.5">{{ $tenant->slug }}</p>
                                @endif
                                @if($tenant->description)
                                    <p class="text-xs text-gray-400 mt-1 truncate">{{ $tenant->description }}</p>
                                @endif
                            </div>

                            {{-- Arrow --}}
                            <div class="shrink-0 text-gray-300 group-hover:text-emerald-500 transition-colors">
                                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </button>
                    </form>
                @empty
                    <div class="text-center p-8 bg-yellow-50 border border-yellow-200 rounded-xl">
                        <svg class="mx-auto h-12 w-12 text-yellow-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-2.5L13.732 4c-.77-.833-1.964-.833-2.732 0L3.34 16.5c-.77.833.192 2.5 1.732 2.5z" />
                        </svg>
                        <p class="mt-4 text-yellow-800 font-medium">Nenhuma organização disponível</p>
                        <p class="text-sm text-yellow-600 mt-2">Você não está vinculado a nenhuma organização. Entre em contato com o administrador do sistema.</p>
                    </div>
                @endforelse
            </div>

            {{-- Footer --}}
            <div class="mt-8 text-center">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="inline-flex items-center gap-2 text-sm text-gray-500 hover:text-gray-700 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                        </svg>
                        Sair da conta
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>