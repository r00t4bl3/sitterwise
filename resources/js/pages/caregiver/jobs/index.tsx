import { Head, Link, usePage, useForm } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Calendar,
    Clock,
    MapPin,
    Building,
} from 'lucide-react';
import { useState } from 'react';
import { RatingInput } from '@/components/RatingInput';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetDescription,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Jobs',
        href: '/jobs',
    },
];

interface Job {
    id: number;
    client_first_name: string;
    client_last_name: string;
    client_email: string;
    client_phone: string;
    start_datetime: string;
    end_datetime: string;
    total_working_hour: number;
    status: string;
    location_type: string;
    service_type: string;
    service_type_label: string;
    address_line1: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    reimbursement: number | null;
    reimbursement_description: string | null;
    bonus: number | null;
    hotel: {
        id: number;
        name: string;
        line1: string | null;
        city: string | null;
        state: string | null;
    } | null;
    address: {
        id: number;
        line1: string;
        city: string | null;
        state: string | null;
        zip: string | null;
    } | null;
    client: {
        id: number;
        first_name: string;
        last_name: string;
        user: {
        email: string;
        phone: string | null;
        } | null;
    };
    // This comes from the accessor - camelCase in JSON
    client_rating: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
}
interface Props {
    jobs: {
        data: Job[];
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

export default function JobsIndex() {
    const { jobs } = usePage().props as unknown as Props;
    const [selectedJob, setSelectedJob] = useState<Job | null>(null);
    const [showCheckout, setShowCheckout] = useState(false);
    const [hasCheckedOut, setHasCheckedOut] = useState(false);

    const checkoutForm = useForm<{
        start_datetime: string;
        end_datetime: string;
        total_working_hour: string;
        reimbursement: string;
        reimbursement_description: string;
        bonus: string;
    }>({
        start_datetime: '',
        end_datetime: '',
        total_working_hour: '',
        reimbursement: '',
        reimbursement_description: '',
        bonus: '',
    });

    const ratingForm = useForm({
        rating: 0,
        comment: '',
        type: 'caregiver_to_client',
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

    const getLocationAddress = (job: Job) => {
        if (job.location_type === 'hotel' && job.hotel) {
            return `${job.hotel.name}${job.hotel.city ? `, ${job.hotel.city}` : ''}`;
        }

        if (job.location_type === 'private_home') {
            if (job.address) {
                return `${job.address.line1}${job.address.city ? `, ${job.address.city}` : ''}`;
            }

            if (job.address_line1) {
                return `${job.address_line1}${job.address_city ? `, ${job.address_city}` : ''}`;
            }
        }

        return 'Address not specified';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div>
                    <h1 className="text-2xl font-semibold text-foreground">
                        My Jobs
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Manage your confirmed and upcoming jobs
                    </p>
                </div>

                {jobs.data.length === 0 ? (
                    <div className="rounded-lg border border-border bg-card p-12 text-center">
                        <Calendar className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <h3 className="text-lg font-medium text-foreground">
                            No confirmed jobs
                        </h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            You don't have any confirmed jobs scheduled yet.
                        </p>
                    </div>
                ) : (
                    <>
                        <div className="border border-border bg-card">
                            <table className="w-full">
                                <thead>
                                    <tr className="bg-foreground">
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            ID
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Client
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Date & Time
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Hours
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Location
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Service
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
                                    {jobs.data.map((job) => (
                                        <tr
                                            key={job.id}
                                            className="border-b border-border transition hover:bg-blush"
                                        >
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {job.id}
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="text-sm font-medium text-foreground">
                                                    {job.client.first_name}{' '}
                                                    {job.client.last_name}
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {job.client.user?.email}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    <div className="text-sm text-foreground">
                                                        {formatDateTime(
                                                            job.start_datetime,
                                                        )}
                                                    </div>
                                                </div>
                                                <div className="flex items-center gap-2 text-xs text-muted-foreground">
                                                    <Clock className="h-3 w-3" />
                                                    Ends:{' '}
                                                    {formatDateTime(
                                                        job.end_datetime,
                                                    )}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {job.total_working_hour} hrs
                                            </td>
                                            <td className="px-4 py-3">
                                                <div className="flex items-center gap-2">
                                                    {job.location_type ===
                                                    'hotel' ? (
                                                        <Building className="h-4 w-4 text-muted-foreground" />
                                                    ) : (
                                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                                    )}
                                                    <span className="text-sm text-foreground">
                                                        {getLocationAddress(
                                                            job,
                                                        )}
                                                    </span>
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                {job.service_type_label}
                                            </td>
                                            <td className="px-4 py-3">
                                                {getStatusBadge(job.status)}
                                            </td>
                                            <td className="flex justify-end gap-x-2 px-4 py-3">
                                                {job.status === 'confirmed' && (
                                                    <Button
                                                        onClick={() => {
                                                            setSelectedJob(job);
                                                            const start =
                                                                new Date(
                                                                    job.start_datetime,
                                                                );
                                                            const end =
                                                                new Date(
                                                                    job.end_datetime,
                                                                );
                                                            const hours =
                                                                (end.getTime() -
                                                                    start.getTime()) /
                                                                (1000 * 60 * 60);
                                                            checkoutForm.setData(
                                                                {
                                                                    start_datetime:
                                                                        job.start_datetime.slice(
                                                                            0,
                                                                            16,
                                                                        ),
                                                                    end_datetime:
                                                                        job.end_datetime.slice(
                                                                            0,
                                                                            16,
                                                                        ),
                                                                    total_working_hour:
                                                                        job.total_working_hour?.toString() ||
                                                                        hours.toFixed(
                                                                            2,
                                                                        ),
                                                                    reimbursement:
                                                                        job.reimbursement?.toString() ||
                                                                        '',
                                                                    reimbursement_description:
                                                                        job.reimbursement_description ||
                                                                        '',
                                                                    bonus:
                                                                        job.bonus?.toString() ||
                                                                        '',
                                                                },
                                                            );
                                                            setHasCheckedOut(
                                                                false,
                                                            );
                                                            ratingForm.reset();
                                                            setShowCheckout(
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        Checkout
                                                    </Button>
                                                )}
                                                {job.status === 'completed' &&
                                                    !job.client_rating && (
                                                        <Button
                                                            onClick={() => {
                                                                setSelectedJob(
                                                                    job,
                                                                );
                                                                setHasCheckedOut(
                                                                    true,
                                                                ); // Skip directly to rating
                                                                ratingForm.reset();
                                                                setShowCheckout(
                                                                    true,
                                                                );
                                                            }}
                                                        >
                                                            Rate
                                                        </Button>
                                                    )}
                                            </td>
                                        </tr>
                                    ))}
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
                    </>
                )}
            </div>

            <Sheet open={showCheckout} onOpenChange={setShowCheckout}>
                <SheetContent>
                    <SheetHeader>
                        <SheetTitle>
                            {hasCheckedOut ? 'Rate the Client' : 'Checkout Job'}
                        </SheetTitle>
                        <SheetDescription>
                            {hasCheckedOut
                                ? `How was your experience with ${selectedJob?.client.first_name}?`
                                : 'Update the job details below.'}
                        </SheetDescription>
                    </SheetHeader>

                    {!hasCheckedOut ? (
                        <form
                            onSubmit={(e) => {
                                e.preventDefault();

                                if (selectedJob) {
                                    checkoutForm.post(
                                        `/jobs/${selectedJob.id}/checkout`,
                                        {
                                            onSuccess: () => {
                                                setHasCheckedOut(true);
                                                checkoutForm.reset();
                                            },
                                        },
                                    );
                                }
                            }}
                            className="px-4 space-y-4"
                        >
                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    Start Date & Time
                                </label>
                                <DateTimePicker
                                    value={checkoutForm.data.start_datetime}
                                    onChange={(datetime) =>
                                        checkoutForm.setData(
                                            'start_datetime',
                                            datetime,
                                        )
                                    }
                                    error={checkoutForm.errors.start_datetime}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    End Date & Time
                                </label>
                                <DateTimePicker
                                    value={checkoutForm.data.end_datetime}
                                    onChange={(datetime) => {
                                        checkoutForm.setData(
                                            'end_datetime',
                                            datetime,
                                        );

                                        if (checkoutForm.data.start_datetime) {
                                            const start = new Date(
                                                checkoutForm.data
                                                    .start_datetime,
                                            );
                                            const end = new Date(datetime);
                                            const hours =
                                                (end.getTime() -
                                                    start.getTime()) /
                                                (1000 * 60 * 60);
                                            checkoutForm.setData(
                                                'total_working_hour',
                                                hours.toFixed(2),
                                            );
                                        }
                                    }}
                                    error={checkoutForm.errors.end_datetime}
                                />
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    Total Hours
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={checkoutForm.data.total_working_hour}
                                    onChange={(e) =>
                                        checkoutForm.setData(
                                            'total_working_hour',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {checkoutForm.errors.total_working_hour && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {checkoutForm.errors.total_working_hour}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    Reimbursement
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={checkoutForm.data.reimbursement}
                                    onChange={(e) =>
                                        checkoutForm.setData(
                                            'reimbursement',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {checkoutForm.errors.reimbursement && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {checkoutForm.errors.reimbursement}
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    Reimbursement Description
                                </label>
                                <input
                                    type="text"
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
                                    placeholder="Enter description"
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {checkoutForm.errors
                                    .reimbursement_description && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {
                                            checkoutForm.errors
                                                .reimbursement_description
                                        }
                                    </p>
                                )}
                            </div>

                            <div>
                                <label className="block text-sm font-medium text-foreground">
                                    Bonus
                                </label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={checkoutForm.data.bonus}
                                    onChange={(e) =>
                                        checkoutForm.setData(
                                            'bonus',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="0.00"
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                {checkoutForm.errors.bonus && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {checkoutForm.errors.bonus}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowCheckout(false);
                                        setSelectedJob(null);
                                        checkoutForm.reset();
                                    }}
                                    className="flex-1 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                                >
                                    Cancel
                                </button>
                                <button
                                    type="submit"
                                    disabled={checkoutForm.processing}
                                    className="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {checkoutForm.processing
                                        ? 'Saving...'
                                        : 'Save Changes'}
                                </button>
                            </div>
                        </form>
                    ) : (

                        <form
                            onSubmit={(e) => {
                                e.preventDefault();

                                if (selectedJob) {
                                    ratingForm.post(
                                        `/jobs/${selectedJob.id}/rate`,
                                        {
                                            onSuccess: () => {
                                                setShowCheckout(false);
                                                setSelectedJob(null);
                                                ratingForm.reset();
                                            },
                                        },
                                    );
                                }
                            }}
                            className="px-4 space-y-4"
                        >
                            <RatingInput
                                value={ratingForm.data.rating}
                                onChange={(val) =>
                                    ratingForm.setData('rating', val)
                                }
                                label="Rating"
                                error={ratingForm.errors.rating}
                            />

                            <div className="space-y-2">
                                <label className="block text-sm font-medium text-foreground">
                                    Comment (Optional)
                                </label>
                                <textarea
                                    value={ratingForm.data.comment}
                                    onChange={(e) =>
                                        ratingForm.setData(
                                            'comment',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Share your experience..."
                                    className="mt-1 block w-full rounded-md border border-input bg-background px-3 py-2 text-sm text-foreground ring-offset-background focus:ring-2 focus:ring-ring focus:ring-offset-2 focus:outline-none disabled:cursor-not-allowed disabled:opacity-50"
                                    rows={4}
                                />
                                {ratingForm.errors.comment && (
                                    <p className="mt-1 text-sm text-red-500">
                                        {ratingForm.errors.comment}
                                    </p>
                                )}
                            </div>

                            <div className="flex gap-2 pt-4">
                                <button
                                    type="button"
                                    onClick={() => {
                                        setShowCheckout(false);
                                        setSelectedJob(null);
                                        ratingForm.reset();
                                    }}
                                    className="flex-1 rounded-md border border-border bg-background px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                                >
                                    Skip
                                </button>
                                <button
                                    type="submit"
                                    disabled={ratingForm.processing}
                                    className="flex-1 rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90 disabled:opacity-50"
                                >
                                    {ratingForm.processing
                                        ? 'Submitting...'
                                        : 'Submit Rating'}
                                </button>
                            </div>
                        </form>
                    )}
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
