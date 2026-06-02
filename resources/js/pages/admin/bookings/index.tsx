import { Head, Link, router, usePage } from '@inertiajs/react';
import { format } from 'date-fns';
import {
    ChevronLeft,
    ChevronRight,
    ChevronUp,
    Baby,
    Dog,
    Calendar as CalendarIcon,
    User,
    Building,
    Users,
    CreditCard,
    Grid3X3,
    List,
    Download,
} from 'lucide-react';
import { useState, useMemo, useEffect, useRef } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayTimeInPT } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';
import { BookingSheet } from './booking-sheet';
import { ExportSheet } from './export-sheet';
import type { Booking as FullBooking, Props } from './types';
import { useBookingSheet } from './use-booking-sheet';

const getPTDate = (str: string): Date | null => {
    const d = new Date(str.replace(/\.\d+Z$/, 'Z'));

    if (isNaN(d.getTime())) {
return null;
}

    const parts = new Intl.DateTimeFormat('en-US', {
        timeZone: 'America/Los_Angeles',
        year: 'numeric', month: '2-digit', day: '2-digit',
        hour: '2-digit', minute: '2-digit', hour12: false,
    }).formatToParts(d);
    const g = (t: string) => parseInt(parts.find(x => x.type === t)!.value, 10);

    return new Date(g('year'), g('month') - 1, g('day'), g('hour'), g('minute'));
};

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '#',
    },
];

const serviceTypeIcons: Record<string, React.ElementType> = {
    babysitter: Baby,
    petsitter: Dog,
    companion_care: User,
    group_childcare_invoiced: Users,
    corporate_invoiced: Building,
};

function getDaysInMonth(
    year: number,
    month: number,
): Array<{ day: number; monthOffset: number }> {
    const firstWeekday = new Date(year, month - 1, 1).getDay(); // 0 = Sun
    const daysInCurrent = new Date(year, month, 0).getDate();
    const daysInPrev = new Date(year, month - 1, 0).getDate();

    const leading = firstWeekday;
    const trailing = (7 - ((firstWeekday + daysInCurrent) % 7)) % 7;

    const cells: Array<{ day: number; monthOffset: number }> = [];

    // previous month days (offset -1)
    for (let i = leading - 1; i >= 0; i--) {
        cells.push({ day: daysInPrev - i, monthOffset: -1 });
    }

    // current month days (offset 0)
    for (let d = 1; d <= daysInCurrent; d++) {
        cells.push({ day: d, monthOffset: 0 });
    }

    // next month days (offset +1)
    for (let d = 1; d <= trailing; d++) {
        cells.push({ day: d, monthOffset: 1 });
    }

    return cells;
}

export default function Bookings() {
    const {
        bookings,
        filters,
        clients,
        hotels,
        caregivers,
        service_types,
        location_types,
        booking_statuses,
        payment_statuses,
        booking_attributes,
        sitter_preferences,
        pet_types,
        client_types,
        discovery_sources,
    } = usePage<Props>().props;

    const sheet = useBookingSheet({
        clients: clients as unknown as Array<{
            id: number;
            name: string;
            [key: string]: unknown;
        }>,
        hotels,
        caregivers: caregivers as unknown as Array<{
            id: number;
            name: string;
            [key: string]: unknown;
        }>,
        service_types,
        location_types,
        booking_statuses,
        payment_statuses,
        booking_attributes,
        sitter_preferences: sitter_preferences as unknown as Array<{
            value: string;
            label: string;
        }>,
        pet_types: pet_types as unknown as Array<{
            value: string;
            label: string;
        }>,
        client_types: client_types as unknown as Array<{
            value: string;
            label: string;
        }>,
        discovery_sources,
    });

    const [currentMonth] = useState(filters.month);
    const [currentYear] = useState(filters.year);
    const [selectedDay, setSelectedDay] = useState<{
        date: string;
        bookings: FullBooking[];
    } | null>(null);
    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status,
    );
    const [searchQuery, setSearchQuery] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');

    useEffect(() => {
        const timer = setTimeout(() => {
            setDebouncedSearch(searchQuery);
        }, 300);

        return () => clearTimeout(timer);
    }, [searchQuery]);

    const [exportSheetOpen, setExportSheetOpen] = useState(false);
    const [showScrollTop, setShowScrollTop] = useState(false);

    const [isTableView, setIsTableView] = useState(() => {
        if (typeof window !== 'undefined') {
            return localStorage.getItem('bookings_view_mode') === 'table';
        }

        return false;
    });

    useEffect(() => {
        localStorage.setItem(
            'bookings_view_mode',
            isTableView ? 'table' : 'calendar',
        );
    }, [isTableView]);

    const tableBodyRef = useRef<HTMLTableSectionElement>(null);

    useEffect(() => {
        const handleScroll = () => {
            setShowScrollTop(window.scrollY > 300);
        };

        window.addEventListener('scroll', handleScroll, { passive: true });

        return () => window.removeEventListener('scroll', handleScroll);
    }, []);

    useEffect(() => {
        if (!isTableView) {
            return;
        }

        requestAnimationFrame(() => {
            if (!tableBodyRef.current) {
                return;
            }

            const todayStr = format(new Date(), 'yyyy-MM-dd');

            const todayRow = tableBodyRef.current.querySelector(
                `tr[data-date="${todayStr}"]`,
            ) as HTMLElement | null;

            const targetRow =
                todayRow ??
                (tableBodyRef.current.querySelector(
                    'tr[data-date]',
                ) as HTMLElement | null);

            targetRow?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        });
    }, [isTableView]);

    const days = getDaysInMonth(currentYear, currentMonth);
    const monthNames = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    const prevMonth = () => {
        let newMonth = currentMonth - 1;
        let newYear = currentYear;

        if (newMonth < 1) {
            newMonth = 12;
            newYear--;
        }

        router.get('/bookings', {
            month: newMonth,
            year: newYear,
        });
    };

    const nextMonth = () => {
        let newMonth = currentMonth + 1;
        let newYear = currentYear;

        if (newMonth > 12) {
            newMonth = 1;
            newYear++;
        }

        router.get('/bookings', {
            month: newMonth,
            year: newYear,
        });
    };

    const filteredBookings = useMemo(() => {
        let result = bookings;

        if (statusFilter) {
            result = result.filter(
                (booking) =>
                    booking.status.toLowerCase() === statusFilter.toLowerCase(),
            );
        }

        if (debouncedSearch.trim()) {
            const query = debouncedSearch.toLowerCase().trim();
            result = result.filter((booking) => {
                const clientName =
                    booking.booking_group?.client?.user?.name?.toLowerCase() || '';
                const caregiverName =
                    booking.caregiver?.user?.name?.toLowerCase() || '';
                const hotelName = booking.booking_group?.hotel?.name?.toLowerCase() || '';

                return (
                    clientName.includes(query) ||
                    caregiverName.includes(query) ||
                    hotelName.includes(query)
                );
            });
        }

        return result;
    }, [bookings, statusFilter, debouncedSearch]);

    const bookingsByDate = useMemo(() => {
        const grouped: Record<string, FullBooking[]> = {};
        filteredBookings.forEach((booking) => {
            const date = getPTDate(booking.start_datetime);

            if (!date) {
                return;
            }

            const key = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

            if (!grouped[key]) {
                grouped[key] = [];
            }

            grouped[key].push(booking);
        });

        return grouped;
    }, [filteredBookings]);

    const currentMonthBookings = useMemo(() => {
        return filteredBookings.filter((booking) => {
            const d = getPTDate(booking.start_datetime);

            if (!d) {
                return false;
            }

            return (
                d.getMonth() + 1 === currentMonth &&
                d.getFullYear() === currentYear
            );
        });
    }, [filteredBookings, currentMonth, currentYear]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Bookings" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Bookings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {currentMonthBookings.length} bookings this month
                            {statusFilter && (
                                <span className="ml-1">
                                    (
                                    {booking_statuses.find(
                                        (s) => s.value === statusFilter,
                                    )?.label || statusFilter}
                                    )
                                </span>
                            )}
                            {debouncedSearch && (
                                <span className="ml-1">
                                    (search: "{debouncedSearch}")
                                </span>
                            )}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <ButtonGroup>
                            <Button
                                variant={!isTableView ? 'default' : 'outline'}
                                onClick={() => setIsTableView(false)}
                                title="Calendar View"
                            >
                                <Grid3X3 className="h-4 w-4" />
                            </Button>
                            <Button
                                variant={isTableView ? 'default' : 'outline'}
                                onClick={() => setIsTableView(true)}
                                title="Table View"
                            >
                                <List className="h-4 w-4" />
                            </Button>
                        </ButtonGroup>
                        <Button
                            variant="outline"
                            onClick={() => setExportSheetOpen(true)}
                        >
                            <Download className="h-4 w-4" />
                            Export
                        </Button>
                        <Button onClick={() => sheet.openCreateSheet()}>
                            Create Booking
                        </Button>
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by client, caregiver, or hotel..."
                            value={searchQuery}
                            onChange={(e) => setSearchQuery(e.target.value)}
                            className="h-8"
                        />
                        {searchQuery && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => setSearchQuery('')}
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
                        onClick={() => setStatusFilter(null)}
                    >
                        All
                    </Button>
                    {booking_statuses.map((status) => (
                        <Button
                            key={status.value}
                            variant={
                                statusFilter === status.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                setStatusFilter(
                                    statusFilter === status.value
                                        ? null
                                        : status.value,
                                )
                            }
                            className={
                                statusFilter === status.value
                                    ? `${status.colors.bg} ${status.colors.text} ${status.colors.border}`
                                    : ''
                            }
                        >
                            {status.label}
                        </Button>
                    ))}
                </div>

                {!isTableView && (
                    <div className="flex flex-wrap gap-3 text-xs">
                        {booking_statuses.map((status) => (
                            <div
                                key={status.value}
                                className="flex items-center gap-1.5"
                            >
                                <span
                                    className={`inline-block h-3 w-3 rounded-[2px] border ${
                                        status.colors?.bg || ''
                                    } ${status.colors?.border || ''}`}
                                />
                                <span className="text-muted-foreground">
                                    {status.label}
                                </span>
                            </div>
                        ))}
                    </div>
                )}

                <div className="border border-border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <button
                            onClick={prevMonth}
                            className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <h2 className="text-lg font-semibold text-foreground">
                            {monthNames[currentMonth - 1]} {currentYear}
                        </h2>
                        <button
                            onClick={nextMonth}
                            className="flex h-8 w-8 cursor-pointer items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

                    {!isTableView ? (
                        <div className="grid grid-cols-7 gap-1">
                            {[
                                'Sun',
                                'Mon',
                                'Tue',
                                'Wed',
                                'Thu',
                                'Fri',
                                'Sat',
                            ].map((day) => (
                                <div
                                    key={day}
                                    className="py-2 text-center text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    {day}
                                </div>
                            ))}

                            {days.map(({ day, monthOffset }) => {
                                // compute actual month/year accounting for monthOffset
                                let cellMonth = currentMonth + monthOffset;
                                let cellYear = currentYear;

                                if (cellMonth < 1) {
                                    cellMonth = 12;
                                    cellYear--;
                                } else if (cellMonth > 12) {
                                    cellMonth = 1;
                                    cellYear++;
                                }

                                const dateStr = `${cellYear}-${String(cellMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                                const dayBookings = (
                                    bookingsByDate[dateStr] || []
                                ).sort(
                                    (a, b) => {
                                        const ae = new Date(a.start_datetime.replace(/\.\d+Z$/, 'Z')).getTime();
                                        const be = new Date(b.start_datetime.replace(/\.\d+Z$/, 'Z')).getTime();

                                        return (ae || 0) - (be || 0);
                                    },
                                );
                                const displayBookings = dayBookings.slice(0, 5);
                                const remainingCount = dayBookings.length - 5;

                                const today = new Date();
                                const todayStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
                                const isToday = dateStr === todayStr;
                                const isTodayOrFuture = dateStr >= todayStr;

                                const isCurrentMonth = monthOffset === 0;

                                return (
                                    <div
                                        key={`${monthOffset}-${day}`}
                                        className={`flex min-h-30 flex-col gap-1 border p-2 ${
                                            isCurrentMonth
                                                ? 'border-border bg-background'
                                                : 'border-dashed border-gray-300 bg-white'
                                        } ${isToday ? 'bg-blush' : ''}`}
                                    >
                                        <span
                                            className={`text-sm ${
                                                isToday
                                                    ? 'font-bold text-foreground'
                                                    : isCurrentMonth
                                                      ? 'font-medium text-foreground'
                                                      : 'text-gray-300'
                                            }`}
                                        >
                                            {day}
                                        </span>
                                        {displayBookings.map((booking) => {
                                            const statusKey =
                                                booking.status?.toLowerCase() ||
                                                'received';
                                            const statusObj =
                                                booking_statuses.find(
                                                    (s) =>
                                                        s.value === statusKey,
                                                ) ||
                                                booking_statuses.find(
                                                    (s) =>
                                                        s.value === 'received',
                                                );
                                            const colors = statusObj?.colors;
                                            const ServiceIcon =
                                                serviceTypeIcons[
                                                    booking.booking_group?.service_type
                                                ] || CalendarIcon;
                                            const canCharge =
                                                (statusKey === 'completed' ||
                                                    statusKey === 'pending') &&
                                                booking.payment_status !==
                                                    'paid';

                                            return (
                                                <div
                                                    key={booking.id}
                                                    className="group relative"
                                                >
                                                    <button
                                                        onClick={() =>
                                                            sheet.openEditSheet(
                                                                booking as unknown as FullBooking,
                                                            )
                                                        }
                                                        className={`flex w-full cursor-pointer items-start gap-2 rounded-[3px] border px-1.5 py-1 text-xs transition-colors hover:brightness-95 ${
                                                            colors?.bg ||
                                                            'bg-blue-100'
                                                        } ${
                                                            colors?.text ||
                                                            'text-blue-800'
                                                        } ${
                                                            colors?.border ||
                                                            'border-blue-300'
                                                        }`}
                                                    >
                                                        <ServiceIcon className="mt-0.5 h-4 w-4 flex-shrink-0 opacity-90" />
                                                        <div className="flex min-w-0 flex-col items-start text-left">
                                                            <span className="text-[10px] leading-tight opacity-80">
                                                                {formatDisplayTimeInPT(
                                                                    booking.start_datetime,
                                                                )}
                                                                -
                                                                {formatDisplayTimeInPT(
                                                                    booking.end_datetime,
                                                                )}
                                                            </span>
                                                            <span className="w-full truncate leading-tight font-semibold whitespace-nowrap">
622|                                                                {booking.booking_group?.client?.user?.name ||
                                                                    `${booking.booking_group?.client?.first_name || ''} ${booking.booking_group?.client?.last_name || ''}`.trim() ||
                                                                    'Unknown Client'}
                                                            </span>
                                                        </div>
                                                    </button>
                                                    {/* eslint-disable-next-line no-constant-binary-expression */}
                                                    {false && canCharge && (
                                                        <button
                                                            onClick={(e) => {
                                                                e.stopPropagation();
                                                                window.location.href =
                                                                    '/admin/bookings/charge?booking_id=' +
                                                                    booking.id;
                                                            }}
                                                            className="absolute -top-1 -right-1 hidden h-4 w-4 items-center justify-center rounded-full bg-green-600 text-white group-hover:flex hover:bg-green-700"
                                                            title="Charge"
                                                        >
                                                            <CreditCard className="h-2.5 w-2.5" />
                                                        </button>
                                                    )}
                                                </div>
                                            );
                                        })}
                                        {remainingCount > 0 && (
                                            <button
                                                onClick={() =>
                                                    setSelectedDay({
                                                        date: dateStr,
                                                        bookings: dayBookings,
                                                    })
                                                }
                                                className="text-xs font-medium text-ring hover:text-foreground"
                                            >
                                                + {remainingCount} more
                                            </button>
                                        )}
                                        {isTodayOrFuture &&
                                            remainingCount <= 0 && (
                                                <button
                                                    onClick={() =>
                                                        sheet.openCreateSheet(
                                                            dateStr,
                                                        )
                                                    }
                                                    className="mt-auto text-xs text-ring hover:text-foreground"
                                                >
                                                    + Add
                                                </button>
                                            )}
                                    </div>
                                );
                            })}
                        </div>
                    ) : (
                        <div className="-mx-4 -mb-4 overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="bg-table-header">
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Date
                                        </th>
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Client Name
                                        </th>
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Time
                                        </th>
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Location
                                        </th>
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Caregiver Name
                                        </th>
                                        <th className="px-4 py-3 text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Status
                                        </th>
                                        <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody
                                    className="bg-background"
                                    ref={tableBodyRef}
                                >
                                    {currentMonthBookings.length === 0 ? (
                                        <tr>
                                            <td
                                                colSpan={7}
                                                className="px-4 py-8 text-center text-sm text-muted-foreground italic"
                                            >
                                                No bookings found for this
                                                month.
                                            </td>
                                        </tr>
                                    ) : (
                                        [...currentMonthBookings]
                                            .sort(
                                                (a, b) => {
                                                    const ae = new Date(a.start_datetime.replace(/\.\d+Z$/, 'Z')).getTime();
                                                    const be = new Date(b.start_datetime.replace(/\.\d+Z$/, 'Z')).getTime();

                                                    return (ae || 0) - (be || 0);
                                                })
                                            .map((booking) => {
                                                const statusKey =
                                                    booking.status?.toLowerCase() ||
                                                    'received';
                                                const isHotel =
                                                    booking.booking_group?.location_type ===
                                                    'hotel';
                                                const hotel = isHotel
                                                    ? hotels.find(
                                                          (h) =>
                                                              h.id ===
                                                              booking.booking_group?.hotel_id,
                                                      )
                                                    : null;
                                                const location = isHotel
                                                    ? hotel?.name
                                                    : booking.booking_group?.address_line1;
                                                const addressQuery =
                                                    isHotel && hotel
                                                        ? `${hotel.line1 || ''} ${hotel.line2 || ''} ${hotel.city || ''} ${hotel.state || ''} ${hotel.zip || ''}`.trim()
                                                        : `${booking.booking_group?.address_line1 || ''} ${booking.booking_group?.address_line2 || ''} ${booking.booking_group?.address_city || ''} ${booking.booking_group?.address_state || ''} ${booking.booking_group?.address_zip || ''}`.trim();
                                                const mapsUrl = addressQuery
                                                    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`
                                                    : null;
                                                const parsedDate = getPTDate(
                                                    booking.start_datetime,
                                                );
                                                const rowIso = parsedDate
                                                    ? format(
                                                          parsedDate,
                                                          'yyyy-MM-dd',
                                                      )
                                                    : '';
                                                const rowDate = parsedDate
                                                    ? format(
                                                          parsedDate,
                                                          'MMM d, yyyy',
                                                      )
                                                    : '';

                                                return (
                                                    <tr
                                                        key={booking.id}
                                                        data-date={rowIso}
                                                        onClick={() =>
                                                            router.visit(
                                                                `/bookings/${booking.ulid}`,
                                                            )
                                                        }
                                                        className="cursor-pointer border-b border-border transition hover:bg-blush"
                                                    >
                                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                                            {rowDate}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm font-medium text-ring">
                                                            <Link
                                                                href={`/clients/${booking.booking_group?.client?.id}`}
                                                                onClick={(e) =>
                                                                    e.stopPropagation()
                                                                }
                                                                className="hover:underline"
                                                            >
                                                                {
                                                                    booking.booking_group?.client?.first_name
                                                                }{' '}
                                                                {
                                                                    booking.booking_group?.client?.last_name
                                                                }
                                                            </Link>
                                                        </td>
                                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                                            {formatDisplayTimeInPT(
                                                                booking.start_datetime,
                                                            )}{' '}
                                                            -{' '}
                                                            {formatDisplayTimeInPT(
                                                                booking.end_datetime,
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm text-foreground">
                                                            {mapsUrl ? (
                                                                <a
                                                                    href={
                                                                        mapsUrl
                                                                    }
                                                                    target="_blank"
                                                                    rel="noopener noreferrer"
                                                                    onClick={(e) =>
                                                                        e.stopPropagation()
                                                                    }
                                                                    className="text-ring hover:underline"
                                                                    title={
                                                                        addressQuery
                                                                    }
                                                                >
                                                                    {location ||
                                                                        '—'}
                                                                </a>
                                                            ) : (
                                                                <div
                                                                    className="max-w-[200px] truncate"
                                                                    title={
                                                                        location ||
                                                                        ''
                                                                    }
                                                                >
                                                                    {location ||
                                                                        '—'}
                                                                </div>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm">
                                                            {booking.caregiver ? (
                                                                <Link
                                                                    href={`/caregivers/${booking.caregiver.id}`}
                                                                    onClick={(e) =>
                                                                        e.stopPropagation()
                                                                    }
                                                                    className="font-medium text-ring hover:underline"
                                                                >
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
                                                                </Link>
                                                            ) : (
                                                                <span className="text-muted-foreground italic">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <StatusBadge
                                                                status={
                                                                    statusKey
                                                                }
                                                                bookingStatuses={
                                                                    booking_statuses
                                                                }
                                                            />
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex justify-end gap-2">
                                                                {/* eslint-disable-next-line no-constant-binary-expression */}
                                                                {false &&
                                                                    (statusKey ===
                                                                        'completed' ||
                                                                        statusKey ===
                                                                            'pending') &&
                                                                    booking.payment_status !==
                                                                        'paid' && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="h-8 w-8 text-green-600 hover:bg-green-50 hover:text-green-700"
                                                                            onClick={(e) => {
                                                                                e.stopPropagation();
                                                                                window.location.href =
                                                                                    '/admin/bookings/charge?booking_id=' +
                                                                                    booking.id;
                                                                            }}
                                                                            title="Charge"
                                                                        >
                                                                            <CreditCard className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                <Button
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        sheet.openDuplicateSheet(
                                                                            booking as unknown as FullBooking,
                                                                        );
                                                                    }}
                                                                    variant="outline"
                                                                >
                                                                    Duplicate
                                                                </Button>
                                                                <Button
                                                                    onClick={(e) => {
                                                                        e.stopPropagation();
                                                                        sheet.openEditSheet(
                                                                            booking as unknown as FullBooking,
                                                                        );
                                                                    }}
                                                                >
                                                                    Edit
                                                                </Button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                );
                                            })
                                    )}
                                </tbody>
                            </table>
                        </div>
                    )}
                </div>

                <BookingSheet {...sheet} />

                <ExportSheet
                    open={exportSheetOpen}
                    onOpenChange={setExportSheetOpen}
                    defaultMonth={filters.month}
                    defaultYear={filters.year}
                />

                <Dialog
                    open={!!selectedDay}
                    onOpenChange={(open) => !open && setSelectedDay(null)}
                >
                    <DialogContent className="max-w-md">
                        <DialogHeader>
                            <DialogTitle>
                                Bookings for{' '}
                                {selectedDay &&
                                    format(
                                        new Date(selectedDay.date + 'T12:00:00'),
                                        'PPPP',
                                    )}
                            </DialogTitle>
                        </DialogHeader>
                        <div className="flex max-h-[60vh] flex-col gap-2 overflow-y-auto py-4 pr-2">
                            {selectedDay?.bookings.map((booking) => {
                                const statusKey =
                                    booking.status?.toLowerCase() || 'received';
                                const statusObj =
                                    booking_statuses.find(
                                        (s) => s.value === statusKey,
                                    ) ||
                                    booking_statuses.find(
                                        (s) => s.value === 'received',
                                    );
                                const colors = statusObj?.colors;
                                const ServiceIcon =
                                    serviceTypeIcons[booking.booking_group?.service_type] ||
                                    CalendarIcon;

                                return (
                                    <button
                                        key={booking.id}
                                        onClick={() => {
                                            setSelectedDay(null);
                                            sheet.openEditSheet(
                                                booking as unknown as FullBooking,
                                            );
                                        }}
                                        className={`flex w-full cursor-pointer items-center justify-between gap-3 rounded-md border p-3 text-left transition hover:brightness-95 ${
                                            colors?.bg || 'bg-blue-100'
                                        } ${colors?.text || 'text-blue-800'} ${
                                            colors?.border || 'border-blue-300'
                                        }`}
                                    >
                                        <div className="flex items-center gap-3 overflow-hidden">
                                            <div className="rounded-full bg-background/20 p-2">
                                                <ServiceIcon className="h-4 w-4" />
                                            </div>
                                            <div className="overflow-hidden">
                                                <div className="text-xs opacity-80">
                                                    {formatDisplayTimeInPT(
                                                        booking.start_datetime,
                                                    )}
                                                    -
                                                    {formatDisplayTimeInPT(
                                                        booking.end_datetime,
                                                    )}
                                                </div>
                                                <div className="truncate font-semibold whitespace-nowrap">
                                                </div>
                                            </div>
                                        </div>
                                        <StatusBadge
                                            status={statusKey}
                                            bookingStatuses={booking_statuses}
                                        />
                                    </button>
                                );
                            })}
                        </div>
                    </DialogContent>
                </Dialog>

                {showScrollTop && (
                    <button
                        onClick={() =>
                            window.scrollTo({ top: 0, behavior: 'smooth' })
                        }
                        className="fixed bottom-6 right-6 z-50 flex h-10 w-10 cursor-pointer items-center justify-center rounded-full border border-border bg-card text-foreground shadow-lg transition-all hover:bg-accent"
                        type="button"
                    >
                        <ChevronUp className="h-5 w-5" />
                    </button>
                )}
            </div>
        </AppLayout>
    );
}
