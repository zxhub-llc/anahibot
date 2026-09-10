<?php

namespace App\Services\FiscalExtraction\Sri;

final class SriJSecurityCheckForm
{
    /**
     * @param array<string, string> $fields
     */
    private function __construct(
        public readonly string $actionUrl,
        public readonly array $fields,
    ) {
    }

    /**
     * @return self|null null si la página no contiene este form (ya es la página final).
     */
    public static function fromHtmlOrNull(string $html, string $baseUrlForAction): ?self
    {
        if (! str_contains($html, 'j_security_check')) {
            return null;
        }

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $form = $xpath->query("//form[contains(@action, 'j_security_check')]")->item(0);

        if ($form === null) {
            return null;
        }

        $action = html_entity_decode($form->getAttribute('action'));
        $actionUrl = self::resolveUrl($baseUrlForAction, $action);

        $fields = [];

        // Capturar TODOS los inputs dentro del formulario de j_security_check
        foreach ($xpath->query('.//input', $form) as $input) {
            $name = $input->getAttribute('name');

            if ($name !== '') {
                $fields[$name] = $input->getAttribute('value');
            }
        }

        return new self($actionUrl, $fields);
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