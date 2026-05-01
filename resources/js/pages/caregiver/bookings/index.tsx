import { Head, Link, usePage } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    User,
    CheckCircle,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
];

interface Booking {
    id: number;
    ulid: string;
    client_name: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    reserved_by: number | null;
    reservation_expires_at: string | null;
    notified_at: string;
    viewed_at: string | null;
}

interface Props {
    bookings: {
        data: Booking[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
}

export default function CaregiverBookings() {
    const { bookings } = usePage().props as unknown as Props;
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const getInitialCountdowns = (): Record<number, number> => {
        const newCountdowns: Record<number, number> = {};
        bookings.data.forEach((booking) => {
            if (booking.reservation_expires_at) {
                const expiresAt = new Date(
                    booking.reservation_expires_at,
                ).getTime();
                const now = Date.now();
                const secondsLeft = Math.max(
                    0,
                    Math.floor((expiresAt - now) / 1000),
                );

                if (secondsLeft > 0) {
                    newCountdowns[booking.id] = secondsLeft;
                }
            }
        });

        return newCountdowns;
    };

    const [countdowns, setCountdowns] =
        useState<Record<number, number>>(getInitialCountdowns);

    useEffect(() => {
        // Start countdown timer for all reserved bookings
        intervalRef.current = setInterval(() => {
            setCountdowns((prev) => {
                const updated = { ...prev };
                let changed = false;
                Object.keys(updated).forEach((bookingId) => {
                    if (updated[Number(bookingId)] > 0) {
                        updated[Number(bookingId)]--;
                        changed = true;
                    } else if (updated[Number(bookingId)] === 0) {
                        delete updated[Number(bookingId)];
                        changed = true;
                    }
                });

                return changed ? updated : prev;
            });
        }, 1000);

        return () => {
            if (intervalRef.current) {
                clearInterval(intervalRef.current);
            }
        };
    }, [bookings.data]);

    const formatDateTime = (dateStr: string) => {
        const date = new Date(dateStr);

        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const getStatusBadge = (booking: Booking) => {
        const secondsLeft = countdowns[booking.id] ?? 0;

        if (secondsLeft > 0) {
            return (
                <div className="flex items-center gap-1 text-yellow-600">
                    <Clock className="h-4 w-4" />
                    <span className="text-xs font-medium">
                        Reserved ({secondsLeft}s)
                    </span>
                </div>
            );
        }

        return (
            <div className="flex items-center gap-1 text-green-600">
                <CheckCircle className="h-4 w-4" />
                <span className="text-xs font-medium">Available</span>
            </div>
        );
    };

    if (bookings.data.length === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Bookings" />
                <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            Available Bookings
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Bookings you've been notified about
                        </p>
                    </div>
                    <div className="rounded-lg border border-border bg-card p-12 text-center">
                        <Calendar className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <h3 className="text-lg font-medium text-foreground">
                            No available bookings
                        </h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            You haven't been notified about any bookings yet.
                        </p>
                    </div>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">
                        Available Bookings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Bookings you've been notified about
                    </p>
                </div>

                {bookings.data.length === 0 ? (
                    <div className="rounded-lg border border-border bg-card p-12 text-center">
                        <Calendar className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <h3 className="text-lg font-medium text-foreground">
                            No available bookings
                        </h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            You haven't been notified about any bookings yet.
                        </p>
                    </div>
                ) : (
                    <div className="space-y-4">
                        {bookings.data.map((booking) => (
                            <div
                                key={booking.id}
                                className="rounded-lg border border-border bg-card p-6"
                            >
                                <div className="flex items-start justify-between">
                                    <div className="space-y-3">
                                        <div className="flex items-center gap-2">
                                            <User className="h-4 w-4 text-muted-foreground" />
                                            <span className="font-medium text-foreground">
                                                {booking.client_name}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Calendar className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-sm text-muted-foreground">
                                                {formatDateTime(
                                                    booking.start_datetime,
                                                )}{' '}
                                                -{' '}
                                                {formatDateTime(
                                                    booking.end_datetime,
                                                )}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-xs text-muted-foreground">
                                                Notified{' '}
                                                {new Date(
                                                    booking.notified_at,
                                                ).toLocaleDateString()}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        {getStatusBadge(booking)}
                                        <Link
                                            href={`/bookings/${booking.ulid}`}
                                        >
                                            <Button size="sm">
                                                View Details
                                            </Button>
                                        </Link>
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {bookings.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {bookings.current_page} of {bookings.last_page}
                        </p>
                        <div className="flex gap-1">
                            {bookings.links.map((link, index) => {
                                if (link.label === '...') {
                                    return null;
                                }

                                const isPrev =
                                    link.label.includes('Previous') ||
                                    link.label.includes('&laquo;');
                                const isNext =
                                    link.label.includes('Next') ||
                                    link.label.includes('&raquo;');

                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`flex h-8 w-8 items-center justify-center rounded text-sm ${
                                            link.active
                                                ? 'bg-foreground text-white'
                                                : 'border border-border text-muted-foreground hover:bg-accent'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    >
                                        {isPrev ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : isNext ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            link.label
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
