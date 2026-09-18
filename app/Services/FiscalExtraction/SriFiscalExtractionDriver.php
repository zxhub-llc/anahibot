<?php

namespace App\Services\FiscalExtraction;

use App\Contracts\FiscalExtraction\FiscalExtractionDriver;
use App\Data\FiscalExtraction\FiscalDocumentReference;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Models\SriCredential;
use App\Services\FiscalExtraction\Sri\SriDocumentTableParser;
use App\Services\FiscalExtraction\Sri\SriSessionInvalidatedException;
use App\Services\FiscalExtraction\Sri\SriAuthorizationPendingException;
use App\Services\Sri\SriPortalClient;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use SoapClient;
use SoapFault;
use Throwable;

class SriFiscalExtractionDriver implements FiscalExtractionDriver
{
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

    public function extract(string $companyRuc, CarbonInterface $dateFrom, CarbonInterface $dateTo): array
    {
        $maxAttempts = 3;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                return $this->extractOnce($companyRuc, $dateFrom, $dateTo);
            } catch (SriSessionInvalidatedException $e) {
                if ($attempt === $maxAttempts) {
                    throw $e;
                }
                Log::warning("SRI session invalidated by SSO (attempt {$attempt}/{$maxAttempts}), reauthenticating and retrying", [
                    'ruc' => $companyRuc,
                    'message' => $e->getMessage(),
                ]);
                $this->authenticate($companyRuc);
            }
        }

        throw new \RuntimeException("No se pudo completar la extracción del SRI para el RUC {$companyRuc} tras {$maxAttempts} intentos.");
    }

    private function extractOnce(string $companyRuc, CarbonInterface $dateFrom, CarbonInterface $dateTo): array
    {
        $received = array_map(
            fn (array $row) => $this->toReference($row, FiscalDocumentDirection::Received),
            $this->portalClient->fetchReceivedDocuments($companyRuc, $dateFrom, $dateTo),
        );

        $issued = array_map(
            fn (array $row) => $this->toReference($row, FiscalDocumentDirection::Issued),
            $this->portalClient->fetchIssuedDocuments($companyRuc, $dateFrom, $dateTo),
        );

        return [...$received, ...$issued];
    }

    public function fetchTaxpayer(string $companyRuc): array
    {
        return $this->portalClient->fetchTaxpayer($companyRuc);
    }

    public function downloadXml(string $companyRuc, string $accessKey): string
    {
        $primaryWsdl = $this->authorizationWsdlFor($accessKey);
        $alternateWsdl = $primaryWsdl === config('fiscal-extraction.sri.soap_authorization_wsdl_prod')
            ? config('fiscal-extraction.sri.soap_authorization_wsdl_test')
            : config('fiscal-extraction.sri.soap_authorization_wsdl_prod');

        try {
            return $this->querySoapAuthorization($accessKey, $primaryWsdl);
        } catch (Throwable $e) {
            Log::info("Primary SOAP WSDL query failed for access key {$accessKey}, retrying with alternate WSDL", [
                'error' => $e->getMessage(),
            ]);
        }

        try {
            return $this->querySoapAuthorization($accessKey, $alternateWsdl);
        } catch (Throwable $e) {
            Log::info("SOAP authorization failed on both WSDLs for {$accessKey}, trying portal fallback");
        }

        $xml = $this->portalClient->downloadXmlFromPortal($companyRuc, $accessKey);
        if ($xml !== null) {
            return $xml;
        }

        throw new SriAuthorizationPendingException(
            "El SRI aún no indexa el comprobante {$accessKey} (WS SOAP x2 + portal). Reintentar más tarde."
        );
    }

    private function querySoapAuthorization(string $accessKey, string $wsdl): string
    {
        try {
            $context = stream_context_create([
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'cafile' => ini_get('openssl.cafile'),
                ],
                'http' => [
                    'protocol_version' => 1.1,
                    'timeout' => config('fiscal-extraction.sri.http_timeout'),
                ],
            ]);

            $client = new SoapClient($wsdl, [
                'trace' => true,
                'exceptions' => true,
                'connection_timeout' => config('fiscal-extraction.sri.http_timeout'),
                'stream_context' => $context,
            ]);

            $response = $client->autorizacionComprobante([
                'claveAccesoComprobante' => $accessKey,
            ]);

            $autorizaciones = $response->RespuestaAutorizacionComprobante->autorizaciones->autorizacion ?? null;

            if ($autorizaciones === null) {
                $numeroComprobantes = $response->RespuestaAutorizacionComprobante->numeroComprobantes ?? null;

                throw new RuntimeException(
                    "El SRI no devolvió autorizaciones para la clave {$accessKey} en el WSDL {$wsdl} (numeroComprobantes: {$numeroComprobantes})."
                );
            }

            $autorizacion = is_array($autorizaciones) ? $autorizaciones[0] : $autorizaciones;

            if (($autorizacion->estado ?? null) !== 'AUTORIZADO') {
                throw new RuntimeException("El comprobante {$accessKey} no está AUTORIZADO en el SRI (estado: {$autorizacion->estado}).");
            }

            return (string) $autorizacion->comprobante;
        } catch (SoapFault $fault) {
            throw new RuntimeException("Fallo SOAP consultando autorización de {$accessKey}: {$fault->getMessage()}", previous: $fault);
        }
    }
    
    private function authorizationWsdlFor(string $accessKey): string
    {
        $ambiente = substr($accessKey, 23, 1);

        return match ($ambiente) {
            '1' => config('fiscal-extraction.sri.soap_authorization_wsdl_test'),
            '2' => config('fiscal-extraction.sri.soap_authorization_wsdl_prod'),
            default => throw new RuntimeException("Dígito de ambiente desconocido ('{$ambiente}') en la clave {$accessKey}."),
        };
    }

    public function downloadRide(string $companyRuc, string $accessKey, string $direction): ?string
    {
        return $this->portalClient->downloadRide($companyRuc, $accessKey, $direction);
    }

    private function toReference(array $row, FiscalDocumentDirection $direction): FiscalDocumentReference
    {
        $accessKeyColumn = $direction === FiscalDocumentDirection::Issued
            ? SriDocumentTableParser::COL_ACCESS_KEY_ISSUED
            : SriDocumentTableParser::COL_ACCESS_KEY;

        $accessKey = trim($row[$accessKeyColumn] ?? '');

        if (strlen($accessKey) !== 49) {
            Log::error('SRI invalid access key row', [
                'direction' => $direction->value,
                'row' => $row,
            ]);

            throw new RuntimeException("Invalid access key detected in the portal list: '{$accessKey}'.");
        }

        $counterpartRaw = $direction === FiscalDocumentDirection::Received
            ? ($row[SriDocumentTableParser::COL_COUNTERPART] ?? '')
            : '';
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