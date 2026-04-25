import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    User as UserIcon,
    Plus,
    ChevronRight,
    CheckCircle2,
    CalendarCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTime, formatDisplayTime } from '@/lib/datetime';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    caregiver?: {
        user: {
            name: string;
        };
    };
    start_datetime: string;
    end_datetime: string;
    status: string;
}

interface ClientDashboardProps {
    user: {
        name: string;
    };
    client: {
        next_booking: Booking & {
            caregiver: {
                user: {
                    name: string;
                };
            };
        };
    };
    stats: {
        total_bookings: number;
        completed_bookings: number;
        upcoming_bookings: Booking[];
        recent_bookings: Booking[];
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function ClientDashboard({
    user,
    stats,
    client,
}: ClientDashboardProps) {
    const upcoming_bookings = stats?.upcoming_bookings || [];
    const recent_bookings = stats?.recent_bookings || [];

    const renderStatusBadge = (status: string) => {
        const statusLower = status.toLowerCase();
        const displayStatus = status.toUpperCase();

        if (statusLower === 'confirmed') {
            return (
                <Badge
                    variant="default"
                    className="bg-green-600 text-[10px] hover:bg-green-600"
                >
                    {displayStatus}
                </Badge>
            );
        }

        if (statusLower === 'pending') {
            return (
                <Badge
                    variant="default"
                    className="bg-yellow-600 text-[10px] hover:bg-yellow-600"
                >
                    {displayStatus}
                </Badge>
            );
        }

        return (
            <Badge variant="secondary" className="text-[10px]">
                {displayStatus}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Client Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Welcome back, {user.name}
                        </h1>
                        <p className="text-muted-foreground">
                            Here is what is happening with your bookings.
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/bookings/create">
                            <Plus className="mr-2 h-4 w-4" /> New Booking
                        </Link>
                    </Button>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow">
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Total Bookings
                            </h3>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {stats.total_bookings}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow">
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Completed
                            </h3>
                            <CheckCircle2 className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {stats.completed_bookings}
                        </div>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow">
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Upcoming
                            </h3>
                            <Clock className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {upcoming_bookings.length}
                        </div>
                    </div>
                </div>

                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                    <div className="col-span-4 rounded-xl border border-border bg-card text-card-foreground shadow">
                        <div className="flex flex-col space-y-1.5 p-6">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Next Booking
                            </h3>
                        </div>
                        <div className="p-6 pt-0">
                            {client.next_booking ? (
                                <div className="rounded-lg border border-primary/20 bg-primary/5 p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                            <CalendarCheck className="h-6 w-6" />
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium tracking-tight text-muted-foreground uppercase">
                                                {
                                                    client.next_booking
                                                        .service_type
                                                }{' '}
                                                Service
                                            </h3>
                                            <p className="text-lg font-bold">
                                                {formatDisplayDateTime(
                                                    client.next_booking
                                                        .start_datetime,
                                                )}
                                            </p>
                                        </div>
                                    </div>

                                    <div className="mb-6 grid gap-2">
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <UserIcon className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                Caregiver:{' '}
                                                {client.next_booking.caregiver
                                                    ?.user.name ||
                                                    'Not assigned yet'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                Duration:{' '}
                                                {formatDisplayTime(
                                                    client.next_booking
                                                        .start_datetime,
                                                )}{' '}
                                                -{' '}
                                                {formatDisplayTime(
                                                    client.next_booking
                                                        .end_datetime,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <Button asChild className="w-full">
                                        <Link
                                            href={`/bookings/${client.next_booking.ulid}`}
                                        >
                                            View Booking Details
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex h-[200px] flex-col items-center justify-center text-center">
                                    <Calendar className="mb-2 h-10 w-10 text-muted-foreground/30" />
                                    <p className="text-muted-foreground">
                                        No upcoming bookings scheduled.
                                    </p>
                                    <Button
                                        variant="link"
                                        asChild
                                        className="mt-2"
                                    >
                                        <Link href="/bookings/create">
                                            Book a sitter now
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="col-span-3 rounded-xl border border-border bg-card text-card-foreground shadow">
                        <div className="flex flex-col space-y-1.5 p-6">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Recent Activity
                            </h3>
                        </div>
                        <div className="p-6 pt-0">
                            <div className="space-y-4">
                                {recent_bookings.length > 0 ? (
                                    recent_bookings.map((booking) => (
                                        <Link
                                            key={booking.id}
                                            href={`/bookings/${booking.ulid}`}
                                            className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                        >
                                            <div className="flex items-center gap-3">
                                                <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                </div>
                                                <div>
                                                    <p className="text-sm font-medium">
                                                        {formatDisplayDateTime(
                                                            booking.start_datetime,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-muted-foreground">
                                                        {
                                                            booking.caregiver
                                                                ?.user.name
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                            <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                        </Link>
                                    ))
                                ) : (
                                    <p className="py-4 text-center text-sm text-muted-foreground">
                                        No recent activity.
                                    </p>
                                )}

                                <div className="pt-2">
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="w-full"
                                    >
                                        <Link href="/bookings">
                                            View All Bookings
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                    <div className="flex flex-col space-y-1.5 p-6">
                        <h3 className="text-lg leading-none font-semibold tracking-tight">
                            Upcoming Bookings
                        </h3>
                    </div>
                    <div className="p-6 pt-0">
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
                            {upcoming_bookings.length > 0 ? (
                                upcoming_bookings.map((booking) => (
                                    <div
                                        key={booking.id}
                                        className="flex items-center justify-between rounded-lg border border-border bg-card p-3 opacity-80"
                                    >
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                                                <CheckCircle2 className="h-4 w-4 text-green-500" />
                                            </div>
                                            <div>
                                                <p className="text-sm font-medium">
                                                    {formatDisplayDateTime(
                                                        booking.start_datetime,
                                                    )}
                                                </p>
                                                <p className="text-xs text-foreground text-muted-foreground">
                                                    {
                                                        booking.caregiver?.user
                                                            .name
                                                    }
                                                </p>
                                            </div>
                                        </div>
                                        {renderStatusBadge(booking.status)}
                                    </div>
                                ))
                            ) : (
                                <div className="col-span-full py-8 text-center">
                                    <p className="text-muted-foreground">
                                        No upcoming bookings.
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
