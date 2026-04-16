import { Link, usePage, useForm, Head } from '@inertiajs/react';
import {
    Calendar,
    Clock,
    MapPin,
    User,
    CheckCircle,
    AlertCircle,
    ArrowLeft,
    Phone,
    Mail,
} from 'lucide-react';
import { useEffect, useState, useRef, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';

interface Booking {
    id: number;
    client_name: string;
    client_phone: string | null;
    client_email: string | null;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    hotel_id: number | null;
    hotel_name: string | null;
    start_datetime: string;
    end_datetime: string;
    status: string;
    special_considerations: string[] | null;
    caregiver_notes: string | null;
    reserved_by: number | null;
    reservation_expires_at: string | null;
    notified_at: string;
    viewed_at: string | null;
    children: Array<{
        name: string;
        gender: string | null;
        birth_year: number | null;
        birth_month: number | null;
    }> | null;
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
}

interface PageProps {
    booking: Booking;
}

const getBreadcrumbTitle = (clientName: string) => [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
    {
        title: clientName,
        href: '#',
    },
];

export default function BookingDetail({ booking }: PageProps) {
    const { props } = usePage();
    const [error, setError] = useState<string | null>(null);
    const [showConfirmSheet, setShowConfirmSheet] = useState(false);
    const [countdown, setCountdown] = useState(0);
    const [confirmed, setConfirmed] = useState(false);
    const countdownRef = useRef<ReturnType<typeof setInterval> | null>(null);

    const reserveForm = useForm({});
    const confirmForm = useForm({});
    const releaseForm = useForm({});

    const breadcrumbs = getBreadcrumbTitle(booking.client_name);

    const startReservation = useCallback(() => {
        setError(null);
        reserveForm.post(`/bookings/${booking.id}/reserve`, {
            onSuccess: () => {
                const expiresIn = (props.expires_in as number) || 60;
                setCountdown(expiresIn);
                setShowConfirmSheet(true);

                countdownRef.current = setInterval(() => {
                    setCountdown((prev) => {
                        if (prev <= 1) {
                            clearInterval(countdownRef.current!);
                            setShowConfirmSheet(false);
                            setError('Reservation expired. Please try again.');

                            return 0;
                        }

                        return prev - 1;
                    });
                }, 1000);
            },
            onError: (errors) => {
                setError(errors.error || 'Failed to reserve booking');
            },
        });
    }, [booking.id, reserveForm, props]);

    const confirmBooking = useCallback(() => {
        confirmForm.post(`/bookings/${booking.id}/confirm`, {
            onSuccess: () => {
                setConfirmed(true);
                setShowConfirmSheet(false);

                if (countdownRef.current) {
                    clearInterval(countdownRef.current);
                }

                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 2000);
            },
            onError: (errors) => {
                setError(errors.error || 'Failed to confirm booking');
            },
        });
    }, [booking.id, confirmForm]);

    const releaseReservation = useCallback(() => {
        releaseForm.post(`/bookings/${booking.id}/release`, {
            onSuccess: () => {
                setShowConfirmSheet(false);
                setCountdown(0);

                if (countdownRef.current) {
                    clearInterval(countdownRef.current);
                }
            },
            onError: () => {
                setShowConfirmSheet(false);

                if (countdownRef.current) {
                    clearInterval(countdownRef.current);
                }
            },
        });
    }, [booking.id, releaseForm]);

    // Cleanup on unmount
    useEffect(() => {
        return () => {
            if (countdownRef.current) {
                clearInterval(countdownRef.current);
            }
        };
    }, []);

    // WebSocket listeners (when Pusher is configured)
    useEffect(() => {
        const echo = (window as any).Echo;
        const channel = echo?.channel(`booking.${booking.id}`);

        if (channel) {
            channel.listen('JobReserved', (data: any) => {
                if (data.caregiver_id !== booking.reserved_by) {
                    setError('Another caregiver has reserved this booking.');
                    setShowConfirmSheet(false);

                    if (countdownRef.current) {
                        clearInterval(countdownRef.current);
                    }
                }
            });

            channel.listen('JobConfirmed', () => {
                setError(
                    'This booking has been confirmed by another caregiver.',
                );
                setShowConfirmSheet(false);

                if (countdownRef.current) {
                    clearInterval(countdownRef.current);
                }
            });
        }

        return () => {
            if (channel) {
                channel.stopListening('JobReserved');
                channel.stopListening('JobConfirmed');
            }
        };
    }, [booking.id, booking.reserved_by]);

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

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link href="/bookings">
                        <Button variant="ghost" size="icon">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Booking Details
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Review and accept this booking
                        </p>
                    </div>
                </div>

                {error && (
                    <div className="flex items-start gap-3 rounded-lg border border-red-200 bg-red-50 p-4">
                        <AlertCircle className="mt-0.5 h-5 w-5 text-red-600" />
                        <div>
                            <p className="text-sm font-medium text-red-800">
                                {error}
                            </p>
                        </div>
                    </div>
                )}

                {confirmed && (
                    <div className="flex items-start gap-3 rounded-lg border border-green-200 bg-green-50 p-4">
                        <CheckCircle className="mt-0.5 h-5 w-5 text-green-600" />
                        <div>
                            <p className="text-sm font-medium text-green-800">
                                Booking confirmed! Redirecting to dashboard...
                            </p>
                        </div>
                    </div>
                )}

                <div className="grid gap-4 md:grid-cols-2">
                    {/* Client Info */}
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            Client Information
                        </h2>
                        <div className="space-y-3">
                            <div className="flex items-center gap-2">
                                <User className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm text-foreground">
                                    {booking.client_name}
                                </span>
                            </div>
                            {booking.client_phone && (
                                <div className="flex items-center gap-2">
                                    <Phone className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {booking.client_phone}
                                    </span>
                                </div>
                            )}
                            {booking.client_email && (
                                <div className="flex items-center gap-2">
                                    <Mail className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {booking.client_email}
                                    </span>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Booking Details */}
                    <div className="rounded-lg border border-border bg-card p-6">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            Booking Details
                        </h2>
                        <div className="space-y-3">
                            <div className="flex items-center gap-2">
                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm text-muted-foreground">
                                    {formatDateTime(booking.start_datetime)}
                                </span>
                            </div>
                            <div className="flex items-center gap-2">
                                <Clock className="h-4 w-4 text-muted-foreground" />
                                <span className="text-sm text-muted-foreground">
                                    {formatDateTime(booking.end_datetime)}
                                </span>
                            </div>
                            {booking.special_considerations &&
                                booking.special_considerations.length > 0 && (
                                    <div>
                                        <h3 className="mb-1 text-sm font-medium text-foreground">
                                            Special Considerations
                                        </h3>
                                        <div className="flex flex-wrap gap-1">
                                            {booking.special_considerations.map(
                                                (consideration, i) => (
                                                    <span
                                                        key={i}
                                                        className="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                                                    >
                                                        {consideration}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}
                            {booking.caregiver_notes && (
                                <div>
                                    <h3 className="mb-1 text-sm font-medium text-foreground">
                                        Notes for Caregiver
                                    </h3>
                                    <p className="text-sm text-muted-foreground">
                                        {booking.caregiver_notes}
                                    </p>
                                </div>
                            )}
                        </div>
                    </div>

                    {/* Address or Hotel */}
                    {(booking.address_line1 || booking.hotel_name) && (
                        <div className="rounded-lg border border-border bg-card p-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Location
                            </h2>
                            <div className="flex items-start gap-2">
                                <MapPin className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                <div className="text-sm text-muted-foreground">
                                    {booking.hotel_name && (
                                        <p className="font-medium text-foreground">
                                            {booking.hotel_name}
                                        </p>
                                    )}
                                    {booking.address_line1 && (
                                        <p>{booking.address_line1}</p>
                                    )}
                                    {booking.address_line2 && (
                                        <p>{booking.address_line2}</p>
                                    )}
                                    {booking.address_city && (
                                        <p>
                                            {booking.address_city},{' '}
                                            {booking.address_state}{' '}
                                            {booking.address_zip}
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Children & Pets */}
                    {(booking.children || booking.pets) && (
                        <div className="rounded-lg border border-border bg-card p-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Children & Pets
                            </h2>
                            <div className="space-y-4">
                                {booking.children &&
                                    booking.children.length > 0 && (
                                        <div>
                                            <h3 className="mb-2 text-sm font-medium text-foreground">
                                                Children (
                                                {booking.children.length})
                                            </h3>
                                            <ul className="space-y-1 text-sm text-muted-foreground">
                                                {booking.children.map(
                                                    (child, i) => (
                                                        <li key={i}>
                                                            {child.name}
                                                        </li>
                                                    ),
                                                )}
                                            </ul>
                                        </div>
                                    )}
                                {booking.pets && booking.pets.length > 0 && (
                                    <div>
                                        <h3 className="mb-2 text-sm font-medium text-foreground">
                                            Pets ({booking.pets.length})
                                        </h3>
                                        <ul className="space-y-1 text-sm text-muted-foreground">
                                            {booking.pets.map((pet, i) => (
                                                <li key={i}>{pet.name}</li>
                                            ))}
                                        </ul>
                                    </div>
                                )}
                            </div>
                        </div>
                    )}
                </div>

                {/* Accept Button */}
                <div className="flex justify-end">
                    <Button
                        size="lg"
                        onClick={startReservation}
                        disabled={reserveForm.processing || confirmed}
                    >
                        {reserveForm.processing && (
                            <Spinner className="mr-2 h-4 w-4" />
                        )}
                        {reserveForm.processing
                            ? 'Reserving...'
                            : 'Accept Booking'}
                    </Button>
                </div>

                {/* Confirmation Sheet */}
                <Sheet
                    open={showConfirmSheet}
                    onOpenChange={setShowConfirmSheet}
                >
                    <SheetContent side="bottom" className="h-auto max-h-[90vh]">
                        <SheetHeader>
                            <SheetTitle>Confirm Booking</SheetTitle>
                            <SheetDescription>
                                You have {countdown} seconds to confirm this
                                booking
                            </SheetDescription>
                        </SheetHeader>

                        <div className="mt-6 space-y-4 p-4">
                            <div className="rounded-lg border border-border bg-muted p-4">
                                <div className="flex items-center justify-between">
                                    <div>
                                        <p className="font-medium text-foreground">
                                            {booking.client_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDateTime(
                                                booking.start_datetime,
                                            )}
                                        </p>
                                    </div>
                                    <div className="text-center">
                                        <div className="text-3xl font-bold text-foreground">
                                            {countdown}
                                        </div>
                                        <div className="text-xs text-muted-foreground">
                                            seconds
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div className="flex gap-3">
                                <Button
                                    variant="outline"
                                    onClick={releaseReservation}
                                    className="flex-1"
                                >
                                    Cancel
                                </Button>
                                <Button
                                    onClick={confirmBooking}
                                    disabled={confirmForm.processing}
                                    className="flex-1"
                                >
                                    {confirmForm.processing && (
                                        <Spinner className="mr-2 h-4 w-4" />
                                    )}
                                    {confirmForm.processing
                                        ? 'Confirming...'
                                        : 'Confirm Booking'}
                                </Button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
