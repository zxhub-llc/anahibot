<?php

namespace App\Services\Sri;

use App\Services\FiscalExtraction\Sri\KeycloakLoginForm;
use App\Services\FiscalExtraction\Sri\SriComprobantesForm;
use App\Services\FiscalExtraction\Sri\SriDocumentTableParser;
use App\Services\FiscalExtraction\Sri\SriJSecurityCheckForm;
use App\Services\FiscalExtraction\Sri\SriSessionInvalidatedException;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;

class SriPortalClient
{
    private const CACHE_PREFIX = 'sri-portal-session:';
    private const KEYCLOAK_AUTHORIZE_PATH = '/auth/realms/Internet/protocol/openid-connect/auth';
    private const KEYCLOAK_CLIENT_ID = 'app-sri-claves-angular';
    private const KEYCLOAK_REDIRECT_URI = 'https://srienlinea.sri.gob.ec/sri-en-linea//contribuyente/perfil';

    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';

    private const SSO_FALLBACK_MARKER = 'sri-en-linea/contribuyente/perfil';

    // idGrupo=55 / redireccion=57 identifican el módulo "Facturación Electrónica" dentro del
    // menú de tuportal-internet — confirmado contra captura real de navegador. Visitar este
    // endpoint marca esa app como "activa" en la sesión de tuportal-internet; sin este paso,
    // redireccion.jspa (llamado más adelante por GeneraToken.jsp/j_security_check) no sabe a
    // qué módulo devolver al usuario y cae al fallback de contribuyente/perfil.
    private const ACCEDER_APLICACION_PATH = '/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55';

    // Render targets de cada tabla en la respuesta parcial JSF. Confirmados contra el HTML
    // real de ambas pantallas.
    private const RENDER_TABLE_BY_DIRECTION = [
        'received' => 'frmPrincipal:tablaCompRecibidos',
        'issued' => 'frmPrincipal:tablaCompEmitidos',
    ];

    // "recibidos" tiene un único botón de consulta ("btnConsultarSinRe"). "emitidos" usa
    // "btnConsultar" — confirmado contra el HTML real de cada pantalla.
    private const SEARCH_BUTTON_BY_DIRECTION = [
        'received' => 'frmPrincipal:btnConsultarSinRe',
        'issued' => 'frmPrincipal:btnConsultar',
    ];

    public function __construct(private readonly SriDocumentTableParser $tableParser)
    {
    }

    private function client(CookieJar $jar): PendingRequest
    {
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        return Http::baseUrl($baseUrl)
            ->withOptions([
                'cookies' => $jar,
                'allow_redirects' => ['max' => 15, 'track_redirects' => true],
                'version' => 1.1,
            ])
            ->withHeaders([
                'User-Agent' => self::USER_AGENT,
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-EC,es;q=0.9',
                'Connection' => 'keep-alive',
            ])
            ->timeout(config('fiscal-extraction.sri.http_timeout'))
            ->retry(3, 1500, function ($exception) {
                return $exception instanceof ConnectionException;
            }, throw: false);
    }

    public function login(string $ruc, string $additionalId, string $password): void
    {
        $jar = new CookieJar();
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        $authorizeUrl = $baseUrl.self::KEYCLOAK_AUTHORIZE_PATH.'?'.http_build_query([
            'client_id' => self::KEYCLOAK_CLIENT_ID,
            'redirect_uri' => self::KEYCLOAK_REDIRECT_URI,
            'state' => (string) Str::uuid(),
            'nonce' => (string) Str::uuid(),
            'response_mode' => 'query',
            'response_type' => 'code',
            'scope' => 'openid',
        ]);

        $loginPage = $this->client($jar)->get($authorizeUrl);

        if ($loginPage->failed()) {
            throw new RuntimeException("No se pudo abrir el login de Keycloak del SRI (HTTP {$loginPage->status()}).");
        }

        $form = KeycloakLoginForm::fromHtml($loginPage->body());

        $username = $additionalId !== ''
            ? strtoupper($ruc).'[ad]'.$additionalId
            : strtoupper($ruc);

        $hashedPassword = md5($password).hash('sha512', $password);

        $response = $this->client($jar)
            ->asForm()
            ->post($form->actionUrl, array_merge($form->hiddenFields, [
                'usuario' => $ruc,
                'ciAdicional' => $additionalId,
                'username' => $username,
                'password' => $hashedPassword,
            ]));

        if (str_contains($response->body(), 'kc-feedback-text')) {
            preg_match('/kc-feedback-text">([^<]+)</', $response->body(), $matches);

            throw new RuntimeException(trim($matches[1] ?? 'El SRI rechazó las credenciales.'));
        }

        if ($response->failed()) {
            throw new RuntimeException("Fallo autenticando en el SRI para el RUC {$ruc} (HTTP {$response->status()}).");
        }

        // Confirmado contra captura real de navegador: sin este paso, el intercambio OAuth
        // cross-client (client_id="app-tuportal-internet") que GeneraToken.jsp necesita más
        // adelante falla e invalida la sesión.
        $warmup = $this->client($jar)->get($baseUrl.self::ACCEDER_APLICACION_PATH);

        if ($warmup->failed()) {
            throw new RuntimeException("Fallo al inicializar la sesión de tuportal-internet para el RUC {$ruc} (HTTP {$warmup->status()}).");
        }

        Cache::put(
            self::CACHE_PREFIX.$ruc,
            serialize($jar),
            now()->addMinutes(config('fiscal-extraction.sri.session_ttl_minutes')),
        );
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function fetchDocuments(string $ruc, string $direction): array
    {
        $jar = $this->sessionFor($ruc);
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        $path = $direction === 'received'
            ? config('fiscal-extraction.sri.portal_documents_received_path')
            : config('fiscal-extraction.sri.portal_documents_issued_path');

        $pageUrl = $baseUrl.$path;
        $menuContext = $direction === 'received'
            ? config('fiscal-extraction.sri.portal_menu_context_received', [])
            : config('fiscal-extraction.sri.portal_menu_context_issued', []);

        try {
            $page = $this->client($jar)->get($pageUrl, $menuContext);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "No se pudo conectar al listado de comprobantes [{$direction}] para el RUC {$ruc}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $finalUrl = (string) $page->effectiveUri();

        if (str_contains($finalUrl, self::SSO_FALLBACK_MARKER)) {
            throw new SriSessionInvalidatedException(
                "El SSO del SRI invalidó la sesión al intentar acceder a comprobantes [{$direction}] para el RUC {$ruc}. Requiere reautenticación."
            );
        }

        if ($page->status() === 503 && str_contains($page->body(), 'sri_mant_pro')) {
            throw new RuntimeException(
                "El portal del SRI está en mantenimiento (HTTP 503). Reintenta más tarde para el RUC {$ruc}."
            );
        }

        if ($page->failed()) {
            throw new RuntimeException("No se pudo abrir el listado de comprobantes [{$direction}] (HTTP {$page->status()}).");
        }

        // El SRI puede encadenar varias rondas de token + j_security_check (auto-submit) antes
        // de entregar la página real con el formulario "frmPrincipal". Se sigue la cadena
        // manualmente (Guzzle no ejecuta el onload=submit() de JS), con un tope de seguridad
        // para no entrar en loop infinito ante un cambio de comportamiento del SRI.
        $maxSecurityCheckHops = 5;

        for ($i = 0; $i < $maxSecurityCheckHops; $i++) {
            $securityForm = SriJSecurityCheckForm::fromHtmlOrNull($page->body(), $finalUrl);

            if ($securityForm === null) {
                break;
            }

            $refererUrl = $finalUrl;

            try {
                $securityResponse = Http::withOptions([
                    'cookies' => $jar,
                    'allow_redirects' => false,
                    'version' => 1.1,
                ])
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Referer' => $refererUrl,
                        'Origin' => 'https://srienlinea.sri.gob.ec',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                    ])
                    ->timeout(config('fiscal-extraction.sri.http_timeout'))
                    ->asForm()
                    ->post($securityForm->actionUrl, $securityForm->fields);
            } catch (ConnectionException $e) {
                throw new RuntimeException(
                    "Conexión reseteada en POST j_security_check [{$direction}] para el RUC {$ruc}: {$e->getMessage()}",
                    previous: $e,
                );
            }

            $nextUrl = $securityResponse->header('Location');

            if ($nextUrl === null) {
                $page = $securityResponse;
                $finalUrl = $securityForm->actionUrl;
                continue;
            }

            if (! str_starts_with($nextUrl, 'http')) {
                $parts = parse_url($securityForm->actionUrl);
                $nextUrl = "{$parts['scheme']}://{$parts['host']}{$nextUrl}";
            }

            try {
                $page = $this->client($jar)
                    ->withHeaders(['Referer' => $securityForm->actionUrl])
                    ->get($nextUrl);
            } catch (ConnectionException $e) {
                throw new RuntimeException(
                    "Conexión reseteada siguiendo redirect post-login [{$direction}] para el RUC {$ruc}: {$e->getMessage()}",
                    previous: $e,
                );
            }

            $finalUrl = (string) $page->effectiveUri();

            if (str_contains($finalUrl, self::SSO_FALLBACK_MARKER)) {
                throw new SriSessionInvalidatedException(
                    "El SSO del SRI invalidó la sesión durante j_security_check [{$direction}] para el RUC {$ruc}. Requiere reautenticación."
                );
            }

            if ($page->failed()) {
                throw new RuntimeException("Fallo siguiendo j_security_check [{$direction}] (HTTP {$page->status()}).");
            }
        }

        Log::info('SRI fetchDocuments redirect chain', [
            'ruc' => $ruc,
            'direction' => $direction,
            'final_url' => $finalUrl,
        ]);

        // "Emitidos" pasa por un paso extra: la página inicial es un MENÚ (menu.jsf) con un
        // form ("consultaDocumentoForm") que hay que enviar para llegar a la pantalla real
        // de consulta (recuperarComprobantes.jsf) — confirmado contra captura real de
        // navegador. "Recibidos" no tiene este paso intermedio.
        if ($direction === 'issued') {
            $menuForm = SriComprobantesForm::fromHtml($page->body(), $finalUrl, 'consultaDocumentoForm');

            $menuPostFields = array_merge($menuForm->fields, [
                'consultaDocumentoForm' => 'consultaDocumentoForm',
                'consultaDocumentoForm:j_idt22' => 'consultaDocumentoForm:j_idt22',
            ]);

            try {
                $page = $this->client($jar)->asForm()->post($menuForm->actionUrl, $menuPostFields);
            } catch (ConnectionException $e) {
                throw new RuntimeException(
                    "Conexión reseteada navegando al menú de emitidos para el RUC {$ruc}: {$e->getMessage()}",
                    previous: $e,
                );
            }

            if ($page->failed()) {
                throw new RuntimeException("Fallo al navegar al menú de comprobantes emitidos (HTTP {$page->status()}).");
            }

            $finalUrl = (string) $page->effectiveUri();
        }

        $form = SriComprobantesForm::fromHtml($page->body(), $finalUrl);
        $renderTable = self::RENDER_TABLE_BY_DIRECTION[$direction] ?? 'frmPrincipal:tablaCompRecibidos';
        $sourceButton = self::SEARCH_BUTTON_BY_DIRECTION[$direction] ?? 'frmPrincipal:btnConsultarSinRe';

        // Todos los campos del formulario real (año/mes/día/tipo/viewstate/opciones ya
        // precargados por el SRI) van tal cual; solo sobreescribimos/agregamos los
        // parámetros propios del POST ajax de PrimeFaces y el marcador de qué botón se pulsó.
        $postFields = array_merge($form->fields, [
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.source' => $sourceButton,
            'javax.faces.partial.execute' => '@all',
            'javax.faces.partial.render' => "frmPrincipal:panelListaComprobantes {$renderTable}",
            $sourceButton => $sourceButton,
        ]);

        try {
            $ajaxResponse = $this->client($jar)
                ->withHeaders([
                    'Faces-Request' => 'partial/ajax',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->asForm()
                ->post($form->actionUrl, $postFields);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "Conexión reseteada buscando comprobantes [{$direction}] para el RUC {$ruc}: {$e->getMessage()}",
                previous: $e,
            );
        }

        if ($ajaxResponse->failed()) {
            throw new RuntimeException("Fallo la búsqueda de comprobantes [{$direction}] para el RUC {$ruc} (HTTP {$ajaxResponse->status()}).");
        }

        return $this->tableParser->parse($ajaxResponse->body(), $renderTable);
    }

    public function downloadRide(string $ruc, string $accessKey): ?string
    {
        $jar = $this->sessionFor($ruc);

        $path = str_replace('{accessKey}', $accessKey, config('fiscal-extraction.sri.portal_ride_path'));
        $url = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/').$path;

        try {
            $response = $this->client($jar)->get($url);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Conexión reseteada descargando RIDE de {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 404) {
            return null;
        }

        if ($response->failed()) {
            throw new RuntimeException("No se pudo descargar el RIDE de {$accessKey} (HTTP {$response->status()}).");
        }

        return $response->body();
    }

    private function sessionFor(string $ruc): CookieJar
    {
        $serialized = Cache::get(self::CACHE_PREFIX.$ruc);

        if ($serialized === null) {
            throw new RuntimeException("No hay sesión SRI activa para el RUC {$ruc}. Llama a authenticate() primero.");
        }

        return unserialize($serialized);
    }
}