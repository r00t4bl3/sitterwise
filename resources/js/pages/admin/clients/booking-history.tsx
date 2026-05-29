import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
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

interface Caregiver {
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

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    status: string;
    start_datetime: string;
    end_datetime: string;
    location_type: string;
    total_amount: number;
    caregiver: Caregiver | null;
    hotel: Hotel | null;
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
    client: {
        id: number;
        first_name: string;
        last_name: string;
    };
    bookings: PaginatedData<Booking>;
    bookingStatuses: BookingStatus[];
    serviceTypes: ServiceType[];
    locationTypes: LocationType[];
    filters: Filters;
}

export default function BookingHistory() {
    const {
        client,
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

        router.get(`/clients/${client.id}/bookings`, params, {
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

    const breadcrumbs: BreadcrumbItem[] = [
        { title: 'Dashboard', href: '/dashboard' },
        { title: 'Clients', href: '/clients' },
        {
            title: `${client.first_name} ${client.last_name}`,
            href: `/clients/${client.id}`,
        },
        { title: 'Booking History', href: '#' },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${client.first_name} ${client.last_name} - Booking History`}
            />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Booking History
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            <Link
                                href={`/clients/${client.id}`}
                                className="text-primary hover:underline"
                            >
                                {client.first_name} {client.last_name}
                            </Link>
                            {' — '}
                            {bookings.total} booking
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
                            placeholder="Search by caregiver or hotel..."
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
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="bg-foreground">
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
                                    Caregiver
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Location
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Amount
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
                                        colSpan={7}
                                        className="px-4 py-8 text-center text-sm text-muted-foreground"
                                    >
                                        No bookings found
                                    </td>
                                </tr>
                            ) : (
                                bookings.data.map((booking) => (
                                    <tr
                                        key={booking.id}
                                        className="border-b border-border transition hover:bg-blush"
                                    >
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
                                        <td className="px-4 py-3 text-sm whitespace-nowrap font-medium text-ring">
                                            {booking.caregiver
                                                ? (
                                                    <Link href={`/caregivers/${booking.caregiver.id}`} className="hover:underline">
                                                        {booking.caregiver.first_name} {booking.caregiver.last_name}
                                                    </Link>
                                                )
                                                : '—'}
                                        </td>
                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                            {booking.hotel?.name ??
                                                locationTypes.find(
                                                    (l) =>
                                                        l.value ===
                                                        booking.location_type,
                                                )?.label ??
                                                booking.location_type}
                                        </td>
                                        <td className="px-4 py-3 text-right text-sm whitespace-nowrap text-foreground">
                                            {booking.total_amount
                                                ? `$${Number(booking.total_amount).toFixed(2)}`
                                                : '—'}
                                        </td>
                                        <td className="flex justify-end gap-x-2 px-4 py-3 whitespace-nowrap">
                                            <Button asChild className="h-8">
                                                <Link
                                                    href={`/bookings/${booking.id}`}
                                                >
                                                    View
                                                </Link>
                                            </Button>
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
