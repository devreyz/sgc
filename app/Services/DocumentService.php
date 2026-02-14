<?php

namespace App\Services;

use App\Models\Document;
use App\Models\DocumentTemplate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use SimpleSoftwareIO\QrCode\Facades\QrCode;

class DocumentService
{
    /**
     * Generate a unique verification hash for a document
     */
    public function generateVerificationHash(array $data): string
    {
        $stringToHash = json_encode($data) . now()->timestamp . Str::random(10);
        return hash('sha256', $stringToHash);
    }

    /**
     * Generate QR Code SVG for document verification
     */
    public function generateQrCode(string $hash): string
    {
        $url = route('document.verify', ['hash' => $hash]);
        return QrCode::size(150)
            ->style('round')
            ->eye('circle')
            ->generate($url);
    }

    /**
     * Generate QR Code PNG base64 for document verification
     */
    public function generateQrCodeBase64(string $hash): string
    {
        $url = route('document.verify', ['hash' => $hash]);
        return base64_encode(
            QrCode::format('png')
                ->size(150)
                ->style('round')
                ->eye('circle')
                ->generate($url)
        );
    }

    /**
     * Replace variables in template content
     */
    public function replaceVariables(string $content, array $variables): string
    {
        foreach ($variables as $key => $value) {
            // Convert value to string if it's not already
            $stringValue = is_array($value) ? json_encode($value) : (string) $value;
            $content = str_replace('{{'.$key.'}}', $stringValue, $content);
        }

        return $content;
    }

    /**
     * Generate document from template with QR code
     */
    public function generateDocument(DocumentTemplate $template, array $variables, ?int $userId = null): array
    {
        // Generate verification hash
        $verificationData = [
            'template_id' => $template->id,
            'variables' => $variables,
            'generated_at' => now()->toIso8601String(),
            'user_id' => $userId,
        ];

        $hash = $this->generateVerificationHash($verificationData);

        // Generate QR code SVG for embedding in HTML
        $qrCodeSvg = $this->generateQrCode($hash);
        
        // Add QR code to variables
        $variables['qrcode'] = $qrCodeSvg;
        $variables['verification_hash'] = $hash;
        $variables['verification_url'] = route('document.verify', ['hash' => $hash]);

        // Replace variables in content
        $content = $this->replaceVariables($template->content, $variables);

        return [
            'content' => $content,
            'hash' => $hash,
            'qr_code' => $qrCodeSvg,
            'verification_data' => $verificationData,
        ];
    }

    /**
     * Format date for templates
     */
    public function formatDate(\DateTime|string|null $date, string $format = 'd/m/Y'): string
    {
        if (!$date) {
            return '-';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        return $date->format($format);
    }

    /**
     * Format date to Brazilian Portuguese
     */
    public function formatDateExtensive(\DateTime|string|null $date): string
    {
        if (!$date) {
            return '-';
        }

        if (is_string($date)) {
            $date = new \DateTime($date);
        }

        $months = [
            1 => 'janeiro', 2 => 'fevereiro', 3 => 'março', 4 => 'abril',
            5 => 'maio', 6 => 'junho', 7 => 'julho', 8 => 'agosto',
            9 => 'setembro', 10 => 'outubro', 11 => 'novembro', 12 => 'dezembro'
        ];

        $day = $date->format('d');
        $month = $months[(int) $date->format('m')];
        $year = $date->format('Y');

        return "$day de $month de $year";
    }

    /**
     * Convert number to Brazilian currency format
     */
    public function formatMoney(float $value): string
    {
        return number_format($value, 2, ',', '.');
    }

    /**
     * Convert number to extensive form in Portuguese
     */
    public function numberToWords(float $value): string
    {
        $formatter = new \NumberFormatter('pt_BR', \NumberFormatter::SPELLOUT);
        
        $reais = floor($value);
        $centavos = round(($value - $reais) * 100);

        $text = $formatter->format($reais);
        $text = ucfirst($text) . ($reais == 1 ? ' real' : ' reais');

        if ($centavos > 0) {
            $centavosText = $formatter->format($centavos);
            $text .= ' e ' . $centavosText . ($centavos == 1 ? ' centavo' : ' centavos');
        }

        return $text;
    }

    /**
     * Get document template variables
     */
    public function getCooperativaVariables(): array
    {
        // TODO: Get from settings/config
        return [
            'cooperativa.nome' => 'Cooperativa Exemplo',
            'cooperativa.cnpj' => '00.000.000/0000-00',
            'cooperativa.endereco' => 'Rua Exemplo, 123 - Centro',
            'cooperativa.cidade' => 'Campo Grande',
            'cooperativa.estado' => 'MS',
            'cooperativa.telefone' => '(67) 3333-4444',
        ];
    }

    /**
     * Get date variables
     */
    public function getDateVariables(): array
    {
        $now = now();

        return [
            'data.hoje' => $this->formatDate($now),
            'data.hoje_extenso' => $this->formatDateExtensive($now),
            'data.mes_atual' => $now->translatedFormat('F'),
            'data.ano_atual' => $now->format('Y'),
        ];
    }
}
