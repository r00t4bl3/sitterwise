import { Head, Link, router, usePage, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    MapPin,
    Building,
    Star,
    TriangleAlert,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/datetime-picker';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipProvider,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import { formatDateTimeLocal } from '@/lib/datetime';
import {
    formatDisplayDateInPT,
    formatDisplayDateTimeInPT,
    formatDisplayDateTimeRangeInPT,
    formatDisplayTimeInPT,
    autoSetEndDateTime,
} from '@/lib/datetime';
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

interface Booking {
    id: number;
    ulid: string;
    assignment_id: number | null;
    assignment_resolution: string | null;
    service_type: string;
    service_type_label: string;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    total_working_hour: number | string;
    paid_to_caregiver: number;
    reimbursement: number;
    tip: number;
    bonus: number;
    paid_to_caregiver_total: number;
    client_name: string;
    children?: Array<{
        name: string;
        gender: string | null;
        birth_year: number | null;
        birth_month: number | null;
    }> | null;
    client: {
        id: number;
    };
    address_line1: string;
    address_line2: string;
    address_city: string;
    address_state: string;
    address_zip: string;
    hotel: {
        name: string;
    } | null;
    client_rating?: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    caregiver_rating?: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
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

interface ServiceType {
    value: string;
    label: string;
}

interface LocationType {
    value: string;
    label: string;
}

interface Props {
    [key: string]: unknown;
    jobs: PaginatedData<Booking>;
    booking_statuses: BookingStatus[];
    service_types: ServiceType[];
    location_types: LocationType[];
    filters: Filters;
}

function parsePT(value: string): Date {
    const date = new Date(value);
    const formatter = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/Los_Angeles',
        year: 'numeric',
        month: 'numeric',
        day: 'numeric',
        hour: 'numeric',
        minute: 'numeric',
        second: 'numeric',
        hour12: false,
    });
    const parts = formatter.formatToParts(date);
    const get = (type: string) =>
        parseInt(parts.find((p) => p.type === type)?.value ?? '0', 10);

    return new Date(
        get('year'),
        get('month') - 1,
        get('day'),
        get('hour'),
        get('minute'),
        get('second'),
    );
}

export default function CaregiverJobsIndex() {
    const { jobs, booking_statuses, service_types, location_types, filters } =
        usePage<Props>().props;

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

        router.get('/jobs', params, {
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

    const getStatusBadge = (
        status: string,
        start_datetime?: string,
        end_datetime?: string,
    ) => {
        const statusKey = status.toLowerCase();

        if (statusKey === 'confirmed' && start_datetime && end_datetime) {
            const now = new Date();
            const start = new Date(start_datetime);
            const end = new Date(end_datetime);

            if (now >= end) {
                return (
                    <div className="inline-flex w-24 items-center justify-center rounded-[3px] border border-green-300 bg-green-100 px-2 py-0.5 text-center text-[10px] font-semibold text-green-800">
                        Ready for Checkout
                    </div>
                );
            } else if (now >= start) {
                return (
                    <div className="inline-flex w-24 items-center justify-center rounded-[3px] border border-green-300 bg-green-100 px-2 py-0.5 text-center text-[10px] font-semibold text-green-800">
                        Active
                    </div>
                );
            }
        }

        return (
            <StatusBadge status={status} bookingStatuses={booking_statuses} />
        );
    };

    const [isCheckoutSheetOpen, setIsCheckoutSheetOpen] = useState(false);
    const [selectedJob, setSelectedJob] = useState<Booking | null>(null);
    const [isCancelDialogOpen, setIsCancelDialogOpen] = useState(false);
    const [cancellingJob, setCancellingJob] = useState<Booking | null>(null);

    const cancelForm = useForm({
        reason: '',
    });

    const openCancelDialog = (job: Booking) => {
        setCancellingJob(job);
        cancelForm.reset();
        setIsCancelDialogOpen(true);
    };

    const handleBackOut = () => {
        if (!cancellingJob?.assignment_id) {
            return;
        }

        cancelForm.post(
            `/assignments/${cancellingJob.assignment_id}/back-out`,
            {
                onSuccess: () => {
                    setIsCancelDialogOpen(false);
                    setCancellingJob(null);
                },
            },
        );
    };

    const checkoutForm = useForm({
        start_datetime: '',
        end_datetime: '',
        total_working_hour: '',
        reimbursement: '',
        reimbursement_description: '',
        bonus: '',
    });

    const calculateTotalHours = (start: string, end: string): number => {
        if (!start || !end) {
            return 0;
        }

        const startDate = new Date(start);
        const endDate = new Date(end);
        const diffMs = endDate.getTime() - startDate.getTime();
        const diffHours = diffMs / (1000 * 60 * 60);

        return Math.max(diffHours, 4);
    };

    const openCheckoutSheet = (job: Booking) => {
        setSelectedJob(job);

        const start = parsePT(job.start_datetime);
        const end = parsePT(job.end_datetime);
        const hours = calculateTotalHours(job.start_datetime, job.end_datetime);

        checkoutForm.setData({
            start_datetime: formatDateTimeLocal(start),
            end_datetime: formatDateTimeLocal(end),
            total_working_hour: hours.toFixed(2),
            reimbursement: '',
            reimbursement_description: '',
            bonus: '',
        });
        setIsCheckoutSheetOpen(true);
    };

    const handleCheckout = (e: React.FormEvent) => {
        e.preventDefault();

        if (!selectedJob) {
            return;
        }

        checkoutForm.post(`/jobs/${selectedJob.ulid}/checkout`, {
            onSuccess: () => {
                setIsCheckoutSheetOpen(false);
            },
        });
    };

    const handleStartDateTimeChange = (value: string) => {
        checkoutForm.setData('start_datetime', value);

        if (value) {
            checkoutForm.setData('end_datetime', autoSetEndDateTime(value));
        }

        const hours = calculateTotalHours(
            value,
            checkoutForm.data.end_datetime,
        );
        checkoutForm.setData('total_working_hour', hours.toFixed(2));
    };

    const handleEndDateTimeChange = (value: string) => {
        checkoutForm.setData('end_datetime', value);
        const hours = calculateTotalHours(
            checkoutForm.data.start_datetime,
            value,
        );
        checkoutForm.setData('total_working_hour', hours.toFixed(2));
    };

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'My Jobs', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Jobs" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            My Jobs
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {jobs.total} job
                            {jobs.total !== 1 ? 's' : ''}
                            {statusFilter && (
                                <span className="ml-1">
                                    (
                                    {booking_statuses.find(
                                        (s) => s.value === statusFilter,
                                    )?.label || statusFilter}
                                    )
                                </span>
                            )}
                            {searchQuery && (
                                <span className="ml-1">
                                    (search: &quot;{searchQuery}&quot;)
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
                    {booking_statuses.map((s) => (
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
                    <table className="w-full min-w-[900px]">
                        <thead>
                            <tr className="bg-table-header">
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
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Earnings
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Review from Caregiver
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Feedback from Client
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {jobs.data.length === 0 ? (
                                <tr>
                                    <td
                                        colSpan={8}
                                        className="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No jobs found
                                    </td>
                                </tr>
                            ) : (
                                jobs.data.map((job) => (
                                    <tr
                                        key={job.id}
                                        className="border-b border-border transition hover:bg-blush"
                                    >
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {formatDisplayDateTimeInPT(
                                                job.start_datetime,
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {service_types.find(
                                                (s) =>
                                                    s.value ===
                                                    job.service_type,
                                            )?.label ?? job.service_type}
                                        </td>
                                        <td className="px-4 py-3">
                                            {getStatusBadge(
                                                job.status,
                                                job.start_datetime,
                                                job.end_datetime,
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="text-sm text-foreground">
                                                {job.client_name || '—'}
                                            </div>
                                            <div className="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                                                {job.location_type ===
                                                'hotel' ? (
                                                    <Building className="mt-0.5 h-3 w-3 shrink-0" />
                                                ) : (
                                                    <MapPin className="mt-0.5 h-3 w-3 shrink-0" />
                                                )}
                                                <span>
                                                    {job.hotel?.name ??
                                                        location_types.find(
                                                            (l) =>
                                                                l.value ===
                                                                job.location_type,
                                                        )?.label ??
                                                        job.location_type}
                                                </span>
                                            </div>
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm whitespace-nowrap text-foreground">
                                            {job.paid_to_caregiver_total
                                                ? `$${Number(job.paid_to_caregiver_total).toFixed(2)}`
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3">
                                            {job.client_rating ? (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div className="flex cursor-default items-center gap-1">
                                                                {[
                                                                    1, 2, 3, 4,
                                                                    5,
                                                                ].map(
                                                                    (star) => (
                                                                        <Star
                                                                            key={
                                                                                star
                                                                            }
                                                                            className={`h-4 w-4 ${
                                                                                star <=
                                                                                job
                                                                                    .client_rating!
                                                                                    .rating
                                                                                    ? 'fill-yellow-400 text-yellow-400'
                                                                                    : 'text-gray-300'
                                                                            }`}
                                                                        />
                                                                    ),
                                                                )}
                                                                <span className="ml-1 text-xs text-muted-foreground">
                                                                    (
                                                                    {
                                                                        job
                                                                            .client_rating!
                                                                            .rating
                                                                    }
                                                                    /5)
                                                                </span>
                                                            </div>
                                                        </TooltipTrigger>
                                                        {job.client_rating
                                                            .comment && (
                                                            <TooltipContent>
                                                                &quot;
                                                                {
                                                                    job
                                                                        .client_rating
                                                                        .comment
                                                                }
                                                                &quot;
                                                            </TooltipContent>
                                                        )}
                                                    </Tooltip>
                                                </TooltipProvider>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">
                                                    Not rated
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3">
                                            {job.caregiver_rating ? (
                                                <TooltipProvider>
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <div className="flex cursor-default items-center gap-1">
                                                                {[
                                                                    1, 2, 3, 4,
                                                                    5,
                                                                ].map(
                                                                    (star) => (
                                                                        <Star
                                                                            key={
                                                                                star
                                                                            }
                                                                            className={`h-4 w-4 ${
                                                                                star <=
                                                                                job
                                                                                    .caregiver_rating!
                                                                                    .rating
                                                                                    ? 'fill-yellow-400 text-yellow-400'
                                                                                    : 'text-gray-300'
                                                                            }`}
                                                                        />
                                                                    ),
                                                                )}
                                                                <span className="ml-1 text-xs text-muted-foreground">
                                                                    (
                                                                    {
                                                                        job
                                                                            .caregiver_rating!
                                                                            .rating
                                                                    }
                                                                    /5)
                                                                </span>
                                                            </div>
                                                        </TooltipTrigger>
                                                        {job.caregiver_rating
                                                            .comment && (
                                                            <TooltipContent>
                                                                &quot;
                                                                {
                                                                    job
                                                                        .caregiver_rating
                                                                        .comment
                                                                }
                                                                &quot;
                                                            </TooltipContent>
                                                        )}
                                                    </Tooltip>
                                                </TooltipProvider>
                                            ) : (
                                                <span className="text-xs text-muted-foreground italic">
                                                    Not rated
                                                </span>
                                            )}
                                        </td>
                                        <td className="flex justify-end gap-x-2 px-4 py-3 whitespace-nowrap">
                                            <Button asChild className="h-8">
                                                <Link
                                                    href={`/jobs/${job.ulid}`}
                                                >
                                                    View
                                                </Link>
                                            </Button>

                                            {job.status.toLowerCase() ===
                                                'confirmed' &&
                                                !job.assignment_resolution && (
                                                    <Button
                                                        size="sm"
                                                        variant="default"
                                                        onClick={() =>
                                                            openCancelDialog(
                                                                job,
                                                            )
                                                        }
                                                    >
                                                        Cancel Job
                                                    </Button>
                                                )}

                                            {job.status.toLowerCase() ===
                                                'confirmed' &&
                                                new Date(job.end_datetime) <
                                                    new Date() && (
                                                    <Button
                                                        size="sm"
                                                        onClick={() =>
                                                            openCheckoutSheet(
                                                                job,
                                                            )
                                                        }
                                                    >
                                                        Checkout
                                                    </Button>
                                                )}
                                        </td>
                                    </tr>
                                ))
                            )}
                        </tbody>
                    </table>
                </div>

                {jobs.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {jobs.current_page} of {jobs.last_page}
                        </p>
                        <div className="flex gap-1">
                            {jobs.links.map((link, index) => {
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

            <Sheet
                open={isCheckoutSheetOpen}
                onOpenChange={setIsCheckoutSheetOpen}
            >
                <SheetContent className="sm:max-w-md">
                    <SheetHeader className="shrink-0">
                        <SheetTitle>Job Checkout</SheetTitle>
                        <SheetDescription>
                            Complete the job by confirming hours and adding any
                            reimbursements.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 overflow-y-auto px-4 pb-6">
                        {selectedJob && (
                            <form
                                onSubmit={handleCheckout}
                                className="space-y-4"
                            >
                                <div className="rounded-lg bg-muted p-4">
                                    <p className="text-sm font-medium">
                                        {selectedJob.client_name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {formatDisplayDateTimeRangeInPT(
                                            selectedJob.start_datetime,
                                            selectedJob.end_datetime,
                                        )}
                                    </p>
                                    {selectedJob.children &&
                                    selectedJob.children.length > 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            {selectedJob.children.map(
                                                (child, index) => (
                                                    <span key={index}>
                                                        {child.name}
                                                        {child.birth_month &&
                                                        child.birth_year
                                                            ? ` (${calculateAge(
                                                                  child.birth_year,
                                                                  child.birth_month,
                                                              )})`
                                                            : ''}
                                                        {index <
                                                        selectedJob.children!
                                                            .length -
                                                            1
                                                            ? ', '
                                                            : ''}
                                                    </span>
                                                ),
                                            )}
                                        </p>
                                    ) : (
                                        <p className="text-xs text-muted-foreground">
                                            (No children)
                                        </p>
                                    )}
                                </div>

                                <div className="space-y-4">
                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            Start Date & Time
                                        </Label>
                                        <DateTimePicker
                                            value={
                                                checkoutForm.data.start_datetime
                                            }
                                            onChange={handleStartDateTimeChange}
                                            placeholder="Select start date and time"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            End Date & Time
                                        </Label>
                                        <DateTimePicker
                                            value={
                                                checkoutForm.data.end_datetime
                                            }
                                            startTime={
                                                checkoutForm.data.start_datetime
                                            }
                                            onChange={handleEndDateTimeChange}
                                            placeholder="Select end date and time"
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            Total Hours
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.25"
                                            value={
                                                checkoutForm.data
                                                    .total_working_hour
                                            }
                                            readOnly
                                        />
                                        <p className="text-xs text-muted-foreground">
                                            Minimum 4 hours. Calculated from
                                            start and end time.
                                        </p>
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            Reimbursement Amount ($)
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            placeholder="0.00"
                                            value={
                                                checkoutForm.data.reimbursement
                                            }
                                            onChange={(e) =>
                                                checkoutForm.setData(
                                                    'reimbursement',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            Reimbursement Description
                                        </Label>
                                        <Textarea
                                            className="flex min-h-[80px] w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                            placeholder="e.g., Parking, tolls, etc."
                                            value={
                                                checkoutForm.data
                                                    .reimbursement_description
                                            }
                                            onChange={(e) =>
                                                checkoutForm.setData(
                                                    'reimbursement_description',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>

                                    <div className="grid gap-2">
                                        <Label className="text-sm font-medium">
                                            Bonus ($)
                                        </Label>
                                        <Input
                                            type="number"
                                            step="0.01"
                                            placeholder="0.00"
                                            value={checkoutForm.data.bonus}
                                            onChange={(e) =>
                                                checkoutForm.setData(
                                                    'bonus',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div className="flex gap-3 pt-4">
                                    <Button
                                        variant="outline"
                                        className="flex-1"
                                        type="button"
                                        onClick={() =>
                                            setIsCheckoutSheetOpen(false)
                                        }
                                    >
                                        Cancel
                                    </Button>
                                    <Button
                                        className="flex-1"
                                        type="submit"
                                        disabled={checkoutForm.processing}
                                    >
                                        {checkoutForm.processing && (
                                            <Spinner className="mr-2 h-4 w-4" />
                                        )}
                                        {checkoutForm.processing
                                            ? 'Processing...'
                                            : 'Confirm Checkout'}
                                    </Button>
                                </div>
                            </form>
                        )}
                    </div>
                </SheetContent>
            </Sheet>

            <Dialog
                open={isCancelDialogOpen}
                onOpenChange={setIsCancelDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <div className="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100">
                            <TriangleAlert className="h-6 w-6 text-red-600" />
                        </div>
                        <DialogTitle className="text-center">
                            You're about to cancel this job
                        </DialogTitle>
                        <DialogDescription className="text-center">
                            {cancellingJob && (
                                <div className="mt-3 rounded-lg border border-border bg-muted p-3 text-left text-sm text-foreground">
                                    <strong className="block">
                                        {cancellingJob.client_name ?? 'Client'}
                                        {cancellingJob.children &&
                                            cancellingJob.children
                                                .length > 0 && (
                                                <>
                                                    {' '}
                                                    &middot;{' '}
                                                    {
                                                        cancellingJob.children
                                                            .length
                                                    }{' '}
                                                    child
                                                    {cancellingJob.children
                                                        .length !== 1
                                                        ? 'ren'
                                                        : ''}
                                                </>
                                            )}
                                    </strong>
                                    {formatDisplayDateInPT(
                                        cancellingJob.start_datetime,
                                    )}
                                    {'\u00B7'}{' '}
                                    {formatDisplayTimeInPT(
                                        cancellingJob.start_datetime,
                                    )}
                                    {'\u2013'}{' '}
                                    {formatDisplayTimeInPT(
                                        cancellingJob.end_datetime,
                                    )}
                                    <br />
                                    {cancellingJob.hotel?.name ??
                                        [
                                            cancellingJob.address_line1,
                                            cancellingJob.address_city,
                                            cancellingJob.address_state,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') ??
                                        cancellingJob.location_type}
                                </div>
                            )}
                        </DialogDescription>
                    </DialogHeader>

                    <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                        <strong>Heads up:</strong> This will count as a back-out
                        on your reliability record. If something has come up
                        that we can help with, text us at 619-663-4379 before
                        canceling.
                    </div>

                    <div className="space-y-2">
                        <Label htmlFor="reason">
                            Reason for cancellation{' '}
                            <span className="text-destructive">*</span>
                        </Label>
                        <Textarea
                            id="reason"
                            value={cancelForm.data.reason}
                            onChange={(e) =>
                                cancelForm.setData('reason', e.target.value)
                            }
                            placeholder="Briefly tell us what's going on. The team will see this."
                            className="min-h-[80px]"
                        />
                        {cancelForm.errors.reason && (
                            <p className="text-sm text-destructive">
                                {cancelForm.errors.reason}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">
                            Required. This helps us understand whether there's
                            something we should know about.
                        </p>
                    </div>

                    <DialogFooter className="gap-3">
                        <Button
                            variant="outline"
                            className="flex-1"
                            onClick={() => setIsCancelDialogOpen(false)}
                        >
                            Never mind, go back
                        </Button>
                        <Button
                            variant="default"
                            className="flex-1"
                            onClick={handleBackOut}
                            disabled={cancelForm.processing}
                        >
                            {cancelForm.processing
                                ? 'Cancelling...'
                                : 'Cancel this job'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
