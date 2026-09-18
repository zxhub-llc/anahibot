import { Link, usePage } from '@inertiajs/react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
import { TeamSwitcher } from '@/components/team-switcher';
import {
    Sidebar,
    SidebarContent,
    SidebarFooter,
    SidebarHeader,
    SidebarMenu,
    SidebarMenuButton,
    SidebarMenuItem,
} from '@/components/ui/sidebar';
import { dashboard } from '@/routes';
import { index as indexAnahiAi } from '@/routes/ai';
import { edit as editSri } from '@/routes/sri-credentials';
import { edit as editCompanies } from '@/routes/companies';
import { index as indexDocuments } from '@/routes/documents';
import { index as indexWebhooks } from '@/routes/webhooks';
import { index as indexExtractions } from '@/routes/extractions';

import type { NavItem } from '@/types';
import { BookOpen01Icon, BuildingIcon, ChatBotIcon, CloudDownloadIcon, DashboardSpeed02Icon, FolderGitTwoIcon, IdCardLanyardIcon, LegalDocument02Icon, WebhookIcon } from '@hugeicons/core-free-icons';

export function AppSidebar() {
    const page = usePage();
    const currentTeam = page.props.currentTeam as { slug: string } | undefined;
    const teamParam = currentTeam?.slug ? { current_team: currentTeam.slug } : undefined;

    const dashboardUrl = currentTeam ? dashboard(currentTeam.slug) : '/';

    const mainNavItems: NavItem[] = [
        {
            title: 'Dashboard',
            href: dashboardUrl,
            icon: DashboardSpeed02Icon,
        },
        {
            title: 'Anahi AI',
            href: teamParam ? indexAnahiAi(teamParam).url : '#',
            icon: ChatBotIcon,
        },
        {
            title: 'Companies',
            href: teamParam ? editCompanies(teamParam).url : '#',
            icon: BuildingIcon,
        },
        {
            title: 'Sri',
            href: teamParam ? editSri(teamParam).url : '#',
            icon: IdCardLanyardIcon,
        },
        {
            title: 'Documents',
            href: teamParam ? indexDocuments(teamParam).url : '#',
            icon: LegalDocument02Icon,
        },
        {
            title: 'Webhooks',
            href: teamParam ? indexWebhooks(teamParam).url : '#',
            icon: WebhookIcon,
        },
        {
            title: 'Extractions',
            href: teamParam ? indexExtractions(teamParam).url : '#',
            icon: CloudDownloadIcon,
        },
    ];

    const footerNavItems: NavItem[] = [
        {
            title: 'Repository',
            href: 'https://github.com/laravel/react-starter-kit',
            icon: FolderGitTwoIcon,
        },
        {
            title: 'Documentation',
            href: 'https://laravel.com/docs/starter-kits#react',
            icon: BookOpen01Icon,
        },
    ];

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboardUrl} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <TeamSwitcher />
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={mainNavItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
