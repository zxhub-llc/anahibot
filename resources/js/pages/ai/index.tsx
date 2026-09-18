import { usePage } from "@inertiajs/react"
import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import ChatLayout from "./sidebar"
import ChatbotDemo from "../chatbot"

interface SharedProps {
    currentTeam?: {
        id: number;
        slug: string;
        name: string;
    } | null;
    [key: string]: unknown;
}

export default function Index() {
    const { props } = usePage<SharedProps>();
    const currentTeam = props.currentTeam;

    return (
        <>
            <SidebarProvider
                style={
                    {
                        "--sidebar-width": "350px",
                    } as React.CSSProperties
                }
            >
                <ChatLayout />
                <SidebarInset className="flex h-screen flex-col overflow-hidden">
                    <div className="flex min-h-70 flex-1 flex-col p-4">
                        <ChatbotDemo currentTeam={currentTeam} />
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </>
    )
}

Index.layout = (props: { currentTeam?: { slug: string } | null }) => ({
    breadcrumbs: [
        {
            title: 'AI',
            href: props.currentTeam ? '/tenant/' + props.currentTeam.slug + '/ai' : '/',
        },
    ],
});
