<?php

namespace App\Http\Controllers;

use App\Models\DocumentVerification;
use App\Services\DocumentService;
use Illuminate\Http\Request;

class DocumentVerificationController extends Controller
{
    public function __construct(
        protected DocumentService $documentService
    ) {}

    /**
     * Publicly verify a document by hash
     */
    public function verify(string $hash, Request $request)
    {
        $verification = DocumentVerification::where('hash', $hash)->first();

        if (!$verification) {
            return view('document.verification-result', [
                'status' => 'not_found',
                'message' => 'Documento não encontrado em nossa base de dados.',
                'verification' => null,
            ]);
        }

        // Mark as verified and track IP
        $verification->markAsVerified($request->ip());

        return view('document.verification-result', [
            'status' => 'verified',
            'message' => 'Documento autêntico! Este documento foi gerado por nossa cooperativa.',
            'verification' => $verification,
        ]);
    }

    /**
     * Show QR code for a document hash
     */
    public function qrcode(string $hash)
    {
        $qrCode = $this->documentService->generateQrCode($hash);

        return response($qrCode)
            ->header('Content-Type', 'image/svg+xml');
    }
}
