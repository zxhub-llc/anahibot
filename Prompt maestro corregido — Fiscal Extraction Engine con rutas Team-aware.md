# PROMPT MAESTRO — FISCAL EXTRACTION ENGINE PARA LARAVEL 13

Actúa como un **Principal Laravel Architect y Senior Backend Engineer especializado en Laravel 13, PHP 8.5, sistemas financieros, automatización de procesos, procesamiento asíncrono, APIs REST, Redis, Horizon, Inertia React y arquitecturas multi-contexto basadas en Teams**.

Debes trabajar sobre una aplicación existente.

No debes rediseñar componentes que ya existen y funcionan correctamente.

No quiero:

- pseudocódigo;
- arquitectura ficticia;
- ejemplos simplificados;
- clases genéricas innecesarias;
- código de demostración;
- TODOs;
- placeholders;
- duplicación de infraestructura existente.

Todo lo que propongas debe ser compatible con la arquitectura actual.

---

# STACK EXISTENTE

La aplicación utiliza:

- Laravel 13
- PHP 8.5
- Laravel Fortify
- Inertia.js
- React
- Wayfinder
- Laravel Queues
- Redis
- Laravel Horizon
- Amazon S3
- Sistema propio de Teams
- Middleware `EnsureTeamMembership`
- Policies y Gates
- Form Requests
- Actions
- Events
- Jobs
- API REST
- Shadcn UI en el frontend

---

# CONTEXTO EXISTENTE: NO REDISEÑAR TEAMS

El sistema de Teams ya existe.

No debes:

- crear otro sistema de tenants;
- crear otro sistema de Companies;
- reemplazar Laravel Fortify;
- rediseñar el ownership existente;
- redefinir Aggregate Roots;
- crear una arquitectura paralela de aislamiento;
- modificar la lógica existente de `current_team`;
- asumir que se necesita una nueva capa de tenancy.

La aplicación ya resuelve el contexto de trabajo mediante:

```php
$request->user()->currentTeam
```

y mediante el parámetro de ruta:

```text
{current_team}
```

El acceso a las rutas operativas está protegido por:

```php
EnsureTeamMembership::class
```

Por lo tanto, debes construir el nuevo módulo sobre esta arquitectura existente.

---

# MODELO OBLIGATORIO DE RUTAS TEAM-AWARE

La aplicación utiliza un prefijo de rutas basado explícitamente en el Team actual.

El patrón existente es:

```php
Route::prefix('{current_team}')
    ->middleware([
        'auth',
        'verified',
        EnsureTeamMembership::class,
    ])
    ->group(function () {
        Route::get('dashboard', DashboardController::class)
            ->name('dashboard');
    });
```

Este patrón debe extenderse al nuevo módulo.

Los recursos operativos del Fiscal Extraction Engine deben existir dentro del Team actual.

Ejemplo:

```text
/{current_team}/dashboard

/{current_team}/extractions
/{current_team}/extractions/{extraction}

/{current_team}/documents
/{current_team}/documents/{document}

/{current_team}/webhooks
/{current_team}/webhooks/{webhook}

/{current_team}/reports
```

La arquitectura de rutas debe seguir este patrón:

```php
Route::prefix('{current_team}')
    ->middleware([
        'auth',
        'verified',
        EnsureTeamMembership::class,
    ])
    ->group(function () {

        Route::get('dashboard', DashboardController::class)
            ->name('dashboard');

        Route::resource('extractions', ExtractionController::class);

        Route::resource('documents', FiscalDocumentController::class)
            ->only([
                'index',
                'show',
            ]);

        Route::resource('webhooks', WebhookEndpointController::class);
    });
```

No debes implementar este ejemplo literalmente si una ruta necesita una estructura más específica.

Pero el principio es obligatorio:

```text
Team context
    ↓
Route prefix
    ↓
EnsureTeamMembership
    ↓
Controller
    ↓
Current team context
```

---

# REGLA FUNDAMENTAL DE RESOLUCIÓN DEL TEAM

El parámetro:

```text
{current_team}
```

no debe ignorarse.

Las páginas Inertia del módulo deben recibir URLs Team-aware.

Ejemplo:

```text
/acme/extractions
/acme/documents
/acme/reports
```

y no:

```text
/extractions
/documents
/reports
```

salvo rutas globales explícitamente definidas.

El modelo debe respetar la arquitectura de navegación existente.

---

# CONTEXTO DEL CONTROLADOR

Los Controllers deben trabajar con el Team que ya está presente en el contexto.

No debes crear un segundo mecanismo para determinar el Team.

El patrón conceptual debe ser:

```php
public function index(
    Request $request,
    Team $currentTeam,
): Response {
    return Inertia::render(...);
}
```

o utilizar el Team resuelto por el mecanismo actual del proyecto cuando eso sea consistente con el binding y middleware existentes.

No asumir que el Team debe obtenerse siempre desde:

```php
$request->user()->currentTeam
```

si el parámetro `{current_team}` ya fue resuelto y validado por la ruta.

Debes respetar el mecanismo real existente.

---

# SETTINGS Y TEAM SETTINGS EXISTENTES

El sistema ya posee rutas globales de configuración.

Ejemplos:

```text
/settings/profile
/settings/security
/settings/appearance
/settings/teams
/settings/teams/{team}
```

También existen operaciones específicas sobre Teams:

```text
/settings/teams/{team}/members
/settings/teams/{team}/invitations
/settings/teams/{team}/company
```

No debes mover automáticamente estos recursos al prefijo:

```text
/{current_team}/...
```

porque forman parte de la arquitectura existente de Settings.

Debes diferenciar entre:

```text
Global account settings
```

y:

```text
Operational Team-scoped application modules
```

Los nuevos módulos de operación fiscal deben utilizar:

```text
/{current_team}/...
```

---

# WAYFINDER ES OBLIGATORIO

El frontend utiliza Wayfinder.

No construir URLs manualmente.

Incorrecto:

```ts
`/${team.slug}/extractions`
```

Incorrecto:

```ts
router.get(`/documents/${document.id}`);
```

Correcto conceptualmente:

```ts
import { index, show, store } from '@/routes/extractions';
```

y utilizar los helpers generados por Wayfinder.

Las rutas generadas deben recibir explícitamente el Team cuando sea requerido por el route model.

Ejemplo conceptual:

```ts
index({
    current_team: currentTeam.slug,
});
```

o el contrato exacto generado por Wayfinder.

No asumir el tipo de retorno de los helpers.

El proyecto puede utilizar helpers que devuelven:

```text
URL
+
HTTP Method
```

Por lo tanto, debes respetar el patrón existente.

Ejemplo:

```ts
router.visit(store({
    current_team: currentTeam.slug,
}), {
    data,
});
```

o la sintaxis real generada.

---

# PATRÓN OBLIGATORIO DE BACKEND

La arquitectura debe seguir:

```text
Controller
    ↓
Form Request
    ↓
Gate / Policy
    ↓
Action
    ↓
Transaction cuando aplique
    ↓
Event
    ↓
Jobs
    ↓
Persistence / Infrastructure
```

Los Controllers deben ser delgados.

No crear lógica de negocio importante directamente en:

```php
public function store()
```

---

# PATRÓN DE CONTROLLERS

El estilo debe ser compatible con el proyecto existente.

Ejemplo conceptual:

```php
public function store(
    StoreExtractionRequest $request,
    CreateExtraction $createExtraction,
): RedirectResponse {
    Gate::authorize('create', Extraction::class);

    $extraction = $createExtraction->handle(
        user: $request->user(),
        team: $request->user()->currentTeam,
        data: $request->validated(),
    );

    Inertia::flash('toast', [
        'type' => 'success',
        'message' => __('Extraction started.'),
    ]);

    return to_route('extractions.show', [
        'current_team' => $request->user()->currentTeam->slug,
        'extraction' => $extraction,
    ]);
}
```

Este código es conceptual.

Debes adaptar los parámetros exactamente al sistema real de bindings y rutas.

La regla importante es:

```text
Route parameters
+
Team context
+
Wayfinder-compatible named routes
```

---

# OBJETIVO DEL MÓDULO

Construir un módulo de extracción fiscal automatizada.

El sistema debe permitir:

1. Crear solicitudes de extracción.
2. Procesar solicitudes de forma asíncrona.
3. Autenticarse en el origen fiscal mediante infraestructura desacoplada.
4. Obtener documentos emitidos y recibidos.
5. Descargar XML.
6. Descargar PDF/RIDE cuando esté disponible.
7. Almacenar documentos privados en Amazon S3.
8. Procesar XML.
9. Extraer metadata fiscal.
10. Normalizar información financiera.
11. Consultar documentos desde Inertia.
12. Consultar información desde API REST.
13. Disparar Webhooks.
14. Mantener historial del procesamiento.
15. Permitir retries controlados.
16. Evitar duplicación de documentos.

---

# REFERENCIA FUNCIONAL

La arquitectura funcional debe inspirarse en plataformas de extracción fiscal que permiten trabajar con:

- contribuyentes;
- solicitudes de extracción;
- documentos recibidos;
- documentos emitidos;
- XML;
- PDF;
- JSON;
- múltiples tipos documentales.

La referencia pública para el comportamiento funcional es:

[TaxoWS Extractions Documentation](https://docs.taxo.ws/extractions?utm_source=chatgpt.com)

No debes copiar infraestructura privada ni asumir acceso interno a servicios de terceros.

La referencia sirve para comprender el patrón funcional del producto.

---

# NO HACER ANÁLISIS INICIAL DE LO YA RESUELTO

NO debes comenzar con:

- análisis profundo de la arquitectura existente;
- identificación de Aggregate Roots;
- rediseño del modelo de ownership;
- diagrama ERD de Teams;
- explicación del Team isolation existente;
- redefinición de Fortify;
- propuesta de tenancy.

Todo eso ya existe y ya fue corregido en el proyecto.

Debes asumir como base válida:

```text
Laravel Fortify
+
Teams
+
Memberships
+
Current Team
+
EnsureTeamMembership
+
Companies
```

---

# PUNTO DE INICIO OBLIGATORIO

Debes pasar directamente a la implementación del módulo.

Primero debes analizar únicamente lo necesario para integrar el nuevo dominio.

Comienza con:

## 1. Mapa del nuevo módulo

Definir:

```text
Extraction domain
Document domain
Processing domain
Storage domain
Webhook domain
API domain
```

## 2. Modelo de rutas

Definir exactamente las rutas Team-aware:

```text
/{current_team}/...
```

Diferenciar:

- rutas Inertia;
- acciones POST/PATCH/DELETE;
- rutas de descarga;
- rutas de retry;
- rutas de cancelación;
- rutas API.

## 3. Dependencias del dominio

Identificar exactamente qué nuevos:

```text
Enums
Models
Migrations
Actions
Form Requests
Controllers
Policies
Jobs
Events
Listeners
Resources
Contracts
Drivers
```

deben existir.

## 4. Flujo técnico

Definir:

```text
Create Extraction
    ↓
Queue Extraction
    ↓
Authenticate
    ↓
Extract
    ↓
Download
    ↓
Store
    ↓
Parse
    ↓
Normalize
    ↓
Complete
```

## 5. Comenzar inmediatamente la implementación

Después de mostrar el mapa exacto del módulo y las dependencias, pasar inmediatamente a crear los archivos reales.

No detenerse esperando validación arquitectónica.

No preguntar si debe continuar.

---

# MÓDULO: EXTRACTIONS

Crear una entidad:

```text
Extraction
```

Representa una solicitud lógica de extracción.

Debe soportar:

```text
Manual
Scheduled
Retry
API-triggered
```

Debe incluir estados mediante Enum.

Estados conceptuales:

```text
Pending
Queued
Running
Completed
CompletedWithErrors
Failed
Cancelled
```

Evaluar si los nombres finales deben adaptarse al proyecto.

---

# MÓDULO: EXTRACTION RUNS

Una Extraction puede tener múltiples ejecuciones.

Ejemplo:

```text
Extraction
    ├── Run #1 → Failed
    ├── Run #2 → Failed
    └── Run #3 → Completed
```

Esto permite distinguir:

```text
Logical extraction request
```

de:

```text
Individual execution attempt
```

La arquitectura debe utilizar este historial para retries.

---

# MÓDULO: DOCUMENTOS FISCALES

Crear un dominio principal:

```text
FiscalDocument
```

Debe soportar múltiples tipos:

```text
Invoice
CreditNote
DebitNote
Retention
PurchaseSettlement
RemissionGuide
```

Usar Enums.

Los documentos deben poder ser:

```text
Received
Issued
```

No crear una tabla principal independiente por cada tipo documental.

Usar una entidad común y relaciones específicas para datos que realmente necesiten normalización.

---

# IDEMPOTENCIA

La idempotencia es obligatoria.

Una misma extracción no debe duplicar:

```text
Fiscal documents
Files
Taxes
Retentions
Webhook deliveries
```

Usar:

- unique constraints;
- access key cuando corresponda;
- estado del documento;
- jobs únicos cuando aplique;
- `WithoutOverlapping`;
- locks selectivos;
- `lockForUpdate()` únicamente cuando sea necesario.

No utilizar un lock global para todo el Team.

---

# STORAGE S3

Los documentos deben almacenarse de forma privada.

La estructura debe considerar:

```text
Team
Company / RUC
Year
Month
Direction
Document type
Access key
```

Ejemplo conceptual:

```text
teams/{team}/companies/{ruc}/{year}/{month}/{direction}/{type}/{access_key}.xml
```

y:

```text
teams/{team}/companies/{ruc}/{year}/{month}/{direction}/{type}/{access_key}.pdf
```

Analizar el path final antes de implementarlo.

Los documentos no deben ser públicos.

Las descargas deben usar URLs temporales.

---

# JOB ARCHITECTURE

No crear un único Job gigante.

Separar responsabilidades.

La arquitectura debe permitir algo equivalente a:

```text
ProcessExtraction
    ↓
ExtractDocumentBatch
    ↓
DownloadFiscalDocument
    ↓
ParseFiscalDocument
    ↓
FinalizeExtraction
```

La granularidad final debe justificarse por:

- concurrencia;
- idempotencia;
- retries;
- costo de serialización;
- volumen de documentos.

---

# DRIVER ARCHITECTURE

No acoplar Jobs directamente al navegador o mecanismo de automatización.

Crear un Contract.

Ejemplo conceptual:

```php
interface FiscalExtractionDriver
{
    public function authenticate(...);

    public function extract(...);

    public function downloadXml(...);

    public function downloadRide(...);
}
```

El dominio debe depender del Contract.

La infraestructura debe implementar el Driver.

Esto debe permitir cambiar posteriormente:

```text
Browser automation
```

por:

```text
Official API
External provider
Dedicated worker service
```

sin romper las Actions ni Jobs del dominio.

---

# WEBHOOK ENGINE

Eventos mínimos:

```text
extraction.started
extraction.completed
extraction.failed
document.received
document.processed
document.failed
```

Implementar:

```text
WebhookEndpoint
WebhookDelivery
```

Los deliveries deben:

- usar Queue;
- firmarse mediante HMAC;
- soportar retries;
- registrar response status;
- registrar delivery status;
- no bloquear la extracción principal.

---

# API REST

Las rutas API no deben utilizar las mismas rutas web.

Usar:

```text
/api/v1
```

La API debe reutilizar las Actions del dominio.

Separación:

```text
Inertia Controller
        ↓
      Action
        ↑
API Controller
```

No duplicar lógica de negocio.

Usar:

- Sanctum;
- API Resources;
- abilities;
- rate limiting;
- pagination;
- filtros permitidos.

---

# ESTRUCTURA OBLIGATORIA

Proponer una estructura limpia basada en el módulo:

```text
app/
├── Actions/
│   ├── Extractions/
│   ├── FiscalDocuments/
│   └── Webhooks/
│
├── Contracts/
│   └── FiscalExtraction/
│
├── Data/
│   └── FiscalDocuments/
│
├── Enums/
│
├── Events/
│   ├── Extractions/
│   └── FiscalDocuments/
│
├── Exceptions/
│
├── Http/
│   ├── Controllers/
│   │   ├── Api/
│   │   │   └── V1/
│   │   ├── Extractions/
│   │   ├── FiscalDocuments/
│   │   └── Webhooks/
│   │
│   ├── Requests/
│   │   ├── Extractions/
│   │   └── Webhooks/
│   │
│   └── Resources/
│
├── Jobs/
│   ├── Extractions/
│   ├── FiscalDocuments/
│   └── Webhooks/
│
├── Listeners/
│
├── Models/
│
├── Policies/
│
└── Services/
    └── FiscalExtraction/
```

No crear carpetas innecesarias.

---

# FORMATO DE IMPLEMENTACIÓN

Cada archivo generado debe incluir:

## Ruta

```text
app/Actions/Extractions/CreateExtraction.php
```

## Código completo

Código real y listo para producción.

## Responsabilidad

Una explicación breve.

Después continuar inmediatamente con el siguiente archivo.

No detener la implementación para pedir confirmación.

---

# ORDEN DE IMPLEMENTACIÓN

Debes implementar directamente en este orden:

1. Modelo exacto de rutas Team-aware.
2. Enums.
3. Migrations del nuevo módulo.
4. Models y relaciones.
5. Policies.
6. Form Requests.
7. Actions.
8. Contracts y Drivers.
9. Jobs.
10. Events.
11. Listeners.
12. Webhooks.
13. Controllers Inertia.
14. Controllers API.
15. API Resources.
16. Rutas web Team-aware.
17. Rutas API.
18. Wayfinder integration.
19. Tests.

---

# REGLA FINAL

No volver a analizar ni rediseñar:

```text
Teams
Memberships
Fortify
Current Team
Ownership base
Existing Companies
Existing Team routes
```

Todo eso ya existe.

El trabajo comienza desde el nuevo módulo.

El Team debe tratarse como parte explícita de la navegación mediante:

```text
/{current_team}/...
```

Las operaciones del módulo deben respetar el contexto de Team proporcionado por:

```text
Route prefix
+
EnsureTeamMembership
+
Current Team
```

Comienza ahora con el mapa exacto de las nuevas rutas y pasa inmediatamente a implementar el primer archivo real.