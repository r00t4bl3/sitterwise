import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTimeInPT } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';

interface BookingStatus {
    value: string;
    label: string;
    colors: {
        bg: string;
        text: string;
        border: string;
    };
}

interface ServiceType {
    value: string;
    label: string;
}

interface LocationType {
    value: string;
    label: string;
}

interface ClientUser {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
}

interface Hotel {
    id: number;
    name: string;
}

interface AssignmentResolution {
    value: string;
    label: string;
    color: string;
}

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    status: string;
    start_datetime: string;
    end_datetime: string;
    location_type: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    paid_to_caregiver_total: number;
    client: ClientUser | null;
    hotel: Hotel | null;
    assignment_id: number | null;
    assignment_resolution: string | null;
    assignment_resolution_label: string | null;
    assignment_resolution_color: string | null;
    assignment_note: string | null;
    late_arrival: boolean;
}

interface BreadcrumbLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: BreadcrumbLink[];
}

interface Filters {
    search: string | null;
    status: string | null;
}

interface Props {
    [key: string]: unknown;
    caregiver: {
        id: number;
        first_name: string;
        last_name: string;
    };
    bookings: PaginatedData<Booking>;
    bookingStatuses: BookingStatus[];
    serviceTypes: ServiceType[];
    locationTypes: LocationType[];
    assignmentResolutions: AssignmentResolution[];
    filters: Filters;
}

export default function JobHistory() {
    const {
        caregiver,
        bookings,
        bookingStatuses,
        serviceTypes,
        locationTypes,
        filters,
    } = usePage<Props>().props;

    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status ?? null,
    );
    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const applyFilters = (search: string, status: string | null) => {
        const params: Record<string, string> = {};

        if (search.trim()) {
            params.search = search.trim();
        }

        if (status) {
            params.status = status;
        }

        router.get(`/caregivers/${caregiver.id}/jobs`, params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters(value, statusFilter);
        }, 300);
    };

    const handleStatusChange = (status: string | null) => {
        setStatusFilter(status);
        applyFilters(searchQuery, status);
    };

    useEffect(() => {
        return () => clearTimeout(debounceTimer.current);
    }, []);

    const excuseForm = useForm({ note: '' });
    const noShowForm = useForm({ note: '' });
    const lateArrivalForm = useForm({ note: '' });

    const [excuseDialog, setExcuseDialog] = useState<{
        open: boolean;
        booking: Booking | null;
    }>({ open: false, booking: null });
    const [noShowDialog, setNoShowDialog] = useState<{
        open: boolean;
        booking: Booking | null;
    }>({ open: false, booking: null });
    const [lateArrivalDialog, setLateArrivalDialog] = useState<{
        open: boolean;
        booking: Booking | null;
    }>({ open: false, booking: null });

    const handleExcuse = () => {
        if (!excuseDialog.booking?.assignment_id) {
            return;
        }

        excuseForm.post(
            `/assignments/${excuseDialog.booking.assignment_id}/excuse`,
            {
                onSuccess: () => {
                    setExcuseDialog({ open: false, booking: null });
                    excuseForm.reset();
                },
            },
        );
    };

    const handleNoShow = () => {
        if (!noShowDialog.booking?.assignment_id) {
            return;
        }

        noShowForm.post(
            `/assignments/${noShowDialog.booking.assignment_id}/no-show`,
            {
                onSuccess: () => {
                    setNoShowDialog({ open: false, booking: null });
                    noShowForm.reset();
                },
            },
        );
    };

    const handleLateArrival = () => {
        if (!lateArrivalDialog.booking?.assignment_id) {
            return;
        }

        lateArrivalForm.post(
            `/assignments/${lateArrivalDialog.booking.assignment_id}/late-arrival`,
            {
                onSuccess: () => {
                    setLateArrivalDialog({ open: false, booking: null });
                    lateArrivalForm.reset();
                },
            },
        );
    };

    const openExcuseDialog = (booking: Booking) => {
        excuseForm.reset();
        setExcuseDialog({ open: true, booking });
    };

    const openNoShowDialog = (booking: Booking) => {
        noShowForm.reset();
        setNoShowDialog({ open: true, booking });
    };

    const openLateArrivalDialog = (booking: Booking) => {
        lateArrivalForm.reset();
        setLateArrivalDialog({ open: true, booking });
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Caregivers', href: '/caregivers' },
        {
            title: `${caregiver.first_name} ${caregiver.last_name}`,
            href: `/caregivers/${caregiver.id}`,
        },
        { title: 'Job History', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${caregiver.first_name} ${caregiver.last_name} - Job History`}
            />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Job History
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={`/caregivers/${caregiver.id}`}
                                className="text-primary hover:underline"
                            >
                                {caregiver.first_name} {caregiver.last_name}
                            </Link>
                            {' — '}
                            {bookings.total} job
                            {bookings.total !== 1 ? 's' : ''}
                            {statusFilter && (
                                <span className="ml-1">
                                    (
                                    {bookingStatuses.find(
                                        (s) => s.value === statusFilter,
                                    )?.label || statusFilter}
                                    )
                                </span>
                            )}
                            {searchQuery && (
                                <span className="ml-1">
                                    (search: "{searchQuery}")
                                </span>
                            )}
                        </p>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by client or hotel..."
                            value={searchQuery}
                            onChange={(e) => handleSearchChange(e.target.value)}
                            className="h-8"
                        />
                        {searchQuery && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleSearchChange('')}
                                className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                type="button"
                            >
                                ×
                            </Button>
                        )}
                    </div>

                    <Button
                        variant={!statusFilter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleStatusChange(null)}
                    >
                        All
                    </Button>
                    {bookingStatuses.map((s) => (
                        <Button
                            key={s.value}
                            variant={
                                statusFilter === s.value ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                handleStatusChange(
                                    statusFilter === s.value ? null : s.value,
                                )
                            }
                            className={
                                statusFilter === s.value
                                    ? `${s.colors.bg} ${s.colors.text} ${s.colors.border}`
                                    : ''
                            }
                        >
                            {s.label}
                        </Button>
                    ))}
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[1000px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    ID
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Date
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Service
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Client
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Location
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Resolution
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Earnings
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {bookings.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No jobs found
                                    </td>
                                </tr>
                            ) : (
                                bookings.data.map((booking) => (
                                    <tr
                                        key={booking.id}
                                        className="border-b border-border transition hover:bg-blush"
                                    >
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {booking.id}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {formatDisplayDateTimeInPT(
                                                booking.start_datetime,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {serviceTypes.find(
                                                (s) =>
                                                    s.value ===
                                                    booking.service_type,
                                            )?.label ?? booking.service_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            <StatusBadge
                                                status={booking.status}
                                                bookingStatuses={
                                                    bookingStatuses
                                                }
                                            />
                                        </td>
                                        <td className="px-4 py-3 text-sm font-medium whitespace-nowrap text-ring">
                                            {booking.client ? (
                                                <Link
                                                    href={`/clients/${booking.client.id}`}
                                                    className="hover:underline"
                                                >
                                                    {booking.client.first_name}{' '}
                                                    {booking.client.last_name}
                                                </Link>
                                            ) : (
                                                '—'
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {booking.hotel?.name ||
                                                [
                                                    booking.address_line1,
                                                    booking.address_city,
                                                    booking.address_state,
                                                ]
                                                    .filter(Boolean)
                                                    .join(', ') ||
                                                locationTypes.find(
                                                    (l) =>
                                                        l.value ===
                                                        booking.location_type,
                                                )?.label ||
                                                booking.location_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {booking.assignment_resolution ? (
                                                <span
                                                    className="inline-block rounded-[3px] px-2 py-0.5 text-[10px] font-semibold"
                                                    style={{
                                                        backgroundColor: `${booking.assignment_resolution_color ?? '#6b7280'}20`,
                                                        color:
                                                            booking.assignment_resolution_color ??
                                                            '#6b7280',
                                                    }}
                                                >
                                                    {
                                                        booking.assignment_resolution_label
                                                    }
                                                </span>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">
                                                    Pending
                                                </span>
                                            )}
                                            {booking.late_arrival && (
                                                <span className="ml-1 inline-block rounded-[3px] bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                                                    Late
                                                </span>
                                            )}
                                            {booking.assignment_note && (
                                                <p className="mt-0.5 text-[10px] leading-tight text-muted-foreground">
                                                    {booking.assignment_note}
                                                </p>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm whitespace-nowrap text-foreground">
                                            {booking.paid_to_caregiver_total
                                                ? `$${Number(booking.paid_to_caregiver_total).toFixed(2)}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 whitespace-nowrap">
                                            <div className="flex flex-col gap-1">
                                                <Button
                                                    size="sm"
                                                    variant="outline"
                                                    asChild
                                                    className="h-7 text-[11px]"
                                                >
                                                    <Link
                                                        href={`/bookings/${booking.ulid}`}
                                                    >
                                                        View
                                                    </Link>
                                                </Button>
                                                {booking.assignment_id && (
                                                    <>
                                                        {(!booking.assignment_resolution ||
                                                            booking.assignment_resolution ===
                                                                'backed_out') && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-7 text-[11px]"
                                                                onClick={() =>
                                                                    openExcuseDialog(
                                                                        booking,
                                                                    )
                                                                }
                                                            >
                                                                Excuse
                                                            </Button>
                                                        )}
                                                        {(!booking.assignment_resolution ||
                                                            booking.assignment_resolution ===
                                                                'backed_out') && (
                                                            <Button
                                                                size="sm"
                                                                variant="outline"
                                                                className="h-7 text-[11px]"
                                                                onClick={() =>
                                                                    openNoShowDialog(
                                                                        booking,
                                                                    )
                                                                }
                                                            >
                                                                No-Show
                                                            </Button>
                                                        )}
                                                        <Button
                                                            size="sm"
                                                            variant="outline"
                                                            className="h-7 text-[11px]"
                                                            onClick={() =>
                                                                openLateArrivalDialog(
                                                                    booking,
                                                                )
                                                            }
                                                        >
                                                            Late
                                                        </Button>
                                                    </>
                                                )}
                                            </div>
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
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
                                                ? 'bg-table-header text-white'
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

            <Dialog
                open={excuseDialog.open}
                onOpenChange={(open) =>
                    setExcuseDialog({ open, booking: null })
                }
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Excuse Back-Out</DialogTitle>
                        <DialogDescription>
                            Mark this back-out as excused. A note is required
                            explaining why.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="excuse-note">
                            Note <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="excuse-note"
                            value={excuseForm.data.note}
                            onChange={(e) =>
                                excuseForm.setData('note', e.target.value)
                            }
                            placeholder="Explain why this back-out is excused..."
                            className="min-h-[80px]"
                        />
                        {excuseForm.errors.note && (
                            <p className="text-sm text-destructive">
                                {excuseForm.errors.note}
                            </p>
                        )}
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            onClick={() =>
                                setExcuseDialog({ open: false, booking: null })
                            }
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleExcuse}
                            disabled={excuseForm.processing}
                        >
                            {excuseForm.processing
                                ? 'Saving...'
                                : 'Mark as Excused'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={noShowDialog.open}
                onOpenChange={(open) =>
                    setNoShowDialog({ open, booking: null })
                }
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Log No-Show</DialogTitle>
                        <DialogDescription>
                            Log this job as a no-show for the caregiver.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="noshow-note">Note (optional)</Label>
                        <Textarea
                            id="noshow-note"
                            value={noShowForm.data.note}
                            onChange={(e) =>
                                noShowForm.setData('note', e.target.value)
                            }
                            placeholder="Any additional details..."
                            className="min-h-[80px]"
                        />
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            onClick={() =>
                                setNoShowDialog({ open: false, booking: null })
                            }
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleNoShow}
                            disabled={noShowForm.processing}
                            variant="destructive"
                        >
                            {noShowForm.processing
                                ? 'Saving...'
                                : 'Log No-Show'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={lateArrivalDialog.open}
                onOpenChange={(open) =>
                    setLateArrivalDialog({ open, booking: null })
                }
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Log Late Arrival</DialogTitle>
                        <DialogDescription>
                            Log a late arrival for this job.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-2">
                        <Label htmlFor="late-note">Note (optional)</Label>
                        <Textarea
                            id="late-note"
                            value={lateArrivalForm.data.note}
                            onChange={(e) =>
                                lateArrivalForm.setData('note', e.target.value)
                            }
                            placeholder="How late were they?"
                            className="min-h-[80px]"
                        />
                    </div>
                    <DialogFooter className="gap-2 sm:gap-0">
                        <Button
                            variant="outline"
                            onClick={() =>
                                setLateArrivalDialog({
                                    open: false,
                                    booking: null,
                                })
                            }
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleLateArrival}
                            disabled={lateArrivalForm.processing}
                        >
                            {lateArrivalForm.processing
                                ? 'Saving...'
                                : 'Log Late Arrival'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
