import { Calendar, Clock, MapPin, User, CheckCircle, AlertCircle } from 'lucide-react';
import { useEffect, useState, useRef } from 'react';
import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
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
    bookings: Booking[];
}

export default function CaregiverBookings({ bookings: initialBookings }: Props) {
    const [bookings, setBookings] = useState<Booking[]>(initialBookings);
    const [countdowns, setCountdowns] = useState<Record<number, number>>({});
    const intervalRef = useRef<ReturnType<typeof setInterval> | null>(null);

    useEffect(() => {
        // Initialize countdowns for reserved bookings
        const newCountdowns: Record<number, number> = {};
        bookings.forEach((booking) => {
            if (booking.reservation_expires_at) {
                const expiresAt = new Date(booking.reservation_expires_at).getTime();
                const now = Date.now();
                const secondsLeft = Math.max(0, Math.floor((expiresAt - now) / 1000));
                if (secondsLeft > 0) {
                    newCountdowns[booking.id] = secondsLeft;
                }
            }
        });
        setCountdowns(newCountdowns);

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
    }, [bookings]);

    // WebSocket listeners for real-time updates
    useEffect(() => {
        const echo = (window as any).Echo;
        if (!echo) return;

        const channels: any[] = [];

        bookings.forEach((booking) => {
            const channel = echo.channel(`booking.${booking.id}`);
            channels.push(channel);

            channel.listen('JobReserved', (data: any) => {
                setCountdowns((prev) => ({
                    ...prev,
                    [booking.id]: data.expires_in,
                }));
                setBookings((prev) =>
                    prev.map((b) =>
                        b.id === booking.id
                            ? { ...b, reserved_by: data.caregiver_id, reservation_expires_at: new Date(Date.now() + data.expires_in * 1000).toISOString() }
                            : b,
                    ),
                );
            });

            channel.listen('JobConfirmed', () => {
                setCountdowns((prev) => {
                    const updated = { ...prev };
                    delete updated[booking.id];
                    return updated;
                });
                setBookings((prev) => prev.filter((b) => b.id !== booking.id));
            });

            channel.listen('JobReleased', () => {
                setCountdowns((prev) => {
                    const updated = { ...prev };
                    delete updated[booking.id];
                    return updated;
                });
                setBookings((prev) =>
                    prev.map((b) =>
                        b.id === booking.id
                            ? { ...b, reserved_by: null, reservation_expires_at: null, status: 'received' }
                            : b,
                    ),
                );
            });
        });

        return () => {
            channels.forEach((channel) => {
                channel.stopListening('JobReserved');
                channel.stopListening('JobConfirmed');
                channel.stopListening('JobReleased');
            });
        };
    }, [bookings]);

    const refreshBookings = () => {
        router.reload({ only: ['bookings'] });
    };

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

    if (bookings.length === 0) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
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
                    <h1 className="text-2xl font-semibold text-foreground">
                        Available Bookings
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Bookings you've been notified about
                    </p>
                </div>

                {bookings.length === 0 ? (
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
                        {bookings.map((booking) => (
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
                                                {formatDateTime(booking.start_datetime)}{' '}
                                                -{' '}
                                                {formatDateTime(booking.end_datetime)}
                                            </span>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <Clock className="h-4 w-4 text-muted-foreground" />
                                            <span className="text-xs text-muted-foreground">
                                                Notified{' '}
                                                {new Date(booking.notified_at).toLocaleDateString()}
                                            </span>
                                        </div>
                                    </div>

                                    <div className="flex items-center gap-4">
                                        {getStatusBadge(booking)}
                                        <Link href={`/bookings/available/${booking.id}`}>
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
            </div>
        </AppLayout>
    );
}
