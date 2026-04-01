import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useMemo, useEffect, useRef } from 'react';
import {
    ChevronLeft,
    ChevronRight,
    Search,
    X,
    Baby,
    Dog,
    Waves,
    Calendar as CalendarIcon,
    Clock,
    MapPin,
    User,
    Building,
    Users,
} from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Spinner } from '@/components/ui/spinner';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { ToasterMessage } from '@/components/toaster-message';
import type { BreadcrumbItem } from '@/types';

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

interface Client {
    id: number;
    name: string;
    email: string;
}

interface Hotel {
    id: number;
    name: string;
    city: string | null;
}

interface Caregiver {
    id: number;
    name: string;
}

interface ClientAddress {
    id: number;
    line1: string;
    city: string;
    state: string;
    zip: string;
}

interface Booking {
    id: number;
    client_id: number;
    service_type: string;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    special_considerations: string[] | null;
    caregiver_notes: string | null;
    notes_to_sitterwise: string | null;
    admin_notes: string | null;
    corporate_id: string | null;
    comped: boolean;
    total_amount: number;
    payment_status: string;
    requires_payment: boolean;
    hotel_id: number | null;
    address_id: number | null;
    caregiver_id: number | null;
    client: {
        id: number;
        first_name: string;
        last_name: string;
        user: {
            profile_photo_path: string | null;
        };
    };
    hotel: {
        id: number;
        name: string;
    } | null;
    caregiver: {
        id: number;
        first_name: string;
        last_name: string;
    } | null;
}

interface Props {
    [key: string]: unknown;
    bookings: Booking[];
    filters: {
        month: number;
        year: number;
        status: string | null;
    };
    clients: Client[];
    hotels: Hotel[];
    caregivers: Caregiver[];
    service_types: Array<{ value: string; label: string }>;
    location_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    special_consideration_options: Array<{ value: string; label: string }>;
}

const statusColors: Record<
    string,
    { bg: string; text: string; border: string }
> = {
    received: {
        bg: 'bg-blue-100',
        text: 'text-blue-800',
        border: 'border-blue-300',
    },
    pending: {
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

const statusLabels: Record<string, string> = {
    received: 'Received',
    pending: 'Pending',
    confirmed: 'Confirmed',
    completed: 'Completed',
    cancelled: 'Cancelled',
};

const serviceTypeIcons: Record<string, React.ElementType> = {
    babysitter: Baby,
    petsitter: Dog,
    companion_care: User,
    group_childcare_invoiced: Users,
    corporate_invoiced: Building,
    comped: CalendarIcon,
};

function getDaysInMonth(year: number, month: number): (number | null)[] {
    const firstDay = new Date(year, month - 1, 1).getDay();
    const daysInMonth = new Date(year, month, 0).getDate();

    const days: (number | null)[] = [];

    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }

    for (let i = 1; i <= daysInMonth; i++) {
        days.push(i);
    }

    return days;
}

function formatTime(datetime: string): string {
    return new Date(datetime).toLocaleTimeString('en-US', {
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
}

function formatDateTimeLocal(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');
    return `${year}-${month}-${day}T${hours}:${minutes}`;
}

interface AutocompleteProps {
    value: number | null;
    onChange: (id: number | null) => void;
    suggestions: Array<{ id: number; name: string; [key: string]: unknown }>;
    onSearch: (query: string) => void;
    placeholder: string;
    loading?: boolean;
    displayValue?: string;
}

function Autocomplete({
    value,
    onChange,
    suggestions,
    onSearch,
    placeholder,
    loading,
    displayValue,
}: AutocompleteProps) {
    const [query, setQuery] = useState(displayValue || '');
    const [showSuggestions, setShowSuggestions] = useState(false);
    const wrapperRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const selectedItem = suggestions.find((s) => s.id === value);

    useEffect(() => {
        if (displayValue !== undefined) {
            setQuery(displayValue);
        }
    }, [displayValue]);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                wrapperRef.current &&
                !wrapperRef.current.contains(event.target as Node)
            ) {
                setShowSuggestions(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleInputChange = (value: string) => {
        setQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!value.trim()) {
            onChange(null);
            return;
        }
        debounceRef.current = setTimeout(() => {
            onSearch(value);
        }, 300);
    };

    return (
        <div ref={wrapperRef} className="relative">
            <input
                type="text"
                value={query}
                onChange={(e) => handleInputChange(e.target.value)}
                onFocus={() =>
                    suggestions.length > 0 && setShowSuggestions(true)
                }
                placeholder={placeholder}
                className="h-10 w-full rounded-[3px] border border-input bg-background px-3 pr-10 text-sm outline-none focus:border-ring"
            />
            {value && (
                <button
                    type="button"
                    onClick={() => {
                        setQuery('');
                        onChange(null);
                    }}
                    className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                >
                    <X className="h-4 w-4" />
                </button>
            )}
            {showSuggestions && (
                <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-auto rounded-[3px] border border-border bg-card shadow-md">
                    {loading ? (
                        <div className="p-3 text-sm text-muted-foreground">
                            Loading...
                        </div>
                    ) : suggestions.length === 0 ? (
                        <div className="p-3 text-sm text-muted-foreground">
                            No results
                        </div>
                    ) : (
                        suggestions.map((item) => (
                            <button
                                key={item.id}
                                type="button"
                                onClick={() => {
                                    onChange(item.id);
                                    setQuery(item.name);
                                    setShowSuggestions(false);
                                }}
                                className="w-full px-3 py-2 text-left text-sm hover:bg-accent"
                            >
                                {item.name}
                            </button>
                        ))
                    )}
                </div>
            )}
        </div>
    );
}

export default function BookingsIndex() {
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
        special_consideration_options,
    } = usePage<Props>().props;

    const [currentMonth, setCurrentMonth] = useState(filters.month);
    const [currentYear, setCurrentYear] = useState(filters.year);
    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status,
    );
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingBooking, setEditingBooking] = useState<Booking | null>(null);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);

    const [clientSuggestions, setClientSuggestions] = useState<
        Array<{ id: number; name: string; [key: string]: unknown }>
    >([]);
    const [hotelSuggestions, setHotelSuggestions] = useState<
        Array<{ id: number; name: string; [key: string]: unknown }>
    >([]);
    const [caregiverSuggestions, setCaregiverSuggestions] = useState<
        Array<{ id: number; name: string; [key: string]: unknown }>
    >([]);
    const [clientAddresses, setClientAddresses] = useState<ClientAddress[]>([]);
    const [loadingSuggestions, setLoadingSuggestions] = useState(false);

    // Store selected item names for autocomplete display
    const [selectedClientName, setSelectedClientName] = useState<string>('');
    const [selectedHotelName, setSelectedHotelName] = useState<string>('');
    const [selectedCaregiverName, setSelectedCaregiverName] =
        useState<string>('');

    const form = useForm<{
        client_id: number | null;
        service_type: string;
        location_type: string;
        start_datetime: string;
        end_datetime: string;
        hotel_id: number | null;
        address_id: number | null;
        caregiver_id: number | null;
        special_considerations: string[];
        caregiver_notes: string;
        notes_to_sitterwise: string;
        admin_notes: string;
        corporate_id: string;
        comped: boolean;
        total_amount: string;
        requires_payment: boolean;
        status: string;
        payment_status: string;
    }>({
        client_id: null,
        service_type: 'babysitter',
        location_type: 'private_home',
        start_datetime: '',
        end_datetime: '',
        hotel_id: null,
        address_id: null,
        caregiver_id: null,
        special_considerations: [],
        caregiver_notes: '',
        notes_to_sitterwise: '',
        admin_notes: '',
        corporate_id: '',
        comped: false,
        total_amount: '',
        requires_payment: true,
        status: 'received',
        payment_status: 'pending',
    });

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
        if (currentMonth === 1) {
            setCurrentMonth(12);
            setCurrentYear(currentYear - 1);
        } else {
            setCurrentMonth(currentMonth - 1);
        }
    };

    const nextMonth = () => {
        if (currentMonth === 12) {
            setCurrentMonth(1);
            setCurrentYear(currentYear + 1);
        } else {
            setCurrentMonth(currentMonth + 1);
        }
    };

    const applyFilters = () => {
        const params = new URLSearchParams();
        params.set('month', String(currentMonth));
        params.set('year', String(currentYear));
        if (statusFilter) {
            params.set('status', statusFilter);
        }
        window.location.href = `/admin/bookings?${params}`;
    };

    const bookingsByDate = useMemo(() => {
        const grouped: Record<string, Booking[]> = {};
        bookings.forEach((booking) => {
            const date = new Date(booking.start_datetime)
                .toISOString()
                .split('T')[0];
            if (!grouped[date]) {
                grouped[date] = [];
            }
            grouped[date].push(booking);
        });
        return grouped;
    }, [bookings]);

    const handleClientSearch = async (query: string) => {
        if (!query.trim()) {
            setClientSuggestions([]);
            return;
        }
        try {
            setLoadingSuggestions(true);
            const params = new URLSearchParams({ q: query });
            const response = await fetch(
                `/clients/search-suggestions?${params}`,
            );
            const data = await response.json();
            setClientSuggestions(data);
        } catch (error) {
            console.error('Client search error:', error);
        } finally {
            setLoadingSuggestions(false);
        }
    };

    const handleHotelSearch = async (query: string) => {
        if (!query.trim()) {
            setHotelSuggestions(
                hotels as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
            return;
        }
        const filtered = hotels.filter((h) =>
            h.name.toLowerCase().includes(query.toLowerCase()),
        );
        setHotelSuggestions(
            filtered as unknown as Array<{
                id: number;
                name: string;
                [key: string]: unknown;
            }>,
        );
    };

    const handleCaregiverSearch = async (query: string) => {
        if (!query.trim()) {
            setCaregiverSuggestions(
                caregivers as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
            return;
        }
        const filtered = caregivers.filter((c) =>
            c.name.toLowerCase().includes(query.toLowerCase()),
        );
        setCaregiverSuggestions(
            filtered as unknown as Array<{
                id: number;
                name: string;
                [key: string]: unknown;
            }>,
        );
    };

    const handleClientChange = async (clientId: number | null) => {
        form.setData('client_id', clientId);
        form.setData('address_id', null);

        if (clientId) {
            try {
                const response = await fetch(`/clients/${clientId}/data`);
                const data = await response.json();
                setClientAddresses(data.client.addresses || []);
            } catch (error) {
                console.error('Error fetching client addresses:', error);
            }
        } else {
            setClientAddresses([]);
        }
    };

    const openCreateSheet = (date?: string) => {
        setEditingBooking(null);
        setSelectedDate(date || null);
        const defaultStart = date ? new Date(`${date}T09:00`) : new Date();
        const defaultEnd = new Date(
            defaultStart.getTime() + 3 * 60 * 60 * 1000,
        );

        form.reset({
            client_id: null,
            service_type: 'babysitter',
            location_type: 'private_home',
            start_datetime: formatDateTimeLocal(defaultStart),
            end_datetime: formatDateTimeLocal(defaultEnd),
            hotel_id: null,
            address_id: null,
            caregiver_id: null,
            special_considerations: [] as string[],
            caregiver_notes: '',
            notes_to_sitterwise: '',
            admin_notes: '',
            corporate_id: '',
            comped: false,
            total_amount: '',
            requires_payment: true,
            status: 'received',
            payment_status: 'pending',
        } as unknown as Parameters<typeof form.reset>[0]);
        setClientAddresses([]);
        setIsSheetOpen(true);
    };

    const openEditSheet = (booking: Booking) => {
        setEditingBooking(booking);

        // Use setData instead of reset to ensure values are applied
        form.setData('client_id', booking.client_id);
        form.setData('service_type', booking.service_type);
        form.setData('location_type', booking.location_type);
        form.setData(
            'start_datetime',
            formatDateTimeLocal(new Date(booking.start_datetime)),
        );
        form.setData(
            'end_datetime',
            formatDateTimeLocal(new Date(booking.end_datetime)),
        );
        form.setData('hotel_id', booking.hotel_id);
        form.setData('address_id', booking.address_id);
        form.setData('caregiver_id', booking.caregiver_id);
        form.setData(
            'special_considerations',
            booking.special_considerations || [],
        );
        form.setData('caregiver_notes', booking.caregiver_notes || '');
        form.setData('notes_to_sitterwise', booking.notes_to_sitterwise || '');
        form.setData('admin_notes', booking.admin_notes || '');
        form.setData('corporate_id', booking.corporate_id || '');
        form.setData('comped', booking.comped);
        form.setData('total_amount', String(booking.total_amount));
        form.setData('requires_payment', booking.requires_payment);
        form.setData('status', booking.status);
        form.setData('payment_status', booking.payment_status);

        if (booking.client_id) {
            const client = clients.find((c) => c.id === booking.client_id);
            if (client) {
                setSelectedClientName(client.name);
                handleClientSearch(client.name);
            }
            handleClientChange(booking.client_id);
        }

        // Also set hotel and caregiver search if they exist
        if (booking.hotel_id) {
            const hotel = hotels.find((h) => h.id === booking.hotel_id);
            if (hotel) {
                setSelectedHotelName(hotel.name);
                handleHotelSearch(hotel.name);
            }
        }

        if (booking.caregiver_id) {
            const caregiver = caregivers.find(
                (c) => c.id === booking.caregiver_id,
            );
            if (caregiver) {
                setSelectedCaregiverName(caregiver.name);
                handleCaregiverSearch(caregiver.name);
            }
        }

        setIsSheetOpen(true);
    };

    const handleSubmit = () => {
        if (editingBooking) {
            form.put(`/admin/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
            });
        } else {
            form.post('/admin/bookings', {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    applyFilters();
                },
            });
        }
    };

    const handleDelete = () => {
        if (
            editingBooking &&
            confirm('Are you sure you want to delete this booking?')
        ) {
            form.delete(`/admin/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    applyFilters();
                },
            });
        }
    };

    const handleSpecialConsiderationChange = (
        option: string,
        checked: boolean,
    ) => {
        if (checked) {
            form.setData('special_considerations', [
                ...form.data.special_considerations,
                option,
            ]);
        } else {
            form.setData(
                'special_considerations',
                form.data.special_considerations.filter((s) => s !== option),
            );
        }
    };

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
                            {monthNames[currentMonth - 1]} {currentYear}
                        </p>
                    </div>
                    <button
                        onClick={() => openCreateSheet()}
                        className="btn-primary"
                    >
                        Create Booking
                    </button>
                </div>

                <div className="flex items-center gap-4">
                    <div className="flex items-center gap-2">
                        <button
                            onClick={prevMonth}
                            className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <button
                            onClick={nextMonth}
                            className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>
                    <select
                        value={statusFilter || ''}
                        onChange={(e) =>
                            setStatusFilter(e.target.value || null)
                        }
                        className="h-10 rounded-[3px] border border-input bg-background px-3 text-sm"
                    >
                        <option value="">All Statuses</option>
                        {booking_statuses.map((status) => (
                            <option key={status.value} value={status.value}>
                                {status.label}
                            </option>
                        ))}
                    </select>
                    <button onClick={applyFilters} className="btn-secondary">
                        Apply
                    </button>
                </div>

                <div className="flex flex-wrap gap-3 text-xs">
                    {Object.entries(statusColors).map(([status, colors]) => (
                        <div key={status} className="flex items-center gap-1.5">
                            <span
                                className={`inline-block h-3 w-3 rounded-[2px] border ${
                                    colors?.bg || 'bg-gray-100'
                                } ${colors?.border || 'border-gray-300'}`}
                            />
                            <span className="text-muted-foreground">
                                {statusLabels[status] || status}
                            </span>
                        </div>
                    ))}
                </div>

                <div className="rounded-[6px] border border-border bg-card p-4">
                    <div className="grid grid-cols-7 gap-1">
                        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(
                            (day) => (
                                <div
                                    key={day}
                                    className="py-2 text-center text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    {day}
                                </div>
                            ),
                        )}

                        {days.map((day, index) => {
                            if (day === null) {
                                return (
                                    <div
                                        key={`empty-${index}`}
                                        className="h-32"
                                    />
                                );
                            }

                            const dateStr = `${currentYear}-${String(currentMonth).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            const dayBookings = bookingsByDate[dateStr] || [];
                            const displayBookings = dayBookings.slice(0, 5);
                            const remainingCount = dayBookings.length - 5;

                            return (
                                <div
                                    key={day}
                                    className="flex min-h-32 flex-col gap-1 border border-border bg-background p-2"
                                >
                                    <span className="text-sm font-medium text-foreground">
                                        {day}
                                    </span>
                                    {displayBookings.map((booking) => {
                                        const statusKey =
                                            booking.status?.toLowerCase() ||
                                            'received';
                                        const colors =
                                            statusColors[statusKey] ||
                                            statusColors.received;
                                        const ServiceIcon =
                                            serviceTypeIcons[
                                                booking.service_type
                                            ] || CalendarIcon;
                                        return (
                                            <button
                                                key={booking.id}
                                                onClick={() =>
                                                    openEditSheet(booking)
                                                }
                                                className={`flex items-center gap-1 rounded-[3px] border px-1 py-0.5 text-xs ${
                                                    colors?.bg || 'bg-blue-100'
                                                } ${
                                                    colors?.text ||
                                                    'text-blue-800'
                                                } ${
                                                    colors?.border ||
                                                    'border-blue-300'
                                                }`}
                                            >
                                                <ServiceIcon className="h-3 w-3 flex-shrink-0" />
                                                <span className="truncate">
                                                    {formatTime(
                                                        booking.start_datetime,
                                                    )}
                                                    -
                                                    {formatTime(
                                                        booking.end_datetime,
                                                    )}
                                                </span>
                                            </button>
                                        );
                                    })}
                                    {remainingCount > 0 && (
                                        <button
                                            onClick={() =>
                                                openCreateSheet(dateStr)
                                            }
                                            className="text-xs font-medium text-ring hover:text-foreground"
                                        >
                                            + {remainingCount} more
                                        </button>
                                    )}
                                    <button
                                        onClick={() => openCreateSheet(dateStr)}
                                        className="mt-auto text-xs text-ring hover:text-foreground"
                                    >
                                        + Add
                                    </button>
                                </div>
                            );
                        })}
                    </div>
                </div>

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent
                        side="right"
                        className="w-full overflow-y-auto sm:max-w-2xl"
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {editingBooking
                                    ? 'Edit Booking'
                                    : 'Create Booking'}
                            </SheetTitle>
                        </SheetHeader>

                        <div className="mt-4 space-y-4 px-4">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Client{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <div className="mt-1">
                                    <Autocomplete
                                        value={form.data.client_id}
                                        onChange={handleClientChange}
                                        suggestions={clientSuggestions}
                                        onSearch={handleClientSearch}
                                        placeholder="Search client..."
                                        loading={loadingSuggestions}
                                        displayValue={selectedClientName}
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Service Type{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={form.data.service_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'service_type',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    >
                                        {service_types.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Location Type{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={form.data.location_type}
                                        onChange={(e) =>
                                            form.setData(
                                                'location_type',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    >
                                        {location_types.map((type) => (
                                            <option
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Start DateTime{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <div className="mt-1">
                                        <DateTimePicker
                                            value={form.data.start_datetime}
                                            onChange={(datetime) =>
                                                form.setData(
                                                    'start_datetime',
                                                    datetime,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        End DateTime{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <div className="mt-1">
                                        <DateTimePicker
                                            value={form.data.end_datetime}
                                            onChange={(datetime) =>
                                                form.setData(
                                                    'end_datetime',
                                                    datetime,
                                                )
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            {form.data.location_type === 'hotel' && (
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Hotel
                                    </label>
                                    <div className="mt-1">
                                        <Autocomplete
                                            value={form.data.hotel_id}
                                            onChange={(id) =>
                                                form.setData('hotel_id', id)
                                            }
                                            suggestions={hotelSuggestions}
                                            onSearch={handleHotelSearch}
                                            placeholder="Search hotel..."
                                            displayValue={selectedHotelName}
                                        />
                                    </div>
                                </div>
                            )}

                            {(form.data.location_type === 'private_home' ||
                                form.data.location_type ===
                                    'vacation_rental') && (
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Address
                                    </label>
                                    <select
                                        value={form.data.address_id || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'address_id',
                                                e.target.value
                                                    ? Number(e.target.value)
                                                    : null,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    >
                                        <option value="">
                                            Select address...
                                        </option>
                                        {clientAddresses.map((addr) => (
                                            <option
                                                key={addr.id}
                                                value={addr.id}
                                            >
                                                {addr.line1}, {addr.city},{' '}
                                                {addr.state} {addr.zip}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            )}

                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Caregiver
                                </label>
                                <div className="mt-1">
                                    <Autocomplete
                                        value={form.data.caregiver_id}
                                        onChange={(id) =>
                                            form.setData('caregiver_id', id)
                                        }
                                        suggestions={caregiverSuggestions}
                                        onSearch={handleCaregiverSearch}
                                        placeholder="Search caregiver..."
                                        displayValue={selectedCaregiverName}
                                    />
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Special Considerations
                                </label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    {special_consideration_options.map(
                                        (option) => (
                                            <label
                                                key={option.value}
                                                className="flex items-center gap-2"
                                            >
                                                <input
                                                    type="checkbox"
                                                    checked={form.data.special_considerations.includes(
                                                        option.value,
                                                    )}
                                                    onChange={(e) =>
                                                        handleSpecialConsiderationChange(
                                                            option.value,
                                                            e.target.checked,
                                                        )
                                                    }
                                                    className="h-4 w-4 rounded border-input"
                                                />
                                                <span className="text-sm text-foreground">
                                                    {option.label}
                                                </span>
                                            </label>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Caregiver Notes
                                </label>
                                <textarea
                                    value={form.data.caregiver_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'caregiver_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Notes to Sitterwise
                                </label>
                                <textarea
                                    value={form.data.notes_to_sitterwise}
                                    onChange={(e) =>
                                        form.setData(
                                            'notes_to_sitterwise',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>

                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Admin Notes
                                </label>
                                <textarea
                                    value={form.data.admin_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'admin_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Corporate ID
                                    </label>
                                    <input
                                        type="text"
                                        value={form.data.corporate_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'corporate_id',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Total Amount
                                    </label>
                                    <input
                                        type="number"
                                        step="0.01"
                                        value={form.data.total_amount}
                                        onChange={(e) =>
                                            form.setData(
                                                'total_amount',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                            </div>

                            <div className="flex items-center gap-4">
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.comped}
                                        onChange={(e) =>
                                            form.setData(
                                                'comped',
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    <span className="text-sm text-foreground">
                                        Comped
                                    </span>
                                </label>
                                <label className="flex items-center gap-2">
                                    <input
                                        type="checkbox"
                                        checked={form.data.requires_payment}
                                        onChange={(e) =>
                                            form.setData(
                                                'requires_payment',
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    <span className="text-sm text-foreground">
                                        Requires Payment
                                    </span>
                                </label>
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Status{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={form.data.status}
                                        onChange={(e) =>
                                            form.setData(
                                                'status',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    >
                                        {booking_statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                                <div>
                                    <label className="text-sm font-medium text-foreground">
                                        Payment Status{' '}
                                        <span className="text-red-500">*</span>
                                    </label>
                                    <select
                                        value={form.data.payment_status}
                                        onChange={(e) =>
                                            form.setData(
                                                'payment_status',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    >
                                        {payment_statuses.map((status) => (
                                            <option
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </option>
                                        ))}
                                    </select>
                                </div>
                            </div>

                            <div className="flex gap-2 pt-4">
                                {editingBooking && (
                                    <button
                                        onClick={handleDelete}
                                        className="btn-secondary bg-red-50 text-red-600 hover:bg-red-100"
                                    >
                                        Delete
                                    </button>
                                )}
                                <button
                                    onClick={handleSubmit}
                                    disabled={form.processing}
                                    className="btn-primary flex-1"
                                >
                                    {form.processing && (
                                        <Spinner className="mr-2 size-4" />
                                    )}
                                    {editingBooking ? 'Update' : 'Create'}{' '}
                                    Booking
                                </button>
                            </div>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
