import { useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { useState } from 'react';
import { RatingInput } from '@/components/rating-input';
import { StripeCardInput } from '@/components/stripe/stripe-card-element';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';

interface BookingData {
    ulid: string;
    start_datetime: string;
    end_datetime: string;
    caregiver_name: string;
    existing_rating?: number;
    existing_comment?: string;
    existing_tip?: number;
}

interface PageProps {
    booking: BookingData;
    has_default_payment_method?: boolean;
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bookings', href: '/bookings' },
    { title: 'Write Review', href: '#' },
];

export default function ReviewBooking({
    booking,
    has_default_payment_method,
}: PageProps) {
    const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);
    const [paymentError, setPaymentError] = useState<string | null>(null);

    const form = useForm({
        rating: booking.existing_rating || 0,
        comment: booking.existing_comment || '',
        tip: booking.existing_tip || '',
        payment_method_id: '',
    });

    const handlePaymentMethodReady = (pmId: string | null) => {
        setPaymentMethodId(pmId);
        form.setData('payment_method_id', pmId || '');
    };

    const hasTip = form.data.tip && parseFloat(String(form.data.tip)) > 0;
    const showCardInput = hasTip && !has_default_payment_method;

    function submit(e: React.FormEvent) {
        e.preventDefault();

        if (showCardInput && !paymentMethodId) {
            setPaymentError('Please enter your card details to add a tip');

            return;
        }

        const queryString = window.location.search;
        form.post(`/reviews/${booking.ulid}${queryString}`);
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Review Booking" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="rounded-lg border border-border bg-card p-6">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Review {booking.caregiver_name}
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        {formatDisplayDateInPT(booking.start_datetime)} from{' '}
                        {formatDisplayTimeInPT(booking.start_datetime)} to{' '}
                        {formatDisplayTimeInPT(booking.end_datetime)}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg border border-border bg-card p-6"
                >
                    <input
                        type="hidden"
                        name="payment_method_id"
                        value={form.data.payment_method_id}
                    />
                    <div>
                        <Label>Rating</Label>
                        <div className="mt-2">
                            <RatingInput
                                value={form.data.rating}
                                onChange={(val) =>
                                    form.setData('rating', val ?? 0)
                                }
                            />
                        </div>
                        {form.errors.rating && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.rating}
                            </p>
                        )}
                    </div>

                    <div>
                        <Label>Feedback (Optional)</Label>
                        <Textarea
                            value={form.data.comment}
                            onChange={(e) =>
                                form.setData('comment', e.target.value)
                            }
                            placeholder="How did things go? Did your sitter meet your expectations? Any specific feedback you have for them?"
                            className="mt-2"
                        />
                        {form.errors.comment && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.comment}
                            </p>
                        )}
                    </div>

                    <div>
                        <Label className="text-sm font-medium text-foreground">
                            Tip Caregiver (Optional)
                        </Label>
                        <div className="relative mt-2">
                            <span className="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                                $
                            </span>
                            <Input
                                type="number"
                                value={form.data.tip}
                                onChange={(e) => {
                                    form.setData('tip', e.target.value);
                                    setPaymentMethodId(null);
                                    setPaymentError(null);
                                }}
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                className="pl-8"
                            />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Tip will be charged immediately. 100% of tips go
                            directly to the sitter.
                        </p>
                        {form.errors.tip && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.tip}
                            </p>
                        )}
                    </div>

                    {showCardInput && (
                        <div>
                            <Label className="text-sm font-medium text-foreground">
                                Payment Details
                            </Label>
                            <div className="mt-2 rounded-md border border-border p-4">
                                <StripeCardInput
                                    onPaymentMethodReady={
                                        handlePaymentMethodReady
                                    }
                                    error={paymentError || undefined}
                                />
                            </div>
                            <p className="mt-2 text-xs text-muted-foreground">
                                Your card details are securely processed by
                                Stripe.
                            </p>
                        </div>
                    )}

                    <Button
                        type="submit"
                        disabled={
                            !!(
                                form.processing ||
                                !form.data.rating ||
                                (showCardInput && !paymentMethodId)
                            )
                        }
                        className="w-full"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        {form.processing
                            ? 'Submitting...'
                            : hasTip
                              ? 'Submit Review & Tip'
                              : 'Submit Review'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
