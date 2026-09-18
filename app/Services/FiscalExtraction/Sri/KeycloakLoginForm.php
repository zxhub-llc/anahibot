<?php

namespace App\Services\FiscalExtraction\Sri;

use RuntimeException;

final class KeycloakLoginForm
{
    public function __construct(
        public readonly string $actionUrl,
        public readonly array $hiddenFields,
        public readonly string $usernameField,
        public readonly string $passwordField,
    ) {
    }

    public static function fromHtml(string $html): self
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML($html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $form = $xpath->query('//form[@id="kc-form-login"]')->item(0);

        if ($form === null) {
            throw new RuntimeException('No se encontró el formulario de login de Keycloak (id="kc-form-login"): la página devuelta no es la esperada.');
        }

        $action = $form->getAttribute('action');

        if ($action === '') {
            throw new RuntimeException('El formulario de login de Keycloak no trae "action" (session_code/execution vigentes).');
        }

        $hidden = [];
        foreach ($xpath->query('.//input[@type="hidden"]', $form) as $input) {
            $hidden[$input->getAttribute('name')] = $input->getAttribute('value');
        }

        $usernameInput = $xpath->query('.//input[@type="text" or @type="email"]', $form)->item(0);
        $passwordInput = $xpath->query('.//input[@type="password"]', $form)->item(0);

        if ($usernameInput === null || $passwordInput === null) {
            throw new RuntimeException('No se encontraron los campos de usuario/clave en el formulario de Keycloak.');
        }

        return new self(
            actionUrl: html_entity_decode($action),
            hiddenFields: $hidden,
            usernameField: $usernameInput->getAttribute('name'),
            passwordField: $passwordInput->getAttribute('name'),
        );
    }
}
