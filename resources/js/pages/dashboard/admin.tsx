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

interface AdminDashboardProps {
    stats: {
        total_caregivers: number;
        active_caregivers: number;
        total_clients: number;
    };
}

export default function AdminDashboard({ stats }: AdminDashboardProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold text-foreground">
                        Admin Dashboard
                    </h1>
                    <p className="text-muted-foreground">
                        Manage your application
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-4">
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Total Caregivers
                        </p>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.total_caregivers}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Active Caregivers
                        </p>
                        <p className="text-2xl font-bold text-green-600">
                            {stats.active_caregivers}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Total Clients
                        </p>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.total_clients}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Pending Approvals
                        </p>
                        <p className="text-2xl font-bold text-amber-600">0</p>
                    </div>
                </div>

                <div className="relative min-h-[300px] flex-1 overflow-hidden rounded-xl border border-border bg-card">
                    <PlaceholderPattern className="absolute inset-0 size-full stroke-ring/20" />
                    <div className="p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            Recent Activity
                        </h2>
                        <p className="text-muted-foreground">
                            View recent system activity
                        </p>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
