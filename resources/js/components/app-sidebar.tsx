import { Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    LayoutGrid,
    Search,
    Settings,
    Shield,
    Users,
    User,
} from 'lucide-react';
import AppLogo from '@/components/app-logo';
import { NavFooter } from '@/components/nav-footer';
import { NavMain } from '@/components/nav-main';
import { NavUser } from '@/components/nav-user';
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
import type { NavItem } from '@/types';

const baseNavItems: NavItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
        icon: LayoutGrid,
    },
];

const caregiverNavItems: NavItem[] = [
    {
        title: 'My Availability',
        href: '/my-availability',
        icon: Calendar,
    },
];

const clientNavItems: NavItem[] = [
    {
        title: 'Find Caregiver',
        href: '#',
        icon: Search,
    },
    {
        title: 'My Bookings',
        href: '#',
        icon: Calendar,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Caregivers',
        href: '/caregivers',
        icon: Users,
    },
    {
        title: 'Clients',
        href: '/clients',
        icon: User,
    },
    {
        title: 'Availability',
        href: '/availabilities',
        icon: Calendar,
    },
    {
        title: 'Settings',
        href: '#',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;

    const roleBasedItems = (() => {
        switch (auth.user.role) {
            case 'caregiver':
                return [...baseNavItems];
            case 'admin':
                return [...baseNavItems, ...adminNavItems];
            case 'client':
            default:
                return [...baseNavItems, ...clientNavItems];
        }
    })();

    return (
        <Sidebar collapsible="icon" variant="inset">
            <SidebarHeader>
                <SidebarMenu>
                    <SidebarMenuItem>
                        <SidebarMenuButton size="lg" asChild>
                            <Link href={dashboard()} prefetch>
                                <AppLogo />
                            </Link>
                        </SidebarMenuButton>
                    </SidebarMenuItem>
                </SidebarMenu>
            </SidebarHeader>

            <SidebarContent>
                <NavMain items={roleBasedItems} />
            </SidebarContent>

            <SidebarFooter>
                <NavFooter items={footerNavItems} className="mt-auto" />
                <NavUser />
            </SidebarFooter>
        </Sidebar>
    );
}
