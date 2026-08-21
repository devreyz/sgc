<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class PublicFormController extends Controller
{
    public function contact(Request $request): JsonResponse
    {
        $data = $this->validateSubmission($request, true);

        Mail::raw($this->messageBody('Novo contato comercial', $data), function ($message) use ($data): void {
            $message->to(config('legal.contact_email'))
                ->replyTo($data['email'], $data['name'])
                ->subject('[SGC] Novo contato comercial');
        });

        return response()->json([
            'message' => 'Recebemos sua mensagem. Em breve entraremos em contato.',
        ], 202);
    }

    public function privacy(Request $request): JsonResponse
    {
        $data = $this->validateSubmission($request);

        Mail::raw($this->messageBody($data['request_type'], $data), function ($message) use ($data): void {
            $message->to(config('legal.privacy_email'))
                ->replyTo($data['email'], $data['name'])
                ->subject('[SGC] '.$data['request_type']);
        });

        Mail::raw(
            "Recebemos sua solicitação. Nossa equipe analisará o pedido e responderá por este e-mail.\n\nTipo de solicitação: {$data['request_type']}",
            function ($message) use ($data): void {
                $message->to($data['email'])
                    ->subject('[SGC] Confirmação de recebimento');
            }
        );

        return response()->json([
            'message' => 'Solicitação recebida. Enviaremos a confirmação para o e-mail informado.',
        ], 202);
    }

    /** @return array{name: string, email: string, organization: ?string, phone: ?string, message: ?string, request_type: string} */
    private function validateSubmission(Request $request, bool $isContact = false): array
    {
        abort_if(filled($request->input('website')), 422);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc,dns', 'max:255'],
            'organization' => ['nullable', 'string', 'max:160'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => [$isContact ? 'required' : 'nullable', 'string', 'max:3000'],
            'request_type' => [$isContact ? 'nullable' : 'required', 'string', 'max:160'],
        ]);

        $data['request_type'] = $isContact ? 'Contato comercial' : $data['request_type'];

        return $data;
    }

    /** @param array{name: string, email: string, organization: ?string, phone: ?string, message: ?string, request_type: string} $data */
    private function messageBody(string $heading, array $data): string
    {
        return implode(PHP_EOL, [
            $heading,
            '',
            'Nome: '.$data['name'],
            'E-mail: '.$data['email'],
            'Organização: '.($data['organization'] ?? 'Não informada'),
            'Telefone: '.($data['phone'] ?? 'Não informado'),
            '',
            'Mensagem:',
            $data['message'] ?? 'Não informada',
        ]);
    }
}
