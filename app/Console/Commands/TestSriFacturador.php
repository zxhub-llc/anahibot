<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Cookie\CookieJar;

class TestSriFacturador extends Command
{
    protected $signature = 'sri:test-facturador {ruc} {password} {viewstate}';

    protected $description = 'Debug del login del facturador SRI';

    public function handle(): int
    {
        $ruc = $this->argument('ruc');
        $password = $this->argument('password');
        $viewState = $this->argument('viewstate');

        $jar = new CookieJar();

        $http = fn () => Http::baseUrl('https://facturadorsri.sri.gob.ec/portal-facturadorsri-internet')
            ->withOptions(['cookies' => $jar, 'allow_redirects' => ['max' => 15, 'track_redirects' => true], 'version' => 1.1, 'force_ip_resolve' => 'v4'])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-EC,es;q=0.9',
            ])->timeout(30);

        // 1) GET fresco para tener cookies + ViewState sincronizados con este jar
        $page = $http()->get('/login.html');
        $this->info('GET /login.html -> HTTP '.$page->status());

        if (!preg_match('/id="javax\.faces\.ViewState"[^>]*value="([^"]+)"/', $page->body(), $m)) {
            $this->error('No se encontro ViewState en el GET fresco.');
            return self::FAILURE;
        }
        $freshViewState = html_entity_decode($m[1]);
        $this->line('ViewState fresco: '.$freshViewState);
        $this->line('ViewState pasado por argumento (ignorado, se usa el fresco): '.$viewState);

        // 2) POST de login usando el ViewState fresco del MISMO jar/sesión
        $loginResponse = $http()
            ->withHeaders([
                'Faces-Request' => 'partial/ajax',
                'X-Requested-With' => 'XMLHttpRequest',
                'Accept' => 'application/xml, text/xml, */*; q=0.01',
                'Origin' => 'https://facturadorsri.sri.gob.ec',
            ])
            ->asForm()
            ->post('/login.html', [
                'javax.faces.partial.ajax' => 'true',
                'javax.faces.source' => 'loginForm:j_idt19',
                'javax.faces.partial.execute' => '@all',
                'loginForm:j_idt19' => 'loginForm:j_idt19',
                'loginForm' => 'loginForm',
                'loginForm:nombreusuario' => $ruc,
                'loginForm:passwordInput' => $password,
                'javax.faces.ViewState' => $freshViewState,
            ]);

        $this->info('POST /login.html -> HTTP '.$loginResponse->status());
        file_put_contents(storage_path('app/facturador-login-response.xml'), $loginResponse->body());
        $this->line('--- RESPUESTA COMPLETA ---');
        $this->line($loginResponse->body());
        $this->line('--- FIN RESPUESTA ---');

        // 3) Ver si dejo cookies de sesion nuevas/distintas
        $this->line('Cookies en jar tras login:');
        foreach ($jar as $cookie) {
            $this->line('  '.$cookie->getName().' = '.substr($cookie->getValue(), 0, 20).'...');
        }

        // 4) GET a inicio.html para confirmar, siguiendo redirects a mano y forzando https
        $home = $this->followHttpsRedirects($jar, 'https://facturadorsri.sri.gob.ec/portal-facturadorsri-internet/pages/inicio.html');
        $this->info('GET /pages/inicio.html -> HTTP '.$home->status().' | url final: '.$home->effectiveUri());
        $this->line('Contiene id_nombre_razon_social: '.(str_contains($home->body(), 'id_nombre_razon_social') ? 'SI' : 'NO'));
        file_put_contents(storage_path('app/facturador-inicio-response.html'), $home->body());

        return self::SUCCESS;
    }

    private function followHttpsRedirects(CookieJar $jar, string $url, int $maxHops = 10)
    {
        $client = fn () => Http::withOptions(['cookies' => $jar, 'allow_redirects' => false, 'version' => 1.1, 'force_ip_resolve' => 'v4'])
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/150.0.0.0 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
                'Accept-Language' => 'es-EC,es;q=0.9',
            ])->timeout(30);

        for ($i = 0; $i < $maxHops; $i++) {
            $response = $client()->get($url);
            $this->line("  hop {$i}: GET {$url} -> HTTP {$response->status()}");

            if (!in_array($response->status(), [301, 302, 303, 307, 308])) {
                return $response;
            }

            $location = $response->header('Location');
            if ($location === null) {
                return $response;
            }
            // Fuerza https, igual que hace el navegador via HSTS
            $url = str_starts_with($location, 'http://')
                ? 'https://'.substr($location, 7)
                : $location;
        }

        throw new \RuntimeException('Demasiados redirects siguiendo '.$url);
    }
}