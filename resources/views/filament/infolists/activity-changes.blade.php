<div class="space-y-4">
    @php
        $properties = $getState();
        $attributes = $properties['attributes'] ?? [];
        $old = $properties['old'] ?? [];
        
        // Campos que devem ser mascarados
        $sensitiveFields = ['password', 'tenant_password', 'remember_token', 'api_token'];
        
        // Tradução de campos comuns
        $fieldTranslations = [
            'name' => 'Nome',
            'email' => 'E-mail',
            'status' => 'Status',
            'amount' => 'Valor',
            'quantity' => 'Quantidade',
            'unit_price' => 'Preço Unitário',
            'description' => 'Descrição',
            'notes' => 'Observações',
            'tenant_name' => 'Nome na Organização',
            'is_admin' => 'Administrador',
            'roles' => 'Funções',
            'created_at' => 'Data de Criação',
            'updated_at' => 'Última Atualização',
        ];
        
        $changes = [];
        foreach ($attributes as $key => $value) {
            if (in_array($key, $sensitiveFields)) {
                continue; // Não mostrar campos sensíveis
            }
            
            $oldValue = $old[$key] ?? null;
            
            // Traduzir nome do campo
            $fieldLabel = $fieldTranslations[$key] ?? ucfirst(str_replace('_', ' ', $key));
            
            // Formatar valores booleanos
            if (is_bool($value)) {
                $value = $value ? 'Sim' : 'Não';
                $oldValue = is_bool($oldValue) ? ($oldValue ? 'Sim' : 'Não') : $oldValue;
            }
            
            // Formatar arrays/JSON
            if (is_array($value)) {
                $value = implode(', ', $value);
            }
            if (is_array($oldValue)) {
                $oldValue = implode(', ', $oldValue);
            }
            
            // Formatar valores nulos
            $value = $value ?? '(vazio)';
            $oldValue = $oldValue ?? '(vazio)';
            
            // Só mostrar se realmete mudou
            if ($oldValue != $value) {
                $changes[] = [
                    'field' => $fieldLabel,
                    'old' => $oldValue,
                    'new' => $value,
                ];
            }
        }
    @endphp
    
    @if (empty($changes))
        <div class="text-gray-500 dark:text-gray-400 text-sm">
            Nenhuma alteração específica registrada.
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                <thead class="bg-gray-50 dark:bg-gray-800">
                    <tr>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Campo
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Valor Anterior
                        </th>
                        <th scope="col" class="px-4 py-3 text-center text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            →
                        </th>
                        <th scope="col" class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-400 uppercase tracking-wider">
                            Valor Novo
                        </th>
                    </tr>
                </thead>
                <tbody class="bg-white dark:bg-gray-900 divide-y divide-gray-200 dark:divide-gray-700">
                    @foreach ($changes as $change)
                        <tr>
                            <td class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                {{ $change['field'] }}
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-500 dark:text-gray-400">
                                <code class="bg-red-50 dark:bg-red-900/20 text-red-700 dark:text-red-400 px-2 py-1 rounded">{{ Str::limit($change['old'], 50) }}</code>
                            </td>
                            <td class="px-4 py-3 text-center text-gray-400">
                                <svg class="w-4 h-4 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7l5 5m0 0l-5 5m5-5H6" />
                                </svg>
                            </td>
                            <td class="px-4 py-3 text-sm text-gray-900 dark:text-gray-100">
                                <code class="bg-green-50 dark:bg-green-900/20 text-green-700 dark:text-green-400 px-2 py-1 rounded">{{ Str::limit($change['new'], 50) }}</code>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
