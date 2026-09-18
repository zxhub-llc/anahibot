"use client"

import { CheckIcon, DownloadIcon, FileTextIcon, PlayIcon, RefreshCwIcon, CalculatorIcon, RotateCcwIcon, Trash2Icon } from "lucide-react"
import { nanoid } from "nanoid"
import { useCallback, useEffect, useState } from "react"
import { toast } from "sonner"
import { router } from "@inertiajs/react"
import {
    Conversation,
    ConversationContent,
    ConversationScrollButton,
} from "@/components/ai/conversation"
import {
    Message,
    MessageBranch,
    MessageBranchContent,
    MessageContent,
    MessageResponse,
} from "@/components/ai/message"
import {
    ModelSelector,
    ModelSelectorContent,
    ModelSelectorEmpty,
    ModelSelectorGroup,
    ModelSelectorInput,
    ModelSelectorItem,
    ModelSelectorList,
    ModelSelectorLogo,
    ModelSelectorLogoGroup,
    ModelSelectorName,
    ModelSelectorTrigger,
} from "@/components/ai/model-selector"
import {
    PromptInput,
    PromptInputBody,
    PromptInputButton,
    PromptInputFooter,
    type PromptInputMessage,
    PromptInputSubmit,
    PromptInputTextarea,
    PromptInputTools,
} from "@/components/ai/prompt-input"
import { Suggestion, Suggestions } from "@/components/ai/suggestion"
import { Button } from "@/components/ui/button"

interface DocumentItem {
    id: number
    access_key: string
    type: string
    type_label: string
    direction: string
    issuer_name: string
    receiver_name: string
    issued_at: string
    subtotal: number
    tax_amount: number
    total: number
    has_xml?: boolean
    has_pdf?: boolean
    has_json?: boolean
    xml_url: string
    pdf_url: string
    json_url: string
}

interface MessageType {
    key: string
    from: "user" | "assistant"
    versions: {
        id: string
        content: string
        action_type?: string
        data?: any
    }[]
}

const models = [
    {
        id: "gemini-2.0-flash",
        name: "Gemini 2.0 Flash (Económico & Preciso)",
        chef: "Google",
        chefSlug: "google",
        providers: ["google"],
    },
    {
        id: "gpt-4o-mini",
        name: "GPT-4o Mini (OpenAI)",
        chef: "OpenAI",
        chefSlug: "openai",
        providers: ["openai"],
    },
]

const initialSuggestions = [
    "📊 Calcular Formulario 104 (IVA)",
    "📋 Ver Formulario 103 (Retenciones)",
    "📁 Generar resumen ATS de este mes",
    "📄 Descargar facturas y XMLs recientes",
    "🔄 Iniciar nueva extracción del SRI",
]

const defaultWelcomeMessage: MessageType[] = [
    {
        key: "init-welcome",
        from: "assistant",
        versions: [
            {
                id: "v-welcome",
                content: "¡Hola! Soy **AnahiAI**, tu asistente fiscal y contable para Ecuador 🇪🇨.\n\nPuedes pedirme calcular tus **Formularios 104 (IVA)**, **103 (Retenciones Renta)**, generar tu **ATS**, extraer documentos del SRI o descargar archivos **XML / PDF / JSON** de tu facturación.",
            },
        ],
    },
]

interface ChatbotDemoProps {
    currentTeam?: {
        id: number
        slug: string
        name: string
    } | null
}

export function ChatbotDemo({ currentTeam }: ChatbotDemoProps) {
    const teamSlug = currentTeam?.slug || "default"
    const storageKey = `anahisoft_chat_history_${teamSlug}`
    const suggestionsKey = `anahisoft_chat_suggestions_${teamSlug}`

    const [model, setModel] = useState<string>(models[0].id)
    const [modelSelectorOpen, setModelSelectorOpen] = useState(false)
    const [text, setText] = useState<string>("")
    const [status, setStatus] = useState<"submitted" | "streaming" | "ready" | "error">("ready")

    const [suggestions, setSuggestions] = useState<string[]>(() => {
        if (typeof window !== "undefined") {
            try {
                const saved = localStorage.getItem(suggestionsKey)
                if (saved) {
                    const parsed = JSON.parse(saved)
                    if (Array.isArray(parsed) && parsed.length > 0) return parsed
                }
            } catch (e) { }
        }
        return initialSuggestions
    })

    const [messages, setMessages] = useState<MessageType[]>(() => {
        if (typeof window !== "undefined") {
            try {
                const saved = localStorage.getItem(storageKey)
                if (saved) {
                    const parsed = JSON.parse(saved)
                    if (Array.isArray(parsed) && parsed.length > 0) return parsed
                }
            } catch (e) { }
        }
        return defaultWelcomeMessage
    })

    // Guardar en localStorage cuando cambian las conversaciones
    useEffect(() => {
        if (typeof window !== "undefined" && messages.length > 0) {
            try {
                localStorage.setItem(storageKey, JSON.stringify(messages))
            } catch (e) { }
        }
    }, [messages, storageKey])

    // Guardar sugerencias en localStorage
    useEffect(() => {
        if (typeof window !== "undefined" && suggestions.length > 0) {
            try {
                localStorage.setItem(suggestionsKey, JSON.stringify(suggestions))
            } catch (e) { }
        }
    }, [suggestions, suggestionsKey])

    const handleClearChat = () => {
        setMessages(defaultWelcomeMessage)
        setSuggestions(initialSuggestions)
        if (typeof window !== "undefined") {
            try {
                localStorage.removeItem(storageKey)
                localStorage.removeItem(suggestionsKey)
            } catch (e) { }
        }
        toast.success("Historial de chat reiniciado")
    }

    const selectedModelData = models.find(m => m.id === model)

    // Formulario interactivo para lanzar extracciones SRI
    const [extractionData, setExtractionData] = useState({
        company_ruc: "",
        date_from: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split("T")[0],
        date_to: new Date().toISOString().split("T")[0],
    })

    const sendChatMessage = useCallback(
        async (userPrompt: string) => {
            if (!userPrompt.trim()) return

            const userMsgId = `user-${Date.now()}`
            const assistantMsgId = `assistant-${Date.now()}`

            // Insert user message
            setMessages(prev => [
                ...prev,
                {
                    key: userMsgId,
                    from: "user",
                    versions: [{ id: userMsgId, content: userPrompt }],
                },
            ])

            setStatus("submitted")

            try {
                const endpoint = teamSlug ? `/${teamSlug}/ai/chat` : "/ai/chat"
                const response = await fetch(endpoint, {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                        "Accept": "application/json",
                        "X-CSRF-TOKEN": (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content || "",
                    },
                    body: JSON.stringify({
                        message: userPrompt,
                        model: model,
                    }),
                })

                if (!response.ok) {
                    throw new Error("Error en el servidor al procesar la solicitud.")
                }

                const resData = await response.json()

                setMessages(prev => [
                    ...prev,
                    {
                        key: assistantMsgId,
                        from: "assistant",
                        versions: [
                            {
                                id: assistantMsgId,
                                content: resData.reply || "He procesado tu solicitud.",
                                action_type: resData.action_type,
                                data: resData.data,
                            },
                        ],
                    },
                ])

                if (Array.isArray(resData.suggestions) && resData.suggestions.length > 0) {
                    setSuggestions(resData.suggestions)
                }

                setStatus("ready")
            } catch (err: any) {
                toast.error("No se pudo conectar con el servicio de IA.", {
                    description: err?.message || "Revisa tu conexión o vuelve a intentarlo.",
                })
                setStatus("ready")
            }
        },
        [teamSlug, model],
    )

    const handleSubmit = (message: PromptInputMessage) => {
        if (!message.text.trim()) return
        const prompt = message.text
        setText("")
        sendChatMessage(prompt)
    }

    const handleSuggestionClick = (suggestion: string) => {
        sendChatMessage(suggestion)
    }

    const handleLaunchExtraction = (defaultRuc: string) => {
        const rucToUse = extractionData.company_ruc || defaultRuc
        if (!rucToUse) {
            toast.error("Por favor ingresa un RUC de empresa válido.")
            return
        }

        router.post(
            `/${teamSlug}/extractions`,
            {
                company_ruc: rucToUse,
                date_from: extractionData.date_from,
                date_to: extractionData.date_to,
            },
            {
                onSuccess: () => {
                    toast.success("¡Proceso de Extracción iniciado en segundo plano!")
                },
                onError: (errors) => {
                    toast.error("Error al iniciar extracción: " + Object.values(errors).join(", "))
                },
            }
        )
    }

    // Custom UI Widgets based on backend action_type
    const renderWidget = (actionType?: string, data?: any) => {
        if (!actionType || !data) return null

        switch (actionType) {
            case "sri_form_104":
                return (
                    <div className="mt-3 rounded-lg border bg-card p-4 text-card-foreground shadow-sm text-sm space-y-3">
                        <div className="flex items-center justify-between border-b pb-2">
                            <span className="font-semibold text-primary flex items-center gap-1.5">
                                <CalculatorIcon className="size-4" /> Formulario 104 (IVA) - {data.period?.period_label}
                            </span>
                            <span className="text-xs bg-primary/10 text-primary px-2 py-0.5 rounded font-mono">
                                SRI Ecuador
                            </span>
                        </div>
                        <div className="grid grid-cols-2 gap-3 pt-1">
                            <div className="rounded bg-muted/50 p-2.5">
                                <div className="text-xs text-muted-foreground">Ventas Netas (Subtotal)</div>
                                <div className="text-base font-bold">${data.sales?.subtotal?.toFixed(2)}</div>
                                <div className="text-xs text-muted-foreground mt-0.5">IVA Ventas: ${data.sales?.iva?.toFixed(2)}</div>
                            </div>
                            <div className="rounded bg-muted/50 p-2.5">
                                <div className="text-xs text-muted-foreground">Compras Netas (Subtotal)</div>
                                <div className="text-base font-bold">${data.purchases?.subtotal?.toFixed(2)}</div>
                                <div className="text-xs text-muted-foreground mt-0.5">IVA Compras: ${data.purchases?.iva?.toFixed(2)}</div>
                            </div>
                        </div>
                        <div className="border-t pt-2 space-y-1.5">
                            <div className="flex justify-between text-xs">
                                <span>Débito Fiscal (IVA Ventas):</span>
                                <span className="font-mono">${data.summary?.debito_fiscal?.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Crédito Fiscal (IVA Compras):</span>
                                <span className="font-mono">-${data.summary?.credito_fiscal?.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-xs">
                                <span>Retenciones de IVA Sufridas:</span>
                                <span className="font-mono">-${data.retentions_supported?.toFixed(2)}</span>
                            </div>
                            <div className="flex justify-between text-sm font-bold border-t pt-1.5 text-emerald-600 dark:text-emerald-400">
                                <span>{data.summary?.impuesto_causado > 0 ? "Impuesto a Pagar:" : "Crédito Tributario Resultante:"}</span>
                                <span className="font-mono">
                                    ${data.summary?.impuesto_causado > 0
                                        ? data.summary?.impuesto_causado?.toFixed(2)
                                        : data.summary?.credito_tributario_resultante?.toFixed(2)}
                                </span>
                            </div>
                        </div>
                    </div>
                )

            case "sri_form_103":
                return (
                    <div className="mt-3 rounded-lg border bg-card p-4 text-card-foreground shadow-sm text-sm space-y-3">
                        <div className="flex items-center justify-between border-b pb-2">
                            <span className="font-semibold text-primary flex items-center gap-1.5">
                                <FileTextIcon className="size-4" /> Formulario 103 (Retenciones Renta)
                            </span>
                            <span className="text-xs font-mono">{data.period?.period_label}</span>
                        </div>
                        <div className="flex justify-between items-center bg-primary/5 p-3 rounded-lg">
                            <div>
                                <div className="text-xs text-muted-foreground">Comprobantes Emitidos</div>
                                <div className="text-lg font-bold">{data.count} retenciones</div>
                            </div>
                            <div className="text-right">
                                <div className="text-xs text-muted-foreground">Total Retenido</div>
                                <div className="text-xl font-black text-primary">${data.total_retained_renta?.toFixed(2)}</div>
                            </div>
                        </div>
                    </div>
                )

            case "document_list":
                if (!data.documents || data.documents.length === 0) {
                    return (
                        <div className="mt-3 rounded-lg border bg-muted/30 p-3.5 text-xs text-muted-foreground flex items-center gap-2">
                            <FileTextIcon className="size-4 text-muted-foreground/70 shrink-0" />
                            <span>No existen documentos contables registrados en el sistema. Puedes indicar el rango de fechas para iniciar una nueva extracción del SRI.</span>
                        </div>
                    )
                }

                return (
                    <div className="mt-3 rounded-lg border bg-card p-3 shadow-sm space-y-2">
                        <div className="text-xs font-semibold text-muted-foreground flex items-center gap-1 pb-1 border-b">
                            <FileTextIcon className="size-3.5" /> Descarga de Comprobantes ({data.documents.length})
                        </div>
                        <div className="max-h-60 overflow-y-auto space-y-2 pr-1">
                            {data.documents.map((doc: DocumentItem) => (
                                <div key={doc.id} className="flex flex-col sm:flex-row justify-between items-start sm:items-center p-2.5 bg-muted/40 rounded border gap-2 text-xs">
                                    <div>
                                        <div className="font-semibold flex items-center gap-1.5">
                                            <span className="uppercase px-1.5 py-0.5 rounded bg-primary/10 text-primary text-[10px]">
                                                {doc.type_label}
                                            </span>
                                            <span className="truncate max-w-[200px]">{doc.issuer_name || doc.receiver_name}</span>
                                        </div>
                                        <div className="text-muted-foreground mt-0.5">
                                            {doc.issued_at} • Total: <strong className="text-foreground">${doc.total?.toFixed(2)}</strong>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-1 shrink-0">
                                        {doc.has_xml !== false && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-[11px] gap-1"
                                                onClick={() => window.open(doc.xml_url, '_blank')}
                                            >
                                                <DownloadIcon className="size-3" /> XML
                                            </Button>
                                        )}
                                        {doc.has_pdf !== false && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-[11px] gap-1"
                                                onClick={() => window.open(doc.pdf_url, '_blank')}
                                            >
                                                <DownloadIcon className="size-3" /> RIDE/PDF
                                            </Button>
                                        )}
                                        {doc.has_json !== false && (
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                className="h-7 px-2 text-[11px] gap-1"
                                                onClick={() => window.open(doc.json_url, '_blank')}
                                            >
                                                <DownloadIcon className="size-3" /> JSON
                                            </Button>
                                        )}
                                        {!doc.has_xml && !doc.has_pdf && !doc.has_json && (
                                            <span className="text-[11px] text-muted-foreground italic px-1">Sin archivos adjuntos</span>
                                        )}
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>
                )

            case "extraction_launcher":
                return (
                    <div className="mt-3 rounded-lg border bg-gradient-to-br from-card to-muted/30 p-4 text-card-foreground shadow-sm space-y-3 text-xs">
                        <div className="font-semibold text-sm flex items-center gap-1.5 text-primary">
                            <RefreshCwIcon className="size-4 animate-spin-slow" /> Iniciar Extracción Automática SRI
                        </div>
                        <div className="grid grid-cols-1 sm:grid-cols-3 gap-2">
                            <div>
                                <label className="text-[11px] text-muted-foreground block mb-1">RUC Empresa:</label>
                                <input
                                    type="text"
                                    placeholder={data.default_ruc || "1790000000001"}
                                    value={extractionData.company_ruc || data.default_ruc || ""}
                                    onChange={(e) => setExtractionData({ ...extractionData, company_ruc: e.target.value })}
                                    className="w-full rounded border bg-background px-2.5 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none"
                                />
                            </div>
                            <div>
                                <label className="text-[11px] text-muted-foreground block mb-1">Fecha Desde:</label>
                                <input
                                    type="date"
                                    value={extractionData.date_from}
                                    onChange={(e) => setExtractionData({ ...extractionData, date_from: e.target.value })}
                                    className="w-full rounded border bg-background px-2.5 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none"
                                />
                            </div>
                            <div>
                                <label className="text-[11px] text-muted-foreground block mb-1">Fecha Hasta:</label>
                                <input
                                    type="date"
                                    value={extractionData.date_to}
                                    onChange={(e) => setExtractionData({ ...extractionData, date_to: e.target.value })}
                                    className="w-full rounded border bg-background px-2.5 py-1.5 text-xs focus:ring-1 focus:ring-primary outline-none"
                                />
                            </div>
                        </div>
                        <Button
                            onClick={() => handleLaunchExtraction(data.default_ruc)}
                            className="w-full h-8 text-xs font-semibold gap-1.5 mt-2"
                        >
                            <PlayIcon className="size-3.5" /> Iniciar Extracción SRI en Segundo Plano
                        </Button>
                    </div>
                )

            default:
                return null
        }
    }

    return (
        <div className="flex h-full w-full min-h-0 flex-col overflow-hidden">
            {/* Header del Chatbot con botón para Nuevo Chat / Limpiar */}
            <div className="flex items-center justify-between border-b px-4 py-2 bg-muted/20 shrink-0">
                <div className="flex items-center gap-2 text-xs font-semibold text-foreground">
                    <span className="inline-block size-2 rounded-full bg-emerald-500 animate-pulse"></span>
                    <span>AnahiAI Chat</span>
                </div>
                <Button
                    onClick={handleClearChat}
                    variant="ghost"
                    size="sm"
                    className="h-7 text-xs text-muted-foreground hover:text-destructive gap-1 px-2"
                    title="Reiniciar historial de la conversación"
                >
                    <RotateCcwIcon className="size-3.5" />
                    <span>Nuevo chat</span>
                </Button>
            </div>

            {/* BODY Chat window */}
            <Conversation className="min-h-0 flex-1 overflow-y-auto no-scrollbar border-b">
                <ConversationContent>
                    {messages.map(({ versions, key, from }) => (
                        <MessageBranch defaultBranch={0} key={key}>
                            <MessageBranchContent>
                                {versions.map(version => (
                                    <Message from={from} key={`${key}-${version.id}`}>
                                        <div className="w-full">
                                            <MessageContent>
                                                <MessageResponse>{version.content}</MessageResponse>
                                            </MessageContent>

                                            {/* Render dynamic widget if action_type is attached */}
                                            {renderWidget(version.action_type, version.data)}
                                        </div>
                                    </Message>
                                ))}
                            </MessageBranchContent>
                        </MessageBranch>
                    ))}
                </ConversationContent>
                <ConversationScrollButton />
            </Conversation>

            {/* Input area & Suggestions */}
            <div className="shrink-0 space-y-3 pt-3">
                <Suggestions className="px-4">
                    {suggestions.map(suggestion => (
                        <Suggestion
                            key={suggestion}
                            onClick={() => handleSuggestionClick(suggestion)}
                            suggestion={suggestion}
                        />
                    ))}
                </Suggestions>
                <div className="w-full px-4 pb-4">
                    <PromptInput globalDrop onSubmit={handleSubmit}>
                        <PromptInputBody>
                            <PromptInputTextarea
                                onChange={event => setText(event.target.value)}
                                value={text}
                                placeholder="Escribe tu consulta tributaria o contable (ej. Calcular Formulario 104, descargar facturas)..."
                            />
                        </PromptInputBody>
                        <PromptInputFooter>
                            <PromptInputTools>
                                <ModelSelector onOpenChange={setModelSelectorOpen} open={modelSelectorOpen}>
                                    <ModelSelectorTrigger asChild>
                                        <PromptInputButton>
                                            {selectedModelData?.chefSlug && (
                                                <ModelSelectorLogo provider={selectedModelData.chefSlug} />
                                            )}
                                            {selectedModelData?.name && (
                                                <ModelSelectorName>{selectedModelData.name}</ModelSelectorName>
                                            )}
                                        </PromptInputButton>
                                    </ModelSelectorTrigger>
                                    <ModelSelectorContent>
                                        <ModelSelectorInput placeholder="Buscar modelos..." />
                                        <ModelSelectorList>
                                            <ModelSelectorEmpty>No se encontraron modelos.</ModelSelectorEmpty>
                                            {["Google", "OpenAI"].map(chef => (
                                                <ModelSelectorGroup heading={chef} key={chef}>
                                                    {models
                                                        .filter(m => m.chef === chef)
                                                        .map(m => (
                                                            <ModelSelectorItem
                                                                key={m.id}
                                                                onSelect={() => {
                                                                    setModel(m.id)
                                                                    setModelSelectorOpen(false)
                                                                }}
                                                                value={m.id}
                                                            >
                                                                <ModelSelectorLogo provider={m.chefSlug} />
                                                                <ModelSelectorName>{m.name}</ModelSelectorName>
                                                                <ModelSelectorLogoGroup>
                                                                    {m.providers.map(provider => (
                                                                        <ModelSelectorLogo key={provider} provider={provider} />
                                                                    ))}
                                                                </ModelSelectorLogoGroup>
                                                                {model === m.id ? (
                                                                    <CheckIcon className="ml-auto size-4" />
                                                                ) : (
                                                                    <div className="ml-auto size-4" />
                                                                )}
                                                            </ModelSelectorItem>
                                                        ))}
                                                </ModelSelectorGroup>
                                            ))}
                                        </ModelSelectorList>
                                    </ModelSelectorContent>
                                </ModelSelector>
                            </PromptInputTools>
                            <PromptInputSubmit
                                disabled={!text.trim() || status === "submitted"}
                                status={status}
                            />
                        </PromptInputFooter>
                    </PromptInput>
                </div>
            </div>
        </div>
    )
}

export default ChatbotDemo
