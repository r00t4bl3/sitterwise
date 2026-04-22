import { Head, Link } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    User as UserIcon,
    Heart,
    Plus,
    Search,
    ChevronRight,
    CheckCircle2,
    CalendarCheck,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

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
    stats: {
        active_bookings: number;
        past_bookings: number;
        favorite_caregivers: number;
    };
    client?: {
        next_booking: Booking | null;
        upcoming_bookings: Booking[];
        recent_bookings: Booking[];
    };
}

export default function ClientDashboard({
    user,
    stats,
    client,
}: ClientDashboardProps) {
    const formatDateTime = (dateStr: string) => {
        const date = new Date(dateStr);

        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const getStatusBadge = (status: string) => {
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
            <Badge variant="outline" className="text-[10px]">
                {displayStatus}
            </Badge>
        );
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header & Quick Actions */}
                <div className="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            Welcome back, {user.name}!
                        </h1>
                        <p className="text-muted-foreground">
                            Find and book caregivers for your loved ones
                        </p>
                    </div>
                    <div className="flex gap-2">
                        <Button asChild>
                            <Link href="/bookings/create">
                                <Plus className="mr-2 h-4 w-4" />
                                Create Booking
                            </Link>
                        </Button>
                    </div>
                </div>

                {/* Stats Cards */}
                <div className="grid gap-4 sm:grid-cols-3">
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Calendar className="h-4 w-4 text-blue-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Active Bookings
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.active_bookings}
                        </p>
                    </div>
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <CheckCircle2 className="h-4 w-4 text-green-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Past Bookings
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.past_bookings}
                        </p>
                    </div>
                    <div className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm">
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Heart className="h-4 w-4 text-pink-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Favorites
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {stats.favorite_caregivers}
                        </p>
                    </div>
                </div>

                {/* Primary Action / Next Booking */}
                <div className="grid gap-6 lg:grid-cols-2">
                    <div className="flex flex-col gap-4">
                        <h2 className="text-lg font-semibold text-foreground">
                            {client?.next_booking
                                ? 'Your Next Booking'
                                : 'Get Started'}
                        </h2>

                        {client?.next_booking ? (
                            <div className="relative overflow-hidden rounded-xl border-2 border-primary/20 bg-card p-6 shadow-md transition-all hover:border-primary/40">
                                <div className="absolute top-0 right-0 p-4">
                                    {getStatusBadge(client.next_booking.status)}
                                </div>

                                <div className="mb-4 flex items-center gap-3">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <CalendarCheck className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-medium tracking-tight text-muted-foreground uppercase">
                                            {client.next_booking.service_type}{' '}
                                            Service
                                        </h3>
                                        <p className="text-lg font-bold">
                                            {formatDateTime(
                                                client.next_booking
                                                    .start_datetime,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="mb-6 grid gap-2">
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <UserIcon className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">
                                            Caregiver:{' '}
                                            {client.next_booking.caregiver?.user
                                                .name || 'Not assigned yet'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <Clock className="h-4 w-4 text-muted-foreground" />
                                        <span>
                                            Duration:{' '}
                                            {
                                                formatDateTime(
                                                    client.next_booking
                                                        .start_datetime,
                                                ).split(',')[1]
                                            }{' '}
                                            -{' '}
                                            {
                                                formatDateTime(
                                                    client.next_booking
                                                        .end_datetime,
                                                ).split(',')[1]
                                            }
                                        </span>
                                    </div>
                                </div>

                                <Button asChild className="w-full">
                                    <Link
                                        href={`/bookings/${client.next_booking.ulid}`}
                                    >
                                        View Booking Details
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="flex h-full flex-col items-center justify-center rounded-xl border border-dashed border-border bg-card p-8 text-center">
                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                    <Calendar className="h-8 w-8 text-muted-foreground" />
                                </div>
                                <h3 className="mb-2 text-lg font-medium">
                                    No upcoming bookings
                                </h3>
                                <p className="mb-6 text-sm text-muted-foreground">
                                    Book a professional caregiver for your kids
                                    or pets today.
                                </p>
                                <Button asChild size="sm">
                                    <Link href="/bookings/create">
                                        Schedule Now
                                    </Link>
                                </Button>
                            </div>
                        )}
                    </div>

                    {/* Secondary Sections */}
                    <div className="flex flex-col gap-6">
                        {/* Other Upcoming */}
                        {client && client.upcoming_bookings.length > 0 && (
                            <div>
                                <div className="mb-3 flex items-center justify-between">
                                    <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                        More Upcoming
                                    </h2>
                                    <Link
                                        href="/bookings"
                                        className="text-xs font-medium text-primary hover:underline"
                                    >
                                        View All
                                    </Link>
                                </div>
                                <div className="space-y-3">
                                    {client.upcoming_bookings.map((booking) => (
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
                                                        {formatDateTime(
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
                                            {getStatusBadge(booking.status)}
                                        </Link>
                                    ))}
                                </div>
                            </div>
                        )}

                        {/* Recent Activity */}
                        <div>
                            <div className="mb-3 flex items-center justify-between">
                                <h2 className="text-sm font-semibold tracking-wider text-muted-foreground uppercase">
                                    Recent Activity
                                </h2>
                                <Link
                                    href="/bookings"
                                    className="text-xs font-medium text-primary hover:underline"
                                >
                                    History
                                </Link>
                            </div>
                            <div className="space-y-3">
                                {client && client.recent_bookings.length > 0 ? (
                                    client.recent_bookings.map((booking) => (
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
                                                        {formatDateTime(
                                                            booking.start_datetime,
                                                        )}
                                                    </p>
                                                    <p className="text-xs text-foreground text-muted-foreground">
                                                        {
                                                            booking.caregiver
                                                                ?.user.name
                                                        }
                                                    </p>
                                                </div>
                                            </div>
                                            <span className="text-[10px] font-medium text-muted-foreground uppercase">
                                                Completed
                                            </span>
                                        </div>
                                    ))
                                ) : (
                                    <p className="py-4 text-center text-xs text-muted-foreground">
                                        No past activity yet.
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
