import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useCallback } from 'react';
import AvailabilityWeekGrid from '@/components/availability-week-grid';
import { ToasterMessage } from '@/components/toaster-message';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
    booked_slots?: string[];
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    availabilities: Availability[];
    timeSlots: Array<{ value: string; label: string }>;
}

export default function ManageAvailability() {
    const { caregiver, availabilities } = usePage<Props>().props;

    const fetchMonthUrl = useCallback(
        (y: number, m: number) =>
            `/availabilities/${caregiver.id}?year=${y}&month=${m}`,
        [caregiver.id],
    );

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Availability',
            href: '/availabilities',
        },
        {
            title: `${caregiver.first_name} ${caregiver.last_name}`,
            href: `/caregivers/${caregiver.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${caregiver.first_name} ${caregiver.last_name} - Availability`}
            />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/availabilities"
                            className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="font-serif text-2xl font-bold text-foreground">
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Manage Availability
                            </p>
                        </div>
                    </div>
                </div>

                <div className="rounded-none border border-border bg-card px-6 py-4 shadow-sm">
                    <AvailabilityWeekGrid
                        initial={availabilities}
                        saveUrl={`/availabilities/${caregiver.id}`}
                        fetchMonthUrl={fetchMonthUrl}
                    />
                </div>
            </div>
        </AppLayout>
    );
}
