import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { MapPin, Building, Star } from 'lucide-react';
import { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/datetime-picker';
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
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import {
    formatDisplayDate,
    formatDisplayTime,
    parseAsLocal,
    autoSetEndDateTime,
} from '@/lib/datetime';

interface Booking {
    id: number;
    ulid: string;
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
    client: {
        id: number;
        user: {
            name: string;
        };
        children?: Array<{
            id: number;
            name: string;
            birth_year?: number;
            birth_month?: number;
        }>;
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

interface Props {
    [key: string]: unknown;
    jobs: {
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
    booking_statuses: Array<{
        value: string;
        label: string;
        colors: { bg: string; text: string; border: string };
    }>;
}

function formatDateTimeLocal(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

export default function CaregiverJobsIndex() {
    const { jobs, booking_statuses } = usePage<Props>().props;

    const getStatusBadge = (
        status: string,
        start_datetime?: string,
        end_datetime?: string,
    ) => {
        const statusKey = status.toLowerCase();

        // Special handling for confirmed jobs - check if they can be checked out
        if (statusKey === 'confirmed' && start_datetime && end_datetime) {
            const now = new Date();
            const start = parseAsLocal(start_datetime) as Date;
            const end = parseAsLocal(end_datetime) as Date;

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

        return Math.max(diffHours, 4); // Minimum 4 hours
    };

    const openCheckoutSheet = (job: Booking) => {
        setSelectedJob(job);

        const start = parseAsLocal(job.start_datetime) as Date;
        const end = parseAsLocal(job.end_datetime) as Date;
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

        // Auto-set end time to 4 hours after start
        if (value) {
            checkoutForm.setData('end_datetime', autoSetEndDateTime(value));
        }

        // Recalculate total hours
        const hours = calculateTotalHours(
            value,
            checkoutForm.data.end_datetime,
        );
        checkoutForm.setData('total_working_hour', hours.toFixed(2));
    };

    const handleEndDateTimeChange = (value: string) => {
        checkoutForm.setData('end_datetime', value);
        // Recalculate total hours
        const hours = calculateTotalHours(
            checkoutForm.data.start_datetime,
            value,
        );
        checkoutForm.setData('total_working_hour', hours.toFixed(2));
    };

    return (
        <AppLayout breadcrumbs={[{ title: 'My Jobs', href: '/jobs' }]}>
            <Head title="My Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            My Jobs
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            View and manage your assigned jobs
                        </p>
                    </div>
                </div>

                <div className="border border-border bg-card">
                    <div className="overflow-x-auto">
                        <table className="w-full">
                            <thead>
                                <tr className="bg-foreground text-white">
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Date & Time
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Client
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Service
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Review from Caregiver
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Feedback from Client
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {jobs.data.map((job) => {
                                    const startDate = parseAsLocal(
                                        job.start_datetime,
                                    ) as Date;
                                    const endDate = parseAsLocal(
                                        job.end_datetime,
                                    ) as Date;
                                    const isSameDay =
                                        startDate.getFullYear() ===
                                            endDate.getFullYear() &&
                                        startDate.getMonth() ===
                                            endDate.getMonth() &&
                                        startDate.getDate() ===
                                            endDate.getDate();

                                    return (
                                        <tr
                                            key={job.id}
                                            className="transition-colors hover:bg-muted/50"
                                        >
                                            <td className="px-4 py-3">
                                                <div className="text-sm text-foreground">
                                                    {isSameDay ? (
                                                        <>
                                                            {formatDisplayDate(
                                                                job.start_datetime,
                                                            )}{' '}
                                                            from{' '}
                                                            {formatDisplayTime(
                                                                job.start_datetime,
                                                            )}{' '}
                                                            to{' '}
                                                            {formatDisplayTime(
                                                                job.end_datetime,
                                                            )}
                                                        </>
                                                    ) : (
                                                        <>
                                                            {formatDisplayDate(
                                                                job.start_datetime,
                                                            )}{' '}
                                                            from{' '}
                                                            {formatDisplayTime(
                                                                job.start_datetime,
                                                            )}{' '}
                                                            to{' '}
                                                            {formatDisplayDate(
                                                                job.end_datetime,
                                                            )}{' '}
                                                            at{' '}
                                                            {formatDisplayTime(
                                                                job.end_datetime,
                                                            )}
                                                        </>
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="font-medium text-foreground">
                                                    {job.client?.user.name}
                                                </div>
                                                <div className="mt-0.5 flex items-start gap-1 text-xs text-muted-foreground">
                                                    {job.location_type ===
                                                    'hotel' ? (
                                                        <Building className="mt-0.5 h-3 w-3 shrink-0" />
                                                    ) : (
                                                        <MapPin className="mt-0.5 h-3 w-3 shrink-0" />
                                                    )}
                                                    <span>
                                                        {job.location_type ===
                                                        'hotel'
                                                            ? `${job.hotel?.name}, ${job.address_city}, ${job.address_state}`
                                                            : `${job.address_line1}, ${job.address_city}, ${job.address_state}`}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-sm text-foreground">
                                                    {job.service_type_label}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                {getStatusBadge(
                                                    job.status,
                                                    job.start_datetime,
                                                    job.end_datetime,
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {job.client_rating ? (
                                                    <div className="flex flex-col gap-1">
                                                        <div className="flex items-center gap-1">
                                                            {[1, 2, 3, 4, 5].map(
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
                                                        {job.client_rating
                                                            .comment && (
                                                            <p className="text-xs text-muted-foreground italic">
                                                                "
                                                                {
                                                                    job
                                                                        .client_rating
                                                                        .comment
                                                                }
                                                                "
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">
                                                        Not rated
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                {job.caregiver_rating ? (
                                                    <div className="flex flex-col gap-1">
                                                        <div className="flex items-center gap-1">
                                                            {[1, 2, 3, 4, 5].map(
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
                                                        {job.caregiver_rating
                                                            .comment && (
                                                            <p className="text-xs text-muted-foreground italic">
                                                                "
                                                                {
                                                                    job
                                                                        .caregiver_rating
                                                                        .comment
                                                                }
                                                                "
                                                            </p>
                                                        )}
                                                    </div>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground italic">
                                                        Not rated
                                                    </span>
                                                )}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex flex-wrap gap-2">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        asChild
                                                    >
                                                        <Link
                                                            href={`/jobs/${job.ulid}`}
                                                        >
                                                            Details
                                                        </Link>
                                                    </Button>

                                                    {job.status.toLowerCase() ===
                                                        'confirmed' &&
                                                        new Date(
                                                            job.end_datetime,
                                                        ) < new Date() && (
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
                                                </div>
                                            </td>
                                        </tr>
                                    );
                                })}

                                {jobs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={7}
                                            className="px-4 py-12 text-center text-muted-foreground"
                                        >
                                            No jobs found.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                </div>

                {jobs.last_page > 1 && (
                    <div className="mt-4 flex items-center justify-between">
                        <div className="text-sm text-muted-foreground">
                            Showing page {jobs.current_page} of {jobs.last_page}
                        </div>
                        <div className="flex gap-2">
                            {jobs.links.map((link, i) => (
                                <Button
                                    key={i}
                                    variant={
                                        link.active ? 'default' : 'outline'
                                    }
                                    size="sm"
                                    asChild
                                    disabled={!link.url}
                                >
                                    <Link href={link.url || '#'}>
                                        <span
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                </Button>
                            ))}
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
                                        {selectedJob.client?.user.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {(() => {
                                            const start = new Date(
                                                selectedJob.start_datetime,
                                            );
                                            const end = new Date(
                                                selectedJob.end_datetime,
                                            );
                                            const isSameDay =
                                                start.getFullYear() ===
                                                    end.getFullYear() &&
                                                start.getMonth() ===
                                                    end.getMonth() &&
                                                start.getDate() ===
                                                    end.getDate();

                                            const dateOptions: Intl.DateTimeFormatOptions =
                                                {
                                                    month: 'short',
                                                    day: 'numeric',
                                                    year: 'numeric',
                                                };
                                            const timeOptions: Intl.DateTimeFormatOptions =
                                                {
                                                    hour: 'numeric',
                                                    minute: '2-digit',
                                                    hour12: true,
                                                };

                                            if (isSameDay) {
                                                return `${start.toLocaleDateString('en-US', dateOptions)} ${start.toLocaleTimeString('en-US', timeOptions)} - ${end.toLocaleTimeString('en-US', timeOptions)}`;
                                            }

                                            return `${start.toLocaleDateString('en-US', dateOptions)} ${start.toLocaleTimeString('en-US', timeOptions)} - ${end.toLocaleDateString('en-US', dateOptions)} ${end.toLocaleTimeString('en-US', timeOptions)}`;
                                        })()}
                                    </p>
                                    {selectedJob.client.children &&
                                    selectedJob.client.children.length > 0 ? (
                                        <p className="text-xs text-muted-foreground">
                                            {selectedJob.client.children.map(
                                                (child, index) => (
                                                    <span key={child.id}>
                                                        {child.name}
                                                        {child.birth_month &&
                                                        child.birth_year
                                                            ? ` (${calculateAge(
                                                                  child.birth_year,
                                                                  child.birth_month,
                                                              )})`
                                                            : ''}
                                                        {index <
                                                        selectedJob.client
                                                            .children!.length -
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
        </AppLayout>
    );
}
