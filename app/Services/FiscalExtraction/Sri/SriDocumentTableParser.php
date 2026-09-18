<?php

namespace App\Services\FiscalExtraction\Sri;

final class SriDocumentTableParser
{
    public const COL_COUNTERPART = 1;
    public const COL_ACCESS_KEY = 3;

    // public const COL_TYPE = 0;
    public const COL_ACCESS_KEY_ISSUED = 2;
    // public const COL_COUNTERPART_RUC = 3;
    // public const COL_COUNTERPART_NAME = 4;
    // public const COL_ISSUED_AT = 5;
    // public const COL_STATUS = 6;
    
    public function parse(string $rawResponse, string $tableId): array
    {
        $html = $this->extractCdataUpdate($rawResponse, $tableId)
            ?? $this->extractCdataUpdate($rawResponse, 'frmPrincipal:panelListaComprobantes')
            ?? $rawResponse;

        $document = new \DOMDocument();
        libxml_use_internal_errors(true);
        $document->loadHTML('<?xml encoding="UTF-8">'.$html);
        libxml_use_internal_errors(false);

        $xpath = new \DOMXPath($document);
        $rows = $xpath->query("//table//tbody/tr");

        $parsed = [];

        foreach ($rows as $row) {
            $cells = $xpath->query('.//td', $row);
            $values = [];

            foreach ($cells as $cell) {
                $values[] = trim(preg_replace('/\s+/', ' ', $cell->textContent));
            }

            if ($values !== []) {
                $parsed[] = $values;
            }
        }

        return $parsed;
    }

    private function extractCdataUpdate(string $xml, string $panelId): ?string
    {
        if (! str_contains($xml, '<partial-response>')) {
            return null;
        }

        $pattern = '/<update id="'.preg_quote($panelId, '/').'">\s*<!\[CDATA\[(.*?)\]\]>\s*<\/update>/s';

        return preg_match($pattern, $xml, $matches) === 1 ? $matches[1] : null;
    }
}