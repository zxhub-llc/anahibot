<?php

namespace App\Services\FiscalExtraction\Sri;

// Estructura de columnas confirmada contra el portal real (tabla "recibidos"):
// 0 Nro | 1 RUC+Razón social emisor | 2 Tipo y serie | 3 Clave de acceso |
// 4 Fecha autorización | 5 Fecha emisión | 6 Sin impuestos | 7 IVA | 8 Total |
// 9 XML | 10 RIDE | 11 Documentos relacionados
// No existe columna de estado/anulado visible en esta tabla: isVoided queda
// siempre false (limitación conocida, no verificable desde el listado).
final class SriDocumentTableParser
{
    public const COL_COUNTERPART = 1;
    public const COL_ACCESS_KEY = 3;

    // public const COL_TYPE = 0;
    // public const COL_ACCESS_KEY = 2;
    // public const COL_COUNTERPART_RUC = 3;
    // public const COL_COUNTERPART_NAME = 4;
    // public const COL_ISSUED_AT = 5;
    // public const COL_STATUS = 6;

    /**
     * @return array<int, array<int, string>>
     */
    public function parse(string $rawResponse, string $tableId): array
    {
        $html = $this->extractCdataUpdate($rawResponse, $this->panelId($tableId)) ?? $rawResponse;

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

    /**
     * Extrae el HTML dentro de <update id="{panelId}"><![CDATA[ ... ]]></update>
     * de una partial-response de PrimeFaces.
     */
    private function extractCdataUpdate(string $xml, string $panelId): ?string
    {
        if (! str_contains($xml, '<partial-response>')) {
            return null;
        }

        $pattern = '/<update id="'.preg_quote($panelId, '/').'">\s*<!\[CDATA\[(.*?)\]\]>\s*<\/update>/s';

        return preg_match($pattern, $xml, $matches) === 1 ? $matches[1] : null;
    }

    private function panelId(string $tableId): string
    {
        return "frmPrincipal:panelListaComprobantes";
    }
}