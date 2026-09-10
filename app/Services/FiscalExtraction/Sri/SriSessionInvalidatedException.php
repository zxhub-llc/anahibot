<?php

namespace App\Services\FiscalExtraction\Sri;

use RuntimeException;

// Señala específicamente que el SSO cross-client del SRI (entre el client_id usado en el
// login inicial y "app-tuportal-internet", usado internamente por GeneraToken.jsp) fue
// invalidado por Keycloak a mitad de la cadena de redirects, devolviendo al usuario a
// contribuyente/perfil en vez de a la página de comprobantes solicitada. Es distinto de
// una sesión expirada por timeout: aquí la sesión SIGUE viva para otros clients, pero
// Keycloak decidió no honrar el SSO para este client_id en particular. La única
// recuperación conocida es reautenticar desde cero (nuevo CookieJar) y reintentar.
class SriSessionInvalidatedException extends RuntimeException
{
}