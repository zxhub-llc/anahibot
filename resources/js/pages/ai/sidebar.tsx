// resources/js/layouts/chat-layout.tsx
import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';

import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { UserTime01Icon } from '@hugeicons/core-free-icons';
import { HugeiconsIcon } from '@hugeicons/react';

interface SharedProps {
    currentTeam?: {
        slug: string;
    } | null;
    [key: string]: unknown;
}

export default function ChatLayout({ children }: PropsWithChildren) {
    const { props } = usePage<SharedProps>();
    const teamSlug = props.currentTeam?.slug || 'default';

    const handleNewChat = () => {
        if (typeof window !== 'undefined') {
            try {
                localStorage.removeItem(`anahisoft_chat_history_${teamSlug}`);
                localStorage.removeItem(`anahisoft_chat_suggestions_${teamSlug}`);
                window.location.reload();
            } catch (e) { }
        }
    };

    return (
        <div className="flex h-screen flex-col px-4 py-6">
            <div className="shrink-0">
                <Heading
                    title="Asistente IA"
                    description="Gestiona tus conversaciones y consultas contables"
                />
            </div>

            <div className="flex min-h-0 flex-1 flex-col lg:flex-row lg:space-x-12">
                {/* Aside fijo con scroll propio en el listado */}
                <aside className="flex w-full max-w-xl flex-col lg:h-full lg:w-48">
                    <nav
                        className="flex flex-1 flex-col space-y-1 space-x-0 overflow-y-auto"
                        aria-label="Chat"
                    >
                        <Button
                            size="sm"
                            variant="ghost"
                            onClick={handleNewChat}
                            className="w-full justify-start gap-2 bg-muted/50 hover:bg-muted"
                        >
                            <HugeiconsIcon
                                icon={UserTime01Icon}
                                className="size-5 opacity-80 group-hover:opacity-100"
                            />
                            <span>Nuevo chat</span>
                        </Button>
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                {/* Contenido con scroll propio */}
                <div className="flex min-h-0 flex-1 flex-col md:max-w-2xl">
                    <section className="max-w-xl flex-1 space-y-12 overflow-y-auto">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}