import { Head, router, usePage } from '@inertiajs/react';
import { CreditCard, DollarSign, Receipt, Loader2, User } from 'lucide-react';
import { useState } from 'react';
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
    bonus: number | null;
    payment_status: string;
    charge_to_client: number | null;
    paid_to_caregiver: number | null;
    sitterwise_cut: number | null;
    paid_to_caregiver_total: number | null;
    total_service_amount: number | null;
    client: {
        full_name: string;
    };
    caregiver: {
        id: number;
        name: string;
    } | null;
    service_type: string;
    start_datetime: string;
}

interface PayoutMethod {
    id: number;
    bank_name: string;
    last4: string;
    is_default: boolean;
}

interface Props {
    [key: string]: unknown;
    booking?: Booking;
    payout_methods?: PayoutMethod[];
    default_payout_method?: PayoutMethod | null;
}

export default function ChargeBooking() {
    const { booking, payout_methods, default_payout_method } =
        usePage<Props>().props;

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

    const chargeToClient = booking?.charge_to_client ?? 0;
    const sitterwiseCut = booking?.sitterwise_cut ?? 0;
    const paidToCaregiver = booking?.paid_to_caregiver ?? 0;
    const paidToCaregiverTotal = booking?.paid_to_caregiver_total ?? 0;
    const totalServiceAmount =
        booking?.total_service_amount ?? booking?.total_amount ?? 0;

    const reimbursementValue = parseFloat(reimbursement) || 0;
    const tipValue = parseFloat(tip) || 0;

    const totalCharged = totalServiceAmount + reimbursementValue + tipValue;

    const handleCharge = async () => {
        if (!booking) {
            return;
        }

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
                    router.visit('/bookings');
                }, 2000);
            }
        } catch {
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

    if (!booking) {
        return (
            <AppLayout breadcrumbs={breadcrumbs}>
                <Head title="Charge Booking" />
                <div className="flex h-full flex-1 flex-col items-center justify-center gap-4 p-4">
                    <p className="text-muted-foreground">
                        No booking selected. Please select a booking to charge.
                    </p>
                    <Button
                        variant="outline"
                        onClick={() => router.visit('/bookings')}
                    >
                        Go to Bookings
                    </Button>
                </div>
            </AppLayout>
        );
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Charge Booking" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Charge Booking #{booking.id}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Client: {booking.client.full_name}
                        </p>
                    </div>
                </div>

                <div className="border border-border bg-card p-6">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Payment Summary
                    </h2>

                    <div className="space-y-3">
                        <div className="flex items-center justify-between">
                            <span className="text-muted-foreground">
                                Service Amount (charge to client)
                            </span>
                            <span className="font-medium text-foreground">
                                {formatCurrency(totalServiceAmount)}
                            </span>
                        </div>

                        {reimbursementValue > 0 && (
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Reimbursement
                                </span>
                                <span className="font-medium text-foreground">
                                    + {formatCurrency(reimbursementValue)}
                                </span>
                            </div>
                        )}

                        {tipValue > 0 && (
                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Tip
                                </span>
                                <span className="font-medium text-foreground">
                                    + {formatCurrency(tipValue)}
                                </span>
                            </div>
                        )}

                        <div className="border-t pt-3">
                            <div className="flex items-center justify-between">
                                <span className="text-lg font-semibold text-foreground">
                                    Total Charged
                                </span>
                                <span className="text-lg font-bold text-ring">
                                    {formatCurrency(totalCharged)}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="border border-border bg-card p-6">
                    <h2 className="mb-4 text-lg font-semibold text-foreground">
                        Caregiver Payout Summary
                    </h2>

                    {booking.caregiver ? (
                        <div className="space-y-3">
                            <div className="flex items-center justify-between">
                                <span className="flex items-center gap-2 text-muted-foreground">
                                    <User className="h-4 w-4" />
                                    Caregiver
                                </span>
                                <span className="font-medium text-foreground">
                                    {booking.caregiver.name}
                                </span>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Caregiver Pay (hourly)
                                </span>
                                <span className="font-medium text-foreground">
                                    {formatCurrency(paidToCaregiverTotal)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Platform Fee (sitterwise cut)
                                </span>
                                <span className="font-medium text-foreground">
                                    {formatCurrency(sitterwiseCut)}
                                </span>
                            </div>

                            <div className="flex items-center justify-between">
                                <span className="text-muted-foreground">
                                    Net Charge to Client
                                </span>
                                <span className="font-medium text-foreground">
                                    {formatCurrency(chargeToClient)}
                                </span>
                            </div>

                            {default_payout_method && (
                                <div className="mt-3 rounded bg-gray-50 p-2 text-xs text-muted-foreground">
                                    Will be transferred to{' '}
                                    {default_payout_method.bank_name} ***
                                    {default_payout_method.last4}
                                </div>
                            )}
                        </div>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            No caregiver assigned to this booking.
                        </p>
                    )}
                </div>

                <div className="border border-border bg-card p-6">
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
                        className={`border p-4 ${
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
                        {isLoading ? 'Processing...' : 'Process Payment'}
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
