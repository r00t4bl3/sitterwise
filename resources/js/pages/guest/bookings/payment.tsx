import {
    EmbeddedCheckoutProvider,
    EmbeddedCheckout,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { useEffect, useState } from 'react';

const stripePromise = loadStripe(
    import.meta.env.VITE_STRIPE_KEY || 'pk_test_placeholder',
);

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
}

interface PaymentPageProps {
    booking: BookingData;
    token: string;
    error?: string;
}

export default function PaymentPage({
    booking,
    token,
    error,
}: PaymentPageProps) {
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [checkoutError, setCheckoutError] = useState<string | null>(
        error || null,
    );
    const [loading, setLoading] = useState(true);

    const formatDate = (dateStr: string) => {
        const date = new Date(dateStr);

        return date.toLocaleDateString('en-US', {
            weekday: 'short',
            month: 'short',
            day: 'numeric',
            year: 'numeric',
        });
    };

    const formatTime = (dateStr: string) => {
        const date = new Date(dateStr);

        return date.toLocaleTimeString('en-US', {
            hour: 'numeric',
            minute: '2-digit',
        });
    };

    const serviceLabels: Record<string, string> = {
        sitting: 'Pet Sitting',
        walking: 'Dog Walking',
        daycare: 'Day Care',
        boarding: 'Boarding',
    };

    const locationLabels: Record<string, string> = {
        home: 'My Home',
        hotel: 'Hotel / Rental',
    };

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
        <div className="min-h-screen bg-gray-50 px-4 py-12">
            <div className="mx-auto max-w-2xl">
                <h1 className="mb-8 text-center text-3xl font-bold text-gray-900">
                    Complete Your Booking
                </h1>

                <div className="mb-6 rounded-lg bg-white p-6 shadow-md">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">
                        Booking Summary
                    </h2>
                    <dl className="space-y-3">
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Service</dt>
                            <dd className="font-medium">
                                {serviceLabels[booking.service_type] ||
                                    booking.service_type}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Location</dt>
                            <dd className="font-medium">
                                {locationLabels[booking.location_type] ||
                                    booking.location_type}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Date</dt>
                            <dd className="font-medium">
                                {formatDate(booking.start_datetime)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Time</dt>
                            <dd className="font-medium">
                                {formatTime(booking.start_datetime)} -{' '}
                                {formatTime(booking.end_datetime)}
                            </dd>
                        </div>
                        <div className="flex justify-between">
                            <dt className="text-gray-600">Address</dt>
                            <dd className="text-right font-medium">
                                {booking.address_line1}
                                <br />
                                {booking.address_city}, {booking.address_state}{' '}
                                {booking.address_zip}
                            </dd>
                        </div>
                        <div className="mt-3 flex justify-between border-t pt-3">
                            <dt className="text-gray-600">Client</dt>
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

                <div className="rounded-lg bg-white p-6 shadow-md">
                    <h2 className="mb-4 text-lg font-semibold text-gray-900">
                        Payment Details
                    </h2>
                    <p className="mb-4 text-gray-600">
                        Please add your payment method to complete the booking.
                        Your card will be stored securely for future bookings.
                    </p>

                    {loading && !clientSecret && (
                        <div className="py-8 text-center">
                            <div className="mx-auto h-8 w-8 animate-spin rounded-full border-4 border-indigo-500 border-t-transparent"></div>
                            <p className="mt-2 text-gray-600">
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

                <p className="mt-6 text-center text-sm text-gray-500">
                    Your payment information is securely processed by Stripe.
                </p>
            </div>
        </div>
    );
}
