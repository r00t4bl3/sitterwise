import { Head } from '@inertiajs/react';
import { CheckCircle } from 'lucide-react';
import GuestLayout from '@/layouts/guest-layout';

interface PageProps {
    caregiver_name: string;
    tip_amount?: number;
}

export default function GuestReviewSuccess({ caregiver_name, tip_amount }: PageProps) {
    return (
        <GuestLayout>
            <Head title="Review Submitted" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-6 p-4">
                <div className="rounded-full bg-green-100 p-4">
                    <CheckCircle className="h-16 w-16 text-green-600" />
                </div>

                <h1 className="text-2xl font-bold text-foreground">
                    Thank You for Your Review!
                </h1>

                <p className="text-center text-muted-foreground">
                    Your review for {caregiver_name} has been submitted successfully.
                    {tip_amount && tip_amount > 0 && (
                        <span className="block mt-2">
                            A tip of ${tip_amount.toFixed(2)} has also been processed.
                        </span>
                    )}
                </p>

                <p className="text-sm text-muted-foreground">
                    You can now close this window.
                </p>
            </div>
        </GuestLayout>
    );
}