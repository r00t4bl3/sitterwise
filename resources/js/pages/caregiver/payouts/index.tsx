import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    CircleDollarSign,
    AlertCircle,
    ExternalLink,
    CreditCard,
    ChevronLeft,
    ChevronRight,
} from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTimeInPT } from '@/lib/datetime';
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
];

interface PayoutMethod {
    id: number;
    bank_name: string;
    last4: string;
    account_type: string;
    is_default: boolean;
    status: string;
}

interface CaregiverPayout {
    id: number;
    amount: number;
    currency: string;
    status: string;
    payout_date: string | null;
    created_at: string;
    provider_transfer_id: string | null;
    payout_method: PayoutMethod | null;
}

interface PayoutsPagination {
    data: CaregiverPayout[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{
        url: string | null;
        label: string;
        active: boolean;
    }>;
}

interface StripeStatus {
    connected: boolean;
    status: string | null;
    details_submitted: boolean;
    charges_enabled: boolean;
    payouts_enabled: boolean;
    requirements: unknown | null;
}

interface Props {
    [key: string]: unknown;
    stripeStatus: StripeStatus;
    payoutMethods: PayoutMethod[];
    payouts: PayoutsPagination;
}

function StatusBadge({ status }: { status: string }) {
    const statusColors: Record<string, { bg: string; text: string }> = {
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        paid: { bg: 'bg-green-100', text: 'text-green-800' },
        failed: { bg: 'bg-red-100', text: 'text-red-800' },
    };

    const colors = statusColors[status] || {
        bg: 'bg-gray-100',
        text: 'text-gray-800',
    };

    return (
        <span
            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${colors.bg} ${colors.text}`}
        >
            {status}
        </span>
    );
}

function PayoutMethodBadge({ method }: { method: PayoutMethod | null }) {
    if (!method) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex items-center gap-1">
            <CreditCard className="h-3 w-3 text-muted-foreground" />
            <span className="text-sm capitalize">
                {method.bank_name} •••• {method.last4}
            </span>
            {method.is_default && (
                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">
                    Default
                </span>
            )}
        </div>
    );
}

function NotConnectedState({ onConnect }: { onConnect: () => void }) {
    const [isConnecting, setIsConnecting] = useState(false);

    const handleConnect = async () => {
        setIsConnecting(true);
        await onConnect();
        setIsConnecting(false);
    };

    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-border bg-card p-12 text-center">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                <CircleDollarSign className="h-8 w-8 text-muted-foreground" />
            </div>
            <h3 className="mb-2 text-lg font-semibold text-foreground">
                Get Started with Payouts
            </h3>
            <p className="mb-6 max-w-md text-sm text-muted-foreground">
                Connect your Stripe account to receive payouts for your
                caregiving services. We&apos;ll guide you through the setup
                process.
            </p>
            <Button
                onClick={handleConnect}
                disabled={isConnecting}
                className="gap-2"
            >
                {isConnecting ? (
                    <>
                        <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        Connecting...
                    </>
                ) : (
                    <>
                        <ExternalLink className="h-4 w-4" />
                        Connect with Stripe
                    </>
                )}
            </Button>
        </div>
    );
}

function PendingState({ onRetry }: { onRetry: () => void }) {
    const [isRetrying, setIsRetrying] = useState(false);

    const handleRetry = async () => {
        setIsRetrying(true);
        await onRetry();
        setIsRetrying(false);
    };

    return (
        <div className="flex flex-col items-center justify-center rounded-lg border border-yellow-200 bg-yellow-50 p-12 text-center dark:border-yellow-800 dark:bg-yellow-900/20">
            <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-yellow-100 dark:bg-yellow-900">
                <AlertCircle className="h-8 w-8 text-yellow-600 dark:text-yellow-400" />
            </div>
            <h3 className="mb-2 text-lg font-semibold text-foreground">
                Setup Incomplete
            </h3>
            <p className="mb-6 max-w-md text-sm text-muted-foreground">
                Your Stripe account is pending. Please complete the verification
                process to start receiving payouts.
            </p>
            <Button
                onClick={handleRetry}
                disabled={isRetrying}
                className="gap-2"
            >
                {isRetrying ? (
                    <>
                        <span className="h-4 w-4 animate-spin rounded-full border-2 border-current border-t-transparent" />
                        Loading...
                    </>
                ) : (
                    <>
                        <ExternalLink className="h-4 w-4" />
                        Continue Setup
                    </>
                )}
            </Button>
        </div>
    );
}

function PayoutMethodCard({ method }: { method: PayoutMethod }) {
    return (
        <div className="flex items-center justify-between rounded-lg border border-border p-4">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-12 items-center justify-center rounded bg-muted">
                    <CircleDollarSign className="h-5 w-5 text-muted-foreground" />
                </div>
                <div>
                    <p className="text-sm font-medium text-foreground">
                        {method.bank_name} •••• {method.last4}
                    </p>
                    <p className="text-xs text-muted-foreground capitalize">
                        {method.account_type}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                {method.is_default && (
                    <span className="rounded bg-amber-100 px-2 py-1 text-xs font-medium text-amber-800 dark:bg-amber-900 dark:text-amber-200">
                        Default
                    </span>
                )}
            </div>
        </div>
    );
}

export default function CaregiverPayoutsIndex() {
    const {
        stripeStatus: initialStatus,
        payoutMethods,
        payouts,
    } = usePage<Props>().props;
    const [stripeStatus] = useState<StripeStatus>(initialStatus);

    const connectForm = useForm({});
    const onboardingForm = useForm<{ url: string }>({ url: '' });

    const formatCurrency = (amount: number, currency: string = 'usd') => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency.toUpperCase(),
        }).format(amount);
    };

    const handleConnect = () => {
        connectForm.post('/payouts/stripe/connect', {
            onSuccess: () => {
                onboardingForm.get('/payouts/stripe/onboarding', {
                    onSuccess: (page: any) => {
                        if (page.props.url) {
                            window.location.href = page.props.url;
                        }
                    },
                });
            },
            onError: (errors) => {
                console.error('Failed to connect:', errors);
            },
        });
    };

    const handleRetry = () => {
        onboardingForm.get('/payouts/stripe/onboarding', {
            onSuccess: (page: any) => {
                if (page.props.url) {
                    window.location.href = page.props.url;
                }
            },
            onError: (errors) => {
                console.error('Failed to retry:', errors);
            },
        });
    };

    const renderStatus = () => {
        if (!stripeStatus.connected) {
            return <NotConnectedState onConnect={handleConnect} />;
        }

        if (!stripeStatus.payouts_enabled || !stripeStatus.charges_enabled) {
            return <PendingState onRetry={handleRetry} />;
        }

        return null;
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payouts" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Payouts
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage your payout settings and view payment history
                        </p>
                    </div>

                    {stripeStatus.connected && stripeStatus.payouts_enabled && (
                        <Sheet>
                            <SheetTrigger asChild>
                                <Button className="gap-2">
                                    <CreditCard className="h-4 w-4" />
                                    Payout Methods
                                </Button>
                            </SheetTrigger>
                            <SheetContent className="sm:max-w-md">
                                <SheetHeader>
                                    <SheetTitle>Payout Methods</SheetTitle>
                                    <SheetDescription>
                                        Manage the bank accounts used to receive
                                        your payouts.
                                    </SheetDescription>
                                </SheetHeader>
                                <div className="space-y-4 px-4">
                                    {payoutMethods.length > 0 ? (
                                        <div className="space-y-2">
                                            {payoutMethods.map((method) => (
                                                <PayoutMethodCard
                                                    key={method.id}
                                                    method={method}
                                                />
                                            ))}
                                        </div>
                                    ) : (
                                        <div className="rounded-lg border border-dashed border-border p-8 text-center">
                                            <p className="text-sm text-muted-foreground">
                                                No payout methods added yet.
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </SheetContent>
                        </Sheet>
                    )}
                </div>

                {renderStatus()}

                {stripeStatus.connected && stripeStatus.payouts_enabled && (
                    <div className="flex flex-col gap-4">
                        <div className="flex items-center justify-between">
                            <h2 className="text-lg font-semibold text-foreground">
                                Payout History
                            </h2>
                            <span className="text-xs text-muted-foreground">
                                {payouts.total} transactions total
                            </span>
                        </div>

                        <div className="border border-border bg-card">
                            <table className="w-full">
                                <thead>
                                    <tr className="bg-foreground">
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Date
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Amount
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Method
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Transfer ID
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {payouts.data.map((payout) => (
                                        <tr
                                            key={payout.id}
                                            className="border-b border-border transition hover:bg-blush"
                                        >
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {formatDisplayDateTimeInPT(
                                                    payout.payout_date ||
                                                        payout.created_at,
                                                )}
                                            </td>
                                            <td className="px-4 py-3 text-sm font-medium text-foreground">
                                                {formatCurrency(
                                                    payout.amount,
                                                    payout.currency,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={payout.status}
                                                />
                                            </td>
                                            <td className="px-4 py-3">
                                                <PayoutMethodBadge
                                                    method={
                                                        payout.payout_method
                                                    }
                                                />
                                            </td>
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {payout.provider_transfer_id ||
                                                    '—'}
                                            </td>
                                        </tr>
                                    ))}
                                    {payouts.data.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={5}
                                                className="px-4 py-8 text-center text-muted-foreground"
                                            >
                                                No payout history found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {payouts.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Page {payouts.current_page} of{' '}
                                    {payouts.last_page}
                                </p>
                                <div className="flex gap-1">
                                    {payouts.links.map((link, index) => {
                                        if (link.label === '...') {
                                            return null;
                                        }

                                        const isPrev =
                                            link.label.includes('Previous') ||
                                            link.label.includes('&laquo;');
                                        const isNext =
                                            link.label.includes('Next') ||
                                            link.label.includes('&raquo;');

                                        return (
                                            <Link
                                                key={index}
                                                href={link.url || '#'}
                                                className={`flex h-8 w-8 items-center justify-center rounded text-sm ${
                                                    link.active
                                                        ? 'bg-foreground text-white'
                                                        : 'border border-border text-muted-foreground hover:bg-accent'
                                                } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                            >
                                                {isPrev ? (
                                                    <ChevronLeft className="h-4 w-4" />
                                                ) : isNext ? (
                                                    <ChevronRight className="h-4 w-4" />
                                                ) : (
                                                    link.label
                                                )}
                                            </Link>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
