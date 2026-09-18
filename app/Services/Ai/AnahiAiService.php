<?php

namespace App\Services\Ai;

use App\Actions\Extractions\CreateExtraction;
use App\Enums\FiscalDocumentDirection;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\Team;
use App\Models\User;
use App\Services\Fiscal\SriTaxCalculatorService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AnahiAiService
{
    public function __construct(
        protected SriTaxCalculatorService $calculator
    ) {}
    
    public function processMessage(Team $team, User $user, string $userMessage, ?string $model = 'gemini-2.0-flash'): array
    {
        $normalizedMessage = Str::lower(trim($userMessage));

        if ($this->isForm104Request($normalizedMessage)) {
            return $this->handleForm104Request($team, $normalizedMessage);
        }

        if ($this->isForm103Request($normalizedMessage)) {
            return $this->handleForm103Request($team, $normalizedMessage);
        }

        if ($this->isAtsRequest($normalizedMessage)) {
            return $this->handleAtsRequest($team, $normalizedMessage);
        }

        if ($this->isFinancialReportRequest($normalizedMessage)) {
            return $this->handleFinancialReportRequest($team, $normalizedMessage);
        }

        if ($this->isExtractionRequest($normalizedMessage)) {
            return $this->handleExtractionRequest($team, $normalizedMessage);
        }

        if ($this->isDocumentSearchRequest($normalizedMessage)) {
            return $this->handleDocumentSearchRequest($team, $normalizedMessage);
        }

        $llmResponse = $this->callLlmApi($team, $userMessage, $model);
        if ($llmResponse !== null) {
            return $llmResponse;
        }

        return $this->handleGenericConversational($team, $userMessage);
    }

    protected function isForm104Request(string $text): bool
    {
        return Str::contains($text, ['104', 'iva', 'debito fiscal', 'credito tributario', 'formulario 104']);
    }

    protected function isForm103Request(string $text): bool
    {
        return Str::contains($text, ['103', 'retencion fuente', 'retenciones renta', 'formulario 103']);
    }

    protected function isAtsRequest(string $text): bool
    {
        return Str::contains($text, ['ats', 'anexo transaccional', 'anulados']);
    }

    protected function isFinancialReportRequest(string $text): bool
    {
        return Str::contains($text, ['reporte', 'resumen de ventas', 'resumen de compras', 'balance', 'grafico', 'estadisticas']);
    }

    protected function isExtractionRequest(string $text): bool
    {
        return Str::contains($text, [
            'extraer', 'extracción', 'sri', 'sincronizar', 'descargar del sri',
            'nueva extraccion', 'traer documentos', 'traer comprobantes',
            'traer todos', 'descargar todos', 'obtener del sri'
        ]);
    }

    protected function isDocumentSearchRequest(string $text): bool
    {
        return Str::contains($text, ['buscar', 'descargar', 'documento', 'factura', 'retencion', 'pdf', 'xml', 'comprobante', 'proveedor', 'cliente']);
    }

    protected function handleForm104Request(Team $team, string $text): array
    {
        $month = $this->extractMonth($text) ?: date('m');
        $year = $this->extractYear($text) ?: date('Y');

        $data = $this->calculator->calculateForm104($team, (string) $month, (string) $year);

        $reply = "### 📊 Formulario 104 - Declaración de IVA ({$data['period']['period_label']})\n\n" .
            "He realizado el cálculo del **Formulario 104 (IVA)** para tu empresa:\n\n" .
            "- **Ventas Netas (Ventas - NC)**: \${$data['sales']['subtotal']} (IVA: \${$data['sales']['iva']})\n" .
            "- **Compras Netas (Compras - NC)**: \${$data['purchases']['subtotal']} (IVA: \${$data['purchases']['iva']})\n" .
            "- **Retenciones de IVA Sufridas**: \${$data['retentions_supported']}\n\n";

        if ($data['summary']['impuesto_causado'] > 0) {
            $reply .= "⚠️ **Resultado**: Impuesto a pagar al SRI de **\${$data['summary']['impuesto_causado']}**.";
        } else {
            $reply .= "✅ **Resultado**: Tienes un **Crédito Tributario de \${$data['summary']['credito_tributario_resultante']}** a favor para el siguiente período.";
        }

        return [
            'reply' => $reply,
            'action_type' => 'sri_form_104',
            'data' => $data,
            'suggestions' => [
                'Ver Formulario 103 (Retenciones Renta)',
                'Generar resumen ATS de este mes',
                'Buscar facturas del mes',
            ],
        ];
    }

    protected function handleForm103Request(Team $team, string $text): array
    {
        $month = $this->extractMonth($text) ?: date('m');
        $year = $this->extractYear($text) ?: date('Y');

        $data = $this->calculator->calculateForm103($team, (string) $month, (string) $year);

        $reply = "### 📋 Formulario 103 - Retenciones en la Fuente de Impuesto a la Renta ({$data['period']['period_label']})\n\n" .
            "Se han consolidado **{$data['count']} comprobantes de retención** emitidos en este período:\n\n" .
            "💰 **Total Retenido a la Fuente**: **\${$data['total_retained_renta']}**\n\n" .
            "Puedes revisar el detalle de retenciones emitidas a continuación.";

        return [
            'reply' => $reply,
            'action_type' => 'sri_form_103',
            'data' => $data,
            'suggestions' => [
                'Calcular Formulario 104 (IVA)',
                'Ver resumen de ATS',
                'Iniciar nueva extracción SRI',
            ],
        ];
    }

    protected function handleAtsRequest(Team $team, string $text): array
    {
        $month = $this->extractMonth($text) ?: date('m');
        $year = $this->extractYear($text) ?: date('Y');

        $data = $this->calculator->calculateAtsSummary($team, (string) $month, (string) $year);

        $reply = "### 📁 Resumen Anexo Transaccional Simplificado (ATS) - {$data['period']['period_label']}\n\n" .
            "- **Ventas Emitidas Válidas**: {$data['issued_documents']['valid_count']} comprobantes (\${$data['issued_documents']['total']})\n" .
            "- **Ventas Anuladas**: {$data['issued_documents']['voided_count']} comprobantes\n" .
            "- **Compras Recibidas Válidas**: {$data['received_documents']['valid_count']} comprobantes (\${$data['received_documents']['total']})\n" .
            "- **Total Transacciones del Período**: {$data['total_transactions']} documentos";

        return [
            'reply' => $reply,
            'action_type' => 'sri_ats',
            'data' => $data,
            'suggestions' => [
                'Calcular Formulario 104 (IVA)',
                'Generar reporte de ventas y compras',
                'Descargar documentos contables',
            ],
        ];
    }

    protected function handleFinancialReportRequest(Team $team, string $text): array
    {
        $data = $this->calculator->generateFinancialReport($team);

        $reply = "### 📈 Reporte Financiero y Contable Consolidado\n\n" .
            "Resumen del período seleccionado ({$data['range']['from']} al {$data['range']['to']}):\n\n" .
            "- **Total de Comprobantes**: {$data['totals']['total_documents']}\n" .
            "- **Total Emitido (Ventas)**: \${$data['totals']['issued_total']}\n" .
            "- **Total Recibido (Compras)**: \${$data['totals']['received_total']}\n" .
            "- **Balance Neto**: \${$data['totals']['net_balance']}\n";

        return [
            'reply' => $reply,
            'action_type' => 'financial_report',
            'data' => $data,
            'suggestions' => [
                'Ver Formulario 104 (IVA)',
                'Buscar facturas del mes',
                'Nueva extracción SRI',
            ],
        ];
    }

    protected function handleExtractionRequest(Team $team, string $text): array
    {
        $company = $team->company;
        $companyRuc = $company?->ruc ?? '';

        $reply = "### 🔄 Extracción y Sincronización de Documentos del SRI\n\n" .
            "Por favor indica el **rango de fechas** (Fecha Desde y Fecha Hasta) para solicitar la extracción automática de comprobantes electrónicos desde el SRI:";

        return [
            'reply' => $reply,
            'action_type' => 'extraction_launcher',
            'data' => [
                'default_ruc' => $companyRuc,
                'default_date_from' => now()->startOfMonth()->toDateString(),
                'default_date_to' => now()->toDateString(),
            ],
            'suggestions' => [
                '📊 Calcular Formulario 104 (IVA)',
                '📄 Ver comprobantes ya registrados',
            ],
        ];
    }

    protected function handleDocumentSearchRequest(Team $team, string $text): array
    {
        $query = $team->fiscalDocuments()->latest()->take(10)->get();

        if ($query->count() === 0) {
            return [
                'reply' => "### 📄 Documentos Contables Encontrados\n\nNo se encontraron documentos contables registrados en el sistema. Puedes seleccionar el rango de fechas e iniciar una nueva extracción del SRI.",
                'action_type' => 'document_list',
                'data' => [
                    'documents' => [],
                ],
                'suggestions' => [
                    '🔄 Iniciar Extracción del SRI',
                    '📊 Calcular Formulario 104 (IVA)',
                ],
            ];
        }

        $documents = $query->map(function (FiscalDocument $doc) use ($team) {
            return [
                'id' => $doc->id,
                'access_key' => $doc->access_key,
                'type' => $doc->type->value,
                'type_label' => match($doc->type) {
                    FiscalDocumentType::Invoice => 'Factura',
                    FiscalDocumentType::CreditNote => 'Nota de Crédito',
                    FiscalDocumentType::DebitNote => 'Nota de Débito',
                    FiscalDocumentType::Retention => 'Retención',
                    FiscalDocumentType::PurchaseSettlement => 'Liquidación',
                    FiscalDocumentType::RemissionGuide => 'Guía Remisión',
                },
                'direction' => $doc->direction->value,
                'issuer_name' => $doc->issuer_name ?: $doc->issuer_ruc,
                'receiver_name' => $doc->receiver_name ?: $doc->receiver_ruc,
                'issued_at' => $doc->issued_at?->toDateString(),
                'subtotal' => (float) $doc->subtotal,
                'tax_amount' => (float) $doc->tax_amount,
                'total' => (float) $doc->total,
                'has_xml' => $doc->hasXml(),
                'has_pdf' => $doc->hasPdf(),
                'has_json' => $doc->hasJson(),
                'xml_url' => route('documents.download.xml', ['current_team' => $team->slug, 'document' => $doc->id]),
                'pdf_url' => route('documents.download.pdf', ['current_team' => $team->slug, 'document' => $doc->id]),
                'json_url' => route('documents.download.json', ['current_team' => $team->slug, 'document' => $doc->id]),
            ];
        })->values()->all();

        $reply = "### 📄 Documentos Contables Encontrados\n\n" .
            "Se muestran los **" . count($documents) . " comprobantes más recientes** registrados en el sistema. Puedes descargar sus archivos disponibles (XML, PDF, JSON):";

        return [
            'reply' => $reply,
            'action_type' => 'document_list',
            'data' => [
                'documents' => $documents,
            ],
            'suggestions' => [
                'Calcular Formulario 104 (IVA)',
                'Generar reporte financiero',
                'Nueva extracción SRI',
            ],
        ];
    }

    protected function callLlmApi(Team $team, string $userMessage, ?string $model): ?array
    {
        $geminiKey = env('GEMINI_API_KEY');
        $openaiKey = env('OPENAI_API_KEY');

        if ($geminiKey) {
            try {
                $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent?key={$geminiKey}", [
                    'contents' => [
                        [
                            'parts' => [
                                ['text' => "Eres AnahiAI, un asistente experto contable y fiscal para Ecuador. Responde en español conciso usando formato Markdown a esta consulta contable del usuario: {$userMessage}"]
                            ]
                        ]
                    ]
                ]);

                if ($response->successful()) {
                    $text = $response->json('candidates.0.content.parts.0.text');
                    if ($text) {
                        return [
                            'reply' => $text,
                            'action_type' => 'general_chat',
                            'data' => [],
                            'suggestions' => [
                                'Calcular Formulario 104 (IVA)',
                                'Generar reporte de ventas',
                                'Descargar documentos contables',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::error("Gemini API call failed: " . $e->getMessage());
            }
        }

        if ($openaiKey) {
            try {
                $response = Http::withToken($openaiKey)->post("https://api.openai.com/v1/chat/completions", [
                    'model' => 'gpt-4o-mini',
                    'messages' => [
                        ['role' => 'system', 'content' => 'Eres AnahiAI, un asistente contable y tributario ecuatoriano preciso y conciso.'],
                        ['role' => 'user', 'content' => $userMessage],
                    ],
                ]);

                if ($response->successful()) {
                    $text = $response->json('choices.0.message.content');
                    if ($text) {
                        return [
                            'reply' => $text,
                            'action_type' => 'general_chat',
                            'data' => [],
                            'suggestions' => [
                                'Calcular Formulario 104 (IVA)',
                                'Formulario 103 (Retenciones)',
                                'Iniciar extracción SRI',
                            ],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                Log::error("OpenAI API call failed: " . $e->getMessage());
            }
        }

        return null;
    }

    protected function handleGenericConversational(Team $team, string $userMessage): array
    {
        $reply = "¡Hola! Soy **AnahiAI**, tu asistente fiscal y contable para Ecuador 🇪🇨.\n\n" .
            "Puedo ayudarte a realizar las siguientes tareas de forma automática:\n\n" .
            "- 📊 **Cálculo de Formularios SRI**: Formulario 104 (IVA), Formulario 103 (Retenciones Renta) y ATS.\n" .
            "- 🔄 **Extracción de Documentos SRI**: Descargar comprobantes electrónicos emitidos y recibidos seleccionando el rango de fechas.\n" .
            "- 📥 **Búsqueda y Descarga Contable**: Archivos XML, RIDE/PDF y JSON de tus facturas y retenciones.\n" .
            "- 📈 **Reportes Financieros**: Consolidado de ventas, compras y balances contables.\n\n" .
            "¿Qué deseas consultar o calcular hoy?";

        return [
            'reply' => $reply,
            'action_type' => 'general_chat',
            'data' => [],
            'suggestions' => [
                '📊 Calcular Formulario 104 (IVA)',
                '📋 Ver Formulario 103 (Retenciones)',
                '📄 Descargar facturas y XMLs',
                '🔄 Iniciar Extracción del SRI',
            ],
        ];
    }

    protected function extractMonth(string $text): ?int
    {
        $months = [
            'enero' => 1, 'febrero' => 2, 'marzo' => 3, 'abril' => 4,
            'mayo' => 5, 'junio' => 6, 'julio' => 7, 'agosto' => 8,
            'septiembre' => 9, 'octubre' => 10, 'noviembre' => 11, 'diciembre' => 12
        ];

        foreach ($months as $name => $num) {
            if (Str::contains($text, $name)) {
                return $num;
            }
        }

        return null;
    }

    protected function extractYear(string $text): ?int
    {
        if (preg_match('/20\d{2}/', $text, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }
}
