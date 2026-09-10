<?php

namespace App\Services\FiscalExtraction;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Models\SriCredential;
use App\Services\FiscalExtraction\Sri\SriDocumentTableParser;
use App\Services\FiscalExtraction\Sri\SriSessionInvalidatedException;
use App\Services\Sri\SriPortalClient;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SoapClient;
use SoapFault;

class SriFiscalExtractionDriver implements FiscalExtractionDriver
{
    private const DOCUMENT_TYPE_BY_CODE = [
        '1' => 'factura',
        '2' => 'liquidacion_compra',
        '3' => 'nota_credito',
        '4' => 'nota_debito',
        '5' => 'guia_remision',
        '6' => 'retencion',
    ];

    public function __construct(private readonly SriPortalClient $portalClient)
    {
    }

    public function authenticate(string $companyRuc): void
    {
        $credential = SriCredential::query()->where('company_ruc', $companyRuc)->first();

        if ($credential === null) {
            throw new RuntimeException("There are no SRI credentials configured for the RUC {$companyRuc}. Configure them in /sri-credentials.");
        }

        $this->portalClient->login($companyRuc, $credential->username, $credential->password);
    }

    public function extract(string $companyRuc): array
    {
        try {
            return $this->extractOnce($companyRuc);
        } catch (SriSessionInvalidatedException $e) {
            
            Log::warning('SRI session invalidated by SSO, reauthenticating and retrying once', [
                'ruc' => $companyRuc,
                'message' => $e->getMessage(),
            ]);

            $this->authenticate($companyRuc);

            return $this->extractOnce($companyRuc);
        }
    }

    private function extractOnce(string $companyRuc): array
    {
        $received = array_map(
            fn (array $row) => $this->toReference($row, FiscalDocumentDirection::Received),
            $this->portalClient->fetchDocuments($companyRuc, 'received'),
        );

        $issued = array_map(
            fn (array $row) => $this->toReference($row, FiscalDocumentDirection::Issued),
            $this->portalClient->fetchDocuments($companyRuc, 'issued'),
        );

        return [...$received, ...$issued];
    }

    public function downloadXml(string $companyRuc, string $accessKey): string
    {
        try {
            $client = new SoapClient(config('fiscal-extraction.sri.soap_authorization_wsdl'), [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => config('fiscal-extraction.sri.http_timeout'),
            ]);

            $response = $client->autorizacionComprobante([
                'claveAccesoComprobante' => $accessKey,
            ]);

            $autorizaciones = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion ?? null;

            if ($autorizaciones === null) {
                throw new RuntimeException("The SRI did not return authorizations for the access key {$accessKey}.");
            }

            $autorizacion = is_array($autorizaciones) ? $autorizaciones[0] : $autorizaciones;

            if (($autorizacion->estado ?? null) !== 'AUTORIZADO') {
                throw new RuntimeException("The receipt {$accessKey} is not AUTHORIZED in the SRI (status: {$autorizacion->estado}).");
            }

            return (string) $autorizacion->comprobante;
        } catch (SoapFault $fault) {
            throw new RuntimeException("SOAP failure consulting authorization of {$accessKey}: {$fault->getMessage()}", previous: $fault);
        }
    }

    public function downloadRide(string $companyRuc, string $accessKey): ?string
    {
        return $this->portalClient->downloadRide($companyRuc, $accessKey);
    }

    /**
     * @param array<int, string> $row fila cruda devuelta por SriDocumentTableParser.
     */
    private function toReference(array $row, FiscalDocumentDirection $direction): FiscalDocumentReference
    {
        $accessKey = trim($row[SriDocumentTableParser::COL_ACCESS_KEY] ?? '');

        if (strlen($accessKey) !== 49) {
            throw new RuntimeException("Invalid access key detected in the portal list: '{$accessKey}'.");
        }

        $counterpartRaw = $row[SriDocumentTableParser::COL_COUNTERPART] ?? '';
        $counterpartRuc = trim(explode(' ', $counterpartRaw, 2)[0] ?? '');

        return new FiscalDocumentReference(
            accessKey: $accessKey,
            type: $this->typeFromAccessKey($accessKey),
            direction: $direction,
            issuerRuc: $direction === FiscalDocumentDirection::Received ? $counterpartRuc : null,
            receiverRuc: $direction === FiscalDocumentDirection::Issued ? $counterpartRuc : null,
            isVoided: false,
        );
    }

    private function typeFromAccessKey(string $accessKey): FiscalDocumentType
    {
        $code = substr($accessKey, 8, 2);

        return match ($code) {
            '01' => FiscalDocumentType::Invoice,
            '04' => FiscalDocumentType::CreditNote,
            '05' => FiscalDocumentType::DebitNote,
            '07' => FiscalDocumentType::Retention,
            '03' => FiscalDocumentType::PurchaseSettlement,
            '06' => FiscalDocumentType::RemissionGuide,
            default => throw new RuntimeException("Unknown voucher type code in access key: {$code}"),
        };
    }
}