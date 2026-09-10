<?php

// Responsabilidad: configuración del módulo. `driver` decide qué implementación de
// FiscalExtractionDriver vincula FiscalExtractionServiceProvider: 'null' (Null Object, para
// desarrollo/tests) o 'sri' (integración real contra el SRI Ecuador).
return [
    'driver' => env('FISCAL_EXTRACTION_DRIVER', 'null'),

    'sri' => [
        // Endpoint OFICIAL y estable del SRI para autorización/descarga de comprobantes por
        // clave de acceso (Web Service público de "Autorización de comprobantes electrónicos").
        'soap_authorization_wsdl' => env(
            'SRI_SOAP_AUTORIZACION_WSDL',
            'https://cel.sri.gob.ec/comprobantes-electronicos-ws/AutorizacionComprobantesOffline?wsdl',
        ),

        // Portal transaccional "SRI en Línea". El LOGIN es Keycloak (realm "Internet") — sus
        // rutas y client_id son estables y están hardcodeadas como constantes en SriPortalClient
        // (confirmadas contra las URLs reales del usuario), NO en config, porque son parte del
        // contrato del cliente OAuth y no varían por entorno. Lo que SÍ varía es el listado de
        // comprobantes (JSF/PrimeFaces, confirmado por su propia URL), configurable abajo.
        'portal_base_url' => env('SRI_PORTAL_BASE_URL', 'https://srienlinea.sri.gob.ec'),

        // Confirmada contra una consulta real del usuario.
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

        'http_timeout' => (int) env('SRI_HTTP_TIMEOUT', 20),
        'session_ttl_minutes' => (int) env('SRI_SESSION_TTL_MINUTES', 15),
    ],
];
