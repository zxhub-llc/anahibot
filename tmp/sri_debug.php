<?php

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Support\Facades\Http;

$client = app(\App\Services\Sri\SriPortalClient::class);
$ruc = '0993407077001';

$reflection = new ReflectionClass($client);
$method = $reflection->getMethod('sessionFor');
$method->setAccessible(true);
$jar = $method->invoke($client, $ruc);

$menuContext = config('fiscal-extraction.sri.portal_menu_context', []);
$url = 'https://srienlinea.sri.gob.ec/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf';
$url .= '?' . http_build_query($menuContext);

$hop = 0;
while ($url !== null && $hop < 10) {
    $hop++;

    echo "Cookies ENVIADAS en hop {$hop}:\n";
    foreach ($jar->toArray() as $c) {
        if (str_contains($c['Path'] ?? '', 'tuportal-internet') || str_contains($c['Path'] ?? '', 'comprobantes')) {
            echo "  {$c['Name']}={$c['Value']} (Path={$c['Path']})\n";
        }
    }

    $response = Http::withOptions([
        'cookies' => $jar,
        'allow_redirects' => false,
    ])->get($url);

    $setCookies = $response->headers()['Set-Cookie'] ?? [];

    echo "--- HOP {$hop} ---\n";
    echo "URL: {$url}\n";
    echo "Status: {$response->status()}\n";
    echo "Location: " . ($response->header('Location') ?: '(none)') . "\n";
    echo "Set-Cookie:\n";
    foreach ($setCookies as $c) {
        echo "  {$c}\n";
    }
    echo "\n";

    $url = $response->header('Location');

    if ($url !== null && !str_starts_with($url, 'http')) {
        $url = 'https://srienlinea.sri.gob.ec' . $url;
    }
}