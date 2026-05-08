import { Head, Link, usePage } from '@inertiajs/react';
import { Calendar, User, ChevronLeft, ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTime } from '@/lib/datetime';
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
];

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    caregiver_name: string | null;
    start_datetime: string;
    end_datetime: string;
    status: string;
}

interface Props {
    bookingStatuses: Array<{value: string, label: string, colors: { bg:string, text: string, border: string}}>;
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
}

export default function ClientBookingsIndex() {
    const { bookings, bookingStatuses } = usePage().props as unknown as Props;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Bookings" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Bookings
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Your upcoming and past bookings asdf
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/bookings/create">Create Booking</Link>
                    </Button>
                </div>

                {bookings.data.length === 0 ? (
                    <div className="rounded-lg border border-border bg-card p-12 text-center">
                        <Calendar className="mx-auto mb-4 h-12 w-12 text-muted-foreground" />
                        <h3 className="text-lg font-medium text-foreground">
                            No bookings found
                        </h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            You haven&apos;t booked any services yet.
                        </p>
                    </div>
                ) : (
                    <div>
                        <div className="space-y-4">
                            {bookings.data.map((booking) => (
                                <div
                                    key={booking.id}
                                    className="rounded-lg border border-border bg-card p-6"
                                >
                                    <div className="flex items-start justify-between">
                                        <div className="space-y-3">
                                            <div className="flex items-center gap-2">
                                                <User className="h-4 w-4 text-muted-foreground" />
                                                <span className="font-medium text-foreground">
                                                    {booking.caregiver_name ||
                                                        'Caregiver not assigned'}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <Calendar className="h-4 w-4 text-muted-foreground" />
                                                <span className="text-sm text-muted-foreground">
                                                    {formatDisplayDateTime(
                                                        booking.start_datetime,
                                                    )}{' '}
                                                    -{' '}
                                                    {formatDisplayDateTime(
                                                        booking.end_datetime,
                                                    )}
                                                </span>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                <StatusBadge
                                                    status={
                                                        booking.status
                                                    }
                                                    bookingStatuses={
                                                        bookingStatuses
                                                    }
                                                />
                                            </div>
                                        </div>

                                        <div className="flex items-center gap-4">
                                            <Link
                                                href={`/bookings/${booking.ulid}`}
                                            >
                                                <Button size="sm">
                                                    View Details
                                                </Button>
                                            </Link>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>

                        {bookings.last_page > 1 && (
                            <div className="flex items-center justify-between">
                                <p className="text-sm text-muted-foreground">
                                    Page {bookings.current_page} of{' '}
                                    {bookings.last_page}
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
                                                } ${
                                                    !link.url
                                                        ? 'pointer-events-none opacity-50'
                                                        : ''
                                                }`}
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
                )}
            </div>
        </AppLayout>
    );
}
