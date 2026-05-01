import { Head, Link, usePage, useForm } from '@inertiajs/react';
import { Calendar, Clock, MapPin, Building } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTime, parseAsLocal } from '@/lib/datetime';

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
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
    };
    address_line1: string;
    address_line2: string;
    address_city: string;
    address_state: string;
    address_zip: string;
    hotel: {
        name: string;
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
    const { jobs } = usePage<Props>().props;

    const getStatusBadge = (
        status: string,
        start_datetime?: string,
        end_datetime?: string,
    ) => {
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
                bg: 'bg-purple-100',
                text: 'text-purple-800',
                border: 'border-purple-300',
            },
            paid: {
                bg: 'bg-indigo-100',
                text: 'text-indigo-800',
                border: 'border-indigo-300',
            },
            cancelled: {
                bg: 'bg-red-100',
                text: 'text-red-800',
                border: 'border-red-300',
            },
        };

        const config = colors[status.toLowerCase()] || colors.received;

        // Special handling for confirmed jobs - check if they can be checked out
        if (
            status.toLowerCase() === 'confirmed' &&
            start_datetime &&
            end_datetime
        ) {
            const now = new Date();
            const start = parseAsLocal(start_datetime) as Date;
            // const end = parseAsLocal(end_datetime) as Date;

            // Can checkout if it's currently during or after the job time
            if (now >= start) {
                return (
                    <span
                        className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${config.bg} ${config.text} ${config.border}`}
                    >
                        Active / Ready for Checkout
                    </span>
                );
            }
        }

        return (
            <span
                className={`inline-flex items-center rounded-full border px-2.5 py-0.5 text-xs font-semibold ${config.bg} ${config.text} ${config.border}`}
            >
                {status.charAt(0).toUpperCase() + status.slice(1)}
            </span>
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

    const openCheckoutSheet = (job: Booking) => {
        setSelectedJob(job);

        const start = parseAsLocal(job.start_datetime) as Date;
        const end = parseAsLocal(job.end_datetime) as Date;
        const hours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);

        checkoutForm.setData({
            start_datetime: formatDateTimeLocal(start),
            end_datetime: formatDateTimeLocal(end),
            total_working_hour:
                job.total_working_hour?.toString() || hours.toFixed(2),
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
                                        Client
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Service
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Date & Time
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Location
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Status
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider uppercase">
                                        Actions
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {jobs.data.map((job) => (
                                    <tr
                                        key={job.id}
                                        className="transition-colors hover:bg-muted/50"
                                    >
                                        <td className="px-4 py-3">
                                            <div className="font-medium text-foreground">
                                                {job.client?.user.name}
                                            </div>
                                            <div className="text-xs text-muted-foreground">
                                                ID: #{job.id}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="text-sm text-foreground capitalize">
                                                {job.service_type.replace(
                                                    '_',
                                                    ' ',
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <div className="text-sm text-foreground">
                                                    {formatDisplayDateTime(
                                                        job.start_datetime,
                                                    )}
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <Clock className="h-3 w-3" />
                                                Ends:{' '}
                                                {formatDisplayDateTime(
                                                    job.end_datetime,
                                                )}
                                            </div>
                                        </td>
                                        <td className="px-4 py-3">
                                            <div className="flex items-start gap-2">
                                                {job.location_type ===
                                                'hotel' ? (
                                                    <Building className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                                ) : (
                                                    <MapPin className="mt-0.5 h-4 w-4 text-muted-foreground" />
                                                )}
                                                <div className="text-sm text-foreground">
                                                    {job.location_type ===
                                                    'hotel' ? (
                                                        <div>
                                                            {job.hotel?.name}
                                                        </div>
                                                    ) : (
                                                        <div>
                                                            {job.address_line1}
                                                        </div>
                                                    )}
                                                    <div className="text-xs text-muted-foreground">
                                                        {job.address_city},{' '}
                                                        {job.address_state}
                                                    </div>
                                                </div>
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
                                            </div>
                                        </td>
                                    </tr>
                                ))}

                                {jobs.data.length === 0 && (
                                    <tr>
                                        <td
                                            colSpan={6}
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
                    <SheetHeader>
                        <SheetTitle>Job Checkout</SheetTitle>
                        <SheetDescription>
                            Complete the job by confirming hours and adding any
                            reimbursements.
                        </SheetDescription>
                    </SheetHeader>

                    {selectedJob && (
                        <form
                            onSubmit={handleCheckout}
                            className="mt-6 space-y-4 px-4"
                        >
                            <div className="rounded-lg bg-muted p-4">
                                <p className="text-sm font-medium">
                                    {selectedJob.client?.user.name}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    {formatDisplayDateTime(
                                        selectedJob.start_datetime,
                                    )}{' '}
                                    -{' '}
                                    {formatDisplayDateTime(
                                        selectedJob.end_datetime,
                                    )}
                                </p>
                            </div>

                            <div className="space-y-4">
                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">
                                        Total Hours
                                    </label>
                                    <input
                                        type="number"
                                        step="0.25"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        value={
                                            checkoutForm.data.total_working_hour
                                        }
                                        onChange={(e) =>
                                            checkoutForm.setData(
                                                'total_working_hour',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">
                                        Reimbursement Amount ($)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
                                        value={checkoutForm.data.reimbursement}
                                        onChange={(e) =>
                                            checkoutForm.setData(
                                                'reimbursement',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <label className="text-sm font-medium">
                                        Reimbursement Description
                                    </label>
                                    <textarea
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
                                    <label className="text-sm font-medium">
                                        Bonus ($)
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        placeholder="0.00"
                                        className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background"
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
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
