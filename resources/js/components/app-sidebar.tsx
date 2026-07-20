import { Link, usePage } from '@inertiajs/react';
import {
    Award,
    Calendar,
    ClipboardList,
    CircleDollarSign,
    FileSymlink,
    Home,
    LayoutGrid,
    ListChecks,
    MapPin,
    MessageCircle,
    Settings,
    Shield,
    Star,
    User,
    Users,
    TrendingUp,
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
        title: 'My Jobs',
        href: '/jobs',
        icon: ClipboardList,
    },
    {
        title: 'Available Jobs',
        href: '/bookings',
        icon: ClipboardList,
    },
    // {
    //     title: 'My Availability',
    //     href: '/availabilities',
    //     icon: CalendarRange,
    // },
    {
        title: 'Milestones',
        href: '/milestones',
        icon: TrendingUp,
    },
];

const clientNavItems: NavItem[] = [
    {
        title: 'Bookings',
        href: '/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Payments',
        href: '/payments',
        icon: CircleDollarSign,
    },
];

const adminNavItems: NavItem[] = [
    {
        title: 'Caregivers',
        href: '/caregivers',
        icon: Users,
    },
    {
        title: 'Caregiver Applications',
        href: '/applications',
        icon: ClipboardList,
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
        title: 'Bookings',
        href: '/bookings',
        icon: ClipboardList,
    },
    {
        title: 'Transactions',
        href: '/transactions',
        icon: CircleDollarSign,
    },
    {
        title: 'SMS Broadcast',
        href: '/broadcast-sms',
        icon: MessageCircle,
    },
];

const superAdminNavItems: NavItem[] = [
    {
        title: 'Caregiver Applications',
        href: '/applications',
        icon: ClipboardList,
    },
    {
        title: 'SMS Broadcast',
        href: '/broadcast-sms',
        icon: MessageCircle,
    },
    {
        title: 'Users',
        href: '/users',
        icon: Users,
    },
    {
        title: 'Certifications',
        href: '/certifications',
        icon: Award,
    },
    {
        title: 'Specialties',
        href: '/specialties',
        icon: Star,
    },
    {
        title: 'Locations',
        href: '/locations',
        icon: MapPin,
    },
    {
        title: 'Attributes',
        href: '/attributes',
        icon: Shield,
    },
    {
        title: 'Hotels',
        href: '/hotels',
        icon: Home,
    },
    {
        title: 'Pricing Rules',
        href: '/pricing-rules',
        icon: CircleDollarSign,
    },
    {
        title: 'Quick Links',
        href: '/quick-links',
        icon: FileSymlink,
    },
    {
        title: 'Talking Points',
        href: '/talking-points',
        icon: ListChecks,
    },
    {
        title: 'Settings',
        href: '/app-settings',
        icon: Settings,
    },
];

const footerNavItems: NavItem[] = [];

export function AppSidebar() {
    const { auth } = usePage<{ auth: { user: { role: string } } }>().props;

    const roleBasedItems = (() => {
        switch (auth.user.role) {
            case 'caregiver':
                return [...baseNavItems, ...caregiverNavItems];
            case 'super_admin':
                return [...baseNavItems, ...superAdminNavItems];
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
