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
    ExternalLink,
    Link as LinkIcon,
} from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTime, formatDisplayTime } from '@/lib/datetime';
import { BookingSheet } from '@/pages/admin/bookings/booking-sheet';
import type { Booking as FullBooking } from '@/pages/admin/bookings/types';
import { useBookingSheet } from '@/pages/admin/bookings/use-booking-sheet';
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
    service_type_label: string;
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

interface QuickLink {
    id: number;
    title: string;
    url: string;
    description: string | null;
    icon: string | null;
    is_external: boolean;
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
        quickLinks?: QuickLink[];
        bookingStatuses: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
        clients?: Array<{ id: number; name: string; [key: string]: unknown }>;
        hotels?: Array<{
            id: number;
            name: string;
            line1: string | null;
            line2: string | null;
            city: string | null;
            state: string | null;
            zip: string | null;
        }>;
        caregivers?: Array<{
            id: number;
            name: string;
            [key: string]: unknown;
        }>;
        serviceTypes?: Array<{ value: string; label: string }>;
        locationTypes?: Array<{ value: string; label: string }>;
        paymentStatuses?: Array<{ value: string; label: string }>;
        specialConsiderationOptions?: Array<{ value: string; label: string }>;
        bookingAttributes?: Array<{
            id: number;
            name: string;
            slug: string;
            type: string;
            options: string[];
        }>;
        sitterPreferenceOptions?: Array<{ value: string; label: string }>;
    };
}

export default function AdminDashboard({ stats, admin }: AdminDashboardProps) {
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
        quickLinks: [],
        bookingStatuses: [],
    };

    const bookingStatuses = safeAdmin.bookingStatuses || [];

    const sheet = useBookingSheet({
        clients: safeAdmin.clients ?? [],
        hotels: safeAdmin.hotels ?? [],
        caregivers: safeAdmin.caregivers ?? [],
        service_types: safeAdmin.serviceTypes ?? [],
        location_types: safeAdmin.locationTypes ?? [],
        booking_statuses: safeAdmin.bookingStatuses ?? [],
        payment_statuses: safeAdmin.paymentStatuses ?? [],
        special_consideration_options:
            safeAdmin.specialConsiderationOptions ?? [],
        booking_attributes: safeAdmin.bookingAttributes ?? [],
        sitter_preference_options: safeAdmin.sitterPreferenceOptions ?? [],
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Admin Dashboard" />
            <ToasterMessage />
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
                <div className="grid gap-4 sm:grid-cols-3">
                    <Link
                        href="/caregivers"
                        className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Users className="h-4 w-4 text-primary" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Caregivers
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {safeStats.activeCaregivers}
                            <span className="text-lg font-normal text-muted-foreground">
                                {' '}
                                / {safeStats.totalCaregivers}
                            </span>
                        </p>
                        <p className="text-xs text-muted-foreground">
                            Active / Total
                        </p>
                    </Link>

                    <Link
                        href="/clients"
                        className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <UserCircle className="h-4 w-4 text-green-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Clients
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {safeStats.totalClients}
                        </p>
                        <p className="text-xs text-muted-foreground">Total</p>
                    </Link>

                    <Link
                        href="/bookings"
                        className="flex flex-col gap-1 rounded-xl border border-border bg-card p-4 shadow-sm transition-all hover:shadow-md"
                    >
                        <div className="flex items-center gap-2 text-muted-foreground">
                            <Calendar className="h-4 w-4 text-blue-500" />
                            <span className="text-xs font-medium tracking-wider uppercase">
                                Bookings
                            </span>
                        </div>
                        <p className="text-2xl font-bold text-foreground">
                            {safeStats.totalBookings}
                        </p>
                        <p className="text-xs text-muted-foreground">Total</p>
                    </Link>
                </div>

                {/* Two Column Layout Below */}
                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Left Column - Bookings Requiring Attention & Recent Activity */}
                    <div className="flex flex-col gap-6">
                        {/* Bookings Requiring Attention */}
                        <div className="flex flex-col gap-4">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Bookings Requiring Attention
                            </h3>
                            <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                                <div className="p-6">
                                    {safeAdmin.bookingsNeedingAttention.length >
                                    0 ? (
                                        <div className="space-y-3">
                                            {safeAdmin.bookingsNeedingAttention.map(
                                                (booking) => (
                                                    <button
                                                        key={booking.id}
                                                        type="button"
                                                        onClick={() =>
                                                            sheet.openEditSheet(
                                                                booking as unknown as FullBooking,
                                                            )
                                                        }
                                                        className="flex w-full cursor-pointer items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                    >
                                                        <div className="flex items-center gap-3">
                                                            <div className="flex h-8 w-8 items-center justify-center rounded bg-red-100">
                                                                <AlertCircle className="h-4 w-4 text-red-600" />
                                                            </div>
                                                            <div className="flex flex-col text-left">
                                                                <p className="text-sm font-medium">
                                                                    {formatDisplayDateTime(
                                                                        booking.start_datetime,
                                                                    )}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {booking
                                                                        .client
                                                                        ?.user
                                                                        ?.name ||
                                                                        'Unknown Client'}{' '}
                                                                    •{' '}
                                                                    {
                                                                        booking.service_type_label
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
                                                    </button>
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
                        </div>

                        {/* Recent Activity */}
                        <div className="flex flex-col gap-4">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Recent Activity
                            </h3>
                            <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                                <div className="p-6">
                                    <div className="space-y-4">
                                        {/* Recent Bookings */}
                                        {safeAdmin.recentBookings.length >
                                            0 && (
                                            <div>
                                                <h4 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                    New Bookings
                                                </h4>
                                                <div className="space-y-2">
                                                    {safeAdmin.recentBookings
                                                        .slice(0, 3)
                                                        .map((booking) => (
                                                            <button
                                                                key={booking.id}
                                                                type="button"
                                                                onClick={() =>
                                                                    sheet.openEditSheet(
                                                                        booking as unknown as FullBooking,
                                                                    )
                                                                }
                                                                className="flex w-full cursor-pointer items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                            >
                                                                <div className="flex items-center gap-3">
                                                                    <div className="flex h-8 w-8 items-center justify-center rounded bg-muted">
                                                                        <Calendar className="h-4 w-4 text-muted-foreground" />
                                                                    </div>
                                                                    <div className="flex flex-col text-left">
                                                                        <p className="text-sm font-medium">
                                                                            {booking
                                                                                .client
                                                                                ?.user
                                                                                ?.name ||
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
                                                            </button>
                                                        ))}
                                                </div>
                                            </div>
                                        )}

                                        {/* Recent Caregivers */}
                                        {safeAdmin.recentCaregivers.length >
                                            0 && (
                                            <div>
                                                <h4 className="mb-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                                    New Caregivers
                                                </h4>
                                                <div className="space-y-2">
                                                    {safeAdmin.recentCaregivers
                                                        .slice(0, 2)
                                                        .map((caregiver) => (
                                                            <Link
                                                                key={
                                                                    caregiver.id
                                                                }
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

                                        {safeAdmin.recentBookings.length ===
                                            0 &&
                                            safeAdmin.recentCaregivers
                                                .length === 0 && (
                                                <p className="py-4 text-center text-sm text-muted-foreground">
                                                    No recent activity
                                                </p>
                                            )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    {/* Right Column - Today's Schedule */}
                    <div className="flex flex-col gap-6">
                        <div className="flex flex-col gap-4">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Today's Schedule
                            </h3>
                            <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                                <div className="p-6">
                                    {safeAdmin.todaysBookings.length > 0 ? (
                                        <div className="space-y-3">
                                            {safeAdmin.todaysBookings.map(
                                                (booking) => (
                                                    <button
                                                        key={booking.id}
                                                        type="button"
                                                        onClick={() =>
                                                            sheet.openEditSheet(
                                                                booking as unknown as FullBooking,
                                                            )
                                                        }
                                                        className="flex w-full cursor-pointer items-center justify-between rounded-lg border border-border bg-card p-3"
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
                                                            <div className="flex flex-col text-left">
                                                                <p className="text-sm font-medium">
                                                                    {booking
                                                                        .client
                                                                        ?.user
                                                                        .name ||
                                                                        'Unknown'}
                                                                </p>
                                                                <p className="text-xs text-muted-foreground">
                                                                    {
                                                                        booking.service_type_label
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
                                                        <StatusBadge
                                                            status={
                                                                booking.status
                                                            }
                                                            bookingStatuses={
                                                                bookingStatuses
                                                            }
                                                        />
                                                    </button>
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
                        </div>

                        {/* Quick Actions */}
                        <div className="flex flex-col gap-4">
                            <h3 className="text-lg leading-none font-semibold tracking-tight">
                                Quick Actions
                            </h3>
                            <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                                <div className="p-6">
                                    <div className="space-y-2">
                                        <Button
                                            className="w-full justify-start"
                                            onClick={() =>
                                                sheet.openCreateSheet()
                                            }
                                        >
                                            <Plus className="mr-2 h-4 w-4" />
                                            Create Booking
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

                        {/* Quick Links */}
                        {safeAdmin.quickLinks &&
                            safeAdmin.quickLinks.length > 0 && (
                                <div className="flex flex-col gap-4">
                                    <h3 className="text-lg leading-none font-semibold tracking-tight">
                                        Quick Links
                                    </h3>
                                    <div className="rounded-xl border border-border bg-card text-card-foreground shadow">
                                        <div className="p-6">
                                            <div className="space-y-2">
                                                {safeAdmin.quickLinks.map(
                                                    (link) => (
                                                        <a
                                                            key={link.id}
                                                            href={link.url}
                                                            target={
                                                                link.is_external
                                                                    ? '_blank'
                                                                    : '_self'
                                                            }
                                                            rel={
                                                                link.is_external
                                                                    ? 'noopener noreferrer'
                                                                    : ''
                                                            }
                                                            className="flex items-center justify-between rounded-lg border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                                        >
                                                            <div className="flex items-center gap-3">
                                                                <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-100">
                                                                    {link.icon ===
                                                                    'ExternalLink' ? (
                                                                        <ExternalLink className="h-4 w-4 text-blue-600" />
                                                                    ) : (
                                                                        <LinkIcon className="h-4 w-4 text-blue-600" />
                                                                    )}
                                                                </div>
                                                                <div>
                                                                    <p className="text-sm font-medium">
                                                                        {
                                                                            link.title
                                                                        }
                                                                    </p>
                                                                    {link.description && (
                                                                        <p className="text-xs text-muted-foreground">
                                                                            {
                                                                                link.description
                                                                            }
                                                                        </p>
                                                                    )}
                                                                </div>
                                                            </div>
                                                            <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                                        </a>
                                                    ),
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            )}
                    </div>
                </div>

                <BookingSheet {...sheet} />
            </div>
        </AppLayout>
    );
}
