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

interface CaregiverDashboardProps {
    caregiver: {
        first_name: string;
        last_name: string;
        rating: number | null;
        status: string;
    };
}

export default function CaregiverDashboard({
    caregiver,
}: CaregiverDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregiver Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold text-foreground">
                        Welcome back, {caregiver.first_name}!
                    </h1>
                    <p className="text-muted-foreground">
                        Manage your availability and appointments
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">Rating</p>
                        <p className="text-2xl font-bold text-foreground">
                            {caregiver.rating
                                ? `${caregiver.rating} ★`
                                : 'No rating'}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <p className="text-2xl font-bold text-foreground">
                            {caregiver.status}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Upcoming Bookings
                        </p>
                        <p className="text-2xl font-bold text-foreground">0</p>
                    </div>
                </div>

                <div className="relative min-h-[300px] flex-1 overflow-hidden rounded-xl border border-border bg-card">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-ring/20" />
                    <div className="p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            My Availability
                        </h2>
                        <p className="text-muted-foreground">
                            Manage your availability schedule
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
