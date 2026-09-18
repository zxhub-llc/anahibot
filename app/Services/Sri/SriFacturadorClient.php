<?php

namespace App\Services\Sri;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

class SriFacturadorClient
{
    private const BASE_URL = 'https://facturadorsri.sri.gob.ec/portal-facturadorsri-internet';
    private const LOGIN_PATH = '/login.html';
    private const HOME_PATH = '/pages/inicio.html';
    private const SEARCH_PATH = '/pages/consultas/consultaComprobantesElectronicos.html';
    private const RIDE_PATH = '/reporte.pdf';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    private function client(CookieJar $jar): PendingRequest
    {
        return Http::baseUrl(self::BASE_URL)
            ->withOptions([
                'cookies' => $jar,
                'allow_redirects' => ['max' => 15, 'track_redirects' => true],
                'version' => 1.1,
                'force_ip_resolve' => 'v4',
                'connect_timeout' => 15,
            ])
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-EC,es;q=0.9',
            ])
            ->timeout(30);
    }

    /**
     * Login propio de facturadorsri (NO comparte sesión con srienlinea).
     * GET login.html (ViewState) -> POST ajax credenciales -> GET inicio.html.
     * El usuario es el RUC completo (13 dígitos), no cédula ni email.
     */
    public function login(string $ruc, string $password): CookieJar
    {
        $jar = new CookieJar();

        try {
            $loginPage = $this->client($jar)->get(self::LOGIN_PATH);
        } catch (ConnectionException $e) {
            throw new RuntimeException("No se pudo abrir el login del facturador SRI: {$e->getMessage()}", previous: $e);
        }

        if ($loginPage->failed()) {
            throw new RuntimeException("No se pudo abrir el login del facturador SRI (HTTP {$loginPage->status()}).");
        }

        if (! preg_match('/id="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $loginPage->body(), $m)) {
            throw new RuntimeException('No se encontró javax.faces.ViewState en la página de login del facturador SRI.');
        }
        $viewState = html_entity_decode($m[1]);

        try {
            $loginResponse = $this->client($jar)
                ->withHeaders([
                    'Faces-Request' => 'partial/ajax',
                    'X-Requested-With' => 'XMLHttpRequest',
                    'Accept' => 'application/xml, text/xml, */*; q=0.01',
                    'Origin' => 'https://facturadorsri.sri.gob.ec',
                ])
                ->asForm()
                ->post(self::LOGIN_PATH, [
                    'javax.faces.partial.ajax' => 'true',
                    'javax.faces.source' => 'loginForm:j_idt19',
                    'javax.faces.partial.execute' => '@all',
                    'loginForm:j_idt19' => 'loginForm:j_idt19',
                    'loginForm' => 'loginForm',
                    'loginForm:nombreusuario' => $ruc,
                    'loginForm:passwordInput' => $password,
                    'javax.faces.ViewState' => $viewState,
                ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada en el login del facturador SRI: {$e->getMessage()}", previous: $e);
        }

        if ($loginResponse->failed()) {
            throw new RuntimeException("Fallo el POST de login del facturador SRI (HTTP {$loginResponse->status()}).");
        }

        // Este GET es el que efectivamente deja la sesión adentro (pasa por 302s).
        try {
            $home = $this->client($jar)->get(self::HOME_PATH);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada validando la sesión del facturador SRI: {$e->getMessage()}", previous: $e);
        }

        if ($home->failed() || ! str_contains($home->body(), 'id_nombre_razon_social')) {
            Log::warning('SRI facturador: login no dejó sesión válida', [
                'ruc' => $ruc,
                'login_body_snippet' => substr($loginResponse->body(), 0, 300),
            ]);
            throw new RuntimeException('El login del facturador SRI no dejó una sesión válida (credenciales incorrectas o flujo cambió).');
        }

        return $jar;
    }

    public function search(CookieJar $jar, string $dateFrom, string $dateTo, string $tipoComprobante = 'FACTURA'): array
    {
        try {
            $page = $this->client($jar)->get(self::SEARCH_PATH);
        } catch (ConnectionException $e) {
            throw new RuntimeException("No se pudo abrir la consulta del facturador SRI: {$e->getMessage()}", previous: $e);
        }

        if ($page->failed()) {
            throw new RuntimeException("Fallo al abrir la consulta del facturador SRI (HTTP {$page->status()}).");
        }

        if (! preg_match('/id="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $page->body(), $matches)) {
            throw new RuntimeException('No se encontró javax.faces.ViewState en la página de consulta del facturador SRI — posible sesión inválida o página cambió.');
        }
        $viewState = html_entity_decode($matches[1]);

        $postFields = [
            'form' => 'form',
            'form:tabView:clnFechaInicio_input' => $dateFrom,
            'form:tabView:j_idt32_input' => $dateTo,
            'form:tabView:j_idt30' => '',
            'form:tabView:j_idt34_focus' => '',
            'form:tabView:j_idt34_input' => $tipoComprobante,
            'form:tabView_activeIndex' => '0',
            'form:tags' => '',
            'form:correosAnulacion' => '',
            'javax.faces.ViewState' => $viewState,
            'form:tabView:j_idt37' => 'form:tabView:j_idt37',
        ];

        try {
            $response = $this->client($jar)->asForm()->post(self::SEARCH_PATH, $postFields);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada buscando en el facturador SRI: {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            throw new RuntimeException("Fallo la búsqueda en el facturador SRI (HTTP {$response->status()}).");
        }

        return [
            'view_state' => $viewState,
            'rows' => $this->parseRows($response->body()),
        ];
    }

    private function parseRows(string $html): array
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors(false);
        $xpath = new \DOMXPath($document);

        $rows = [];
        foreach ($xpath->query("//tbody[@id='form:tabView:dataTableComprobantes_data']/tr") as $tr) {
            $cells = $xpath->query('./td', $tr);
            if ($cells->length < 6) {
                continue;
            }
            $claveNode = $xpath->query(".//span[contains(@id,':claveAcceso')]", $tr)->item(0);
            $rows[] = [
                'fecha' => trim($cells->item(0)->textContent),
                'tipo' => trim($cells->item(1)->textContent),
                'numero' => trim($cells->item(2)->textContent),
                'identificacion' => trim($cells->item(3)->textContent),
                'razon_social' => trim($cells->item(4)->textContent),
                'clave_acceso' => $claveNode !== null ? trim($claveNode->getAttribute('title')) : '',
                'valor_total' => trim($cells->item(6)->textContent),
            ];
        }

        return $rows;
    }

    public function downloadRide(CookieJar $jar, string $accessKey): ?string
    {
        try {
            $response = $this->client($jar)->get(self::RIDE_PATH, ['claveAcceso' => $accessKey]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada descargando RIDE de {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 404) {
            return null;
        }
        if ($response->failed()) {
            throw new RuntimeException("No se pudo descargar el RIDE de {$accessKey} (HTTP {$response->status()}).");
        }

        $contentType = $response->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'pdf')) {
            Log::warning('SRI facturador: RIDE respondió sin content-type pdf', [
                'accessKey' => $accessKey,
                'content_type' => $contentType,
            ]);
            return null;
        }

        return $response->body();
    }

    public function downloadXml(CookieJar $jar, string $viewState, int $rowIndex, string $dateFrom, string $dateTo, string $tipoComprobante = 'FACTURA'): ?string
    {
        $downloadFields = [
            'form' => 'form',
            'form:tabView:clnFechaInicio_input' => $dateFrom,
            'form:tabView:j_idt32_input' => $dateTo,
            'form:tabView:j_idt30' => '',
            'form:tabView:j_idt34_focus' => '',
            'form:tabView:j_idt34_input' => $tipoComprobante,
            'form:tabView_activeIndex' => '0',
            'form:tags' => '',
            'form:correosAnulacion' => '',
            'javax.faces.ViewState' => $viewState,
            "form:tabView:dataTableComprobantes:{$rowIndex}:j_idt64" => "form:tabView:dataTableComprobantes:{$rowIndex}:j_idt64",
        ];

        try {
            $response = $this->client($jar)->asForm()->post(self::SEARCH_PATH, $downloadFields);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada descargando XML (fila {$rowIndex}): {$e->getMessage()}", previous: $e);
        }

        if ($response->failed()) {
            return null;
        }

        $contentType = $response->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'xml')) {
            Log::warning('SRI facturador: descarga de XML respondió sin content-type xml', [
                'row_index' => $rowIndex,
                'content_type' => $contentType,
                'body_snippet' => substr($response->body(), 0, 300),
            ]);
            return null;
        }

        return $response->body();
    }
}