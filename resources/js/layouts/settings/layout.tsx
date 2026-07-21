import { Link, usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import Heading from '@/components/heading';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { useCurrentUrl } from '@/hooks/use-current-url';
import { cn, toUrl } from '@/lib/utils';
import { edit as editAppearance } from '@/routes/appearance';
import { edit as editProfile } from '@/routes/profile';
// import { edit as editPushNotifications } from '@/routes/push-notifications';
import { edit as editSecurity } from '@/routes/security';
import { pause as pauseRoute } from '@/routes/settings/caregiver';
import type { NavItem } from '@/types';

const sidebarNavItems: NavItem[] = [
    {
        title: 'Profile',
        href: editProfile(),
        icon: null,
    },
    {
        title: 'Availability Preferences',
        href: '/settings/caregiver/availability',
        icon: null,
    },
    {
        title: 'Languages Spoken',
        href: '/settings/caregiver/languages',
        icon: null,
    },
    {
        title: 'Calendar Sync',
        href: '/settings/caregiver/calendar-sync',
        icon: null,
    },
    {
        title: 'Pause Account',
        href: pauseRoute(),
        icon: null,
    },
    {
        title: 'Appearance',
        href: editAppearance(),
        icon: null,
    },
    {
        title: 'Security',
        href: editSecurity(),
        icon: null,
    },
    // Push Notifications temporarily hidden from the nav — feature still buggy.
    // The route/page remain; re-add this item to restore it.
    // {
    //     title: 'Push Notifications',
    //     href: editPushNotifications(),
    //     icon: null,
    // },
];

export default function SettingsLayout({ children }: PropsWithChildren) {
    const { auth } = usePage().props as unknown as {
        auth: { user: { role: string } | null };
    };
    const { isCurrentOrParentUrl } = useCurrentUrl();

    const visibleItems = sidebarNavItems.filter((item) => {
        if (
            item.title === 'Pause Account' ||
            item.title === 'Availability Preferences' ||
            item.title === 'Languages Spoken' ||
            item.title === 'Calendar Sync'
        ) {
            return auth.user?.role === 'caregiver';
        }

        return true;
    });

    // When server-side rendering, we only render the layout on the client...
    if (typeof window === 'undefined') {
        return null;
    }

    return (
        <div className="px-4 py-6">
            <Heading
                title="Settings"
                description="Manage your profile and account settings"
            />

            <div className="flex flex-col lg:flex-row lg:space-x-12">
                <aside className="w-full max-w-xl lg:w-48">
                    <nav
                        className="flex flex-col space-y-1 space-x-0"
                        aria-label="Settings"
                    >
                        {visibleItems.map((item, index) => (
                            <Button
                                key={`${toUrl(item.href)}-${index}`}
                                size="sm"
                                variant="ghost"
                                asChild
                                className={cn('w-full justify-start', {
                                    'bg-muted': isCurrentOrParentUrl(item.href),
                                })}
                            >
                                <Link href={item.href}>
                                    {item.icon && (
                                        <item.icon className="h-4 w-4" />
                                    )}
                                    {item.title}
                                </Link>
                            </Button>
                        ))}
                    </nav>
                </aside>

                <Separator className="my-6 lg:hidden" />

                <div className="flex-1 md:max-w-2xl">
                    <section className="max-w-xl space-y-12">
                        {children}
                    </section>
                </div>
            </div>
        </div>
    );
}
