import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    User as UserIcon,
    Plus,
    ChevronRight,
    CheckCircle2,
    Activity,
} from 'lucide-react';
import QuickLinks from '@/components/quick-links';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import {
    formatDisplayDateTimeInPT,
    formatDisplayTimeInPT,
} from '@/lib/datetime';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    caregiver?: {
        first_name: string;
        last_name: string;
    };
    start_datetime: string;
    end_datetime: string;
    status: string;
}

interface BookingStatus {
    value: string;
    label: string;
    colors: {
        bg: string;
        text: string;
        border: string;
    };
}

interface ClientDashboardProps {
    client: {
        firstName: string;
        nextBooking: Booking & {
            caregiver: {
                first_name: string;
                last_name: string;
            };
        };
    };
    stats: {
        totalBookings: number;
        completedBookings: number;
        upcomingBookings: Booking[];
        recentBookings: Booking[];
    };
    bookingStatuses: BookingStatus[];
    quickLinks?: Array<{
        id: number;
        title: string;
        url: string;
        description: string | null;
        icon: string | null;
        is_external: boolean;
    }>;
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

export default function ClientDashboard({
    stats,
    client,
    bookingStatuses,
    quickLinks,
}: ClientDashboardProps) {
    const upcomingBookings = stats?.upcomingBookings || [];
    const recentBookings = stats?.recentBookings || [];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Client Dashboard" />

            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight">
                            Welcome back, {client.firstName}
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

                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Calendar className="h-4 w-4 text-primary" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Total Bookings
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.totalBookings}
                        </p>
                    </div>
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Completed
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.completedBookings}
                        </p>
                    </div>
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Clock className="h-4 w-4 text-blue-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Upcoming
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {upcomingBookings.length}
                        </p>
                        </div>
                    </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-4">
                        <h3 className="text-lg leading-none font-semibold tracking-tight">
                            Next Booking
                        </h3>
                        <div className="col-span-3 rounded-xl border border-border bg-card text-card-foreground shadow">
                            {client.nextBooking ? (
                                <div className="rounded-lg border border-primary/20 bg-primary/5 p-6">
                                    <div className="mb-4 flex items-center gap-3">
                                        <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                            <Calendar className="h-8 w-8 text-muted-foreground" />
                                        </div>
                                        <div>
                                            <h3 className="text-sm font-medium tracking-tight text-muted-foreground uppercase">
                                                {
                                                    client.nextBooking
                                                        .service_type
                                                }{' '}
                                                Service
                                            </h3>
                                            <p className="text-lg font-bold">
                                                {formatDisplayDateTimeInPT(
                                                    client.nextBooking
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
                                                {client.nextBooking.caregiver
                                                    ? `${client.nextBooking.caregiver.first_name} ${client.nextBooking.caregiver.last_name}`
                                                    : 'Not assigned yet'}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                Duration:{' '}
                                                {formatDisplayTimeInPT(
                                                    client.nextBooking
                                                        .start_datetime,
                                                )}{' '}
                                                -{' '}
                                                {formatDisplayTimeInPT(
                                                    client.nextBooking
                                                        .end_datetime,
                                                )}
                                            </span>
                                        </div>
                                    </div>

                                    <Button asChild className="w-full">
                                        <Link
                                            href={`/bookings/${client.nextBooking.ulid}`}
                                        >
                                            View Booking Details
                                        </Link>
                                    </Button>
                                </div>
                            ) : (
                                <div className="flex h-[200px] flex-col items-center justify-center text-center">
                                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                        <Calendar className="h-8 w-8 text-muted-foreground" />
                                    </div>
                                    <h3 className="mb-4 text-lg font-medium">
                                        No upcoming bookings scheduled.
                                    </h3>
                                    <Button asChild>
                                        <Link href="/bookings/create">
                                            Book a sitter now
                                        </Link>
                                    </Button>
                                </div>
                            )}
                        </div>
                        <h3 className="text-lg leading-none font-semibold tracking-tight">
                            Upcoming Bookings
                        </h3>
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            {upcomingBookings.length > 0 ? (
                                <div className="p-6">
                                    <div className="space-y-2">
                                        {upcomingBookings.map((booking) => (
                                            <Link
                                                key={booking.id}
                                                href={`/bookings/${booking.ulid}`}
                                                className="flex w-full items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                                                        <Activity className="h-4 w-4 text-muted-foreground" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {formatDisplayDateTimeInPT(
                                                                booking.start_datetime,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {booking.caregiver
                                                                ? `${booking.caregiver.first_name} ${booking.caregiver.last_name}`
                                                                : 'No caregiver assigned'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            ) : (
                                <div className="flex h-[200px] flex-col items-center justify-center text-center">
                                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                        <Activity className="h-8 w-8 text-muted-foreground" />
                                    </div>
                                    <h3 className="mb-4 text-lg font-medium">
                                        No upcoming bookings.
                                    </h3>
                                </div>
                            )}
                        </div>
                    </div>

                    <div className="flex flex-col gap-4">
                        <h3 className="text-lg leading-none font-semibold tracking-tight">
                            Recent Activity
                        </h3>
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            <div className="p-6">
                                {recentBookings.length > 0 ? (
                                    <div className="space-y-2">
                                        {recentBookings
                                            .slice(0, 3)
                                            .map((booking) => (
                                                <Link
                                                    key={booking.id}
                                                    href={`/bookings/${booking.ulid}`}
                                                    className="flex w-full items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                                        </div>
                                                        <div className="flex flex-col text-left">
                                                            <p className="text-sm font-medium">
                                                                {formatDisplayDateTimeInPT(
                                                                    booking.start_datetime,
                                                                )}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {booking.caregiver
                                                                    ? `${booking.caregiver.first_name} ${booking.caregiver.last_name}`
                                                                    : ''}
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <StatusBadge
                                                            status={
                                                                booking.status
                                                            }
                                                            bookingStatuses={
                                                                bookingStatuses
                                                            }
                                                        />
                                                        <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                    </div>
                                                </Link>
                                            ))}
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
                                ) : (
                                    <div className="flex h-[100px] flex-col items-center justify-center text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No recent activity
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>
                        {quickLinks && <QuickLinks links={quickLinks} />}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
