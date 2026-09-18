<?php

namespace App\Services\Sri;

use App\Services\FiscalExtraction\Sri\KeycloakLoginForm;
use App\Services\FiscalExtraction\Sri\SriComprobantesForm;
use App\Services\FiscalExtraction\Sri\SriDocumentTableParser;
use App\Services\FiscalExtraction\Sri\SriJSecurityCheckForm;
use App\Services\FiscalExtraction\Sri\SriSessionInvalidatedException;
use App\Services\FiscalExtraction\Sri\SriSearchSessionExpiredException;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SriPortalClient
{
    private const CACHE_PREFIX = 'sri-portal-session:';
    private const KEYCLOAK_AUTHORIZE_PATH = '/auth/realms/Internet/protocol/openid-connect/auth';
    private const KEYCLOAK_CLIENT_ID = 'app-sri-claves-angular';
    private const KEYCLOAK_REDIRECT_URI = 'https://srienlinea.sri.gob.ec/sri-en-linea//contribuyente/perfil';
    private const USER_AGENT = 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36';
    private const SSO_FALLBACK_MARKER = 'sri-en-linea/contribuyente/perfil';
    private const ACCEDER_APLICACION_PATH = '/tuportal-internet/accederAplicacion.jspa?redireccion=57&idGrupo=55';
    private const KEYCLOAK_TOKEN_PATH = '/auth/realms/Internet/protocol/openid-connect/token';
    private const TAXPAYER_PROFILE_PATH = '/sri-catastro-sujeto-servicio-internet/rest/privado/contribuyente/perfil';
    private const CONTACT_INFO_PATH = '/sri-claves-servicio-internet/rest/privado/medioContacto/obtenerMediosContacto';
    private const ESTABLISHMENTS_PATH = '/sri-catastro-sujeto-servicio-internet/rest/Establecimiento/consultarPorNumeroRuc';
    private const TOKEN_CACHE_PREFIX = 'sri-portal-token:';

    private const SSO_INVALIDATION_MARKER = 'SSO del SRI invalidó';
    private const JBOSS_BEAN_CONSTRUCTION_FAILURE_MARKER = 'JBAS011048';

    private const RENDER_TABLE_BY_DIRECTION = [
        'received' => 'frmPrincipal:tablaCompRecibidos',
        'issued' => 'frmPrincipal:tablaCompEmitidos',
    ];

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
        $timeout = (int) config('fiscal-extraction.sri.http_timeout', 30);

        return Http::baseUrl($baseUrl)
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
                'Connection' => 'close',
            ])
            ->timeout($timeout);
    }

    public function login(string $ruc, string $additionalId, string $password): void
    {
        $maxAttempts = 3;
        $lastException = null;
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $this->loginOnce($ruc, $additionalId, $password);
                return;
            } catch (ConnectionException|RuntimeException $e) {
                $lastException = $e;
                if (str_contains($e->getMessage(), 'rechazó las credenciales')) {
                    throw $e;
                }
                Log::warning("SRI Keycloak login attempt {$attempt}/{$maxAttempts} failed, retrying...", [
                    'ruc' => $ruc,
                    'error' => $e->getMessage(),
                ]);
                if ($attempt < $maxAttempts) {
                    usleep(1500000);
                }
            }
        }
        throw $lastException ?? new RuntimeException("No se pudo iniciar sesión en el SRI para el RUC {$ruc}.");
    }

    private function loginOnce(string $ruc, string $additionalId, string $password): void
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

        try {
            $loginPage = $this->client($jar)->get($authorizeUrl);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Timeout o error de conexión abriendo login de Keycloak del SRI: {$e->getMessage()}", previous: $e);
        }

        if ($loginPage->failed()) {
            throw new RuntimeException("No se pudo abrir el login de Keycloak del SRI (HTTP {$loginPage->status()}).");
        }

        $form = KeycloakLoginForm::fromHtml($loginPage->body());
        $username = $additionalId !== '' ? strtoupper($ruc).'[ad]'.$additionalId : strtoupper($ruc);
        $hashedPassword = md5($password).hash('sha512', $password);

        try {
            $credentialsResponse = Http::baseUrl($baseUrl)
                ->withOptions([
                    'cookies' => $jar,
                    'allow_redirects' => false,
                    'version' => 1.1,
                    'force_ip_resolve' => 'v4',
                ])
                ->withHeaders([
                    'User-Agent' => self::USER_AGENT,
                    'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                    'Accept-Language' => 'es-EC,es;q=0.9',
                ])
                ->timeout(30)
                ->asForm()
                ->post($form->actionUrl, array_merge($form->hiddenFields, [
                    'usuario' => $ruc,
                    'ciAdicional' => $additionalId,
                    'username' => $username,
                    'password' => $hashedPassword,
                ]));
        } catch (ConnectionException $e) {
            throw new RuntimeException("Timeout o error de conexión enviando credenciales a Keycloak: {$e->getMessage()}", previous: $e);
        }

        if (str_contains($credentialsResponse->body(), 'kc-feedback-text')) {
            preg_match('/kc-feedback-text">([^<]+)</', $credentialsResponse->body(), $matches);
            throw new RuntimeException(trim($matches[1] ?? 'El SRI rechazó las credenciales.'));
        }

        $location = $credentialsResponse->header('Location');
        if (! $credentialsResponse->redirect() || $location === null) {
            throw new RuntimeException("Keycloak no redirigió tras las credenciales para el RUC {$ruc} (HTTP {$credentialsResponse->status()}).");
        }

        parse_str((string) parse_url($location, PHP_URL_QUERY), $query);
        $authorizationCode = $query['code'] ?? null;

        if ($authorizationCode === null) {
            throw new RuntimeException("No se encontró 'code' en el redirect de Keycloak para el RUC {$ruc}. URL: {$location}");
        }

        try {
            $tokenResponse = $this->client($jar)->asForm()->post(self::KEYCLOAK_TOKEN_PATH, [
                'code' => $authorizationCode,
                'grant_type' => 'authorization_code',
                'client_id' => self::KEYCLOAK_CLIENT_ID,
                'redirect_uri' => self::KEYCLOAK_REDIRECT_URI,
            ]);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Timeout intercambiando el code por access_token: {$e->getMessage()}", previous: $e);
        }

        if ($tokenResponse->failed()) {
            throw new RuntimeException("Fallo intercambiando code por access_token (HTTP {$tokenResponse->status()}): {$tokenResponse->body()}");
        }

        $tokenData = $tokenResponse->json();
        if (! isset($tokenData['access_token'])) {
            throw new RuntimeException('La respuesta del token endpoint no trae access_token.');
        }

        Cache::put(
            self::TOKEN_CACHE_PREFIX.$ruc,
            $tokenData['access_token'],
            now()->addSeconds(max((int) ($tokenData['expires_in'] ?? 280) - 20, 30)),
        );

        try {
            $this->client($jar)->get($location);
        } catch (ConnectionException $e) {
        }

        try {
            $warmup = $this->client($jar)->get($baseUrl.self::ACCEDER_APLICACION_PATH);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Timeout o error de conexión inicializando sesión de tuportal-internet: {$e->getMessage()}", previous: $e);
        }

        if ($warmup->failed()) {
            throw new RuntimeException("Fallo al inicializar la sesión de tuportal-internet para el RUC {$ruc} (HTTP {$warmup->status()}).");
        }

        Cache::put(
            self::CACHE_PREFIX.$ruc,
            serialize($jar),
            now()->addMinutes(config('fiscal-extraction.sri.session_ttl_minutes')),
        );
    }

    public function fetchTaxpayer(string $ruc): array
    {
        $accessToken = Cache::get(self::TOKEN_CACHE_PREFIX.$ruc);
        if ($accessToken === null) {
            throw new RuntimeException("No hay access_token en caché para el RUC {$ruc}. Llama a login() primero.");
        }

        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->get(self::TAXPAYER_PROFILE_PATH);
        } catch (ConnectionException $e) {
            throw new RuntimeException("Timeout consultando el perfil del contribuyente {$ruc}: {$e->getMessage()}", previous: $e);
        }

        if ($response->status() === 401) {
            throw new RuntimeException("El access_token para el RUC {$ruc} expiró. Reautentica.");
        }
        if ($response->failed()) {
            throw new RuntimeException("Fallo consultando el perfil del contribuyente {$ruc} (HTTP {$response->status()}).");
        }

        $data = $response->json();
        if (($data['codigo'] ?? null) !== '200') {
            throw new RuntimeException("El SRI no devolvió perfil válido para {$ruc}: ".($data['mensaje'] ?? 'sin mensaje'));
        }

        $contactInfo = $this->fetchContactInfo($ruc, $accessToken);
        $establishment = $this->fetchMainEstablishment($ruc, $accessToken);

        return [
            'ruc' => $data['identificacion'] ?? $ruc,
            'business_name' => $data['nombreCompleto'] ?? null,
            'legal_representative' => $data['representanteLegal']['nombre'] ?? null,
            'legal_representative_id' => $data['representanteLegal']['identificacion'] ?? null,
            'trade_name' => $establishment['trade_name'],
            'email' => $contactInfo['email'],
            'phone' => $contactInfo['phone'],
            'address' => $establishment['address'],
        ];
    }
    
    private function fetchContactInfo(string $ruc, string $accessToken): array
    {
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->get(self::CONTACT_INFO_PATH);
        } catch (ConnectionException $e) {
            Log::warning('SRI: fallo consultando medios de contacto, se omite', [
                'ruc' => $ruc,
                'error' => $e->getMessage(),
            ]);
            return ['email' => null, 'phone' => null];
        }

        if ($response->failed()) {
            Log::warning('SRI: medios de contacto respondió error, se omite', [
                'ruc' => $ruc,
                'status' => $response->status(),
            ]);
            return ['email' => null, 'phone' => null];
        }

        $objeto = $response->json('objeto') ?? [];

        return [
            'email' => $objeto['correo'] ?? null,
            'phone' => $objeto['celular'] ?? null,
        ];
    }
    
    private function fetchMainEstablishment(string $ruc, string $accessToken): array
    {
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');

        try {
            $response = Http::baseUrl($baseUrl)
                ->withToken($accessToken)
                ->withHeaders(['Accept' => 'application/json', 'User-Agent' => self::USER_AGENT])
                ->timeout(20)
                ->get(self::ESTABLISHMENTS_PATH, ['numeroRuc' => $ruc]);
        } catch (ConnectionException $e) {
            Log::warning('SRI: fallo consultando establecimientos, se omite', [
                'ruc' => $ruc,
                'error' => $e->getMessage(),
            ]);
            return ['trade_name' => null, 'address' => null];
        }

        if ($response->failed()) {
            Log::warning('SRI: establecimientos respondió error, se omite', [
                'ruc' => $ruc,
                'status' => $response->status(),
            ]);
            return ['trade_name' => null, 'address' => null];
        }

        $establishments = $response->json() ?? [];
        if ($establishments === []) {
            return ['trade_name' => null, 'address' => null];
        }

        $matriz = collect($establishments)->firstWhere('matriz', 'SI') ?? $establishments[0];

        return [
            'trade_name' => $matriz['nombreFantasiaComercial'] ?? null,
            'address' => $matriz['direccionCompleta'] ?? null,
        ];
    }

    private function openSearchPage(CookieJar $jar, string $ruc, string $direction): SriComprobantesForm
    {
        $maxFullAttempts = 2;
        $lastException = null;

        for ($fullAttempt = 1; $fullAttempt <= $maxFullAttempts; $fullAttempt++) {
            try {
                return $this->openSearchPageAttempt($jar, $ruc, $direction);
            } catch (SriSessionInvalidatedException $e) {
                if (str_contains($e->getMessage(), self::SSO_INVALIDATION_MARKER)) {
                    throw $e;
                }

                $lastException = $e;
                if ($fullAttempt === $maxFullAttempts) {
                    Log::error('SRI openSearchPage agotó los reintentos completos', [
                        'ruc' => $ruc,
                        'direction' => $direction,
                        'error' => $e->getMessage(),
                    ]);
                    break;
                }

                Log::info("SRI j_security_check persistente, reiniciando navegación completa desde el menú (intento {$fullAttempt}/{$maxFullAttempts})", [
                    'ruc' => $ruc,
                    'direction' => $direction,
                    'error' => $e->getMessage(),
                ]);
                usleep(1_000_000);
            }
        }

        throw $lastException;
    }

    private function openSearchPageAttempt(CookieJar $jar, string $ruc, string $direction): SriComprobantesForm
    {
        $baseUrl = rtrim(config('fiscal-extraction.sri.portal_base_url'), '/');
        
        $menuUrl = $baseUrl.config('fiscal-extraction.sri.portal_documents_issued_path');
        $menuContext = $direction === 'received'
            ? config('fiscal-extraction.sri.portal_menu_context_received', [])
            : config('fiscal-extraction.sri.portal_menu_context_issued', []);

        try {
            $page = $this->client($jar)->get($menuUrl, $menuContext);
        } catch (ConnectionException $e) {
            throw new RuntimeException(
                "No se pudo conectar al menú de comprobantes [{$direction}] para el RUC {$ruc}: {$e->getMessage()}",
                previous: $e,
            );
        }

        $finalUrl = (string) $page->effectiveUri();

        if (str_contains($finalUrl, self::SSO_FALLBACK_MARKER)) {
            throw new SriSessionInvalidatedException(
                self::SSO_INVALIDATION_MARKER." al intentar acceder a comprobantes [{$direction}] para el RUC {$ruc}. Requiere reautenticación."
            );
        }

        if ($page->status() === 503 && str_contains($page->body(), 'sri_mant_pro')) {
            throw new RuntimeException(
                "El portal del SRI está en mantenimiento (HTTP 503). Reintenta más tarde para el RUC {$ruc}."
            );
        }

        if ($page->failed()) {
            throw new RuntimeException("No se pudo abrir el menú de comprobantes [{$direction}] (HTTP {$page->status()}).");
        }

        $maxSecurityCheckHops = 5;
        for ($i = 0; $i < $maxSecurityCheckHops; $i++) {
            $securityForm = SriJSecurityCheckForm::fromHtmlOrNull($page->body(), $finalUrl);
            if ($securityForm === null) {
                break;
            }

            $refererUrl = $finalUrl;
            try {
                $securityResponse = Http::baseUrl($baseUrl)
                    ->withOptions([
                        'cookies' => $jar,
                        'allow_redirects' => false,
                        'version' => 1.1,
                        'force_ip_resolve' => 'v4',
                    ])
                    ->withHeaders([
                        'User-Agent' => self::USER_AGENT,
                        'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                        'Accept-Language' => 'es-EC,es;q=0.9',
                        'Referer' => $refererUrl,
                        'Origin' => 'https://srienlinea.sri.gob.ec',
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Connection' => 'close',
                    ])
                    ->timeout(30)
                    ->asForm()
                    ->post($securityForm->actionUrl, $securityForm->fields);
            } catch (ConnectionException $e) {
                throw new SriSessionInvalidatedException(
                    "El portal JSF no respondió al POST j_security_check [{$direction}] para el RUC {$ruc}. Forzando reinicio de sesión: {$e->getMessage()}",
                    previous: $e
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

            $maxRedirectRetries = 3;
            for ($retry = 1; $retry <= $maxRedirectRetries; $retry++) {
                try {
                    $page = Http::baseUrl($baseUrl)
                        ->withOptions([
                            'cookies' => $jar,
                            'allow_redirects' => ['max' => 15, 'track_redirects' => true],
                            'version' => 1.1,
                            'force_ip_resolve' => 'v4',
                        ])
                        ->withHeaders([
                            'User-Agent' => self::USER_AGENT,
                            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                            'Accept-Language' => 'es-EC,es;q=0.9',
                            'Referer' => $securityForm->actionUrl,
                            'Connection' => 'close',
                        ])
                        ->timeout(30)
                        ->get($nextUrl);
                } catch (ConnectionException $e) {
                    if ($retry === $maxRedirectRetries) {
                        throw new SriSessionInvalidatedException(
                            "El portal JSF no respondió al redirect post-login [{$direction}] para el RUC {$ruc} tras {$maxRedirectRetries} intentos. Forzando reinicio de sesión: {$e->getMessage()}",
                            previous: $e
                        );
                    }
                    usleep(800_000 * $retry);
                    continue;
                }

                $finalUrl = (string) $page->effectiveUri();

                if (str_contains($finalUrl, self::SSO_FALLBACK_MARKER)) {
                    throw new SriSessionInvalidatedException(
                        self::SSO_INVALIDATION_MARKER." durante j_security_check [{$direction}] para el RUC {$ruc}. Requiere reautenticación."
                    );
                }

                if ($page->failed()) {
                    
                    if (str_contains($page->body(), self::JBOSS_BEAN_CONSTRUCTION_FAILURE_MARKER)) {
                        throw new SriSessionInvalidatedException(
                            self::JBOSS_BEAN_CONSTRUCTION_FAILURE_MARKER." en j_security_check [{$direction}] para el RUC {$ruc}. Requiere reinicio de navegación."
                        );
                    }

                    if ($retry === $maxRedirectRetries) {
                        Log::error('SRI j_security_check failed response body', [
                            'ruc' => $ruc,
                            'direction' => $direction,
                            'status' => $page->status(),
                            'body' => substr($page->body(), 0, 2000),
                        ]);
                        throw new SriSessionInvalidatedException(
                            "Fallo siguiendo j_security_check [{$direction}] tras {$maxRedirectRetries} intentos (HTTP {$page->status()})."
                        );
                    }
                    Log::warning("SRI j_security_check redirect devolvió HTTP {$page->status()}, reintentando ({$retry}/{$maxRedirectRetries})", [
                        'ruc' => $ruc,
                        'direction' => $direction,
                    ]);
                    usleep(800_000 * $retry);
                    continue;
                }

                break;
            }
        }

        if ($direction === 'received') {
            $receivedUrl = $baseUrl.config('fiscal-extraction.sri.portal_documents_received_path');
            try {
                $page = $this->client($jar)->get($receivedUrl, $menuContext);
            } catch (ConnectionException $e) {
                throw new RuntimeException("Error conectando a comprobantes recibidos para RUC {$ruc}: {$e->getMessage()}", previous: $e);
            }
            if ($page->failed()) {
                throw new RuntimeException("Fallo al abrir comprobantes recibidos (HTTP {$page->status()}).");
            }
            $finalUrl = (string) $page->effectiveUri();
        } elseif ($direction === 'issued') {
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

        return SriComprobantesForm::fromHtml($page->body(), $finalUrl);
    }

    private function searchOnce(CookieJar $jar, SriComprobantesForm $form, string $direction, array $extraFields): array 
    {
        $renderTable = self::RENDER_TABLE_BY_DIRECTION[$direction] ?? 'frmPrincipal:tablaCompRecibidos';
        $sourceButton = self::SEARCH_BUTTON_BY_DIRECTION[$direction] ?? 'frmPrincipal:btnConsultarSinRe';

        $postFields = array_merge($form->fields, $extraFields, [
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
                "Conexión reseteada buscando comprobantes [{$direction}]: {$e->getMessage()}",
                previous: $e,
            );
        }

        if (! str_contains($ajaxResponse->body(), '<partial-response>')) {
            throw new SriSearchSessionExpiredException(
                "La sesión de búsqueda [{$direction}] expiró a mitad del loop (respuesta sin partial-response)."
            );
        }

        if ($ajaxResponse->failed()) {
            throw new RuntimeException("Fallo la búsqueda de comprobantes [{$direction}] (HTTP {$ajaxResponse->status()}).");
        }

        return $this->tableParser->parse($ajaxResponse->body(), $renderTable);
    }
    
    public function fetchReceivedDocuments(string $ruc, CarbonInterface $dateFrom, CarbonInterface $dateTo): array
    {
        $jar = $this->sessionFor($ruc);
        $form = $this->openSearchPage($jar, $ruc, 'received');

        $cursor = CarbonImmutable::parse($dateFrom)->startOfMonth();
        $end = CarbonImmutable::parse($dateTo)->startOfMonth();

        $all = [];
        $maxMonths = 60;
        $i = 0;

        while ($cursor->lte($end) && $i < $maxMonths) {
            Log::info('SRI fetchReceivedDocuments month', ['ruc' => $ruc, 'year' => $cursor->year, 'month' => $cursor->month]);

            $extraFields = [
                'frmPrincipal:ano' => (string) $cursor->year,
                'frmPrincipal:mes' => (string) $cursor->month,
                'frmPrincipal:dia' => '0',
            ];

            try {
                $rows = $this->searchOnce($jar, $form, 'received', $extraFields);
                $all = [...$all, ...$rows];
            } catch (SriSearchSessionExpiredException $e) {
                Log::warning('SRI received search session expired mid-loop, reopening and retrying once', [
                    'ruc' => $ruc,
                    'year' => $cursor->year,
                    'month' => $cursor->month,
                ]);
                try {
                    $form = $this->openSearchPage($jar, $ruc, 'received');
                    $rows = $this->searchOnce($jar, $form, 'received', $extraFields);
                    $all = [...$all, ...$rows];
                } catch (SriSessionInvalidatedException $inner) {
                    throw $inner;
                } catch (Throwable $inner) {
                    Log::warning('SRI fetchReceivedDocuments month failed after reopen, skipping', [
                        'ruc' => $ruc,
                        'year' => $cursor->year,
                        'month' => $cursor->month,
                        'error' => $inner->getMessage(),
                    ]);
                }
            } catch (SriSessionInvalidatedException $e) {
                throw $e;
            } catch (Throwable $e) {
                Log::warning('SRI fetchReceivedDocuments month failed, skipping', [
                    'ruc' => $ruc,
                    'year' => $cursor->year,
                    'month' => $cursor->month,
                    'error' => $e->getMessage(),
                ]);
            }

            $cursor = $cursor->addMonth();
            $i++;
        }

        return $all;
    }

    public function fetchIssuedDocuments(string $ruc, CarbonInterface $dateFrom, CarbonInterface $dateTo): array
    {
        $jar = $this->sessionFor($ruc);
        $form = $this->openSearchPage($jar, $ruc, 'issued');

        $yesterday = CarbonImmutable::now()->subDay()->startOfDay();
        $from = CarbonImmutable::parse($dateFrom)->startOfDay();
        $to = CarbonImmutable::parse($dateTo)->startOfDay();
        $effectiveTo = $to->lessThan($yesterday) ? $to : $yesterday;

        $all = [];
        $cursor = $from;
        $maxDays = 730;
        $i = 0;

        while ($cursor->lte($effectiveTo) && $i < $maxDays) {
            Log::info('SRI fetchIssuedDocuments day', ['ruc' => $ruc, 'date' => $cursor->format('d/m/Y')]);

            $extraFields = [
                'frmPrincipal:calendarFechaDesde_input' => $cursor->format('d/m/Y'),
            ];

            try {
                $rows = $this->searchOnce($jar, $form, 'issued', $extraFields);
                $all = [...$all, ...$rows];
            } catch (SriSearchSessionExpiredException $e) {
                Log::warning('SRI issued search session expired mid-loop, reopening and retrying once', [
                    'ruc' => $ruc,
                    'date' => $cursor->format('d/m/Y'),
                ]);
                try {
                    $form = $this->openSearchPage($jar, $ruc, 'issued');
                    $rows = $this->searchOnce($jar, $form, 'issued', $extraFields);
                    $all = [...$all, ...$rows];
                } catch (SriSessionInvalidatedException $inner) {
                    throw $inner;
                } catch (Throwable $inner) {
                    Log::warning('SRI fetchIssuedDocuments day failed after reopen, skipping', [
                        'ruc' => $ruc,
                        'date' => $cursor->format('d/m/Y'),
                        'error' => $inner->getMessage(),
                    ]);
                }
            } catch (SriSessionInvalidatedException $e) {
                throw $e;
            } catch (Throwable $e) {
                Log::warning('SRI fetchIssuedDocuments day failed, skipping', [
                    'ruc' => $ruc,
                    'date' => $cursor->format('d/m/Y'),
                    'error' => $e->getMessage(),
                ]);
            }

            $cursor = $cursor->addDay();
            $i++;
        }

        return $all;
    }

    public function downloadXmlFromPortal(string $ruc, string $accessKey): ?string
    {
        $lock = Cache::lock("sri-portal-session-lock:{$ruc}", 60);

        return $lock->block(30, function () use ($ruc, $accessKey) {
            return $this->downloadXmlFromPortalLocked($ruc, $accessKey);
        });
    }

    private function downloadXmlFromPortalLocked(string $ruc, string $accessKey): ?string
    {
        $jar = $this->sessionFor($ruc);
        $form = $this->openSearchPage($jar, $ruc, 'received');

        $emissionDate = CarbonImmutable::createFromFormat('dmY', substr($accessKey, 0, 8));
        $tipoComprobante = $this->tipoComprobanteFilterFor(substr($accessKey, 8, 2));

        $baseSearchFields = [
            'frmPrincipal:opciones' => 'ruc',
            'frmPrincipal:ano' => (string) $emissionDate->year,
            'frmPrincipal:mes' => (string) $emissionDate->month,
            'frmPrincipal:dia' => (string) $emissionDate->day,
            'frmPrincipal:cmbTipoComprobante' => $tipoComprobante,
        ];

        $searchFields = array_merge($form->fields, $baseSearchFields, [
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.source' => 'frmPrincipal:btnConsultarSinRe',
            'javax.faces.partial.execute' => '@all',
            'javax.faces.partial.render' => 'frmPrincipal:panelListaComprobantes frmPrincipal:tablaCompRecibidos',
            'frmPrincipal:btnConsultarSinRe' => 'frmPrincipal:btnConsultarSinRe',
        ]);

        unset($searchFields['frmPrincipal:txtParametro']);

        try {
            $searchResponse = $this->client($jar)
                ->withHeaders([
                    'Faces-Request' => 'partial/ajax',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->asForm()
                ->post($form->actionUrl, $searchFields);
        } catch (ConnectionException $e) {
            Log::error('SRI portal XML fallback: search connection failed', ['accessKey' => $accessKey, 'error' => $e->getMessage()]);
            throw new RuntimeException("Conexión reseteada buscando por RUC/fecha para {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        Log::info('SRI portal XML fallback: search response', [
            'accessKey' => $accessKey,
            'status' => $searchResponse->status(),
            'has_partial_response' => str_contains($searchResponse->body(), '<partial-response>'),
            'body_snippet' => substr($searchResponse->body(), 0, 1500),
        ]);

        if ($searchResponse->failed() || ! str_contains($searchResponse->body(), '<partial-response>')) {
            return null;
        }

        $rows = $this->tableParser->parse($searchResponse->body(), 'frmPrincipal:tablaCompRecibidos');
        Log::info('SRI portal XML fallback: parsed rows', ['accessKey' => $accessKey, 'row_count' => count($rows)]);

        if ($rows === []) {
            return null;
        }

        $rowIndex = null;
        foreach ($rows as $index => $row) {
            if (trim($row[SriDocumentTableParser::COL_ACCESS_KEY] ?? '') === $accessKey) {
                $rowIndex = $index;
                break;
            }
        }

        if ($rowIndex === null) {
            Log::warning('SRI portal XML fallback: access key not found among rows for RUC/date/type search', [
                'accessKey' => $accessKey,
                'row_count' => count($rows),
            ]);
            return null;
        }

        if (! preg_match('/<update id="javax\.faces\.ViewState">\s*<!\[CDATA\[(.*?)\]\]>/s', $searchResponse->body(), $matches)) {
            Log::warning('SRI portal XML fallback: ViewState not found in search response', ['accessKey' => $accessKey]);
            return null;
        }
        $updatedViewState = $matches[1];

        $linkId = "frmPrincipal:tablaCompRecibidos:{$rowIndex}:lnkXml";
        $downloadFields = array_merge($form->fields, $baseSearchFields, [
            'javax.faces.ViewState' => $updatedViewState,
            $linkId => $linkId,
        ]);
        unset($downloadFields['frmPrincipal:txtParametro']);

        try {
            $downloadResponse = $this->client($jar)->asForm()->post($form->actionUrl, $downloadFields);
        } catch (ConnectionException $e) {
            Log::error('SRI portal XML fallback: download connection failed', ['accessKey' => $accessKey, 'error' => $e->getMessage()]);
            throw new RuntimeException("Conexión reseteada descargando XML de {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        Log::info('SRI portal XML fallback: download response', [
            'accessKey' => $accessKey,
            'status' => $downloadResponse->status(),
            'content_type' => $downloadResponse->header('Content-Type'),
            'content_disposition' => $downloadResponse->header('Content-Disposition'),
            'body_snippet' => substr($downloadResponse->body(), 0, 500),
        ]);

        if ($downloadResponse->failed()) {
            return null;
        }

        $contentType = $downloadResponse->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'xml')) {
            return null;
        }

        return $this->unwrapAutorizacionEnvelope($downloadResponse->body(), $accessKey);
    }

    private function unwrapAutorizacionEnvelope(string $rawXml, string $accessKey): ?string
    {
        try {
            $envelope = new \SimpleXMLElement($rawXml);
        } catch (\Exception $e) {
            Log::warning('SRI portal fallback: envelope XML no parseable, devolviendo crudo', [
                'accessKey' => $accessKey,
                'error' => $e->getMessage(),
            ]);
            return $rawXml;
        }

        if (! isset($envelope->comprobante)) {
            return $rawXml;
        }

        if ((string) ($envelope->estado ?? '') !== 'AUTORIZADO') {
            Log::warning('SRI portal fallback: comprobante no AUTORIZADO en el sobre descargado', [
                'accessKey' => $accessKey,
                'estado' => (string) ($envelope->estado ?? ''),
            ]);
            return null;
        }

        return (string) $envelope->comprobante;
    }

    public function downloadRide(string $ruc, string $accessKey, string $direction): ?string
    {
        $lock = Cache::lock("sri-portal-session-lock:{$ruc}", 60);

        return $lock->block(30, function () use ($ruc, $accessKey, $direction) {
            return $this->downloadRideLocked($ruc, $accessKey, $direction);
        });
    }

    private function downloadRideLocked(string $ruc, string $accessKey, string $direction): ?string
    {
        $jar = $this->sessionFor($ruc);
        $form = $this->openSearchPage($jar, $ruc, $direction);

        $emissionDate = CarbonImmutable::createFromFormat('dmY', substr($accessKey, 0, 8));
        $tipoComprobante = $this->tipoComprobanteFilterFor(substr($accessKey, 8, 2), $direction);

        $renderTable = self::RENDER_TABLE_BY_DIRECTION[$direction] ?? 'frmPrincipal:tablaCompRecibidos';
        $sourceButton = self::SEARCH_BUTTON_BY_DIRECTION[$direction] ?? 'frmPrincipal:btnConsultarSinRe';

        $baseSearchFields = $direction === 'received'
            ? [
                'frmPrincipal:opciones' => 'ruc',
                'frmPrincipal:ano' => (string) $emissionDate->year,
                'frmPrincipal:mes' => (string) $emissionDate->month,
                'frmPrincipal:dia' => (string) $emissionDate->day,
                'frmPrincipal:cmbTipoComprobante' => $tipoComprobante,
            ]
            : [
                'frmPrincipal:opciones' => 'ruc',
                'frmPrincipal:calendarFechaDesde_input' => $emissionDate->format('d/m/Y'),
                'frmPrincipal:cmbEstadoAutorizacion' => 'AUT',
                'frmPrincipal:cmbTipoComprobante' => $tipoComprobante,
                'frmPrincipal:cmbEstablecimiento' => '',
                'frmPrincipal:txtPuntoEmision' => '',
            ];

        $searchFields = array_merge($form->fields, $baseSearchFields, [
            'javax.faces.partial.ajax' => 'true',
            'javax.faces.source' => $sourceButton,
            'javax.faces.partial.execute' => '@all',
            'javax.faces.partial.render' => "frmPrincipal:panelListaComprobantes {$renderTable}",
            $sourceButton => $sourceButton,
        ]);
        unset($searchFields['frmPrincipal:txtParametro']);

        try {
            $searchResponse = $this->client($jar)
                ->withHeaders([
                    'Faces-Request' => 'partial/ajax',
                    'X-Requested-With' => 'XMLHttpRequest',
                ])
                ->asForm()
                ->post($form->actionUrl, $searchFields);
        } catch (ConnectionException $e) {
            Log::error('SRI portal RIDE fallback: search connection failed', [
                'accessKey' => $accessKey,
                'direction' => $direction,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException("Conexión reseteada buscando RIDE de {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        Log::info('SRI portal RIDE fallback: search response', [
            'accessKey' => $accessKey,
            'direction' => $direction,
            'status' => $searchResponse->status(),
            'has_partial_response' => str_contains($searchResponse->body(), '<partial-response>'),
        ]);

        if ($searchResponse->failed() || ! str_contains($searchResponse->body(), '<partial-response>')) {
            return null;
        }

        $rows = $this->tableParser->parse($searchResponse->body(), $renderTable);
        Log::info('SRI portal RIDE fallback: parsed rows', [
            'accessKey' => $accessKey,
            'direction' => $direction,
            'row_count' => count($rows),
        ]);

        if ($rows === []) {
            return null;
        }

        $accessKeyColumn = $direction === 'received'
            ? SriDocumentTableParser::COL_ACCESS_KEY
            : SriDocumentTableParser::COL_ACCESS_KEY_ISSUED;

        $rowIndex = null;
        foreach ($rows as $index => $row) {
            if (trim($row[$accessKeyColumn] ?? '') === $accessKey) {
                $rowIndex = $index;
                break;
            }
        }

        if ($rowIndex === null) {
            Log::warning('SRI portal RIDE fallback: access key not found among rows', [
                'accessKey' => $accessKey,
                'direction' => $direction,
                'row_count' => count($rows),
            ]);
            return null;
        }

        if (! preg_match('/<update id="javax\.faces\.ViewState">\s*<!\[CDATA\[(.*?)\]\]>/s', $searchResponse->body(), $matches)) {
            Log::warning('SRI portal RIDE fallback: ViewState not found in search response', ['accessKey' => $accessKey]);
            return null;
        }
        $updatedViewState = $matches[1];

        $linkId = "{$renderTable}:{$rowIndex}:lnkPdf";
        $downloadFields = array_merge($form->fields, $baseSearchFields, [
            'javax.faces.ViewState' => $updatedViewState,
            $linkId => $linkId,
        ]);
        unset($downloadFields['frmPrincipal:txtParametro']);

        try {
            $downloadResponse = $this->client($jar)->asForm()->post($form->actionUrl, $downloadFields);
        } catch (ConnectionException $e) {
            Log::error('SRI portal RIDE fallback: download connection failed', [
                'accessKey' => $accessKey,
                'direction' => $direction,
                'error' => $e->getMessage(),
            ]);
            throw new RuntimeException("Conexión reseteada descargando RIDE de {$accessKey}: {$e->getMessage()}", previous: $e);
        }

        Log::info('SRI portal RIDE fallback: download response', [
            'accessKey' => $accessKey,
            'direction' => $direction,
            'status' => $downloadResponse->status(),
            'content_type' => $downloadResponse->header('Content-Type'),
            'content_disposition' => $downloadResponse->header('Content-Disposition'),
        ]);

        if ($downloadResponse->failed()) {
            return null;
        }

        $contentType = $downloadResponse->header('Content-Type') ?? '';
        if (! str_contains($contentType, 'pdf')) {
            return null;
        }

        return $downloadResponse->body();
    }

    private function tipoComprobanteFilterFor(string $code, string $direction = 'received'): string
    {
        if ($direction === 'issued') {
            return match ($code) {
                '01' => '1',
                '03' => '2',
                '04' => '3',
                '05' => '4',
                '06' => '5',
                '07' => '6',
                default => throw new RuntimeException("Tipo de comprobante sin equivalente en el combo de Emitidos para el código: {$code}"),
            };
        }

        return match ($code) {
            '01' => '1',
            '03' => '2',
            '04' => '3',
            '05' => '4',
            '07' => '6',
            default => throw new RuntimeException("Tipo de comprobante sin equivalente en el combo de Recibidos para el código: {$code}"),
        };
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