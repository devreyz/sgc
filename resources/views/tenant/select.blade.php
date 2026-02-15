<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Selecionar Organização - {{ config('app.name') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css" rel="stylesheet">
</head>
<body class="bg-gray-100">
    <div class="min-h-screen flex items-center justify-center py-12 px-4 sm:px-6 lg:px-8">
        <div class="max-w-md w-full space-y-8">
            <div>
                <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900">
                    Selecionar Organização
                </h2>
                <p class="mt-2 text-center text-sm text-gray-600">
                    Escolha a organização que deseja acessar
                </p>
            </div>

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            <div class="space-y-4">
                @foreach($tenants as $tenant)
                    <form action="{{ route('tenant.switch') }}" method="POST">
                        @csrf
                        <input type="hidden" name="tenant_id" value="{{ $tenant->id }}">
                        <button type="submit" class="w-full flex items-center justify-between p-6 bg-white border border-gray-300 rounded-lg hover:bg-gray-50 hover:border-blue-500 transition">
                            <div class="flex items-center">
                                <div class="flex-shrink-0 h-12 w-12 bg-blue-500 rounded-full flex items-center justify-center">
                                    <span class="text-white font-bold text-xl">{{ substr($tenant->name, 0, 1) }}</span>
                                </div>
                                <div class="ml-4 text-left">
                                    <h3 class="text-lg font-medium text-gray-900">{{ $tenant->name }}</h3>
                                    <p class="text-sm text-gray-500">{{ $tenant->slug }}</p>
                                </div>
                            </div>
                            <svg class="h-6 w-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>
                @endforeach
            </div>

            <div class="text-center">
                <form action="{{ route('logout') }}" method="POST">
                    @csrf
                    <button type="submit" class="text-sm text-gray-600 hover:text-gray-900">
                        Sair
                    </button>
                </form>
            </div>
        </div>
    </div>
</body>
</html>
