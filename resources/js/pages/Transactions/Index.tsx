import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Calendar, Clock, User, Receipt } from 'lucide-react';
import { useState } from 'react';
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
    user: {
        email: string;
    };
}

interface Booking {
    id: number;
    start_datetime: string;
    end_datetime: string;
    total_price: number;
    status: string;
    client: Client;
    caregiver?: Caregiver;
}

interface Props {
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
                            className="h-10 rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring w-full max-w-md"
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
                        <>
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
                                                            {booking.caregiver.first_name}{' '}
                                                            {booking.caregiver.last_name}
                                                        </div>
                                                        <div className="text-xs text-muted-foreground">
                                                            {booking.caregiver.user?.email}
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
                                                    {formatDateTime(booking.end_datetime)}
                                                </div>
                                            </td>
                                            <td className="px-4 py-3 text-sm text-foreground">
                                                ${booking.total_price?.toFixed(2) ?? '0.00'}
                                            </td>
                                            <td className="px-4 py-3">
                                                {getStatusBadge(booking.status)}
                                            </td>
                                            <td className="flex justify-end gap-x-2 px-4 py-3">
                                                <Link
                                                    href={`/bookings/${booking.id}`}
                                                    className="btn-primary h-8"
                                                >
                                                    View
                                                </Link>
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </>
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
        </AppLayout>
    );
}
