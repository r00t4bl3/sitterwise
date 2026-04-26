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
}

export default function TransactionsIndex() {
    const { bookings, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [selectedBooking, setSelectedBooking] = useState<Booking | null>(
        null,
    );
    const [showPaymentSheet, setShowPaymentSheet] = useState(false);
    const [showConfirmDialog, setShowConfirmDialog] = useState(false);

    const [localValues, setLocalValues] = useState({
        reimbursement: '',
        reimbursement_description: '',
        tip: '',
        bonus: '',
    });

    const paymentForm = useForm({
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

    const getStatusBadge = (status: string) => {
        const colors: Record<
            string,
            { bg: string; text: string; border: string }
        > = {
            received: {
                bg: 'bg-blue-100',
                text: 'text-blue-800',
                border: 'border-blue-300',
            },
            reserved: {
                bg: 'bg-yellow-100',
                text: 'text-yellow-800',
                border: 'border-yellow-300',
            },
            confirmed: {
                bg: 'bg-green-100',
                text: 'text-green-800',
                border: 'border-green-300',
            },
            completed: {
                bg: 'bg-gray-100',
                text: 'text-gray-800',
                border: 'border-gray-300',
            },
            cancelled: {
                bg: 'bg-red-100',
                text: 'text-red-800',
                border: 'border-red-300',
            },
        };

        const style = colors[status] || {
            bg: 'bg-gray-100',
            text: 'text-gray-800',
            border: 'border-gray-300',
        };

        return (
            <span
                className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-medium ${style.bg} ${style.text} ${style.border}`}
            >
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
        );
    };

    const openPaymentSheet = (booking: Booking) => {
        setSelectedBooking(booking);
        setLocalValues({
            reimbursement: String(booking.reimbursement ?? ''),
            reimbursement_description: booking.reimbursement_description ?? '',
            tip: String(booking.tip ?? ''),
            bonus: String(booking.bonus ?? ''),
        });
        paymentForm.setData({
            reimbursement: booking.reimbursement ?? 0,
            reimbursement_description: booking.reimbursement_description ?? '',
            tip: booking.tip ?? 0,
            bonus: booking.bonus ?? 0,
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
                                            <td className="px-4 py-3">
                                                <div className="text-sm font-medium text-foreground">
                                                    {booking.client.first_name}{' '}
                                                    {booking.client.last_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {booking.client.user?.email}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {booking.caregiver ? (
                                                    <>
                                                        <div className="text-sm font-medium text-foreground">
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
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {
                                                                booking
                                                                    .caregiver
                                                                    .user?.email
                                                            }
                                                        </div>
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
                                                {getStatusBadge(booking.status)}
                                            </td>
                                            <td className="flex justify-end gap-x-2 px-4 py-3">
                                                {booking.status ===
                                                    'completed' && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            openPaymentSheet(
                                                                booking,
                                                            )
                                                        }
                                                    >
                                                        Payment Approval
                                                    </Button>
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
                                <label className="text-sm font-medium text-foreground">
                                    Checkout At
                                </label>
                                <Input
                                    value={
                                        selectedBooking?.checkout_at
                                            ? formatDateTime(
                                                  selectedBooking.checkout_at,
                                              )
                                            : '-'
                                    }
                                    readOnly
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Total Working Hours
                                </label>
                                <Input
                                    value={
                                        selectedBooking?.total_working_hour?.toFixed(
                                            2,
                                        ) ?? '0.00'
                                    }
                                    readOnly
                                    className="mt-1"
                                />
                            </div>
                        </div>

                        <div className="grid grid-cols-2 gap-4">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Children Count
                                </label>
                                <Input
                                    value={
                                        selectedBooking?.children?.length ?? 0
                                    }
                                    readOnly
                                    className="mt-1"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Pets Count
                                </label>
                                <Input
                                    value={selectedBooking?.pets?.length ?? 0}
                                    readOnly
                                    className="mt-1"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Service Type
                            </label>
                            <Input
                                value={selectedBooking?.service_type ?? '-'}
                                readOnly
                                className="mt-1"
                            />
                        </div>

                        <div className="rounded-md border border-border bg-muted p-4">
                            <h4 className="mb-3 text-sm font-semibold text-foreground">
                                Payment Summary
                            </h4>
                            <div className="space-y-2 text-sm">
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Charge to Client
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            selectedBooking?.charge_to_client ??
                                            0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Paid to Caregiver
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            selectedBooking?.paid_to_caregiver ??
                                            0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                                <div className="flex justify-between">
                                    <span className="text-muted-foreground">
                                        Sitterwise Cut
                                    </span>
                                    <span className="font-medium">
                                        $
                                        {(
                                            selectedBooking?.sitterwise_cut ?? 0
                                        ).toFixed(2)}
                                    </span>
                                </div>
                            </div>
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
