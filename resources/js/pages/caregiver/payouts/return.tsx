import { Head, usePage, router } from '@inertiajs/react';
import { CheckCircle2, XCircle, ArrowRight } from 'lucide-react';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Payouts',
        href: '/payouts',
    },
    {
        title: 'Setup Complete',
        href: '/payouts/stripe/return',
    },
];

function SuccessState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-green-200 bg-green-50 p-12 text-center dark:border-green-800 dark:bg-green-900/20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100 dark:bg-green-900">
                <CheckCircle2 className="h-8 w-8 text-green-600 dark:text-green-400" />
            </div>
            <h3 className="mb-2 text-lg font-semibold text-foreground">
                Stripe Account Connected!
            </h3>
            <p className="mb-6 max-w-md text-sm text-muted-foreground">
                Your Stripe account has been successfully connected. You can now
                receive payouts for your caregiving services.
            </p>
            <Button onClick={() => router.visit('/payouts')}>
                Go to Payouts
                <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
        </div>
    );
}

function ErrorState() {
    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-red-200 bg-red-50 p-12 text-center dark:border-red-800 dark:bg-red-900/20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100 dark:bg-red-900">
                <XCircle className="h-8 w-8 text-red-600 dark:text-red-400" />
            </div>
            <h3 className="mb-2 text-lg font-semibold text-foreground">
                Setup Incomplete
            </h3>
            <p className="mb-6 max-w-md text-sm text-muted-foreground">
                There was an issue completing your Stripe setup. You can try
                again or contact support for assistance.
            </p>
            <Button onClick={() => router.visit('/payouts')}>
                Back to Payouts
                <ArrowRight className="ml-2 h-4 w-4" />
            </Button>
        </div>
    );
}

export default function CaregiverPayoutsReturn() {
    const { success } = usePage<{ success: boolean }>().props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Setup Complete" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                {success ? <SuccessState /> : <ErrorState />}
            </div>
        </AppLayout>
    );
}
