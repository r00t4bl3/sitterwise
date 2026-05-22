import { Head, Link, usePage } from '@inertiajs/react';
import GuestLayout from '@/layouts/guest-layout';

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
    address_line1: string;
    address_city: string;
    address_state: string;
    address_zip: string;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);

    return date.toLocaleDateString('en-US', {
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
}

function formatTime(dateString: string): string {
    const date = new Date(dateString);

    return date.toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

export default function GuestBookingConfirmation() {
    const { booking } = usePage().props as unknown as {
        booking: BookingData;
    };

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
                                {formatDate(booking.start_datetime)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">Time</span>
                            <span className="font-medium">
                                {formatTime(booking.start_datetime)} -{' '}
                                {formatTime(booking.end_datetime)}
                            </span>
                        </div>
                        <div className="flex justify-between">
                            <span className="text-muted-foreground">
                                Location
                            </span>
                            <span className="text-right font-medium">
                                {booking.address_line1}
                                <br />
                                {booking.address_city}, {booking.address_state}{' '}
                                {booking.address_zip}
                            </span>
                        </div>
                    </div>

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

                    <div className="mt-6 flex justify-center">
                        Track this booking anytime — we'll email you a link to set up 
                        your account so you can check status, view past bookings, and 
                        book again with one click.
                    </div>
                </div>
            </div>
        </GuestLayout>
    );
}
