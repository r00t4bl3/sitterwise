import { Head, Link } from '@inertiajs/react';
import {
    Users,
    UserCircle,
    Calendar,
    Clock,
    AlertCircle,
    ChevronRight,
    Briefcase,
    Plus,
} from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTime, formatDisplayTime } from '@/lib/datetime';
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
    start_datetime: string;
    end_datetime: string;
    status: string;
    client?: {
        user: {
            name: string;
        };
    };
    caregiver?: {
        user: {
            name: string;
        };
    } | null;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        name: string;
    };
    created_at: string;
}

interface AdminDashboardProps {
    stats: {
        totalCaregivers?: number;
        activeCaregivers?: number;
        totalClients?: number;
        totalBookings?: number;
    };
    admin?: {
        bookingsNeedingAttention: Booking[];
        todaysBookings: Booking[];
        recentBookings: Booking[];
        recentCaregivers: Caregiver[];
    };
}

export default function AdminDashboard({ stats, admin }: AdminDashboardProps) {
    // Provide defaults for safety
    const safeStats = {
        totalCaregivers: 0,
        activeCaregivers: 0,
        totalClients: 0,
        totalBookings: 0,
        ...stats,
    };

    const safeAdmin = admin ?? {
        bookingsNeedingAttention: [],
        todaysBookings: [],
        recentBookings: [],
        recentCaregivers: [],
    };
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

        if (statusLower === 'received') {
            return (
                <Badge
                    variant="default"
                    className="bg-blue-600 text-[10px] hover:bg-blue-600"
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
            <Head title="Admin Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">
                        Admin Dashboard
                    </h1>
                    <p className="text-muted-foreground">
                        Manage your application
                    </p>
                </div>

                {/* Summary Panels - 3 columns */}
                <div className="grid gap-4 md:grid-cols-3">
                    <Link
                        href="/caregivers"
                        className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Caregivers
                            </h3>
                            <Users className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {safeStats.activeCaregivers}
                            <span className="text-lg font-normal text-muted-foreground">
                                {' '}
                                / {safeStats.totalCaregivers}
                            </span>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            Active / Total
                        </p>
                    </Link>

                    <Link
                        href="/clients"
                        className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Clients
                            </h3>
                            <UserCircle className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {safeStats.totalClients}
                        </div>
                        <p className="text-xs text-muted-foreground">Total</p>
                    </Link>

                    <Link
                        href="/bookings"
                        className="rounded-xl border border-border bg-card p-6 text-card-foreground shadow transition-all hover:border-primary/50 hover:shadow-md"
                    >
                        <div className="flex flex-row items-center justify-between space-y-0 pb-2">
                            <h3 className="text-sm font-medium tracking-tight">
                                Bookings
                            </h3>
                            <Calendar className="h-4 w-4 text-muted-foreground" />
                        </div>
                        <div className="text-2xl font-bold">
                            {safeStats.totalBookings}
                        </div>
                        <p className="text-xs text-muted-foreground">Total</p>
                    </Link>
                </div>

                {/* Two Column Layout Below */}
                <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-7">
                    {/* Left Column - Bookings Requiring Attention & Recent Activity */}
                    <div className="col-span-4 flex flex-col gap-4">
                        {/* Bookings Requiring Attention */}
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            <div className="flex flex-col space-y-1.5 p-6">
                                <h3 className="text-lg leading-none font-semibold tracking-tight">
                                    Bookings Requiring Attention
                                </h3>
                            </div>
                            <div className="p-6 pt-0">
                                {safeAdmin.bookingsNeedingAttention.length >
                                0 ? (
                                    <div className="space-y-3">
                                        {safeAdmin.bookingsNeedingAttention.map(
                                            (booking) => (
                                                <Link
                                                    key={booking.id}
                                                    href={`/bookings/${booking.ulid}`}
                                                    className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-8 w-8 items-center justify-center rounded bg-red-100">
                                                            <AlertCircle className="h-4 w-4 text-red-600" />
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {formatDisplayDateTime(
                                                                    booking.start_datetime,
                                                                )}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {booking.client
                                                                    ?.user
                                                                    .name ||
                                                                    'Unknown Client'}{' '}
                                                                •{' '}
                                                                {
                                                                    booking.service_type
                                                                }
                                                            </p>
                                                        </div>
                                                    </div>
                                                    <div className="flex items-center gap-2">
                                                        <Badge
                                                            variant="outline"
                                                            className="border-red-300 text-[10px] text-red-700"
                                                        >
                                                            No Caregiver
                                                        </Badge>
                                                        <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                    </div>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex h-[100px] flex-col items-center justify-center text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No bookings requiring attention
                                        </p>
                                    </div>
                                )}
                                <div className="pt-4">
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

                        {/* Recent Activity */}
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            <div className="flex flex-col space-y-1.5 p-6">
                                <h3 className="text-lg leading-none font-semibold tracking-tight">
                                    Recent Activity
                                </h3>
                            </div>
                            <div className="p-6 pt-0">
                                <div className="space-y-4">
                                    {/* Recent Bookings */}
                                    {safeAdmin.recentBookings.length > 0 && (
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                New Bookings
                                            </h4>
                                            <div className="space-y-2">
                                                {safeAdmin.recentBookings
                                                    .slice(0, 3)
                                                    .map((booking) => (
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
                                                                        {booking
                                                                            .client
                                                                            ?.user
                                                                            .name ||
                                                                            'Unknown'}
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        {formatDisplayDateTime(
                                                                            booking.start_datetime,
                                                                        )}
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <div className="flex items-center gap-2">
                                                                {renderStatusBadge(
                                                                    booking.status,
                                                                )}
                                                                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                            </div>
                                                        </Link>
                                                    ))}
                                            </div>
                                        </div>
                                    )}

                                    {/* Recent Caregivers */}
                                    {safeAdmin.recentCaregivers.length > 0 && (
                                        <div>
                                            <h4 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                New Caregivers
                                            </h4>
                                            <div className="space-y-2">
                                                {safeAdmin.recentCaregivers
                                                    .slice(0, 2)
                                                    .map((caregiver) => (
                                                        <Link
                                                            key={caregiver.id}
                                                            href={`/caregivers/${caregiver.id}`}
                                                            className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex h-8 w-8 items-center justify-center rounded bg-green-100">
                                                                    <Users className="h-4 w-4 text-green-600" />
                                                                </div>
                                                                <div>
                                                                    <p className="text-sm font-medium">
                                                                        {
                                                                            caregiver
                                                                                .user
                                                                                .name
                                                                        }
                                                                    </p>
                                                                    <p className="text-xs text-muted-foreground">
                                                                        Joined
                                                                        recently
                                                                    </p>
                                                                </div>
                                                            </div>
                                                            <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                        </Link>
                                                    ))}
                                            </div>
                                        </div>
                                    )}

                                    {safeAdmin.recentBookings.length === 0 &&
                                        safeAdmin.recentBookings.length ===
                                            0 && (
                                            <p className="py-4 text-center text-sm text-muted-foreground">
                                                No recent activity
                                            </p>
                                        )}
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column - Today's Schedule */}
                    <div className="col-span-3 flex flex-col gap-4">
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            <div className="flex flex-col space-y-1.5 p-6">
                                <h3 className="text-lg leading-none font-semibold tracking-tight">
                                    Today&apos;s Schedule
                                </h3>
                            </div>
                            <div className="p-6 pt-0">
                                {safeAdmin.todaysBookings.length > 0 ? (
                                    <div className="space-y-3">
                                        {safeAdmin.todaysBookings.map(
                                            (booking) => (
                                                <div
                                                    key={booking.id}
                                                    className="flex items-center justify-between rounded-lg border border-border bg-card p-3"
                                                >
                                                    <div className="flex items-center gap-3">
                                                        <div className="flex h-10 w-10 flex-col items-center justify-center rounded bg-primary/10 text-primary">
                                                            <Clock className="h-4 w-4" />
                                                            <span className="text-[10px] font-bold">
                                                                {formatDisplayTime(
                                                                    booking.start_datetime,
                                                                )}
                                                            </span>
                                                        </div>
                                                        <div>
                                                            <p className="text-sm font-medium">
                                                                {booking.client
                                                                    ?.user
                                                                    .name ||
                                                                    'Unknown'}
                                                            </p>
                                                            <p className="text-xs text-muted-foreground">
                                                                {
                                                                    booking.service_type
                                                                }
                                                            </p>
                                                            {booking.caregiver ? (
                                                                <p className="text-xs text-green-600">
                                                                    {
                                                                        booking
                                                                            .caregiver
                                                                            .user
                                                                            .name
                                                                    }
                                                                </p>
                                                            ) : (
                                                                <p className="text-xs text-red-600">
                                                                    Unassigned
                                                                </p>
                                                            )}
                                                        </div>
                                                    </div>
                                                    {renderStatusBadge(
                                                        booking.status,
                                                    )}
                                                </div>
                                            ),
                                        )}
                                    </div>
                                ) : (
                                    <div className="flex h-[200px] flex-col items-center justify-center text-center">
                                        <Briefcase className="mb-2 h-10 w-10 text-muted-foreground/30" />
                                        <p className="text-muted-foreground">
                                            No bookings scheduled for today
                                        </p>
                                    </div>
                                )}
                            </div>
                        </div>

                        {/* Quick Actions */}
                        <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                            <div className="flex flex-col space-y-1.5 p-6">
                                <h3 className="text-lg leading-none font-semibold tracking-tight">
                                    Quick Actions
                                </h3>
                            </div>
                            <div className="p-6 pt-0">
                                <div className="space-y-2">
                                    <Button
                                        asChild
                                        className="w-full justify-start"
                                    >
                                        <Link href="/bookings">
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Booking
                                        </Link>
                                    </Button>
                                    <Button
                                        variant="outline"
                                        asChild
                                        className="w-full justify-start"
                                    >
                                        <Link href="/caregivers">
                                            <Users className="mr-2 h-4 w-4" />
                                            Manage Caregivers
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
