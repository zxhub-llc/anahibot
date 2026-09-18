<?php

namespace App\Services\FiscalExtraction\Sri;

use RuntimeException;

final class SriComprobantesForm
{

    public function __construct(
        public readonly string $actionUrl,
        public readonly string $viewState,
        public readonly array $fields,
    ) {
    }

    public static function fromHtml(string $html, string $fallbackActionUrl, string $formName = 'frmPrincipal'): self
    {
        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $form = $xpath->query("//form[@id='{$formName}' or @name='{$formName}']")->item(0);

        if ($form === null) {
            throw new RuntimeException("No se encontró el formulario '{$formName}' en la página del SRI: la sesión puede haber expirado o el layout cambió.");
        }

        $action = trim($form->getAttribute('action'));
        $actionUrl = $action !== ''
            ? self::resolveUrl($fallbackActionUrl, html_entity_decode($action))
            : $fallbackActionUrl;

        $fields = [];

        foreach ($xpath->query('.//input', $form) as $input) {
            $name = $input->getAttribute('name');

            if ($name === '') {
                continue;
            }

            $type = strtolower($input->getAttribute('type'));

            if (in_array($type, ['submit', 'button', 'reset', 'image'], true)) {
                continue;
            }

            if (in_array($type, ['checkbox', 'radio'], true) && ! $input->hasAttribute('checked')) {
                continue;
            }

            $fields[$name] = $input->getAttribute('value');
        }

        foreach ($xpath->query('.//select', $form) as $select) {
            $name = $select->getAttribute('name');

            if ($name === '') {
                continue;
            }

            $selected = $xpath->query('.//option[@selected]', $select)->item(0)
                ?? $xpath->query('.//option', $select)->item(0);

            $fields[$name] = $selected?->getAttribute('value') ?? '';
        }

        $viewState = $fields['javax.faces.ViewState'] ?? null;

        if ($viewState === null) {
            throw new RuntimeException('javax.faces.ViewState no encontrado en el formulario del SRI: la sesión puede haber expirado.');
        }

        return new self($actionUrl, $viewState, $fields);
    }

    private static function resolveUrl(string $baseUrl, string $action): string
    {
        if (str_starts_with($action, 'http://') || str_starts_with($action, 'https://')) {
            return $action;
        }

        $parts = parse_url($baseUrl);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? '';
        $path = $parts['path'] ?? '/';

        if (str_starts_with($action, '/')) {
            return "{$scheme}://{$host}{$action}";
        }

        $directory = substr($path, 0, strrpos($path, '/') + 1);

        return "{$scheme}://{$host}{$directory}{$action}";
    }
}