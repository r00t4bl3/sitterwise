import { Head, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';
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

    const hasSiblings = booking.booking_group && booking.booking_group.bookings_count > 1;

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
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Date</span>
                            <span className="font-medium">
                                {formatDisplayDateInPT(booking.start_datetime)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Time</span>
                            <span className="font-medium">
                                {formatDisplayTimeInPT(booking.start_datetime)} -{' '}
                                {formatDisplayTimeInPT(booking.end_datetime)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Location
                            </span>
                            <span className="text-right font-medium">
                                {booking.hotel_name ? (
                                    booking.hotel_name
                                ) : (
                                    <>
                                        {booking.address_line1}<br />
                                        {booking.address_city}, {booking.address_state}{' '}
                                        {booking.address_zip}
                                    </>
                                )}
                            </span>
                        </div>
                    </div>

                    {hasSiblings && (
                        <div className="mt-4 rounded-lg border border-blue-200 bg-blue-50 p-4">
                            <h3 className="text-sm font-medium text-blue-800 mb-2">
                                This is a group booking ({booking.booking_group!.bookings_count} dates)
                            </h3>
                            <div className="space-y-1.5">
                                {booking.booking_group!.sibling_bookings.map((sibling) => (
                                    <div
                                        key={sibling.id}
                                        className="flex items-center justify-between rounded border border-blue-100 bg-white px-3 py-2 text-xs"
                                    >
                                        <span className="text-foreground">
                                            {formatDisplayDateInPT(sibling.start_datetime)}
                                            {' '}
                                            {formatDisplayTimeInPT(sibling.start_datetime)} - {formatDisplayTimeInPT(sibling.end_datetime)}
                                        </span>
                                        <span className="capitalize text-muted-foreground">
                                            {sibling.status}
                                        </span>
                                    </div>
                                ))}
                            </div>
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
                        <div className="mt-6 rounded-lg border border-navy/20 bg-navy/[0.03] p-5 text-center">
                            <p className="mb-3 text-sm text-muted-foreground">
                                Set up your account now to track bookings,
                                manage preferences, and book faster next time.
                            </p>
                            <Link
                                href={passwordSetupUrl}
                                className="inline-flex h-11 items-center justify-center rounded-[3px] bg-navy px-6 text-sm font-semibold text-white transition-colors hover:bg-navy-light focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-navy focus-visible:ring-offset-2"
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
