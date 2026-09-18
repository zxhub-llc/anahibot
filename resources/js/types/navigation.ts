import type { InertiaLinkProps } from '@inertiajs/react';
import type { IconSvgElement } from '@hugeicons/react';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: IconSvgElement | null;
    isActive?: boolean;
};