import type { InertiaLinkProps } from '@inertiajs/react';
import type { IconDefinition } from '@fortawesome/fontawesome-svg-core';

export type BreadcrumbItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
};

export type NavItem = {
    title: string;
    href: NonNullable<InertiaLinkProps['href']>;
    icon?: IconDefinition | null;
    isActive?: boolean;
};
