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
    CreditCard,
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import type { Message } from '@/components/toaster-message';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';
import { BookingDetailsSection } from './booking-details-section';
import { PersonalInfoSection } from './personal-info-section';
import type {
    ClientAddress,
    ClientChild,
    ClientPet,
    Booking,
    Props,
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
    if (!birthYear) {
        return '-';
    }

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
        special_consideration_options,
        booking_attributes,
    } = usePage<Props>().props;

    const sitter_preference_options = [
        { value: 'college_aged', label: 'College Aged' },
        { value: 'seasoned', label: 'Seasoned' },
        { value: 'baby_specialist', label: 'Baby Specialist' },
        { value: 'special_needs_exp', label: 'Special Needs Experience' },
        { value: 'willing_to_swim', label: 'Willing to Swim' },
    ];

    const client_type_options = [
        { value: 'resident', label: 'San Diego Resident' },
        { value: 'vacationer', label: 'Vacationer' },
        { value: 'invoiced', label: 'Invoiced' },
    ];

    // TODO: Pull special_consideration_options from booking_attributes
    // in the database once the 'special_considerations' attribute definition exists.

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
    const [, setAddressMode] = useState<'select' | 'input'>('select');
    const [clientMode, setClientMode] = useState<'select' | 'input'>('select');
    const [selectedClientType, setSelectedClientType] = useState<string | null>(
        null,
    );
    const [loadingSuggestions, setLoadingSuggestions] = useState(false);

    const [selectedClientName, setSelectedClientName] = useState<string>('');
    const [selectedHotelName, setSelectedHotelName] = useState<string>('');
    const [selectedCaregiverName, setSelectedCaregiverName] =
        useState<string>('');
    const [isAddressLocked, setIsAddressLocked] = useState(false);
    const [showManualAddressInput, setShowManualAddressInput] = useState(false);
    const [addressValue, setAddressValue] = useState('');

    const [message, setMessage] = useState<Message | null>(null);

    // Handle flash messages from server
    const flash = (usePage().props as Record<string, unknown>).flash as Record<
        string,
        string
    > | null;

    useEffect(() => {
        // Prioritize flash messages over existing messages
        if (flash?.success) {
            setMessage({ type: 'success', content: flash.success });
        } else if (flash?.error) {
            setMessage({ type: 'error', content: flash.error });
        }
    }, [flash]);

    // Clear message after it has been displayed (with debounce to prevent clearing new messages)
    useEffect(() => {
        if (message) {
            const timer = setTimeout(() => {
                setMessage(null);
            }, 5000); // Clear after 5 seconds to allow user to see the message

            return () => clearTimeout(timer);
        }
    }, [message]);

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
        sitter_preferences: string[];
        other_adults_present: string;
        emergency_instructions: string;
        special_needs_notes: string;
        requires_payment: boolean;
        status: string;
        payment_status: string;
        rental_platform: string | null;
        address_line1: string;
        address_line2: string;
        address_city: string;
        address_state: string;
        address_zip: string;
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
        sitter_preferences: [],
        other_adults_present: '',
        emergency_instructions: '',
        special_needs_notes: '',
        requires_payment: true,
        status: 'received',
        payment_status: 'pending',
        rental_platform: null,
        address_line1: '',
        address_line2: '',
        address_city: '',
        address_state: '',
        address_zip: '',
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

        console.log('Applying filters with params:', params.toString());

        // window.location.href = `/bookings?${params}`;
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

    const currentMonthBookings = useMemo(() => {
        return bookings.filter((booking) => {
            const startDate = new Date(booking.start_datetime);

            return (
                startDate.getMonth() + 1 === currentMonth &&
                startDate.getFullYear() === currentYear
            );
        });
    }, [bookings, currentMonth, currentYear]);

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

                // Store client_type for location type filtering
                setSelectedClientType(data.client.client_type || null);

                // Reset address fields
                form.setData('address_line1', '');
                form.setData('address_line2', '');
                form.setData('address_city', '');
                form.setData('address_state', '');
                form.setData('address_zip', '');

                // Auto-set location_type based on client_type
                const clientType = data.client.client_type;

                if (clientType === 'resident') {
                    form.setData('location_type', 'private_home');
                } else if (clientType === 'vacationer') {
                    form.setData('location_type', 'hotel');
                }

                // Fetch recommended caregivers for this client
                fetchRecommendedCaregivers(clientId);

                // Populate client fields
                form.setData(
                    'emergency_instructions',
                    data.client.emergency_instructions || '',
                );
                form.setData(
                    'special_needs_notes',
                    data.client.special_needs_notes || '',
                );
                form.setData(
                    'sitter_preferences',
                    data.client.sitter_preferences || [],
                );
                form.setData(
                    'other_adults_present',
                    data.client.other_adults_present || '',
                );
                form.setData(
                    'how_did_you_hear',
                    data.client.how_did_you_hear || '',
                );
            } catch (error) {
                console.error('Error fetching client data:', error);
            }
        } else {
            setClientAddresses([]);
            setClientChildren([]);
            setClientPets([]);
            setSelectedClientType(null);
            setCaregiverSuggestions(
                caregivers as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
        }
    };

    const fetchRecommendedCaregivers = async (clientId: number) => {
        try {
            const params = new URLSearchParams({
                client_id: clientId.toString(),
            });

            // Add booking context if available
            if (form.data.service_type) {
                params.append('service_type', form.data.service_type);
            }

            if (form.data.start_datetime) {
                params.append('start_datetime', form.data.start_datetime);
            }

            if (form.data.end_datetime) {
                params.append('end_datetime', form.data.end_datetime);
            }

            const response = await fetch(
                `/bookings/recommended-caregivers?${params}`,
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();

            setCaregiverSuggestions(
                data as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
        } catch (error) {
            console.error('Error fetching recommended caregivers:', error);
            // Fallback to default list
            setCaregiverSuggestions(
                caregivers as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
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
            sitter_preferences: [],
            other_adults_present: '',
            emergency_instructions: '',
            special_needs_notes: '',
            requires_payment: true,
            status: 'received',
            payment_status: 'pending',
            rental_platform: null,
            address_line1: '',
            address_line2: '',
            address_city: '',
            address_state: '',
            address_zip: '',
            new_client: {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                client_type: 'vacationer',
            },
            new_children: [],
            new_pets: [],
            deleted_child_ids: [],
            deleted_pet_ids: [],
            save_children_pets_to_profile: true,
        });
        setClientAddresses([]);
        setClientChildren([]);
        setClientPets([]);
        setNewChildren([]);
        setNewPets([]);
        setDeletedChildIds([]);
        setDeletedPetIds([]);
        setAddressMode('select');
        setClientMode('select');
        setIsAddressLocked(false);
        setShowManualAddressInput(false);
        setAddressValue('');
        setSelectedClientName('');
        setSelectedHotelName('');
        setSelectedCaregiverName('');
        setIsSheetOpen(true);
    };

    const openEditSheet = (booking: Booking) => {
        setEditingBooking(booking);

        const formData = {
            client_id: booking.client_id,
            service_type: booking.service_type,
            location_type: booking.location_type,
            start_datetime: formatDateTimeLocal(new Date(booking.start_datetime)),
            end_datetime: formatDateTimeLocal(new Date(booking.end_datetime)),
            hotel_id: booking.hotel_id,
            address_id: booking.address_id,
            caregiver_id: booking.caregiver_id,
            special_considerations: booking.special_considerations || [],
            caregiver_notes: booking.caregiver_notes || '',
            notes_to_sitterwise: booking.notes_to_sitterwise || '',
            admin_notes: booking.admin_notes || '',
            corporate_id: booking.corporate_id || '',
            sitter_preferences: booking.sitter_preferences || [],
            other_adults_present: booking.other_adults_present || '',
            emergency_instructions: booking.emergency_instructions || '',
            special_needs_notes: booking.special_needs_notes || '',
            how_did_you_hear: booking.how_did_you_hear || '',
            requires_payment: booking.requires_payment,
            status: booking.status,
            payment_status: booking.payment_status,
            rental_platform: booking.rental_platform || null,
            address_line1: booking.address_line1 || '',
            address_line2: booking.address_line2 || '',
            address_city: booking.address_city || '',
            address_state: booking.address_state || '',
            address_zip: booking.address_zip || '',
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
        };

        form.setData(formData);

        const isPrivateHome = booking.location_type === 'private_home';
        const hasAddressId = !!booking.address_id;
        const hasDirectAddress = !!booking.address_line1;

        if (isPrivateHome && hasAddressId) {
            setShowManualAddressInput(false);
            setAddressMode('select');
        } else if (hasDirectAddress) {
            setShowManualAddressInput(true);
            setAddressMode('input');
        }

        if (hasDirectAddress) {
            setAddressValue(
                `${booking.address_line1}${
                    booking.address_line2 ? `, ${booking.address_line2}` : ''
                }, ${booking.address_city || ''}, ${booking.address_state || ''} ${booking.address_zip || ''}`.trim(),
            );
            setIsAddressLocked(true);
        } else {
            setAddressValue('');
            setIsAddressLocked(false);
        }

        if (booking.client_id) {
            const client = clients.find((c) => c.id === booking.client_id);

            if (client) {
                setSelectedClientName(client.name);
                handleClientSearch(client.name);
            }

            // Fetch client specific data without overriding form.setData for client_id/address_id
            fetchClientDataOnly(booking.client_id);
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

    const fetchClientDataOnly = async (clientId: number) => {
        try {
            const response = await fetch(`/clients/${clientId}/data`);
            const data = await response.json();
            setClientAddresses(data.client.addresses || []);
            setClientChildren(data.client.children || []);
            setClientPets(data.client.pets || []);
            setSelectedClientType(data.client.client_type || null);
            fetchRecommendedCaregivers(clientId);
        } catch (error) {
            console.error('Error fetching client data:', error);
        }
    };

    const handleSubmit = () => {
        // Validate start/end datetime
        const start = form.data.start_datetime;
        const end = form.data.end_datetime;

        if (!start || !end) {
            return; // Inline error will show via useEffect
        }

        const startDate = new Date(start);
        const endDate = new Date(end);
        const now = new Date();

        if (startDate < now) {
            return; // Inline error will show
        }

        if (endDate <= startDate) {
            return; // Inline error will show
        }

        const diffMs = endDate.getTime() - startDate.getTime();
        const diffHours = diffMs / (1000 * 60 * 60);

        if (diffHours < 4) {
            return; // Inline error will show
        }

        form.setData('new_children', newChildren);
        form.setData('new_pets', newPets);
        form.setData('deleted_child_ids', deletedChildIds);
        form.setData('deleted_pet_ids', deletedPetIds);
        form.setData(
            'save_children_pets_to_profile',
            saveChildrenPetsToProfile,
        );

        if (editingBooking) {
            form.put(`/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    setMessage({ type: 'error', content: errorMessage });
                },
            });
        } else {
            form.post('/bookings', {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    // applyFilters();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    setMessage({ type: 'error', content: errorMessage });
                },
            });
        }
    };

    const handleDelete = () => {
        if (
            editingBooking &&
            confirm('Are you sure you want to delete this booking?')
        ) {
            form.delete(`/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    applyFilters();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    setMessage({ type: 'error', content: errorMessage });
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
            <ToasterMessage message={message} />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Bookings
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {currentMonthBookings.length} bookings this month
                        </p>
                    </div>
                    <Button onClick={() => openCreateSheet()}>
                        Create Booking
                    </Button>
                </div>

                <div className="flex items-center gap-4">
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
                    <Button onClick={applyFilters}>Filter</Button>
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

                <div className="border border-border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <button
                            onClick={prevMonth}
                            className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronLeft className="h-4 w-4" />
                        </button>
                        <h2 className="text-lg font-semibold text-foreground">
                            {monthNames[currentMonth - 1]} {currentYear}
                        </h2>
                        <button
                            onClick={nextMonth}
                            className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                        >
                            <ChevronRight className="h-4 w-4" />
                        </button>
                    </div>

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
                            const dayBookings = bookingsByDate[dateStr] || [];
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
                                    className={`flex min-h-32 flex-col gap-1 border p-2 ${
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
                                        const colors =
                                            statusColors[statusKey] ||
                                            statusColors.received;
                                        const ServiceIcon =
                                            serviceTypeIcons[
                                                booking.service_type
                                            ] || CalendarIcon;
                                        const canCharge =
                                            (statusKey === 'completed' ||
                                                statusKey === 'pending') &&
                                            booking.payment_status !== 'paid';

                                        return (
                                            <div
                                                key={booking.id}
                                                className="group relative"
                                            >
                                                <button
                                                    onClick={() =>
                                                        openEditSheet(booking)
                                                    }
                                                    className={`flex w-full cursor-pointer items-center gap-1 rounded-[3px] border px-1 py-0.5 text-xs ${
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
                                                {canCharge && (
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
                                                openCreateSheet(dateStr)
                                            }
                                            className="text-xs font-medium text-ring hover:text-foreground"
                                        >
                                            + {remainingCount} more
                                        </button>
                                    )}
                                    {isTodayOrFuture && remainingCount <= 0 && (
                                        <button
                                            onClick={() =>
                                                openCreateSheet(dateStr)
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

                        <div className="space-y-4 px-4">
                            <PersonalInfoSection
                                form={form}
                                editingBooking={editingBooking}
                                clientMode={clientMode}
                                setClientMode={setClientMode}
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
                                selectedClientType={selectedClientType}
                                location_types={location_types}
                                sitter_preference_options={
                                    sitter_preference_options
                                }
                                client_type_options={client_type_options}
                                booking_attributes={booking_attributes}
                                hotels={hotels}
                                hotelSuggestions={hotelSuggestions}
                                selectedHotelName={selectedHotelName}
                                handleHotelSearch={handleHotelSearch}
                                calculateAge={calculateAge}
                                isAddressLocked={isAddressLocked}
                                setIsAddressLocked={setIsAddressLocked}
                                showManualAddressInput={showManualAddressInput}
                                setShowManualAddressInput={
                                    setShowManualAddressInput
                                }
                                addressValue={addressValue}
                                setAddressValue={setAddressValue}
                                caregiverSuggestions={caregiverSuggestions}
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
