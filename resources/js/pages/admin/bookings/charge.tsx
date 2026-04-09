import { Head, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { CreditCard, DollarSign, Receipt, Loader2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
    {
        title: 'Charge Booking',
        href: '/admin/bookings/charge',
    },
];

interface Booking {
    id: number;
    total_amount: number;
    reimbursement: number | null;
    tip: number | null;
    payment_status: string;
    client: {
        full_name: string;
    };
    service_type: string;
    start_datetime: string;
}

interface Props {
    [key: string]: unknown;
    booking?: Booking;
}

export default function ChargeBooking() {
    const { booking } = usePage<Props>().props;

    const [reimbursement, setReimbursement] = useState<string>(
        booking?.reimbursement?.toString() || '0',
    );
    const [tip, setTip] = useState<string>(booking?.tip?.toString() || '0');
    const [notes, setNotes] = useState('');
    const [isLoading, setIsLoading] = useState(false);
    const [result, setResult] = useState<{
        success?: boolean;
        message?: string;
    } | null>(null);

    const baseAmount = booking?.total_amount || 0;
    const reimbursementValue = parseFloat(reimbursement) || 0;
    const tipValue = parseFloat(tip) || 0;
    const total = baseAmount + reimbursementValue + tipValue;

    const handleCharge = async () => {
        if (!booking) return;

        setIsLoading(true);
        setResult(null);

        try {
            const response = await fetch(
                `/admin/bookings/${booking.id}/charge`,
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN':
                            (
                                document.querySelector(
                                    'meta[name="csrf-token"]',
                                ) as HTMLMetaElement
                            )?.content || '',
                    },
                    body: JSON.stringify({
                        reimbursement: reimbursementValue,
                        tip: tipValue,
                        notes,
                    }),
                },
            );

            const data = await response.json();
            setResult(data);

            if (data.success) {
                setTimeout(() => {
                    window.location.href = '/bookings';
                }, 2000);
            }
        } catch (error) {
            setResult({
                success: false,
                message: 'An error occurred while processing the payment.',
            });
        } finally {
            setIsLoading(false);
        }
    };

    const formatCurrency = (amount: number) => {
        return new Intl.NumberFormat('en-US', {
            style: 'currency',
            currency: 'USD',
        }).format(amount);
    };

    const dummyBooking: Booking = {
        id: 1,
        total_amount: 150.0,
        reimbursement: 25.0,
        tip: 20.0,
        payment_status: 'pending',
        client: {
            full_name: 'John Smith',
        },
        service_type: 'babysitter',
        start_datetime: '2026-04-15T10:00:00',
    };

    const displayBooking = booking || dummyBooking;
    const displayReimbursement = booking
        ? reimbursementValue
        : reimbursementValue;
    const displayTip = booking ? tipValue : tipValue;
    const displayTotal =
        (displayBooking.total_amount || 0) + displayReimbursement + displayTip;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Charge Booking" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Charge Booking #{displayBooking.id}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Client: {displayBooking.client.full_name}
                        </p>
                    </div>
                </div>

                <div className="rounded-[6px] border border-border bg-card p-6">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Payment Summary
                    </h2>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                Base Amount
                            </span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(
                                    displayBooking.total_amount || 0,
                                )}
                            </span>
                        </div>

                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                Reimbursement
                            </span>
                            <span className="font-medium text-foreground">
                                + {formatCurrency(displayReimbursement)}
                            </span>
                        </div>

                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">Tip</span>
                            <span className="font-medium text-foreground">
                                + {formatCurrency(displayTip)}
                            </span>
                        </div>

                        <div className="border-t pt-3">
                            <div className="flex items-center justify-between">
                                <span className="text-lg font-semibold text-foreground">
                                    Total
                                </span>
                                <span className="text-lg font-bold text-ring">
                                    {formatCurrency(displayTotal)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="rounded-[6px] border border-border bg-card p-6">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Adjustments
                    </h2>

                    <div className="grid gap-4">
                        <div>
                            <Label
                                htmlFor="reimbursement"
                                className="flex items-center gap-2"
                            >
                                <Receipt className="h-4 w-4" />
                                Reimbursement
                            </Label>
                            <div className="relative mt-1">
                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                                    $
                                </span>
                                <Input
                                    id="reimbursement"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={reimbursement}
                                    onChange={(e) =>
                                        setReimbursement(e.target.value)
                                    }
                                    className="pl-7"
                                    placeholder="0.00"
                                />
                            </div>
                            <p className="mt-1 text-xs text-muted-foreground">
                                Additional fees (parking, etc.)
                            </p>
                        </div>

                        <div>
                            <Label
                                htmlFor="tip"
                                className="flex items-center gap-2"
                            >
                                <DollarSign className="h-4 w-4" />
                                Tip
                            </Label>
                            <div className="relative mt-1">
                                <span className="absolute top-1/2 left-3 -translate-y-1/2 text-muted-foreground">
                                    $
                                </span>
                                <Input
                                    id="tip"
                                    type="number"
                                    step="0.01"
                                    min="0"
                                    value={tip}
                                    onChange={(e) => setTip(e.target.value)}
                                    className="pl-7"
                                    placeholder="0.00"
                                />
                            </div>
                        </div>

                        <div>
                            <Label
                                htmlFor="notes"
                                className="flex items-center gap-2"
                            >
                                Notes
                            </Label>
                            <textarea
                                id="notes"
                                value={notes}
                                onChange={(e) => setNotes(e.target.value)}
                                rows={2}
                                className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                placeholder="Optional notes..."
                            />
                        </div>
                    </div>
                </div>

                {result && (
                    <div
                        className={`rounded-[6px] border p-4 ${
                            result.success
                                ? 'border-green-500 bg-green-50 text-green-700'
                                : 'border-red-500 bg-red-50 text-red-700'
                        }`}
                    >
                        {result.message}
                    </div>
                )}

                <div className="flex justify-end gap-2">
                    <Button
                        variant="outline"
                        onClick={() => window.history.back()}
                        disabled={isLoading}
                    >
                        Cancel
                    </Button>
                    <Button
                        onClick={handleCharge}
                        disabled={isLoading}
                        className="gap-2"
                    >
                        {isLoading && (
                            <Loader2 className="h-4 w-4 animate-spin" />
                        )}
                        <CreditCard className="h-4 w-4" />
                        {isLoading ? 'Processing...' : 'Charge Client'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
