import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTimeRangeInPT } from '@/lib/datetime';
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
    booking_group: {
        id: number;
        bookings_count: number;
    } | null;
}

interface Props {
    bookingStatuses: Array<{
        value: string;
        label: string;
        colors: { bg: string; text: string; border: string };
    }>;
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
                            Your upcoming and past bookings
                        </p>
                    </div>
                    <Button asChild>
                        <Link href="/bookings/create">Create Booking</Link>
                    </Button>
                </div>

                <div className="border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="w-12 px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    #
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Date
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Caregiver
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Action
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {bookings.data.map((booking, index) => (
                                <tr
                                    key={booking.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {(bookings.current_page - 1) *
                                            bookings.per_page +
                                            index +
                                            1}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {formatDisplayDateTimeRangeInPT(
                                            booking.start_datetime,
                                            booking.end_datetime,
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {booking.caregiver_name || (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <StatusBadge
                                                status={booking.status}
                                                bookingStatuses={
                                                    bookingStatuses
                                                }
                                            />
                                            {booking.booking_group
                                                ?.bookings_count > 1 && (
                                                <Badge
                                                    variant="outline"
                                                    className="text-xs"
                                                >
                                                    Multi-Day (
                                                    {
                                                        booking.booking_group
                                                            .bookings_count
                                                    }
                                                    )
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={`/bookings/${booking.ulid}`}
                                        >
                                            <Button size="sm">
                                                View Details
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                            {bookings.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={5}
                                        className="px-4 py-8 text-center text-muted-foreground"
                                    >
                                        No booking history found.
                                    </td>
                                </tr>
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
        </AppLayout>
    );
}
