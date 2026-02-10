<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Criar Ordem de Serviço - Portal Prestador</title>
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

            <div class="bg-white shadow-md rounded-lg p-6">
                <h2 class="text-xl font-bold mb-6">Criar Nova Ordem de Serviço</h2>

                @if ($errors->any())
                    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded">
                        <ul class="list-disc list-inside">
                            @foreach ($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                <form method="POST" action="{{ route('provider.orders.store') }}" class="space-y-6">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Service -->
                        <div>
                            <label for="service_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Serviço * <span class="text-xs text-gray-500">(Tipo de serviço)</span>
                            </label>
                            <select name="service_id" id="service_id" required
                                    class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Selecione...</option>
                                @foreach($services as $service)
                                    <option value="{{ $service->id }}" data-price="{{ $service->base_price }}">
                                        {{ $service->name }} - R$ {{ number_format($service->base_price, 2, ',', '.') }}/{{ $service->unit }}
                                    </option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Associate (Optional) -->
                        <div>
                            <label for="associate_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Associado <span class="text-xs text-gray-500">(Opcional)</span>
                            </label>
                            <select name="associate_id" id="associate_id"
                                    class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Nenhum</option>
                                @foreach($associates as $associate)
                                    <option value="{{ $associate->id }}">{{ $associate->name }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Equipment (Optional) -->
                        <div>
                            <label for="asset_id" class="block text-sm font-medium text-gray-700 mb-2">
                                Equipamento <span class="text-xs text-gray-500">(Opcional)</span>
                            </label>
                            <select name="asset_id" id="asset_id"
                                    class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                                <option value="">Nenhum</option>
                                @foreach($equipment as $equip)
                                    <option value="{{ $equip->id }}">{{ $equip->name }} - {{ $equip->model }}</option>
                                @endforeach
                            </select>
                        </div>

                        <!-- Scheduled Date -->
                        <div>
                            <label for="scheduled_date" class="block text-sm font-medium text-gray-700 mb-2">
                                Data Agendada *
                            </label>
                            <input type="date" name="scheduled_date" id="scheduled_date" required
                                   value="{{ old('scheduled_date', date('Y-m-d')) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Start Time -->
                        <div>
                            <label for="start_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Hora Início
                            </label>
                            <input type="time" name="start_time" id="start_time"
                                   value="{{ old('start_time') }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- End Time -->
                        <div>
                            <label for="end_time" class="block text-sm font-medium text-gray-700 mb-2">
                                Hora Fim
                            </label>
                            <input type="time" name="end_time" id="end_time"
                                   value="{{ old('end_time') }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Quantity -->
                        <div>
                            <label for="quantity" class="block text-sm font-medium text-gray-700 mb-2">
                                Quantidade *
                            </label>
                            <input type="number" name="quantity" id="quantity" required step="0.01" min="0"
                                   value="{{ old('quantity', 1) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Unit -->
                        <div>
                            <label for="unit" class="block text-sm font-medium text-gray-700 mb-2">
                                Unidade *
                            </label>
                            <input type="text" name="unit" id="unit" required
                                   value="{{ old('unit', 'hora') }}"
                                   placeholder="Ex: hora, dia, km"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Unit Price -->
                        <div>
                            <label for="unit_price" class="block text-sm font-medium text-gray-700 mb-2">
                                Preço Unitário (R$) *
                            </label>
                            <input type="number" name="unit_price" id="unit_price" required step="0.01" min="0"
                                   value="{{ old('unit_price') }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Location -->
                        <div>
                            <label for="location" class="block text-sm font-medium text-gray-700 mb-2">
                                Local *
                            </label>
                            <input type="text" name="location" id="location" required
                                   value="{{ old('location') }}"
                                   placeholder="Endereço ou local do serviço"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>

                        <!-- Distance -->
                        <div>
                            <label for="distance_km" class="block text-sm font-medium text-gray-700 mb-2">
                                Distância (km)
                            </label>
                            <input type="number" name="distance_km" id="distance_km" step="0.01" min="0"
                                   value="{{ old('distance_km', 0) }}"
                                   class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">
                        </div>
                    </div>

                    <!-- Notes -->
                    <div>
                        <label for="notes" class="block text-sm font-medium text-gray-700 mb-2">
                            Observações
                        </label>
                        <textarea name="notes" id="notes" rows="3"
                                  class="w-full border border-gray-300 rounded-md px-4 py-2 focus:ring-2 focus:ring-blue-500">{{ old('notes') }}</textarea>
                    </div>

                    <!-- Total Preview -->
                    <div class="bg-blue-50 border border-blue-200 rounded-lg p-4">
                        <div class="flex justify-between items-center">
                            <span class="font-medium text-gray-700">Valor Total Estimado:</span>
                            <span class="text-2xl font-bold text-blue-600" id="total_preview">R$ 0,00</span>
                        </div>
                    </div>

                    <!-- Buttons -->
                    <div class="flex justify-end gap-4">
                        <a href="{{ route('provider.orders') }}" 
                           class="px-6 py-2 border border-gray-300 rounded-md text-gray-700 hover:bg-gray-50">
                            Cancelar
                        </a>
                        <button type="submit" 
                                class="px-6 py-2 bg-blue-600 text-white rounded-md hover:bg-blue-700">
                            Criar Ordem de Serviço
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Auto-fill price from service selection
        document.getElementById('service_id').addEventListener('change', function() {
            const selectedOption = this.options[this.selectedIndex];
            const price = selectedOption.getAttribute('data-price');
            if (price) {
                document.getElementById('unit_price').value = price;
                calculateTotal();
            }
        });

        // Calculate total when quantity or price changes
        document.getElementById('quantity').addEventListener('input', calculateTotal);
        document.getElementById('unit_price').addEventListener('input', calculateTotal);

        function calculateTotal() {
            const quantity = parseFloat(document.getElementById('quantity').value) || 0;
            const unitPrice = parseFloat(document.getElementById('unit_price').value) || 0;
            const total = quantity * unitPrice;
            document.getElementById('total_preview').textContent = 
                'R$ ' + total.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        }

        // Initial calculation
        calculateTotal();
    </script>
</body>
</html>
