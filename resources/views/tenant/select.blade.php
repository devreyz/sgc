<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecione uma Organização</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full">
            <div class="bg-white shadow-lg rounded-lg p-8">
                <div class="text-center mb-8">
                    <h2 class="text-3xl font-bold text-gray-900">Selecione uma Organização</h2>
                    <p class="mt-2 text-sm text-gray-600">Escolha qual organização você deseja acessar</p>
                </div>

                @if(session('error'))
                    <div class="mb-4 p-4 bg-red-100 border border-red-400 text-red-700 rounded">
                        {{ session('error') }}
                    </div>
                @endif

                <div class="space-y-3">
                    @forelse($tenants as $tenant)
                        <form action="{{ route('tenant.switch') }}" method="POST">
                            @csrf
                            <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                            <button type="submit" class="w-full flex items-center justify-between p-4 bg-gray-50 hover:bg-emerald-50 border border-gray-200 hover:border-emerald-500 rounded-lg transition-colors">
                                <div class="flex items-center space-x-3">
                                    <div class="shrink-0">
                                        <svg class="h-8 w-8 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4" />
                                        </svg>
                                    </div>
                                    <div class="text-left">
                                        <p class="text-lg font-semibold text-gray-900">{{ $tenant->name }}</p>
                                        <p class="text-sm text-gray-500">{{ $tenant->slug }}</p>
                                    </div>
                                </div>
                                <svg class="h-5 w-5 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </button>
                        </form>
                    @empty
                        <div class="text-center p-6 bg-yellow-50 border border-yellow-200 rounded-lg">
                            <p class="text-yellow-800">Você não está vinculado a nenhuma organização.</p>
                            <p class="text-sm text-yellow-600 mt-2">Entre em contato com o administrador.</p>
                        </div>
                    @endforelse
                </div>

                <div class="mt-6 text-center">
                    <form action="{{ route('logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
