import { Head, usePage } from '@inertiajs/react';
import { CreditCard, Plus, Trash2, Star } from 'lucide-react';
import { useState } from 'react';
import { StripeCheckout } from '@/components/stripe/stripe-checkout';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
} from '@/components/ui/dialog';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Payments',
        href: '/payments',
    },
];

interface PaymentMethod {
    id: number;
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
    is_default: boolean;
    status: string;
}

interface Booking {
    id: number;
    start_datetime: string;
    service_type: string;
}

interface ClientPayment {
    id: number;
    booking_id: number;
    amount: number;
    currency: string;
    status: string;
    provider: string;
    paid_at: string | null;
    created_at: string;
    booking: Booking | null;
    payment_method: PaymentMethod | null;
}

interface Props {
    [key: string]: unknown;
    payments: {
        data: ClientPayment[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    paymentMethods: PaymentMethod[];
}

function StatusBadge({ status }: { status: string }) {
    const statusColors: Record<string, { bg: string; text: string }> = {
        pending: { bg: 'bg-yellow-100', text: 'text-yellow-800' },
        authorized: { bg: 'bg-blue-100', text: 'text-blue-800' },
        captured: { bg: 'bg-green-100', text: 'text-green-800' },
        failed: { bg: 'bg-red-100', text: 'text-red-800' },
        refunded: { bg: 'bg-gray-100', text: 'text-gray-800' },
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

function PaymentMethodBadge({ method }: { method: PaymentMethod | null }) {
    if (!method) {
        return <span className="text-muted-foreground">—</span>;
    }

    return (
        <div className="flex items-center gap-1">
            <CreditCard className="h-3 w-3 text-muted-foreground" />
            <span className="text-sm capitalize">
                {method.brand} •••• {method.last4}
            </span>
            {method.is_default && (
                <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-800">
                    Default
                </span>
            )}
        </div>
    );
}

function PaymentMethodCard({
    method,
    onSetDefault,
    onDelete,
}: {
    method: PaymentMethod;
    onSetDefault: (id: number) => void;
    onDelete: (id: number) => void;
}) {
    const [isDeleting, setIsDeleting] = useState(false);

    const handleDelete = async () => {
        setIsDeleting(true);

        try {
            await onDelete(method.id);
        } finally {
            setIsDeleting(false);
        }
    };

    return (
        <div className="flex items-center justify-between rounded-lg border border-border p-4">
            <div className="flex items-center gap-3">
                <div className="flex h-10 w-12 items-center justify-center rounded bg-muted">
                    <CreditCard className="h-5 w-5 text-muted-foreground" />
                </div>
                <div>
                    <p className="text-sm font-medium text-foreground capitalize">
                        {method.brand} •••• {method.last4}
                    </p>
                    <p className="text-xs text-muted-foreground">
                        Expires {method.exp_month.toString().padStart(2, '0')}/
                        {method.exp_year}
                    </p>
                </div>
            </div>
            <div className="flex items-center gap-2">
                {method.is_default ? (
                    <span className="flex items-center gap-1 text-xs font-medium text-amber-600">
                        <Star className="h-3 w-3 fill-amber-600" /> Default
                    </span>
                ) : (
                    <>
                        <Button
                            variant="link"
                            size="sm"
                            type="button"
                            onClick={() => onSetDefault(method.id)}
                        >
                            Set as default
                        </Button>
                        <Button
                            variant="ghost"
                            size="icon-sm"
                            type="button"
                            onClick={handleDelete}
                            disabled={isDeleting}
                            className="text-muted-foreground hover:bg-red-50 hover:text-red-600"
                        >
                            <Trash2 className="h-4 w-4" />
                        </Button>
                    </>
                )}
            </div>
        </div>
    );
}

export default function ClientPaymentsIndex() {
    const { payments, paymentMethods: initialPaymentMethods } =
        usePage<Props>().props;
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [isAddingCard, setIsAddingCard] = useState(false);
    const [clientSecret, setClientSecret] = useState<string | null>(null);
    const [paymentMethods, setPaymentMethods] = useState<PaymentMethod[]>(
        initialPaymentMethods,
    );
    const [isLoading, setIsLoading] = useState(false);

    const formatCurrency = (amount: number, currency: string = 'usd') => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: currency.toUpperCase(),
        }).format(amount);
    };

    const formatDate = (dateString: string | null) => {
        if (!dateString) {
            return '—';
        }

        return new Date(dateString).toLocaleDateString('en-US', {
            year: 'numeric',
            month: 'short',
            day: 'numeric',
        });
    };

    const handleAddCardClick = async () => {
        setIsLoading(true);

        try {
            const response = await fetch('/payments/setup-intent', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                },
            });
            const data = await response.json();
            setClientSecret(data.client_secret);
            setIsAddingCard(true);
        } catch (error) {
            console.error('Failed to create setup intent:', error);
        } finally {
            setIsLoading(false);
        }
    };

    const handleSetDefault = async (id: number) => {
        try {
            const response = await fetch(`/payments/methods/${id}/default`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                setPaymentMethods((methods) =>
                    methods.map((m) => ({
                        ...m,
                        is_default: m.id === id,
                    })),
                );
            }
        } catch (error) {
            console.error('Failed to set default:', error);
        }
    };

    const handleDelete = async (id: number) => {
        try {
            const response = await fetch(`/payments/methods/${id}`, {
                method: 'DELETE',
                headers: {
                    'Content-Type': 'application/json',
                },
            });

            if (response.ok) {
                setPaymentMethods((methods) =>
                    methods.filter((m) => m.id !== id),
                );
            }
        } catch (error) {
            console.error('Failed to delete:', error);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Payments" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Payments
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {payments.total} transactions total
                        </p>
                    </div>
                    <Button onClick={() => setIsModalOpen(true)}>
                        <Plus className="h-4 w-4" />
                        Manage Payment Methods
                    </Button>
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
                                    Payment Method
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Booking
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {payments.data.map((payment) => (
                                <tr
                                    key={payment.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {formatDate(payment.created_at)}
                                    </td>
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {formatCurrency(
                                            payment.amount,
                                            payment.currency,
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusBadge status={payment.status} />
                                    </td>
                                    <td className="px-4 py-3">
                                        <PaymentMethodBadge
                                            method={payment.payment_method}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {payment.booking ? (
                                            <span>#{payment.booking.id}</span>
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {payments.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No payment history found.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>

                {payments.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {payments.current_page} of {payments.last_page}
                        </p>
                    </div>
                )}
            </div>

            <Dialog open={isModalOpen} onOpenChange={setIsModalOpen}>
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Payment Methods</DialogTitle>
                        <DialogDescription>
                            Manage your saved payment methods for future
                            bookings.
                        </DialogDescription>
                    </DialogHeader>

                    {isAddingCard && clientSecret ? (
                        <div className="mt-4">
                            <StripeCheckout clientSecret={clientSecret} />
                        </div>
                    ) : (
                        <div className="mt-4 space-y-4">
                            {paymentMethods.length > 0 ? (
                                <div className="space-y-2">
                                    {paymentMethods.map((method) => (
                                        <PaymentMethodCard
                                            key={method.id}
                                            method={method}
                                            onSetDefault={handleSetDefault}
                                            onDelete={handleDelete}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No payment methods saved yet.
                                </p>
                            )}

                            <Button
                                type="button"
                                onClick={handleAddCardClick}
                                disabled={isLoading}
                                className="w-full"
                            >
                                <Plus className="mr-2 h-4 w-4" />
                                {isLoading
                                    ? 'Loading...'
                                    : 'Add New Payment Method'}
                            </Button>
                        </div>
                    )}
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
