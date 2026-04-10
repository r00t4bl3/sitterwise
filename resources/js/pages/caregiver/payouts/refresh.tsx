import { Head, useForm, router } from '@inertiajs/react';
import { AlertCircle, ArrowRight } from 'lucide-react';
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
        title: 'Try Again',
        href: '/payouts/stripe/refresh',
    },
];

export default function CaregiverPayoutsRefresh() {
    const onboardingForm = useForm<{ url: string }>({ url: '' });

    const handleRetry = () => {
        onboardingForm.get('/payouts/stripe/onboarding', {
            onSuccess: (page: any) => {
                if (page.props.url) {
                    window.location.href = page.props.url;
                }
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Try Again" />
            <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                <div className="flex flex-col items-center justify-center rounded-lg border border-yellow-200 bg-yellow-50 p-12 text-center dark:border-yellow-800 dark:bg-yellow-900/20">
                    <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                        <AlertCircle className="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
                    </div>
                    <h3 className="mb-2 text-lg font-semibold text-foreground">
                        Continue Your Setup
                    </h3>
                    <p className="mb-6 max-w-md text-sm text-muted-foreground">
                        You&apos;ve exited the Stripe onboarding process before
                        completing it. Click below to continue where you left
                        off.
                    </p>
                    <div className="flex gap-4">
                        <Button
                            variant="outline"
                            onClick={() => router.visit('/payouts')}
                        >
                            Back to Payouts
                        </Button>
                        <Button
                            onClick={handleRetry}
                            disabled={onboardingForm.processing}
                        >
                            {onboardingForm.processing ? (
                                <span className="flex items-center gap-2">
                                    <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                                    Loading...
                                </span>
                            ) : (
                                <>
                                    Continue Setup
                                    <ArrowRight className="ml-2 h-4 w-4" />
                                </>
                            )}
                        </Button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
