<?php

return [
    'driver' => env('FISCAL_EXTRACTION_DRIVER', 'null'),
    'sri' => [
        'portal_base_url' => env('SRI_PORTAL_BASE_URL', 'https://srienlinea.sri.gob.ec'),
        'portal_documents_received_path' => env(
            'SRI_PORTAL_DOCS_RECEIVED_PATH',
            '/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf',
        ),
        'portal_documents_issued_path' => env(
            'SRI_PORTAL_DOCS_ISSUED_PATH',
            '/comprobantes-electronicos-internet/pages/consultas/menu.jsf',
        ),
        'portal_ride_path' => env(
            'SRI_PORTAL_RIDE_PATH',
            '/comprobantes-electronicos-internet/pages/descargas/ride/{accessKey}',
        ),
        'portal_menu_context_received' => [
            'contextoMPT' => env('SRI_PORTAL_MENU_CONTEXTO', 'https://srienlinea.sri.gob.ec/tuportal-internet'),
            'pathMPT' => env('SRI_PORTAL_MENU_PATH', 'Facturación Electrónica'),
            'actualMPT' => 'Comprobantes electrónicos recibidos ',
            'linkMPT' => '/comprobantes-electronicos-internet/pages/consultas/recibidos/comprobantesRecibidos.jsf?',
            'esFavorito' => env('SRI_PORTAL_MENU_FAVORITO', 'S'),
        ],
        'portal_menu_context_issued' => [
            'contextoMPT' => env('SRI_PORTAL_MENU_CONTEXTO', 'https://srienlinea.sri.gob.ec/tuportal-internet'),
            'pathMPT' => env('SRI_PORTAL_MENU_PATH', 'Facturación Electrónica'),
            'actualMPT' => 'Comprobantes electrónicos emitidos ',
            'linkMPT' => '/comprobantes-electronicos-internet/pages/consultas/emitidos/comprobantesEmitidosConsulta.jsf?',
            'esFavorito' => env('SRI_PORTAL_MENU_FAVORITO', 'S'),
        ],
        'http_timeout' => (int) env('SRI_HTTP_TIMEOUT', 30),
        'session_ttl_minutes' => (int) env('SRI_SESSION_TTL_MINUTES', 15),
        'soap_authorization_wsdl_prod' => 'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        'soap_authorization_wsdl_test' => 'https://celcer.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
    ],
];
