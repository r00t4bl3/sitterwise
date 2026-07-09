import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect } from 'react';
import GuestLayout from '@/layouts/guest-layout';
import { trackEvent } from '@/lib/analytics';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';

interface SiblingBooking {
    id: number;
    ulid: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
}

interface BookingData {
    id: number;
    ulid: string;
    service_type: string;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    client_first_name: string;
    client_last_name: string;
    hotel_name?: string;
    address_line1: string;
    address_city: string;
    address_state: string;
    address_zip: string;
    booking_group: {
        id: number;
        bookings_count: number;
        sibling_bookings: SiblingBooking[];
    } | null;
}

export default function GuestBookingConfirmation() {
    const { booking, passwordSetupUrl } = usePage().props as unknown as {
        booking: BookingData;
        passwordSetupUrl: string | null;
    };

    // Conversion: a guest reached the booking confirmation. Fire GA4's
    // recommended `generate_lead` (mark as a conversion in GA4) plus a custom
    // `booking_complete`. Once per confirmation view.
    const groupSize = booking.booking_group?.bookings_count ?? 1;
    useEffect(() => {
        trackEvent('booking_complete', {
            booking_id: booking.ulid,
            service_type: booking.service_type,
            group_size: groupSize,
        });
        trackEvent('generate_lead', { currency: 'USD' });
    }, [booking.ulid, booking.service_type, groupSize]);

    const hasSiblings =
        booking.booking_group && booking.booking_group.bookings_count > 1;

    const allDates = hasSiblings
        ? [
              {
                  id: booking.id,
                  ulid: booking.ulid,
                  start_datetime: booking.start_datetime,
                  end_datetime: booking.end_datetime,
                  status: booking.status,
              },
              ...booking.booking_group!.sibling_bookings,
          ]
        : [];

    return (
        <GuestLayout>
            <Head title="Booking Confirmed" />
            <div className="mx-auto max-w-lg">
                <div className="rounded-lg border bg-card p-6 text-card-foreground">
                    <div className="mb-6 text-center">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                            <svg
                                className="h-8 w-8 text-green-600"
                                fill="none"
                                stroke="currentColor"
                                viewBox="0 0 24 24"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <h1 className="text-2xl font-semibold">
                            Booking Request Received
                        </h1>
                        <p className="mt-2 text-muted-foreground">
                            Thank you for your booking request,{' '}
                            {booking.client_first_name}!
                        </p>
                    </div>

                    <div className="space-y-4 rounded-lg bg-muted p-4">
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Booking #
                            </span>
                            <span className="font-medium">{booking.ulid}</span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Status
                            </span>
                            <span className="font-medium capitalize">
                                {booking.status}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Service Type
                            </span>
                            <span className="font-medium capitalize">
                                {booking.service_type}
                            </span>
                        </div>
                        {!hasSiblings && (
                            <>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Date
                                    </span>
                                    <span className="font-medium">
                                        {formatDisplayDateInPT(
                                            booking.start_datetime,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Time
                                    </span>
                                    <span className="font-medium">
                                        {formatDisplayTimeInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        -{' '}
                                        {formatDisplayTimeInPT(
                                            booking.end_datetime,
                                        )}
                                    </span>
                                </div>
                            </>
                        )}
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Location
                            </span>
                            <span className="text-right font-medium">
                                {booking.hotel_name ? (
                                    booking.hotel_name
                                ) : (
                                    <>
                                        {booking.address_line1}
                                        <br />
                                        {booking.address_city},{' '}
                                        {booking.address_state}{' '}
                                        {booking.address_zip}
                                    </>
                                )}
                            </span>
                        </div>
                    </div>

                    {hasSiblings && (
                        <div className="mt-4 space-y-4 rounded-lg bg-muted p-4">
                            <div className="flex justify-between">
                                <span className="text-muted-foreground">
                                    Date
                                </span>
                                <span className="text-muted-foreground">
                                    Time
                                </span>
                            </div>
                            {allDates.map((d) => (
                                <div
                                    key={d.id}
                                    className="flex justify-between"
                                >
                                    <span className="font-medium">
                                        {formatDisplayDateInPT(
                                            d.start_datetime,
                                        )}
                                    </span>
                                    <span className="font-medium">
                                        {formatDisplayTimeInPT(
                                            d.start_datetime,
                                        )}{' '}
                                        -{' '}
                                        {formatDisplayTimeInPT(d.end_datetime)}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}

                    <div className="mt-6 text-center text-sm text-muted-foreground">
                        <p>
                            We will review your request and get back to you
                            shortly.
                        </p>
                        <p className="mt-2">
                            A confirmation email will be sent to your email
                            address once your booking is confirmed.
                        </p>
                    </div>

                    <div className="mt-6 text-center text-sm text-muted-foreground">
                        Track this booking anytime — we'll email you a link to
                        set up your account so you can check status, view past
                        bookings, and book again with one click.
                    </div>

                    {passwordSetupUrl && (
                        <div className="mt-6 rounded-lg border border-border bg-card p-5 text-center">
                            <p className="mb-3 text-sm text-muted-foreground">
                                Set up your account now to track bookings,
                                manage preferences, and book faster next time.
                            </p>
                            <Link
                                href={passwordSetupUrl}
                                className="inline-flex h-11 items-center justify-center rounded-[3px] bg-table-header px-6 text-sm font-semibold text-white transition-colors hover:brightness-110 focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:outline-none"
                            >
                                Set Your Password
                            </Link>
                        </div>
                    )}
                </div>
            </div>
        </GuestLayout>
    );
}
