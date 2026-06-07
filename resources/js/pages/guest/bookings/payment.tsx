import { Head } from '@inertiajs/react';
import {
    EmbeddedCheckoutProvider,
    EmbeddedCheckout,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { useEffect, useState } from 'react';
import BookingProgress from '@/components/booking-progress';
import GuestLayout from '@/layouts/guest-layout';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';

const stripePromise = loadStripe(
    import.meta.env.VITE_STRIPE_KEY || 'pk_test_placeholder',
);

interface BookingDate {
    start_datetime: string;
    end_datetime: string;
}

interface BookingData {
    client_first_name: string;
    client_last_name: string;
    client_email: string;
    service_type: string;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    address_line1: string;
    address_city: string;
    address_state: string;
    address_zip: string;
    hotel_name: string | null;
    dates?: BookingDate[] | null;
}

interface PaymentPageProps {
    booking: BookingData;
    token: string;
    error?: string;
    location_types: Array<{ value: string; label: string }>;
}

export default function PaymentPage({
    booking,
    token,
    error,
    location_types,
}: PaymentPageProps) {
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [checkoutError, setCheckoutError] = useState<string | null>(
        error || null,
    );
    const [loading, setLoading] = useState(true);

    const serviceLabels: Record<string, string> = {
        sitting: 'Pet Sitting',
        walking: 'Dog Walking',
        daycare: 'Day Care',
        boarding: 'Boarding',
    };

    const locationLabels = Object.fromEntries(
        location_types.map((lt) => [lt.value, lt.label]),
    );

    useEffect(() => {
        const fetchSetupIntent = async () => {
            try {
                const response = await fetch(
                    `/book/payment/${token}/setup-intent`,
                    {
                        headers: {
                            'Content-Type': 'application/json',
                            Accept: 'application/json',
                        },
                        credentials: 'same-origin',
                    },
                );

                if (!response.ok) {
                    throw new Error('Failed to create setup intent');
                }

                const data = await response.json();

                if (data.client_secret) {
                    setClientSecret(data.client_secret);
                } else {
                    setCheckoutError(
                        data.error || 'Failed to load payment form.',
                    );
                }
            } catch {
                setCheckoutError(
                    'Failed to load payment form. Please try again.',
                );
            } finally {
                setLoading(false);
            }
        };

        fetchSetupIntent();
    }, [token]);

    return (
        <GuestLayout>
            <Head title="Complete Your Booking" />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <BookingProgress currentStep={2} />

                <div className="mx-auto w-full max-w-2xl">
                    <h1 className="mb-8 text-center text-3xl font-bold text-foreground">
                        Complete Your Booking
                    </h1>

                    <div className="mb-6 rounded-lg bg-card p-6 shadow-xs border border-border">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Booking Summary
                    </h2>
                    <dl className="space-y-3">
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Service</dt>
                            <dd className="font-medium">
                                {serviceLabels[booking.service_type] ||
                                    booking.service_type}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Location</dt>
                            <dd className="font-medium">
                                {locationLabels[booking.location_type] ||
                                    booking.location_type}
                            </dd>
                        </div>
                        {booking.dates && booking.dates.length > 1 ? (
                            <div className="flex justify-between">
                                <dt className="text-muted-foreground">Dates</dt>
                                <dd className="text-right font-medium">
                                    {booking.dates.map((d, i) => (
                                        <div key={i} className={i > 0 ? 'mt-2' : ''}>
                                            <div>{formatDisplayDateInPT(d.start_datetime)}</div>
                                            <div className="text-xs text-muted-foreground">
                                                {formatDisplayTimeInPT(d.start_datetime)} -{' '}
                                                {formatDisplayTimeInPT(d.end_datetime)}
                                            </div>
                                        </div>
                                    ))}
                                </dd>
                            </div>
                        ) : (
                            <>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Date</dt>
                                    <dd className="font-medium">
                                        {formatDisplayDateInPT(booking.start_datetime)}
                                    </dd>
                                </div>
                                <div className="flex justify-between">
                                    <dt className="text-muted-foreground">Time</dt>
                                    <dd className="font-medium">
                                        {formatDisplayTimeInPT(booking.start_datetime)} -{' '}
                                        {formatDisplayTimeInPT(booking.end_datetime)}
                                    </dd>
                                </div>
                            </>
                        )}
                        <div className="flex justify-between">
                            <dt className="text-muted-foreground">Address</dt>
                            <dd className="text-right font-medium">
                                {booking.address_line1}
                                <br />
                                {booking.address_city}, {booking.address_state}{' '}
                                {booking.address_zip}
                            </dd>
                        </div>
                        <div className="mt-3 flex justify-between border-t pt-3">
                            <dt className="text-muted-foreground">Client</dt>
                            <dd className="font-medium">
                                {booking.client_first_name}{' '}
                                {booking.client_last_name}
                            </dd>
                        </div>
                    </dl>
                </div>

                {checkoutError && (
                    <div className="mb-6 rounded-md border border-red-200 bg-red-50 px-4 py-3 text-red-700">
                        {checkoutError}
                    </div>
                )}

                <div className="rounded-lg bg-card p-6 shadow-xs border border-border">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Payment Details
                    </h2>
                    <p className="mb-4 text-muted-foreground">
                        Please add your payment method to complete the booking.
                        Your card will be stored securely for future bookings.
                    </p>

                    {loading && !clientSecret && (
                        <div className="py-8 text-center">
                            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                            <p className="mt-2 text-muted-foreground">
                                Loading payment form...
                            </p>
                        </div>
                    )}

                    {clientSecret && (
                        <EmbeddedCheckoutProvider
                            stripe={stripePromise}
                            options={{ clientSecret }}
                        >
                            <EmbeddedCheckout />
                        </EmbeddedCheckoutProvider>
                    )}
                </div>

                <p className="mt-6 text-center text-sm text-muted-foreground">
                    Your payment information is securely processed by Stripe.
                </p>
            </div>
        </div>
    </GuestLayout>
    );
}
