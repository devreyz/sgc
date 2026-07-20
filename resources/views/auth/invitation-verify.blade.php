@extends('layouts.security-public')

@section('title', 'Validar acesso')

@section('heading')
    <div class="mx-auto max-w-md text-center">
        <div
            class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl
                   bg-green-50 text-green-600 ring-1 ring-green-100"
            aria-hidden="true"
        >
            <svg
                class="h-8 w-8"
                viewBox="0 0 24 24"
                fill="none"
                stroke="currentColor"
                stroke-width="2"
                stroke-linecap="round"
                stroke-linejoin="round"
            >
                <rect x="3" y="11" width="18" height="10" rx="2"></rect>
                <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                <path d="M12 15v2"></path>
            </svg>
        </div>

        <h1 class="text-2xl font-bold tracking-tight text-slate-950 sm:text-3xl">
            Validar acesso
        </h1>

    </div>
@endsection

@section('content')
    <div class="mx-auto w-full max-w-md">
        <form id="code-form" class="space-y-6" novalidate>
            @csrf

            {{-- Valor final enviado para o backend --}}
            <input
                type="hidden"
                id="access-code"
                name="code"
                value=""
            >

            <fieldset>
                <legend class="mb-3 block text-center text-sm font-semibold text-slate-700">
                    Código de acesso
                </legend>

                <div
                    id="otp-container"
                    class="grid grid-cols-6 gap-2 sm:gap-3"
                    role="group"
                    aria-label="Código de acesso com seis dígitos"
                >
                    @for ($index = 0; $index < 6; $index++)
                        <input
                            type="text"
                            class="otp-input aspect-square min-w-0 rounded-xl border border-slate-300
                                   bg-white text-center text-xl font-bold text-slate-950 shadow-sm
                                   outline-none transition
                                   hover:border-slate-400
                                   focus:border-green-500 focus:ring-4 focus:ring-green-100
                                   disabled:cursor-not-allowed disabled:bg-slate-100
                                   sm:rounded-2xl sm:text-2xl"
                            data-index="{{ $index }}"
                            maxlength="1"
                            inputmode="numeric"
                            pattern="[0-9]"
                            autocomplete="{{ $index === 0 ? 'one-time-code' : 'off' }}"
                            aria-label="Dígito {{ $index + 1 }} de 6"
                            @if ($index === 0) autofocus @endif
                        >
                    @endfor
                </div>

            </fieldset>

            <button
                class="flex min-h-12 w-full items-center justify-center gap-2 rounded-xl
                       bg-green-600 px-5 py-3 text-sm font-semibold text-white shadow-sm
                       transition
                       hover:bg-green-700
                       focus:outline-none focus:ring-4 focus:ring-green-200
                       disabled:cursor-not-allowed disabled:opacity-60
                       sm:rounded-2xl sm:text-base"
                id="submit-code"
                type="submit"
            >
                <svg
                    id="loading-spinner"
                    class="hidden h-5 w-5 animate-spin"
                    viewBox="0 0 24 24"
                    fill="none"
                    aria-hidden="true"
                >
                    <circle
                        class="opacity-25"
                        cx="12"
                        cy="12"
                        r="9"
                        stroke="currentColor"
                        stroke-width="3"
                    ></circle>

                    <path
                        class="opacity-90"
                        fill="currentColor"
                        d="M21 12a9 9 0 0 0-9-9v3a6 6 0 0 1 6 6h3Z"
                    ></path>
                </svg>

                <span id="submit-text">Continuar</span>
            </button>
        </form>

        <div
            id="status"
            class="{{ session('error') ? '' : 'hidden' }} mt-4 rounded-xl border px-4 py-3 text-sm leading-5
                   {{ session('error')
                        ? 'border-red-200 bg-red-50 text-red-700'
                        : 'border-slate-200 bg-slate-50 text-slate-700' }}"
            role="status"
            aria-live="polite"
            aria-atomic="true"
        >
            {{ session('error') }}
        </div>

    </div>
@endsection

@push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const form = document.getElementById('code-form');
            const button = document.getElementById('submit-code');
            const buttonText = document.getElementById('submit-text');
            const spinner = document.getElementById('loading-spinner');
            const status = document.getElementById('status');
            const hiddenCodeInput = document.getElementById('access-code');
            const otpContainer = document.getElementById('otp-container');
            const otpInputs = Array.from(
                document.querySelectorAll('.otp-input')
            );

            let isSubmitting = false;

            const sanitizeCode = (value) => {
                return String(value ?? '')
                    .replace(/\D/g, '')
                    .slice(0, 6);
            };

            const getCode = () => {
                return otpInputs.map((input) => input.value).join('');
            };

            const syncHiddenInput = () => {
                hiddenCodeInput.value = getCode();
            };

            const focusInput = (index) => {
                const input = otpInputs[index];

                if (!input) {
                    return;
                }

                input.focus();
                input.select();
            };

            const clearStatus = () => {
                status.textContent = '';
                status.className = 'hidden';
            };

            const showStatus = (message, type = 'default') => {
                const baseClasses =
                    'mt-4 rounded-xl border px-4 py-3 text-sm leading-5';

                const typeClasses = {
                    default: 'border-slate-200 bg-slate-50 text-slate-700',
                    error: 'border-red-200 bg-red-50 text-red-700',
                    success: 'border-emerald-200 bg-emerald-50 text-emerald-700',
                };

                status.className = `${baseClasses} ${typeClasses[type]}`;
                status.textContent = message;
            };

            const setLoading = (loading) => {
                isSubmitting = loading;
                button.disabled = loading;

                otpInputs.forEach((input) => {
                    input.disabled = loading;
                });

                spinner.classList.toggle('hidden', !loading);
                buttonText.textContent = loading
                    ? 'Validando...'
                    : 'Continuar';
            };

            const fillOtpInputs = (value, startIndex = 0) => {
                const digits = sanitizeCode(value);

                if (!digits) {
                    return;
                }

                digits.split('').forEach((digit, offset) => {
                    const targetInput = otpInputs[startIndex + offset];

                    if (targetInput) {
                        targetInput.value = digit;
                    }
                });

                syncHiddenInput();

                const nextIndex = Math.min(
                    startIndex + digits.length,
                    otpInputs.length - 1
                );

                const firstEmptyIndex = otpInputs.findIndex(
                    (input) => input.value === ''
                );

                if (firstEmptyIndex >= 0) {
                    focusInput(firstEmptyIndex);
                } else {
                    focusInput(nextIndex);
                    button.focus();
                }
            };

            otpInputs.forEach((input, index) => {
                input.addEventListener('focus', () => {
                    input.select();
                });

                input.addEventListener('input', (event) => {
                    clearStatus();

                    const digits = sanitizeCode(event.target.value);

                    if (digits.length > 1) {
                        event.target.value = '';
                        fillOtpInputs(digits, index);
                        return;
                    }

                    event.target.value = digits;
                    syncHiddenInput();

                    if (digits && index < otpInputs.length - 1) {
                        focusInput(index + 1);
                    }
                });

                input.addEventListener('keydown', (event) => {
                    if (event.key === 'Backspace') {
                        if (input.value) {
                            input.value = '';
                            syncHiddenInput();
                            return;
                        }

                        if (index > 0) {
                            event.preventDefault();
                            otpInputs[index - 1].value = '';
                            syncHiddenInput();
                            focusInput(index - 1);
                        }

                        return;
                    }

                    if (event.key === 'Delete') {
                        input.value = '';
                        syncHiddenInput();
                        return;
                    }

                    if (event.key === 'ArrowLeft' && index > 0) {
                        event.preventDefault();
                        focusInput(index - 1);
                        return;
                    }

                    if (
                        event.key === 'ArrowRight' &&
                        index < otpInputs.length - 1
                    ) {
                        event.preventDefault();
                        focusInput(index + 1);
                        return;
                    }

                    if (
                        event.key.length === 1 &&
                        !/^\d$/.test(event.key)
                    ) {
                        event.preventDefault();
                    }
                });

                input.addEventListener('paste', (event) => {
                    event.preventDefault();

                    const pastedCode = sanitizeCode(
                        event.clipboardData?.getData('text')
                    );

                    if (!pastedCode) {
                        return;
                    }

                    otpInputs.forEach((otpInput) => {
                        otpInput.value = '';
                    });

                    fillOtpInputs(pastedCode, 0);
                });
            });

            otpContainer.addEventListener('click', () => {
                const firstEmptyIndex = otpInputs.findIndex(
                    (input) => input.value === ''
                );

                if (firstEmptyIndex >= 0) {
                    focusInput(firstEmptyIndex);
                }
            });

            form.addEventListener('submit', async (event) => {
                event.preventDefault();

                if (isSubmitting) {
                    return;
                }

                const accessCode = sanitizeCode(getCode());
                syncHiddenInput();

                if (accessCode.length !== 6) {
                    showStatus(
                        'Digite os seis dígitos do código de acesso.',
                        'error'
                    );

                    const firstEmptyIndex = otpInputs.findIndex(
                        (input) => input.value === ''
                    );

                    focusInput(firstEmptyIndex >= 0 ? firstEmptyIndex : 0);
                    return;
                }

                setLoading(true);
                showStatus('Validando seu código de acesso...');

                try {
                    const csrfToken = document.querySelector(
                        'meta[name="csrf-token"]'
                    )?.getAttribute('content');

                    if (!csrfToken) {
                        throw new Error(
                            'Token de segurança não encontrado.'
                        );
                    }

                    const response = await fetch(
                        @json(route('access.invitation.code')),
                        {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Accept': 'application/json',
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': csrfToken,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({
                                code: accessCode,
                            }),
                        }
                    );

                    const contentType =
                        response.headers.get('content-type') || '';

                    const data = contentType.includes('application/json')
                        ? await response.json()
                        : {};

                    if (!response.ok) {
                        throw new Error(
                            data.message ||
                            'Não foi possível validar este acesso.'
                        );
                    }

                    if (!data.redirect) {
                        throw new Error(
                            'O endereço de redirecionamento não foi informado.'
                        );
                    }

                    showStatus(
                        'Código validado. Redirecionando...',
                        'success'
                    );

                    window.location.assign(data.redirect);
                } catch (error) {
                    showStatus(
                        error instanceof Error && error.message
                            ? error.message
                            : 'Não foi possível validar este acesso.',
                        'error'
                    );

                    setLoading(false);

                    otpInputs.forEach((input) => {
                        input.value = '';
                    });

                    syncHiddenInput();
                    focusInput(0);
                }
            });

            // Caso o navegador preencha automaticamente o primeiro campo
            // usando autocomplete="one-time-code".
            window.setTimeout(() => {
                const autoFilledValue = sanitizeCode(otpInputs[0].value);

                if (autoFilledValue.length > 1) {
                    otpInputs.forEach((input) => {
                        input.value = '';
                    });

                    fillOtpInputs(autoFilledValue);
                }
            }, 300);
        });
    </script>
@endpush
