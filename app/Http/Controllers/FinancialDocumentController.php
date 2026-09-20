<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Models\BankAccount;
use App\Models\FinancialCheckInstrument;
use App\Services\FinancialCheckService;
use App\Services\FinancialDocumentIdentityService;
use App\Services\FinancialDocumentPaymentService;
use App\Services\FinancialDocumentPresenter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;
use Throwable;

class FinancialDocumentController extends Controller
{
    public function show(Request $request, string $publicId, FinancialDocumentIdentityService $identities, FinancialDocumentPresenter $presenter): View
    {
        $identity = $identities->find($publicId);
        if (! $identity) {
            return view('financial-documents.show', ['documentView' => null, 'accounts' => collect(), 'paymentMethods' => collect()]);
        }
        $documentView = $presenter->present($identity, $request->user());
        $accounts = $documentView['can_pay']
            ? BankAccount::withoutGlobalScopes()->where('tenant_id', $identity->tenant_id)->where('status', true)->orderBy('name')->get(['id', 'name'])
            : collect();
        $paymentMethods = collect(PaymentMethod::cases())->reject(fn (PaymentMethod $method) => $method === PaymentMethod::CHEQUE);

        return view('financial-documents.show', compact('documentView', 'accounts', 'paymentMethods'));
    }

    public function qr(string $publicId, FinancialDocumentIdentityService $identities)
    {
        $identity = $identities->find($publicId);
        abort_unless($identity, 404);

        return response($identities->qrSvg($identity), 200, [
            'Content-Type' => 'image/svg+xml; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400, immutable',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function pay(Request $request, string $publicId, FinancialDocumentIdentityService $identities, FinancialDocumentPresenter $presenter, FinancialDocumentPaymentService $payments): JsonResponse|RedirectResponse
    {
        $identity = $identities->find($publicId);
        abort_unless($identity && $request->user() && $presenter->canPay($request->user(), $identity), 403);
        $data = $request->validate($this->paymentRules($identity->tenant_id));

        return $this->execute($request, function () use ($payments, $identity, $data, $request): void {
            $payments->pay($identity, $data, $request->user());
        }, 'Pagamento registrado com segurança.');
    }

    public function issueCheck(Request $request, string $publicId, FinancialDocumentIdentityService $identities, FinancialDocumentPresenter $presenter, FinancialCheckService $checks): JsonResponse|RedirectResponse
    {
        $identity = $identities->find($publicId);
        abort_unless($identity && $request->user() && $presenter->canPay($request->user(), $identity), 403);
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'check_number' => ['required', 'string', 'max:80'],
            'bank_name' => ['required', 'string', 'max:120'],
            'account_reference' => ['nullable', 'string', 'max:120'],
            'issue_date' => ['required', 'date'],
            'expected_delivery_date' => ['nullable', 'date', 'after_or_equal:issue_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->execute($request, function () use ($checks, $identity, $data, $request): void {
            $checks->issue($identity, $data, $request->user());
        }, 'Cheque emitido. O comprovante só será liquidado após a confirmação da entrega.');
    }

    public function deliverCheck(Request $request, string $publicId, int $check, FinancialDocumentIdentityService $identities, FinancialDocumentPresenter $presenter, FinancialCheckService $checks): JsonResponse|RedirectResponse
    {
        $identity = $identities->find($publicId);
        abort_unless($identity && $request->user() && $presenter->canPay($request->user(), $identity), 403);
        $instrument = FinancialCheckInstrument::withoutGlobalScopes()
            ->where('tenant_id', $identity->tenant_id)->where('financial_document_identity_id', $identity->id)->findOrFail($check);
        $data = $request->validate([
            'operation_key' => ['required', 'uuid'],
            'payment_date' => ['required', 'date'],
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $identity->tenant_id)->where('status', true))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return $this->execute($request, function () use ($checks, $instrument, $data, $request): void {
            $checks->deliver($instrument, $data, $request->user());
        }, 'Entrega confirmada e pagamento liquidado.');
    }

    public function cancelCheck(Request $request, string $publicId, int $check, FinancialDocumentIdentityService $identities, FinancialDocumentPresenter $presenter, FinancialCheckService $checks): JsonResponse|RedirectResponse
    {
        $identity = $identities->find($publicId);
        abort_unless($identity && $request->user() && $presenter->canPay($request->user(), $identity), 403);
        $instrument = FinancialCheckInstrument::withoutGlobalScopes()
            ->where('tenant_id', $identity->tenant_id)->where('financial_document_identity_id', $identity->id)->findOrFail($check);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $this->execute($request, function () use ($checks, $instrument, $data, $request): void {
            $checks->cancel($instrument, $data['reason'], $request->user());
        }, 'Cheque cancelado sem alterar o saldo do comprovante.');
    }

    private function paymentRules(int $tenantId): array
    {
        return [
            'operation_key' => ['required', 'uuid'],
            'amount' => ['required', 'numeric', 'min:0.01'],
            'payment_date' => ['required', 'date'],
            'payment_method' => ['required', Rule::in(collect(PaymentMethod::cases())->reject(fn ($method) => $method === PaymentMethod::CHEQUE)->pluck('value')->all())],
            'bank_account_id' => ['required', 'integer', Rule::exists('bank_accounts', 'id')->where(fn ($query) => $query->where('tenant_id', $tenantId)->where('status', true))],
            'document_number' => ['nullable', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    private function execute(Request $request, callable $callback, string $message): JsonResponse|RedirectResponse
    {
        try {
            $callback();
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['payment' => $exception->getMessage()]);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['payment' => 'Não foi possível concluir a operação financeira. Nenhum pagamento parcial foi confirmado.']);
        }

        if ($request->expectsJson()) {
            return response()->json(['ok' => true, 'message' => $message, 'url' => $request->fullUrl()]);
        }

        return back()->with('success', $message);
    }
}
