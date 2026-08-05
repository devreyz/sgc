<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\BankAccount;
use App\Models\Tenant;
use App\Services\TemplatedPdfService;
use App\Support\FinanceModuleRegistry;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FinanceManagementController extends Controller
{
    public function index(Request $request, Tenant $tenant, string $module): View
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'view_any');

        return view('finance.management.index', compact('tenant', 'module', 'config'));
    }

    public function data(Request $request, Tenant $tenant, string $module): JsonResponse
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'view_any');
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'page' => ['nullable', 'integer', 'min:1'], 'per_page' => ['nullable', 'integer', 'between:10,50']]);
        $model = $config['model'];
        $columns = array_values(array_unique(array_merge(['id'], $config['columns'], array_keys($config['fields']))));
        $query = $model::query()->where('tenant_id', $tenant->id)->select($columns);
        if (filled($filters['q'] ?? null)) {
            $search = str_replace(['%', '_'], ['\\%', '\\_'], $filters['q']);
            $searchable = array_values(array_intersect(['name', 'description', 'code', 'document_number', 'cpf', 'cpf_cnpj', 'cnpj'], $columns));
            $query->where(function ($query) use ($searchable, $search): void {
                foreach ($searchable as $index => $column) {
                    $method = $index === 0 ? 'where' : 'orWhere';
                    $query->{$method}($column, 'like', '%'.$search.'%');
                }
            });
        }
        $page = $query->latest('id')->paginate($filters['per_page'] ?? 20);

        return response()->json([
            'data' => collect($page->items())->map(fn (Model $record) => $this->serialize($record, $columns)),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total()],
            'abilities' => [
                'create' => $config['creatable'] && $request->user()->can('create_'.$config['permission']),
                'update' => $config['updatable'] && $request->user()->can('update_'.$config['permission']),
                'delete' => $config['deletable'] && $request->user()->can('delete_'.$config['permission']),
                'view' => $config['viewable'],
                'print' => $config['printable'],
            ],
        ]);
    }

    public function store(Request $request, Tenant $tenant, string $module): JsonResponse
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'create');
        abort_unless($config['creatable'], 405);
        $data = $request->validate(Arr::map($config['fields'], fn ($field) => $field['rules']));
        $model = $config['model'];
        try {
            $record = DB::transaction(function () use ($model, $tenant, $data, $request): Model {
                $record = new $model($data);
                $record->tenant_id = $tenant->id;
                if ($record instanceof BankAccount) {
                    $record->current_balance = $data['initial_balance'] ?? 0;
                    if ($record->is_default) {
                        BankAccount::query()->where('tenant_id', $tenant->id)->update(['is_default' => false]);
                    }
                }
                if ($record->isFillable('created_by')) {
                    $record->created_by = $request->user()->id;
                }
                $record->save();

                return $record;
            });
        } catch (QueryException) {
            return response()->json(['message' => 'Os dados informados conflitam com um registro existente.'], 422);
        }

        return response()->json(['message' => 'Registro criado.', 'data' => $this->serialize($record, array_merge(['id'], $config['columns'], array_keys($config['fields'])))], 201);
    }

    public function update(Request $request, Tenant $tenant, string $module, int $record): JsonResponse
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'update');
        abort_unless($config['updatable'], 405);
        $model = $this->record($config, $tenant, $record);
        $data = $request->validate(Arr::map($config['fields'], fn ($field) => $field['rules']));
        try {
            DB::transaction(function () use ($model, $data, $tenant): void {
                if ($model instanceof BankAccount) {
                    unset($data['initial_balance']);
                }
                $model->fill($data);
                if ($model instanceof BankAccount && $model->is_default) {
                    BankAccount::query()->where('tenant_id', $tenant->id)->whereKeyNot($model->id)->update(['is_default' => false]);
                }
                $model->save();
            });
        } catch (QueryException) {
            return response()->json(['message' => 'Os dados informados conflitam com um registro existente.'], 422);
        }

        return response()->json(['message' => 'Alteracoes salvas.', 'data' => $this->serialize($model->fresh(), array_merge(['id'], $config['columns'], array_keys($config['fields'])))]);
    }

    public function destroy(Request $request, Tenant $tenant, string $module, int $record): JsonResponse
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'delete');
        abort_unless($config['deletable'], 405, 'Este cadastro deve ser desativado ou cancelado para preservar o historico.');
        $model = $this->record($config, $tenant, $record);
        try {
            DB::transaction(fn () => $model->delete());
        } catch (QueryException) {
            return response()->json(['message' => 'Este registro possui vinculos e nao pode ser removido.'], 422);
        }

        return response()->json(['message' => 'Registro removido.']);
    }

    public function show(Request $request, Tenant $tenant, string $module, int $record): View
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'view');
        abort_unless($config['viewable'], 404);
        $model = $this->record($config, $tenant, $record);
        $columns = array_values(array_unique(array_merge(['id'], $config['detail_columns'])));

        return view('finance.management.show', [
            'tenant' => $tenant,
            'module' => $module,
            'config' => $config,
            'record' => $this->serialize($model, $columns),
        ]);
    }

    public function print(Request $request, Tenant $tenant, string $module, int $record, TemplatedPdfService $pdfService)
    {
        $config = $this->authorizeModule($request, $tenant, $module, 'view');
        abort_unless($config['printable'], 404);
        $model = $this->record($config, $tenant, $record);
        $columns = array_values(array_unique(array_merge(['id'], $config['detail_columns'])));
        $pdf = $pdfService->generateSystemPdf('pdf.finance-record-detail', [
            'tenant' => $tenant,
            'title' => $config['label'],
            'record' => $this->serialize($model, $columns),
            'labels' => collect($config['fields'])->map(fn ($field) => $field['label'])->all(),
        ], ['tenant' => $tenant, 'title' => $config['label']]);

        return $pdf->stream(str($config['label'])->slug().'-'.$model->getKey().'.pdf');
    }

    private function authorizeModule(Request $request, Tenant $tenant, string $module, string $ability): array
    {
        abort_unless((int) session('tenant_id') === (int) $tenant->id, 403);
        $config = FinanceModuleRegistry::get($module);
        abort_unless($request->user()->can($ability.'_'.$config['permission']), 403);

        return $config;
    }

    private function record(array $config, Tenant $tenant, int $id): Model
    {
        return $config['model']::query()->where('tenant_id', $tenant->id)->findOrFail($id);
    }

    private function serialize(Model $record, array $columns): array
    {
        return collect(array_unique($columns))->mapWithKeys(function (string $column) use ($record): array {
            $value = $record->getAttribute($column);
            if ($value instanceof \BackedEnum) {
                $value = $value->value;
            }
            if ($value instanceof \DateTimeInterface) {
                $value = $value->format('Y-m-d');
            }

            return [$column => $value];
        })->all();
    }
}
