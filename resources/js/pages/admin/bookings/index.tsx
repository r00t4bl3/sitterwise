import { Head, useForm, usePage } from '@inertiajs/react';
import {
    ChevronLeft,
    ChevronRight,
    Baby,
    Dog,
    Calendar as CalendarIcon,
    User,
    Building,
    Users,
} from 'lucide-react';
import { useState, useMemo } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { PersonalInfoSection } from './personal-info-section';
import { BookingDetailsSection } from './booking-details-section';
import type {
    Client,
    Hotel,
    Caregiver,
    ClientAddress,
    ClientChild,
    ClientPet,
    Booking,
    Props,
    BookingFormData,
} from './types';

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

function calculateAge(
    birthYear: number | null,
    birthMonth: number | null,
): string {
    if (!birthYear) return '-';

    const today = new Date();
    const birthDate = new Date(birthYear, (birthMonth || 1) - 1, 1);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
        age--;
    }

    if (age < 1) {
        const months =
            (today.getFullYear() - birthDate.getFullYear()) * 12 +
            today.getMonth() -
            birthDate.getMonth();
        return `${months}mo`;
    }

    return `${age}yr`;
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
        booking_attributes,
    } = usePage<Props>().props;

    const [currentMonth, setCurrentMonth] = useState(filters.month);
    const [currentYear, setCurrentYear] = useState(filters.year);
    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status,
    );
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingBooking, setEditingBooking] = useState<Booking | null>(null);

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
    const [clientChildren, setClientChildren] = useState<ClientChild[]>([]);
    const [clientPets, setClientPets] = useState<ClientPet[]>([]);
    const [addressMode, setAddressMode] = useState<'select' | 'input'>(
        'select',
    );
    const [clientMode, setClientMode] = useState<'select' | 'input'>('select');
    const [loadingSuggestions, setLoadingSuggestions] = useState(false);

    const [selectedClientName, setSelectedClientName] = useState<string>('');
    const [selectedHotelName, setSelectedHotelName] = useState<string>('');
    const [selectedCaregiverName, setSelectedCaregiverName] =
        useState<string>('');

    const [deletedChildIds, setDeletedChildIds] = useState<number[]>([]);
    const [deletedPetIds, setDeletedPetIds] = useState<number[]>([]);

    const [newChildren, setNewChildren] = useState<
        Array<{
            tempId: string;
            name: string;
            gender: string;
            birth_month: string;
            birth_year: string;
        }>
    >([]);
    const [newPets, setNewPets] = useState<
        Array<{
            tempId: string;
            name: string;
            type: string;
            breed: string;
            notes: string;
        }>
    >([]);
    const [saveChildrenPetsToProfile, setSaveChildrenPetsToProfile] =
        useState(true);

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
        how_did_you_hear: string;
        sitter_preferences: string;
        other_adults_in_home: string;
        medical_info: string;
        emergency_instructions: string;
        requires_payment: boolean;
        status: string;
        payment_status: string;
        vacation_rental_platform: string;
        booking_address: {
            line1: string;
            line2: string;
            city: string;
            state: string;
            zip: string;
        };
        new_client: {
            first_name: string;
            last_name: string;
            email: string;
            phone: string;
            client_type: string;
        };
        new_children: Array<{
            name: string;
            gender: string;
            birth_month: string;
            birth_year: string;
        }>;
        new_pets: Array<{
            name: string;
            type: string;
            breed: string;
            notes: string;
        }>;
        deleted_child_ids: number[];
        deleted_pet_ids: number[];
        save_children_pets_to_profile: boolean;
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
        how_did_you_hear: '',
        sitter_preferences: '',
        other_adults_in_home: '',
        medical_info: '',
        emergency_instructions: '',
        requires_payment: true,
        status: 'received',
        payment_status: 'pending',
        vacation_rental_platform: '',
        booking_address: {
            line1: '',
            line2: '',
            city: '',
            state: '',
            zip: '',
        },
        new_client: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            client_type: 'individual',
        },
        new_children: [],
        new_pets: [],
        deleted_child_ids: [],
        deleted_pet_ids: [],
        save_children_pets_to_profile: true,
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
        setAddressMode('select');
        setClientMode('select');

        if (clientId) {
            try {
                const response = await fetch(`/clients/${clientId}/data`);
                const data = await response.json();
                setClientAddresses(data.client.addresses || []);
                setClientChildren(data.client.children || []);
                setClientPets(data.client.pets || []);
            } catch (error) {
                console.error('Error fetching client data:', error);
            }
        } else {
            setClientAddresses([]);
            setClientChildren([]);
            setClientPets([]);
        }
    };

    const openCreateSheet = (date?: string) => {
        setEditingBooking(null);

        let defaultStart: Date;

        if (date) {
            defaultStart = new Date(`${date}T09:00`);
        } else {
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            tomorrow.setHours(9, 0, 0, 0);
            defaultStart = tomorrow;
        }

        const defaultEnd = new Date(
            defaultStart.getTime() + 4 * 60 * 60 * 1000,
        );

        form.setData({
            client_id: null,
            service_type: 'babysitter',
            location_type: 'private_home',
            start_datetime: formatDateTimeLocal(defaultStart),
            end_datetime: formatDateTimeLocal(defaultEnd),
            hotel_id: null,
            address_id: null,
            caregiver_id: null,
            special_considerations: [],
            caregiver_notes: '',
            notes_to_sitterwise: '',
            admin_notes: '',
            corporate_id: '',
            how_did_you_hear: '',
            sitter_preferences: '',
            other_adults_in_home: '',
            medical_info: '',
            emergency_instructions: '',
            requires_payment: true,
            status: 'received',
            payment_status: 'pending',
            vacation_rental_platform: '',
            booking_address: {
                line1: '',
                line2: '',
                city: '',
                state: '',
                zip: '',
            },
            new_client: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                client_type: 'individual',
            },
        });
        setClientAddresses([]);
        setClientChildren([]);
        setClientPets([]);
        setNewChildren([]);
        setNewPets([]);
        setDeletedChildIds([]);
        setDeletedPetIds([]);
        setSaveChildrenPetsToProfile(true);
        setAddressMode('select');
        setClientMode('select');
        setIsSheetOpen(true);
    };

    const openEditSheet = (booking: Booking) => {
        setEditingBooking(booking);

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
        form.setData('requires_payment', booking.requires_payment);
        form.setData('status', booking.status);
        form.setData('payment_status', booking.payment_status);

        if (
            booking.location_type === 'vacation_rental' &&
            booking.attributeDefinitions
        ) {
            const platformAttr = booking.attributeDefinitions.find(
                (attr: {
                    pivot: { attribute_definition_id: number; value: string };
                }) => {
                    const attrDef = booking_attributes.find(
                        (a: { id: number; slug: string }) =>
                            a.id === attr.pivot.attribute_definition_id &&
                            a.slug === 'vacation_rental_platform',
                    );

                    return !!attrDef;
                },
            );

            if (platformAttr) {
                form.setData(
                    'vacation_rental_platform',
                    platformAttr.pivot.value,
                );
            }
        }

        if (booking.booking_address) {
            form.setData('booking_address', {
                line1: booking.booking_address.line1 || '',
                line2: booking.booking_address.line2 || '',
                city: booking.booking_address.city || '',
                state: booking.booking_address.state || '',
                zip: booking.booking_address.zip || '',
            });
        }

        if (booking.client_id) {
            const client = clients.find((c) => c.id === booking.client_id);

            if (client) {
                setSelectedClientName(client.name);
                handleClientSearch(client.name);
            }

            handleClientChange(booking.client_id);
        }

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
        form.setData('new_children', newChildren);
        form.setData('new_pets', newPets);
        form.setData('deleted_child_ids', deletedChildIds);
        form.setData('deleted_pet_ids', deletedPetIds);
        form.setData(
            'save_children_pets_to_profile',
            saveChildrenPetsToProfile,
        );

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

    const handleAddChild = () => {
        setNewChildren([
            ...newChildren,
            {
                tempId: `new-${Date.now()}`,
                name: '',
                gender: '',
                birth_month: '',
                birth_year: '',
            },
        ]);
    };

    const handleRemoveChild = (tempId: string, id?: number) => {
        if (id) {
            setDeletedChildIds([...deletedChildIds, id]);
            setClientChildren(clientChildren.filter((c) => c.id !== id));
        } else {
            setNewChildren(newChildren.filter((c) => c.tempId !== tempId));
        }
    };

    const handleUpdateChild = (
        tempId: string,
        field: string,
        value: string | boolean,
    ) => {
        setNewChildren(
            newChildren.map((c) =>
                c.tempId === tempId ? { ...c, [field]: value } : c,
            ),
        );
    };

    const handleAddPet = () => {
        setNewPets([
            ...newPets,
            {
                tempId: `new-${Date.now()}`,
                name: '',
                type: '',
                breed: '',
                notes: '',
            },
        ]);
    };

    const handleRemovePet = (tempId: string, id?: number) => {
        if (id) {
            setDeletedPetIds([...deletedPetIds, id]);
            setClientPets(clientPets.filter((p) => p.id !== id));
        } else {
            setNewPets(newPets.filter((p) => p.tempId !== tempId));
        }
    };

    const handleUpdatePet = (tempId: string, field: string, value: string) => {
        setNewPets(
            newPets.map((p) =>
                p.tempId === tempId ? { ...p, [field]: value } : p,
            ),
        );
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
                                                className={`flex cursor-pointer items-center gap-1 rounded-[3px] border px-1 py-0.5 text-xs ${
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
                            <SheetDescription>
                                {editingBooking
                                    ? 'Update booking details below.'
                                    : 'Fill in the details to create a new booking.'}
                            </SheetDescription>
                        </SheetHeader>

                        <div className="mt-4 space-y-4 px-4">
                            <PersonalInfoSection
                                form={form}
                                clientMode={clientMode}
                                setClientMode={setClientMode}
                                addressMode={addressMode}
                                setAddressMode={setAddressMode}
                                clientSuggestions={clientSuggestions}
                                clientAddresses={clientAddresses}
                                clientChildren={clientChildren}
                                clientPets={clientPets}
                                newChildren={newChildren}
                                newPets={newPets}
                                onAddChild={handleAddChild}
                                onRemoveChild={handleRemoveChild}
                                onUpdateChild={handleUpdateChild}
                                onAddPet={handleAddPet}
                                onRemovePet={handleRemovePet}
                                onUpdatePet={handleUpdatePet}
                                saveChildrenPetsToProfile={
                                    saveChildrenPetsToProfile
                                }
                                onSaveChildrenPetsToProfileChange={
                                    setSaveChildrenPetsToProfile
                                }
                                loadingSuggestions={loadingSuggestions}
                                selectedClientName={selectedClientName}
                                handleClientSearch={handleClientSearch}
                                handleClientChange={handleClientChange}
                                location_types={location_types}
                                booking_attributes={booking_attributes}
                                hotels={hotels}
                                hotelSuggestions={hotelSuggestions}
                                selectedHotelName={selectedHotelName}
                                handleHotelSearch={handleHotelSearch}
                                calculateAge={calculateAge}
                            />

                            <BookingDetailsSection
                                form={form}
                                editingBooking={editingBooking}
                                service_types={service_types}
                                special_consideration_options={
                                    special_consideration_options
                                }
                                booking_statuses={booking_statuses}
                                payment_statuses={payment_statuses}
                                caregiverSuggestions={caregiverSuggestions}
                                selectedCaregiverName={selectedCaregiverName}
                                handleCaregiverSearch={handleCaregiverSearch}
                                handleSpecialConsiderationChange={
                                    handleSpecialConsiderationChange
                                }
                                handleSubmit={handleSubmit}
                                handleDelete={handleDelete}
                                setIsSheetOpen={setIsSheetOpen}
                            />
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
