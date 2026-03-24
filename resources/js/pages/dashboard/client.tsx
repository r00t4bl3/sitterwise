import { Head } from '@inertiajs/react';
import { PlaceholderPattern } from '@/components/ui/placeholder-pattern';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface ClientDashboardProps {
    user: {
        name: string;
    };
}

export default function ClientDashboard({ user }: ClientDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Client Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold text-foreground">
                        Welcome back, {user.name}!
                    </h1>
                    <p className="text-muted-foreground">
                        Find and book caregivers
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Active Bookings
                        </p>
                        <p className="text-2xl font-bold text-foreground">0</p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Past Bookings
                        </p>
                        <p className="text-2xl font-bold text-foreground">0</p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Favorite Caregivers
                        </p>
                        <p className="text-2xl font-bold text-foreground">0</p>
                    </div>
                </div>

                <div className="relative min-h-[300px] flex-1 overflow-hidden rounded-xl border border-border bg-card">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-ring/20" />
                    <div className="p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            Find a Caregiver
                        </h2>
                        <p className="text-muted-foreground">
                            Search for available caregivers
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
