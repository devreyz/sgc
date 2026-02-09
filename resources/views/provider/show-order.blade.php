<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ordem #{{ $order->number }} - Portal Prestador</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100">
    <div class="min-h-screen">
        <!-- Header -->
        <header class="bg-white shadow">
            <div class="max-w-7xl mx-auto px-4 py-4 sm:px-6 lg:px-8 flex justify-between items-center">
                <div>
                    <h1 class="text-2xl font-bold text-gray-900">Portal do Prestador</h1>
                    <p class="text-sm text-gray-600">{{ $provider->name }}</p>
                </div>
                <div class="flex items-center gap-4">
                    <a href="{{ route('provider.dashboard') }}" class="text-blue-600 hover:text-blue-800">Dashboard</a>
                    <a href="{{ route('provider.orders') }}" class="text-blue-600 hover:text-blue-800">Minhas Ordens</a>
                    <form method="POST" action="{{ route('logout') }}" class="inline">
                        @csrf
                        <button type="submit" class="text-red-600 hover:text-red-800">Sair</button>
                    </form>
                </div>
            </div>
        </header>

        <!-- Main Content -->
        <main class="max-w-7xl mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <div class="mb-6">
                <a href="{{ route('provider.orders') }}" class="text-blue-600 hover:text-blue-800">
                    ← Voltar para Ordens
                </a>
            </div>

            @if (session('success'))
                <div class="mb-4 bg-green-50 border border-green-200 text-green-700 px-4 py-3 rounded">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any())
                <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                    <ul class="list-disc list-inside">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="bg-white shadow-md rounded-lg p-6">
                <!-- Order Header -->
                <div class="flex justify-between items-start mb-6 border-b pb-4">
                    <div>
                        <h2 class="text-2xl font-bold">Ordem #{{ $order->number }}</h2>
                        <p class="text-gray-600">Criada em {{ $order->created_at->format('d/m/Y H:i') }}</p>
                    </div>
                    <div>
                        <span class="px-4 py-2 rounded-full text-sm font-medium
                            @if($order->status->value === 'scheduled') bg-blue-100 text-blue-800
                            @elseif($order->status->value === 'in_progress') bg-yellow-100 text-yellow-800
                            @elseif($order->status->value === 'completed') bg-green-100 text-green-800
                            @else bg-gray-100 text-gray-800
                            @endif">
                            {{ $order->status->getLabel() }}
                        </span>
                    </div>
                </div>

                <!-- Order Details -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <h3 class="font-semibold text-gray-700 mb-3">Detalhes do Serviço</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Serviço:</span>
                                <span class="font-medium">{{ $order->service->name ?? 'N/A' }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Data Agendada:</span>
                                <span class="font-medium">{{ $order->scheduled_date->format('d/m/Y') }}</span>
                            </div>
                            @if($order->start_time)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Horário:</span>
                                <span class="font-medium">{{ $order->start_time }} - {{ $order->end_time }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between">
                                <span class="text-gray-600">Local:</span>
                                <span class="font-medium">{{ $order->location }}</span>
                            </div>
                            @if($order->associate)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Associado:</span>
                                <span class="font-medium">{{ $order->associate->name }}</span>
                            </div>
                            @endif
                            @if($order->equipment)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Equipamento:</span>
                                <span class="font-medium">{{ $order->equipment->name }}</span>
                            </div>
                            @endif
                        </div>
                    </div>

                    <div>
                        <h3 class="font-semibold text-gray-700 mb-3">Valores</h3>
                        <div class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span class="text-gray-600">Quantidade:</span>
                                <span class="font-medium">{{ number_format($order->quantity, 2, ',', '.') }} {{ $order->unit }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Preço Unitário:</span>
                                <span class="font-medium">R$ {{ number_format($order->unit_price, 2, ',', '.') }}</span>
                            </div>
                            <div class="flex justify-between">
                                <span class="text-gray-600">Subtotal:</span>
                                <span class="font-medium">R$ {{ number_format($order->total_price, 2, ',', '.') }}</span>
                            </div>
                            @if($order->discount > 0)
                            <div class="flex justify-between">
                                <span class="text-gray-600">Desconto:</span>
                                <span class="font-medium text-red-600">- R$ {{ number_format($order->discount, 2, ',', '.') }}</span>
                            </div>
                            @endif
                            <div class="flex justify-between border-t pt-2">
                                <span class="text-gray-700 font-semibold">Total:</span>
                                <span class="font-bold text-lg text-green-600">R$ {{ number_format($order->final_price, 2, ',', '.') }}</span>
                            </div>
                        </div>
                    </div>
                </div>

                @if($order->notes)
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-700 mb-2">Observações</h3>
                    <p class="text-gray-600 text-sm">{{ $order->notes }}</p>
                </div>
                @endif

                @if($order->work_description)
                <div class="mb-6">
                    <h3 class="font-semibold text-gray-700 mb-2">Descrição da Execução</h3>
                    <p class="text-gray-600 text-sm">{{ $order->work_description }}</p>
                    @if($order->execution_date)
                    <p class="text-sm text-gray-500 mt-2">Executado em: {{ $order->execution_date->format('d/m/Y') }}</p>
                    @endif
                </div>
                @endif

                <!-- Actions -->
                @if($order->status->value === 'scheduled' || $order->status->value === 'in_progress')
                <div class="border-t pt-6">
                    <h3 class="font-semibold text-gray-700 mb-4">Concluir Serviço</h3>
                    <form method="POST" action="{{ route('provider.orders.complete', $order->id) }}" enctype="multipart/form-data">
                        @csrf
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                            <div>
                                <label for="execution_date" class="block text-sm font-medium text-gray-700 mb-2">
                                    Data de Execução *
                                </label>
                                <input type="date" name="execution_date" id="execution_date" required
                                       value="{{ old('execution_date', date('Y-m-d')) }}"
                                       class="w-full border border-gray-300 rounded-md px-4 py-2">
                            </div>

                            <div>
                                <label for="hours_worked" class="block text-sm font-medium text-gray-700 mb-2">
                                    Horas Trabalhadas *
                                </label>
                                <input type="number" name="hours_worked" id="hours_worked" required step="0.5" min="0"
                                       value="{{ old('hours_worked') }}"
                                       class="w-full border border-gray-300 rounded-md px-4 py-2">
                            </div>

                            @if($order->equipment)
                            <div>
                                <label for="horimeter_start" class="block text-sm font-medium text-gray-700 mb-2">
                                    Horímetro Inicial
                                </label>
                                <input type="number" name="horimeter_start" id="horimeter_start" step="0.1"
                                       value="{{ old('horimeter_start') }}"
                                       class="w-full border border-gray-300 rounded-md px-4 py-2">
                            </div>

                            <div>
                                <label for="horimeter_end" class="block text-sm font-medium text-gray-700 mb-2">
                                    Horímetro Final
                                </label>
                                <input type="number" name="horimeter_end" id="horimeter_end" step="0.1"
                                       value="{{ old('horimeter_end') }}"
                                       class="w-full border border-gray-300 rounded-md px-4 py-2">
                            </div>

                            <div>
                                <label for="fuel_used" class="block text-sm font-medium text-gray-700 mb-2">
                                    Combustível Usado (L)
                                </label>
                                <input type="number" name="fuel_used" id="fuel_used" step="0.1"
                                       value="{{ old('fuel_used') }}"
                                       class="w-full border border-gray-300 rounded-md px-4 py-2">
                            </div>
                            @endif
                        </div>

                        <div class="mb-4">
                            <label for="work_description" class="block text-sm font-medium text-gray-700 mb-2">
                                Descrição do Trabalho Realizado *
                            </label>
                            <textarea name="work_description" id="work_description" required rows="4"
                                      class="w-full border border-gray-300 rounded-md px-4 py-2">{{ old('work_description') }}</textarea>
                        </div>

                        <div class="mb-6">
                            <label for="receipt" class="block text-sm font-medium text-gray-700 mb-2">
                                Comprovante (Foto/PDF) * <span class="text-xs text-gray-500">(Obrigatório para pagamento)</span>
                            </label>
                            <input type="file" name="receipt" id="receipt" required accept=".pdf,.jpg,.jpeg,.png"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2">
                            <p class="text-xs text-gray-500 mt-1">Formatos: PDF, JPG, PNG (max 5MB)</p>
                        </div>

                        <div class="flex justify-end gap-4">
                            <button type="submit" 
                                    class="px-8 py-3 bg-green-600 text-white rounded-md hover:bg-green-700 font-medium">
                                Concluir e Enviar para Aprovação
                            </button>
                        </div>
                    </form>
                </div>
                @elseif($order->status->value === 'completed')
                <div class="bg-green-50 border border-green-200 rounded-lg p-4">
                    <p class="text-green-800 font-medium">✓ Serviço concluído e aguardando aprovação para pagamento.</p>
                </div>
                @endif

                <!-- Work History -->
                @if($order->works && $order->works->count() > 0)
                <div class="border-t pt-6 mt-6">
                    <h3 class="font-semibold text-gray-700 mb-4">Histórico de Trabalhos</h3>
                    <div class="space-y-3">
                        @foreach($order->works as $work)
                        <div class="bg-gray-50 rounded-lg p-4">
                            <div class="flex justify-between items-start mb-2">
                                <div>
                                    <p class="font-medium">{{ $work->work_date->format('d/m/Y') }}</p>
                                    <p class="text-sm text-gray-600">{{ $work->hours_worked }}h trabalhadas</p>
                                </div>
                                <div class="text-right">
                                    <p class="font-bold text-green-600">R$ {{ number_format($work->total_value, 2, ',', '.') }}</p>
                                    <span class="text-xs px-2 py-1 rounded-full
                                        @if($work->payment_status === 'pendente') bg-yellow-100 text-yellow-800
                                        @elseif($work->payment_status === 'pago') bg-green-100 text-green-800
                                        @else bg-gray-100 text-gray-800
                                        @endif">
                                        {{ $work->payment_status_label }}
                                    </span>
                                </div>
                            </div>
                            <p class="text-sm text-gray-700">{{ $work->description }}</p>
                            @if($work->notes)
                            <p class="text-xs text-gray-500 mt-2">{{ $work->notes }}</p>
                            @endif
                        </div>
                        @endforeach
                    </div>
                </div>
                @endif
            </div>
        </main>
    </div>
</body>
</html>
