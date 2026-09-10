# Fiscal Extraction Engine — módulo completo (backend + frontend)

Segunda entrega: integra el sistema real de permisos del proyecto (`TeamRole`/`TeamPermission`),
agrega Spatie MediaLibrary para XML/PDF/JSON, implementa un driver real contra el SRI Ecuador,
corrige el modelo de rutas de la API, y agrega las vistas Inertia/React del módulo.

## 1. Permisos — reemplaza tus enums reales

Se extendieron `App\Enums\TeamPermission` y `App\Enums\TeamRole` (archivos en `app/Enums/`) con
6 permisos nuevos del módulo, **conservando intactos** los 7 permisos y los métodos públicos que
ya tenías (`label()`, `permissions()`, `hasPermission()`, `level()`, `isAtLeast()`, `assignable()`).
Reemplaza tus dos archivos reales por estos. Owner hereda todos los permisos automáticamente
(`TeamPermission::cases()`); Admin gana gestión completa del módulo fiscal; Member solo lectura
(`extraction:view`, `document:view`).

Las Policies (`ExtractionPolicy`, `FiscalDocumentPolicy`, `WebhookEndpointPolicy`,
`SriCredentialPolicy`) ya no asumen métodos como `belongsToTeam()`/`hasTeamPermission()` que no
existían en tu `User`/`Team` reales: ahora resuelven el `TeamRole` del usuario vía
`Team::memberships()` (la relación que ya tienes) y llaman `TeamRole::hasPermission()` a través
del trait `App\Policies\Concerns\ChecksTeamPermission`.

**Corrección importante**: en la entrega anterior, `Gate::authorize('create', Extraction::class)`
no podía saber a qué Team pertenece la acción (no hay instancia todavía). Ahora los Controllers
llaman `Gate::authorize('create', [Extraction::class, $currentTeam])` y las Policies reciben
`create(User $user, Team $team)` / `viewAny(User $user, Team $team)` explícitamente.

## 2. API sin `{current_team}` — tenías razón

Las rutas API (`routes/api_v1_fiscal.php`) ya NO usan `{current_team}` ni `EnsureTeamMembership`:
ambos son conceptos de sesión web (el Team "activo" del usuario logueado en el navegador). Un
token Sanctum ya identifica al usuario, y un usuario `Account::Accountant` (ve tu `User` model)
puede tener acceso a varios Teams sin que el token esté atado a uno solo. Ahora el Team se resuelve
como cualquier recurso anidado, por slug:

```
GET  /api/v1/teams/{team:slug}/extractions
POST /api/v1/teams/{team:slug}/extractions
GET  /api/v1/teams/{team:slug}/documents
```

La autorización real ("¿este usuario del token pertenece a ese Team y tiene el permiso X?") la
hace la Policy vía `Gate::authorize`, exactamente igual que en el Controller Inertia.

Las rutas **web** (Inertia) sí mantienen `{current_team}` + `EnsureTeamMembership`, sin cambios,
porque ahí sí aplica el concepto de Team activo de sesión.

## 3. Spatie MediaLibrary

`composer require spatie/laravel-medialibrary:^11.0`, luego:

```
php artisan vendor:publish --provider="Spatie\MediaLibrary\MediaLibraryServiceProvider" --tag="medialibrary-migrations"
php artisan migrate
```

`FiscalDocument` implementa `HasMedia` con 3 colecciones single-file sobre el disk `s3` (privado):
`xml`, `pdf`, `json`. Las columnas `xml_path`/`pdf_path` de la primera entrega se eliminaron de la
migración — MediaLibrary gestiona sus propias rutas en su tabla `media`. `StoreFiscalDocument`
adjunta el XML al crear el documento; `ParseFiscalDocument` genera y adjunta el JSON normalizado al
terminar de parsear. Las descargas (`downloadXml`/`downloadPdf`/`downloadJson` en
`FiscalDocumentController`) redirigen a `$media->getTemporaryUrl(...)` (URL S3 firmada, 5 min).

## 4. Clasificación de documentos (dashboard)

`resources/js/lib/fiscal-navigation.ts` define el mapa exacto pedido, en ambas direcciones:

```
Documentos Recibidos          Documentos Emitidos
 ├── Facturas de Compra        ├── Facturas de Venta
 ├── Retenciones                ├── Retenciones
 ├── Notas de Crédito           ├── Notas de Crédito
 ├── Notas de Débito            ├── Liquidaciones de Venta
 ├── Liquidaciones de Compra    └── Documentos Anulados
 └── Documentos Anulados
```

"Documentos Anulados" es una clasificación transversal (columna `is_voided` en `fiscal_documents`,
poblada por `SriFiscalExtractionDriver::toReference()` desde el listado del SRI), no un tipo
documental nuevo — un documento anulado conserva su `type` real (factura, retención, etc.) y
además se marca `is_voided = true`.

`FiscalDocumentController@index` acepta `?direction=&type=&voided=1` y el sidebar de
`resources/js/pages/fiscal-documents/index.tsx` navega exactamente con esos params — no hay
lógica de filtrado duplicada en el frontend. Integra `fiscalDocumentNavigation` en el componente
de sidebar que ya tenga tu proyecto (no se generó un sidebar propio para no pelear con el tuyo, ya
que no compartiste su estructura de props).

La tabla de documentos trae los 3 botones de descarga (XML/PDF/JSON) por fila, deshabilitados
según `has_xml`/`has_pdf`/`has_json`.

## 5. Configuración de conexión con el SRI

Vista: `/{current_team}/sri-credentials` (`resources/js/pages/sri-credentials/edit.tsx`). Guarda
RUC + usuario + contraseña del portal `srienlinea.sri.gob.ec`, cifrados con el cast `encrypted`
de Laravel (`SriCredential`, nunca se serializan — ver `$hidden`). Botón "Probar conexión" ejecuta
`TestSriConnection`, que llama `authenticate()` en el driver real y guarda el resultado.

## 6. Driver real del SRI — activarlo

```
FISCAL_EXTRACTION_DRIVER=sri
```

`SriFiscalExtractionDriver` combina dos piezas de estabilidad distinta (ver docblocks de las
clases para el detalle):

- **`downloadXml()`** usa el Web Service SOAP **oficial y estable** del SRI
  (`AutorizacionComprobantesOffline`, endpoint público, no requiere sesión). Requiere la
  extensión `ext-soap` habilitada en PHP. El mapeo de tipo documental usa los códigos oficiales de
  la clave de acceso SRI (01 Factura, 03 Liquidación, 04 Nota de Crédito, 05 Nota de Débito, 06
  Guía de Remisión, 07 Retención) — esta parte es 100% confiable.
- **`authenticate()`/`extract()`/`downloadRide()`** usan `SriPortalClient` contra el portal
  transaccional del contribuyente, que **no tiene un contrato público documentado por el SRI**
  (es una SPA autenticada que puede cambiar sus endpoints internos sin aviso). Las rutas en
  `config/fiscal-extraction.php` (`SRI_PORTAL_*` en `.env`) son el punto de partida de la
  arquitectura correcta (login → sesión con cookies → listado → descarga); antes de operar en
  producción, confirma/ajusta esas 5 rutas inspeccionando el tráfico de una sesión real logueada
  en `srienlinea.sri.gob.ec` (DevTools → Network) y actualízalas solo en `SriPortalClient` — el
  Contract, las Actions y los Jobs del dominio no cambian sea cual sea el driver.

Mientras no confirmes las rutas del portal, deja `FISCAL_EXTRACTION_DRIVER=null` (default): el
pipeline completo sigue corriendo end-to-end con `NullFiscalExtractionDriver` para desarrollo/tests.

## 7. Integración pendiente (resumen, ver también README de la entrega anterior)

1. `routes/web.php` → `require __DIR__.'/fiscal-extraction.php';`
2. `routes/api.php` → `require __DIR__.'/api_v1_fiscal.php';`
3. `bootstrap/providers.php` → agregar `App\Providers\FiscalExtractionServiceProvider::class`.
4. Reemplazar `app/Enums/TeamPermission.php` y `app/Enums/TeamRole.php` por los de este módulo.
5. `composer require spatie/laravel-medialibrary:^11.0` + publicar su migración + `php artisan migrate`.
6. Copiar `resources/js/*` a las rutas equivalentes de tu proyecto. Los imports `@/routes/...`
   asumen los nombres de export que Wayfinder genera a partir de los nombres de ruta
   (`extractions.store` → `store`, `documents.download.xml` → `downloadXml`, etc.); si tu versión
   de Wayfinder genera otros nombres, ajusta esos imports — es lo único frontend atado a una
   convención no verificable sin tu proyecto real.
7. `ext-soap` habilitado en PHP para el driver SRI real.
8. Variables de entorno: `FISCAL_EXTRACTION_DRIVER`, `SRI_PORTAL_*`, `SRI_SOAP_AUTORIZACION_WSDL`.

## Pipeline técnico (sin cambios respecto a la entrega anterior)

```
CreateExtraction (Action)
  → ProcessExtraction (Job, ShouldBeUnique)
    → authenticate() + extract() vía FiscalExtractionDriver (Null o SRI real)
    → ExtractDocumentBatch (Job, Bus::batch)
      → DownloadFiscalDocument (Job, por documento)
        → StoreFiscalDocument (Action, idempotente, Media Library → S3)
        → ParseFiscalDocumentJob (Job)
          → ParseFiscalDocument (Action, normaliza XML + genera JSON)
      → FinalizeExtraction (Job, al cerrar el batch)
        → CompleteExtraction (Action, cierra Extraction + ExtractionRun, dispara evento)
          → Listener → DispatchWebhook (Action) → DeliverWebhook (Job, HMAC + retries)
```