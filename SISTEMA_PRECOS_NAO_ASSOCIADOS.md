# Sistema de Preços para Não-Associados

## Visão Geral

O sistema atualmente permite criar ordens de serviço apenas para associados. Para implementar preços diferentes para não-associados, temos duas opções:

## Opção 1: Serviços Duplicados (Simples)

**Vantagem**: Implementação imediata, sem alterações no banco de dados
**Desvantagem**: Duplicação de cadastros

### Implementação:

1. Criar serviços duplicados no admin:
    - "Aração de Terra - Sócio" (R$ 100,00)
    - "Aração de Terra - Não-Sócio" (R$ 150,00)

2. Filtrar exibição no formulário baseado no tipo de ordem

## Opção 2: Sistema de Preços por Categoria (Recomendado)

**Vantagem**: Flexível, escalável, sem duplicação
**Desvantagem**: Requer alterações no banco de dados

### Estrutura Proposta:

#### 1. Nova Migration: Tabela `service_prices`

```php
Schema::create('service_prices', function (Blueprint $table) {
    $table->id();
    $table->foreignId('service_id')->constrained()->cascadeOnDelete();
    $table->enum('customer_type', ['associate', 'non_associate', 'government', 'private']);
    $table->decimal('unit_price', 10, 2);
    $table->boolean('is_active')->default(true);
    $table->timestamps();

    $table->unique(['service_id', 'customer_type']);
});
```

#### 2. Alterar `service_orders` table

Adicionar campo para identificar tipo de cliente:

```php
$table->enum('customer_type', ['associate', 'non_associate', 'government', 'private'])
    ->default('associate')
    ->after('associate_id');

$table->string('customer_name')->nullable()->after('customer_type');
$table->string('customer_document')->nullable()->after('customer_name');
```

#### 3. Model `ServicePrice`

```php
class ServicePrice extends Model
{
    protected $fillable = [
        'service_id',
        'customer_type',
        'unit_price',
        'is_active'
    ];

    public function service()
    {
        return $this->belongsTo(Service::class);
    }
}
```

#### 4. Atualizar Model `Service`

```php
public function prices()
{
    return $this->hasMany(ServicePrice::class);
}

public function getPriceForType($customerType)
{
    $price = $this->prices()
        ->where('customer_type', $customerType)
        ->where('is_active', true)
        ->first();

    return $price?->unit_price ?? $this->base_price;
}
```

#### 5. Atualizar Formulário `create-order.blade.php`

Adicionar campo para tipo de cliente:

```html
<div class="form-group">
    <label for="customer_type">Tipo de Cliente *</label>
    <select name="customer_type" id="customer_type" required>
        <option value="associate">Associado</option>
        <option value="non_associate">Não-Associado</option>
        <option value="government">Governo</option>
        <option value="private">Particular</option>
    </select>
</div>

<!-- Campos condicionais -->
<div id="associate-fields" style="display: none;">
    <div class="form-group">
        <label for="associate_id">Associado</label>
        <select name="associate_id" id="associate_id">
            <!-- Lista de associados -->
        </select>
    </div>
</div>

<div id="non-associate-fields" style="display: none;">
    <div class="form-group">
        <label for="customer_name">Nome do Cliente *</label>
        <input type="text" name="customer_name" id="customer_name" />
    </div>

    <div class="form-group">
        <label for="customer_document">CPF/CNPJ *</label>
        <input type="text" name="customer_document" id="customer_document" />
    </div>
</div>
```

JavaScript para alternar campos:

```javascript
document
    .getElementById("customer_type")
    .addEventListener("change", function () {
        const type = this.value;

        document.getElementById("associate-fields").style.display =
            type === "associate" ? "block" : "none";

        document.getElementById("non-associate-fields").style.display =
            type !== "associate" ? "block" : "none";

        // Atualizar preço do serviço
        updateServicePrice();
    });

function updateServicePrice() {
    const serviceId = document.getElementById("service_id").value;
    const customerType = document.getElementById("customer_type").value;

    if (!serviceId || !customerType) return;

    fetch(`/api/services/${serviceId}/price?type=${customerType}`)
        .then((response) => response.json())
        .then((data) => {
            unitPrice = data.price;
            document.getElementById("unit_display").value = data.unit;
            calculateTotal();
        });
}
```

#### 6. Nova Rota API

```php
// routes/api.php
Route::get('/services/{service}/price', function(Service $service, Request $request) {
    $type = $request->get('type', 'associate');
    return response()->json([
        'price' => $service->getPriceForType($type),
        'unit' => $service->unit
    ]);
})->middleware('auth');
```

#### 7. Atualizar Controller `storeOrder`

```php
$validated = $request->validate([
    'customer_type' => 'required|in:associate,non_associate,government,private',
    'associate_id' => 'required_if:customer_type,associate|exists:associates,id',
    'customer_name' => 'required_unless:customer_type,associate|string|max:255',
    'customer_document' => 'required_unless:customer_type,associate|string|max:20',
    // ... outros campos
]);

$service = Service::findOrFail($validated['service_id']);
$unitPrice = $service->getPriceForType($validated['customer_type']);

$order = ServiceOrder::create([
    'customer_type' => $validated['customer_type'],
    'associate_id' => $validated['customer_type'] === 'associate' ? $validated['associate_id'] : null,
    'customer_name' => $validated['customer_name'] ?? null,
    'customer_document' => $validated['customer_document'] ?? null,
    'unit_price' => $unitPrice,
    // ... outros campos
]);
```

## Resumo

### Para Implementação Rápida (Opção 1)

1. Criar serviços duplicados no painel admin
2. Adicionar filtro no formulário de criação

### Para Implementação Completa (Opção 2)

1. Criar migration `service_prices`
2. Alterar migration `service_orders`
3. Criar model `ServicePrice`
4. Atualizar model `Service`
5. Atualizar formulário com campos condicionais
6. Adicionar rota API para buscar preços
7. Atualizar controller de criação

## Status Atual

- ✅ Formulário preparado para receber modificações
- ✅ Layout responsivo implementado
- ✅ Sistema de associados funcionando
- ⏳ Aguardando decisão sobre qual opção implementar

## Próximos Passos

Escolher a opção preferida e iniciar implementação conforme documentação acima.
