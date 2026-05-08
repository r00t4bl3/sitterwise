import { Head, Link, usePage, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Calendar,
    Clock,
    Receipt,
    DollarSign,
} from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Transactions',
        href: '/transactions',
    },
];

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        email: string;
    };
    has_active_payment_method: boolean;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    user?: {
        email: string;
    };
}

interface Booking {
    id: number;
    start_datetime: string;
    end_datetime: string;
    total_price: number;
    status: string;
    checkout_at: string | null;
    total_working_hour: number | null;
    children: Array<{ name: string }> | null;
    pets: Array<{ name: string; type: string | null }> | null;
    service_type: string;
    reimbursement: number | null;
    reimbursement_description: string | null;
    tip: number | null;
    bonus: number | null;
    paid_to_caregiver: number | null;
    sitterwise_cut: number | null;
    charge_to_client: number | null;
    charge_to_client_hourly: number | null;
    paid_to_caregiver_hourly: number | null;
    sitterwise_cut_hourly: number | null;
    client: Client;
    caregiver?: Caregiver;
}

interface Props {
    [key: string]: unknown;
    bookings: {
        data: Booking[];
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
    filters: {
        search: string | null;
    };
    booking_statuses: Array<{
        value: string;
        label: string;
        colors: {
            bg: string;
            text: string;
            border: string;
        };
    }>;
}

export default function TransactionsIndex() {
    const { bookings, filters, booking_statuses } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedBooking, setSelectedBooking] = useState<Booking | null>(
        null,
    );
    const [showPaymentSheet, setShowPaymentSheet] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);

    const [localValues, setLocalValues] = useState({
        total_working_hour: '',
        reimbursement: '',
        reimbursement_description: '',
        tip: '',
        bonus: '',
    });

    const [recalculatedValues, setRecalculatedValues] = useState({
        charge_to_client: 0,
        paid_to_caregiver: 0,
        sitterwise_cut: 0,
    });

    const paymentForm = useForm({
        total_working_hour: 0,
        reimbursement: 0,
        reimbursement_description: '',
        tip: 0,
        bonus: 0,
    });

    const formatDateTime = (dateStr: string) => {
        const date = new Date(dateStr);

        return date.toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: 'numeric',
            minute: '2-digit',
            hour12: true,
        });
    };

    const openPaymentSheet = (booking: Booking) => {
        const hourlyRate = booking.charge_to_client_hourly ?? 0;
        const caregiverHourly = booking.paid_to_caregiver_hourly ?? 0;
        const cutHourly = booking.sitterwise_cut_hourly ?? 0;
        const hours = booking.total_working_hour ?? 0;

        setSelectedBooking(booking);
        setLocalValues({
            total_working_hour: String(booking.total_working_hour ?? ''),
            reimbursement: String(booking.reimbursement ?? ''),
            reimbursement_description: booking.reimbursement_description ?? '',
            tip: String(booking.tip ?? ''),
            bonus: String(booking.bonus ?? ''),
        });
        paymentForm.setData({
            total_working_hour: booking.total_working_hour ?? 0,
            reimbursement: booking.reimbursement ?? 0,
            reimbursement_description: booking.reimbursement_description ?? '',
            tip: booking.tip ?? 0,
            bonus: booking.bonus ?? 0,
        });
        setRecalculatedValues({
            charge_to_client: hourlyRate * hours,
            paid_to_caregiver: caregiverHourly * hours,
            sitterwise_cut: cutHourly * hours,
        });
        setShowPaymentSheet(true);
    };

    const handlePaymentSubmit = () => {
        if (!selectedBooking) {
            return;
        }

        paymentForm.post(`/bookings/${selectedBooking.id}/process-payment`, {
            onSuccess: () => {
                setShowPaymentSheet(false);
                setShowConfirmDialog(false);
                setSelectedBooking(null);
            },
        });
    };

    const recalculatePayment = (hoursStr: string) => {
        if (!selectedBooking) {
            return;
        }

        const hours = parseFloat(hoursStr) || 0;
        const hourlyRate = selectedBooking.charge_to_client_hourly ?? 0;
        const caregiverHourly = selectedBooking.paid_to_caregiver_hourly ?? 0;
        const cutHourly = selectedBooking.sitterwise_cut_hourly ?? 0;

        setRecalculatedValues({
            charge_to_client: hourlyRate * hours,
            paid_to_caregiver: caregiverHourly * hours,
            sitterwise_cut: cutHourly * hours,
        });

        paymentForm.setData({
            total_working_hour: hours,
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Transactions" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Transactions
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {bookings.total} transactions total
                        </p>
                    </div>
                </div>

                <div className="flex gap-4">
                    <form method="get" className="flex flex-1 gap-2">
                        <input
                            type="search"
                            name="search"
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            placeholder="Search by booking ID, client or caregiver name..."
                            className="h-10 w-full max-w-md rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                        />
                        <button type="submit" className="btn-primary">
                            Search
                        </button>
                    </form>
                </div>

                <div className="border border-border bg-card">
                    {bookings.data.length === 0 ? (
                        <div className="rounded-lg border border-border bg-card p-12 text-center">
                            <Receipt className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                            <h3 className="text-lg font-medium text-foreground">
                                No completed transactions found.
                            </h3>
                            <p className="mt-2 text-sm text-muted-foreground">
                                There are no completed bookings to display.
                            </p>
                        </div>
                    ) : (
                        <div className="overflow-x-auto">
                            <table className="w-full min-w-[800px]">
                                <thead>
                                    <tr className="bg-foreground">
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            ID
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Client
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Caregiver
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Date & Time
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Total Price
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {bookings.data.map((booking) => (
                                        <tr
                                            key={booking.id}
                                            className="border-b border-border transition hover:bg-blush"
                                        >
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {booking.id}
                                            </td>
                                            <td className="px-4 py-3 text-sm font-medium text-ring">
                                                <Link
                                                    href={`/clients/${booking.client.id}`}
                                                    className="hover:underline"
                                                >
                                                    {booking.client.first_name}{' '}
                                                    {booking.client.last_name}
                                                </Link>
                                            </td>
                                            <td className="px-4 py-3 text-sm font-medium text-ring ">
                                                {booking.caregiver ? (
                                                    <>
                                                        <Link
                                                            href={`/caregivers/${booking.caregiver.id}`}
                                                            className="hover:underline"
                                                        >
                                                            {
                                                                booking
                                                                    .caregiver
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                booking
                                                                    .caregiver
                                                                    .last_name
                                                            }
                                                        </Link>
                                                    </>
                                                ) : (
                                                    <span className="text-sm text-muted-foreground">
                                                        N/A
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    <div className="text-sm text-foreground">
                                                        {formatDateTime(
                                                            booking.start_datetime,
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Clock className="h-3 w-3" />
                                                    Ends:{' '}
                                                    {formatDateTime(
                                                        booking.end_datetime,
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                $
                                                {Number(
                                                    booking.total_price ?? 0,
                                                ).toFixed(2)}
                                            </td>
                                            <td className="px-4 py-3">
                                                <StatusBadge
                                                    status={booking.status}
                                                    bookingStatuses={
                                                        booking_statuses
                                                    }
                                                />
                                            </td>
                                            <td className="flex flex-col items-end gap-y-1 px-4 py-3">
                                                {booking.status ===
                                                    'completed' && (
                                                    <div className="flex flex-col items-center gap-y-1">
                                                        <Button
                                                            size="sm"
                                                            disabled={
                                                                !booking.client
                                                                    .has_active_payment_method
                                                            }
                                                            onClick={() =>
                                                                openPaymentSheet(
                                                                    booking,
                                                                )
                                                            }
                                                        >
                                                            Payment Approval
                                                        </Button>
                                                        {!booking.client
                                                            .has_active_payment_method && (
                                                            <span className="text-[10px] font-medium text-destructive">
                                                                No active
                                                                payment method
                                                            </span>
                                                        )}
                                                    </div>
                                                )}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                {bookings.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {bookings.current_page} of {bookings.last_page}
                        </p>
                        <div className="flex gap-1">
                            {bookings.links.map((link, index) => {
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

            {/* Payment Approval Sheet */}
            <Sheet open={showPaymentSheet} onOpenChange={setShowPaymentSheet}>
                <SheetContent side="right" className="sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>Payment Approval</SheetTitle>
                        <SheetDescription>
                            Review and process payment for Booking #
                            {selectedBooking?.id}
                        </SheetDescription>
                    </SheetHeader>

                    <div className="space-y-4 overflow-y-auto px-4">
                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Checkout At</Label>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selectedBooking?.checkout_at
                                        ? formatDateTime(
                                              selectedBooking.checkout_at,
                                          )
                                        : '-'}
                                </p>
                            </div>
                            <div>
                                <Label>Service Type</Label>
                                <p className="mt-1 text-sm text-muted-foreground capitalize">
                                    {selectedBooking?.service_type?.replace(
                                        /_/g,
                                        ' ',
                                    ) ?? '-'}
                                </p>
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <Label>Children Count</Label>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selectedBooking?.children?.length ?? 0}
                                </p>
                            </div>
                            <div>
                                <Label>Pets Count</Label>
                                <p className="mt-1 text-sm text-muted-foreground">
                                    {selectedBooking?.pets?.length ?? 0}
                                </p>
                            </div>
                        </div>

                        <div>
                            <Label>Total Working Hours</Label>
                            <Input
                                type="text"
                                inputMode="decimal"
                                value={localValues.total_working_hour}
                                onChange={(e) => {
                                    const val = e.target.value.replace(
                                        /[^0-9.]/g,
                                        '',
                                    );
                                    setLocalValues({
                                        ...localValues,
                                        total_working_hour: val,
                                    });
                                    recalculatePayment(val);
                                }}
                                placeholder="0.00"
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Reimbursement
                            </label>
                            <div className="relative mt-1">
                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    inputMode="decimal"
                                    placeholder="0.00"
                                    value={localValues.reimbursement}
                                    onChange={(e) => {
                                        const val = e.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        );
                                        setLocalValues({
                                            ...localValues,
                                            reimbursement: val,
                                        });
                                    }}
                                    onBlur={() =>
                                        paymentForm.setData({
                                            ...paymentForm.data,
                                            reimbursement:
                                                parseFloat(
                                                    localValues.reimbursement,
                                                ) || 0,
                                        })
                                    }
                                    className="pl-8"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Reimbursement Description
                            </label>
                            <Input
                                value={
                                    paymentForm.data.reimbursement_description
                                }
                                onChange={(e) =>
                                    paymentForm.setData({
                                        ...paymentForm.data,
                                        reimbursement_description:
                                            e.target.value,
                                    })
                                }
                                placeholder="Enter description..."
                                className="mt-1"
                            />
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Tip
                            </label>
                            <div className="relative mt-1">
                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="0.00"
                                    value={localValues.tip}
                                    onChange={(e) => {
                                        const val = e.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        );
                                        setLocalValues({
                                            ...localValues,
                                            tip: val,
                                        });
                                    }}
                                    onBlur={() =>
                                        paymentForm.setData({
                                            ...paymentForm.data,
                                            tip:
                                                parseFloat(localValues.tip) ||
                                                0,
                                        })
                                    }
                                    className="pl-8"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Bonus
                            </label>
                            <div className="relative mt-1">
                                <DollarSign className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                                <Input
                                    type="text"
                                    placeholder="0.00"
                                    value={localValues.bonus}
                                    onChange={(e) => {
                                        const val = e.target.value.replace(
                                            /[^0-9.]/g,
                                            '',
                                        );
                                        setLocalValues({
                                            ...localValues,
                                            bonus: val,
                                        });
                                    }}
                                    onBlur={() =>
                                        paymentForm.setData({
                                            ...paymentForm.data,
                                            bonus:
                                                parseFloat(localValues.bonus) ||
                                                0,
                                        })
                                    }
                                    className="pl-8"
                                />
                            </div>
                        </div>

                        <div className="rounded-md border border-border bg-muted p-4">
                            <h4 className="mb-3 text-sm font-semibold text-foreground">
                                Payment Summary
                            </h4>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Caregiver Service
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {recalculatedValues.paid_to_caregiver.toFixed(
                                            2,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Sitterwise Fee
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {recalculatedValues.sitterwise_cut.toFixed(
                                            2,
                                        )}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Reimbursement
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            parseFloat(
                                                localValues.reimbursement,
                                            ) || 0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Bonus
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            parseFloat(localValues.bonus) || 0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Tip
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            parseFloat(localValues.tip) || 0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                                <div className="mt-2 flex justify-between border-t border-border pt-2">
                                    <span className="font-medium text-foreground">
                                        Charged to Client
                                    </span>
                                    <span className="font-medium text-foreground">
                                        $
                                        {(
                                            recalculatedValues.charge_to_client +
                                            (parseFloat(
                                                localValues.reimbursement,
                                            ) || 0) +
                                            (parseFloat(localValues.bonus) ||
                                                0) +
                                            (parseFloat(localValues.tip) || 0)
                                        ).toFixed(2)}
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div className="mt-10 flex w-full gap-2 space-y-2 px-4">
                        <Button
                            onClick={() => setShowConfirmDialog(true)}
                            disabled={paymentForm.processing}
                            className="flex-1"
                        >
                            {paymentForm.processing && (
                                <Spinner className="mr-2 h-4 w-4" />
                            )}
                            Process Payment
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setShowPaymentSheet(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

            {/* Confirm Dialog */}
            <Dialog
                open={showConfirmDialog}
                onOpenChange={setShowConfirmDialog}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Payment Processing</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to process payment for Booking
                            #{selectedBooking?.id}? This action will update the
                            reimbursement, tip, and bonus fields.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setShowConfirmDialog(false)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handlePaymentSubmit}
                            disabled={paymentForm.processing}
                        >
                            {paymentForm.processing && (
                                <Spinner className="mr-2 h-4 w-4" />
                            )}
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
