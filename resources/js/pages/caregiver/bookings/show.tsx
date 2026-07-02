import { Link, useForm, Head } from '@inertiajs/react';
import {
    Calendar,
    MapPin,
    User,
    CheckCircle,
    AlertCircle,
    ArrowLeft,
    Phone,
    Mail,
    Heart,
    Building,
    Home,
    Building2,
    PartyPopper,
} from 'lucide-react';
import React from 'react';
import { useEffect, useState, useRef, useCallback } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
    SheetFooter,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import {
    formatDisplayDateInPT,
    formatDisplayDateTimeRangeInPT,
    formatDisplayTimeInPT,
} from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';

interface SiblingBooking {
    id: number;
    ulid: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
}

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
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
    location_type: string;
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
    children_notes: string | null;
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
    booking_group: {
        id: number;
        bookings_count: number;
        sibling_bookings: SiblingBooking[];
    } | null;
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

interface PageProps {
    booking: Booking;
    booking_statuses: BookingStatus[];
    flash: {
        success: string | null;
        error: string | null;
    };
    expires_in?: number;
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

export default function BookingDetail({
    booking,
    booking_statuses,
}: PageProps) {
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
            onSuccess: (page) => {
                const props = page.props as any;
                const flashError = props.flash?.error;

                if (flashError) {
                    setError(flashError);

                    return;
                }

                const expiresIn = props.expires_in || 60;
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
    }, [booking.id, reserveForm]);

    const confirmBooking = useCallback(() => {
        confirmForm.post(`/bookings/${booking.id}/confirm`, {
            onSuccess: (page) => {
                const props = page.props as any;
                const flashError = props.flash?.error;

                if (flashError) {
                    setError(flashError);

                    return;
                }

                setConfirmed(true);
                setShowConfirmSheet(false);

                if (countdownRef.current) {
                    clearInterval(countdownRef.current);
                }
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

    const getLocationIcon = (locationType: string) => {
        switch (locationType) {
            case 'hotel':
                return Building;
            case 'private_home':
                return Home;
            case 'vacation_rental':
                return Building2;
            case 'event_venue':
                return PartyPopper;
            default:
                return MapPin;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookings" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href="/bookings"
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
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
                    <div className="flex items-start gap-3 rounded-lg border border-destructive/20 bg-destructive/10 p-4">
                        <AlertCircle className="mt-0.5 h-5 w-5 text-destructive" />
                        <div>
                            <p className="text-sm font-medium text-destructive">
                                {error}
                            </p>
                        </div>
                    </div>
                )}

                {confirmed && (
                    <div className="border-success/20 bg-success/10 flex items-start gap-3 rounded-lg border p-4">
                        <CheckCircle className="text-success mt-0.5 h-5 w-5" />
                        <div>
                            <p className="text-success text-sm font-medium">
                                Booking confirmed! Redirecting to dashboard...
                            </p>
                        </div>
                    </div>
                )}

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        {/* Client Information */}
                        <div className="left-panel min-w-0">
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
                                            <a
                                                href={`sms:${booking.client_phone}`}
                                                className="text-primary hover:underline"
                                            >
                                                {formatPhoneDisplay(
                                                    booking.client_phone,
                                                )}
                                            </a>
                                        </span>
                                    </div>
                                )}
                                {booking.client_email && (
                                    <div className="flex min-w-0 items-center gap-2">
                                        <Mail className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 break-all text-sm text-muted-foreground">
                                            {booking.client_email}
                                        </span>
                                    </div>
                                )}

                                {booking.service_type && (
                                    <div className="flex items-center gap-2">
                                        <Heart className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-foreground">
                                            {booking.service_type}
                                        </span>
                                        {booking.booking_group &&
                                            booking.booking_group
                                                .bookings_count > 1 && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    Multi-Day (
                                                    {
                                                        booking.booking_group
                                                            .bookings_count
                                                    }
                                                    )
                                                </Badge>
                                            )}
                                    </div>
                                )}

                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {formatDisplayDateInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        from{' '}
                                        {formatDisplayTimeInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        to{' '}
                                        {formatDisplayTimeInPT(
                                            booking.end_datetime,
                                        )}
                                    </span>
                                </div>

                                {booking.booking_group &&
                                    booking.booking_group.bookings_count >
                                        1 && (
                                        <div className="ml-6 space-y-1.5 border-l-2 border-border pl-3">
                                            {booking.booking_group.sibling_bookings.map(
                                                (sibling) => (
                                                    <Link
                                                        key={sibling.id}
                                                        href={`/bookings/${sibling.ulid}`}
                                                        className="flex items-center justify-between rounded px-2 py-1 text-xs transition-colors hover:bg-accent"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {formatDisplayDateInPT(
                                                                sibling.start_datetime,
                                                            )}{' '}
                                                            {formatDisplayTimeInPT(
                                                                sibling.start_datetime,
                                                            )}{' '}
                                                            -{' '}
                                                            {formatDisplayTimeInPT(
                                                                sibling.end_datetime,
                                                            )}
                                                        </span>
                                                        <StatusBadge
                                                            status={
                                                                sibling.status
                                                            }
                                                            bookingStatuses={
                                                                booking_statuses
                                                            }
                                                        />
                                                    </Link>
                                                ),
                                            )}
                                        </div>
                                    )}

                                {booking.hotel_name && (
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.hotel_name}
                                        </span>
                                    </div>
                                )}

                                <div className="flex min-w-0 items-start gap-2">
                                    {React.createElement(
                                        getLocationIcon(booking.location_type),
                                        {
                                            className:
                                                'mt-0.5 h-4 w-4 shrink-0 text-muted-foreground',
                                        },
                                    )}
                                    <span className="min-w-0 break-words text-sm text-muted-foreground">
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${booking.address_line1} ${booking.address_line2 || ''} ${booking.address_city} ${booking.address_state} ${booking.address_zip}`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="break-words text-primary hover:underline"
                                        >
                                            {booking.address_line1 && (
                                                <span>
                                                    {booking.address_line1}
                                                    ,{' '}
                                                </span>
                                            )}{' '}
                                            {booking.address_line2 && (
                                                <span>
                                                    {booking.address_line2}
                                                    ,{' '}
                                                </span>
                                            )}
                                            {booking.address_city && (
                                                <span>
                                                    {booking.address_city},{' '}
                                                </span>
                                            )}
                                            {booking.address_state && (
                                                <span>
                                                    {booking.address_state}
                                                    ,{' '}
                                                </span>
                                            )}
                                            {booking.address_zip && (
                                                <span>
                                                    {booking.address_zip}
                                                </span>
                                            )}
                                        </a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="right-panel grid min-w-0 gap-6">
                            {/* Children  */}
                            {booking.children_notes ? (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {booking.children_notes}
                                    </p>
                                </div>
                            ) : booking.children ? (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children ({booking.children?.length})
                                    </h2>
                                    <div className="space-y-4">
                                        {booking.children &&
                                            booking.children.length > 0 && (
                                                <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                                    {booking.children.map(
                                                        (child, i) => (
                                                            <li key={i}>
                                                                {child.name} (
                                                                {calculateAge(
                                                                    child.birth_year,
                                                                    child.birth_month,
                                                                )}
                                                                )
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                    </div>
                                </div>
                            ) : null}

                            {/* Pets */}
                            {booking.pets && (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Pets ({booking.pets?.length})
                                    </h2>
                                    <div className="space-y-4">
                                        {booking.pets &&
                                            booking.pets.length > 0 && (
                                                <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                                    {booking.pets.map(
                                                        (pet, i) => (
                                                            <li key={i}>
                                                                {pet.name} (
                                                                {pet.breed} /{' '}
                                                                {pet.type})
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                    </div>
                                </div>
                            )}

                            {/* Notes & Considerations */}
                            <div>
                                <h2 className="text-md mb-2 font-semibold text-foreground">
                                    Notes & Considerations
                                </h2>
                                <div className="space-y-3">
                                    {booking.special_considerations &&
                                        booking.special_considerations.length >
                                            0 && (
                                            <div>
                                                <h3 className="mb-1 text-sm font-medium text-foreground">
                                                    Special Considerations
                                                </h3>
                                                <div className="flex flex-wrap gap-1">
                                                    {booking.special_considerations.map(
                                                        (consideration, i) => (
                                                            <span
                                                                key={i}
                                                                className="inline-flex items-center rounded-full bg-yellow-600/10 px-2 py-0.5 text-xs font-medium text-yellow-600 dark:text-yellow-400"
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
                        </div>
                    </div>
                </div>

                {/* Accept Button — sticky full-width bar on mobile, inline on desktop */}
                <div className="sticky bottom-0 z-10 -mx-4 border-t border-border bg-background p-4 pb-[calc(env(safe-area-inset-bottom)+1rem)] sm:static sm:mx-0 sm:border-0 sm:bg-transparent sm:p-0 sm:pb-0">
                    <div className="flex justify-end">
                        <Button
                            size="lg"
                            onClick={startReservation}
                            disabled={reserveForm.processing || confirmed}
                            className="w-full sm:w-auto"
                        >
                            {reserveForm.processing && (
                                <Spinner className="mr-2 h-4 w-4" />
                            )}
                            {reserveForm.processing
                                ? 'Reserving...'
                                : 'Accept Booking'}
                        </Button>
                    </div>
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
                                booking. Click "Confirm Booking" to accept
                                {booking.booking_group &&
                                booking.booking_group.sibling_bookings.length >
                                    0 ? (
                                    <>
                                        {' '}
                                        <strong>all dates</strong>
                                    </>
                                ) : null}
                                , or "Cancel" to release the reservation and
                                allow other caregivers to accept it.
                            </SheetDescription>
                        </SheetHeader>

                        <div className="mb-6 space-y-4 p-4">
                            <div className="mb-12 rounded-lg border border-border bg-muted p-4">
                                <div className="flex items-center justify-between">
                                    <div className="space-y-3">
                                        <p className="font-medium text-foreground">
                                            {booking.client_name}
                                        </p>
                                        <p className="text-sm text-muted-foreground">
                                            {formatDisplayDateTimeRangeInPT(
                                                booking.start_datetime,
                                                booking.end_datetime,
                                            )}
                                        </p>
                                        {booking.booking_group &&
                                            booking.booking_group
                                                .bookings_count > 1 && (
                                                <div className="ml-4 space-y-1.5 border-l-2 border-border pl-3">
                                                    {booking.booking_group.sibling_bookings.map(
                                                        (sibling) => (
                                                            <p
                                                                key={sibling.id}
                                                                className="text-xs text-muted-foreground"
                                                            >
                                                                {formatDisplayDateTimeRangeInPT(
                                                                    sibling.start_datetime,
                                                                    sibling.end_datetime,
                                                                )}
                                                            </p>
                                                        ),
                                                    )}
                                                </div>
                                            )}
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
                        </div>
                        <SheetFooter>
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
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
