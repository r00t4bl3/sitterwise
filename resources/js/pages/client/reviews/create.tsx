import { useForm } from '@inertiajs/react';
import { Head } from '@inertiajs/react';
import { RatingInput } from '@/components/rating-input';
import AppLayout from '@/layouts/app-layout';
import { Button } from '@/components/ui/button';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { ToasterMessage } from '@/components/toaster-message';
import { formatDisplayDate, formatDisplayTime } from '@/lib/datetime';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';

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
}

const breadcrumbs = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Bookings', href: '/bookings' },
    { title: 'Write Review', href: '#' },
];

export default function ReviewBooking({ booking }: PageProps) {
    const form = useForm({
        rating: booking.existing_rating || 0,
        comment: booking.existing_comment || '',
        tip: booking.existing_tip || '',
    });

    function submit(e: React.FormEvent) {
        e.preventDefault();
        form.post(`/reviews/${booking.ulid}`);
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
                        {formatDisplayDate(booking.start_datetime)} from{' '}
                        {formatDisplayTime(booking.start_datetime)} to{' '}
                        {formatDisplayTime(booking.end_datetime)}
                    </p>
                </div>

                <form
                    onSubmit={submit}
                    className="space-y-6 rounded-lg border border-border bg-card p-6"
                >
                    <div>
                        <Label>Rating</Label>
                        <div className="mt-2">
                            <RatingInput
                                value={form.data.rating}
                                onChange={(val) => form.setData('rating', val)}
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
                                onChange={(e) =>
                                    form.setData('tip', e.target.value)
                                }
                                placeholder="0.00"
                                min="0"
                                step="0.01"
                                className="pl-8"
                            />
                        </div>
                        <p className="mt-1 text-xs text-muted-foreground">
                            Tip will be charged immediately to your default
                            payment method. 100% of tips go directly to the
                            sitter.
                        </p>
                        {form.errors.tip && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.tip}
                            </p>
                        )}
                    </div>

                    <Button
                        type="submit"
                        disabled={form.processing || form.data.rating === 0}
                        className="w-full"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        {form.processing ? 'Submitting...' : 'Submit Review'}
                    </Button>
                </form>
            </div>
        </AppLayout>
    );
}
