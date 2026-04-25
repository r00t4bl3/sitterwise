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
    Grid3X3,
    List,
} from 'lucide-react';
import { useState, useMemo, useEffect } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { ButtonGroup } from '@/components/ui/button-group';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayTime, parseAsLocal } from '@/lib/datetime';
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
        sitter_preferences,
    } = usePage<Props>().props;

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
    const [sheetMode, setSheetMode] = useState<'create' | 'edit' | 'duplicate'>(
        'create',
    );
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
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
        child_ids: number[];
        pet_ids: number[];
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
        child_ids: [],
        pet_ids: [],
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
            const date = parseAsLocal(booking.start_datetime);

            if (!date) {
                return;
            }

            const localDate = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`;

            if (!grouped[localDate]) {
                grouped[localDate] = [];
            }

            grouped[localDate].push(booking);
        });

        return grouped;
    }, [bookings]);

    const currentMonthBookings = useMemo(() => {
        return bookings.filter((booking) => {
            const startDate = parseAsLocal(booking.start_datetime);

            if (!startDate) {
                return false;
            }

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
        form.setData((prev) => ({
            ...prev,
            client_id: clientId,
            address_id: null,
            child_ids: [],
            pet_ids: [],
        }));
        setAddressMode('select');
        setClientMode('select');

        if (clientId) {
            const data = await fetchClientDataOnly(clientId);

            if (data) {
                const clientType = data.client.client_type;
                let locationType = form.data.location_type;

                if (clientType === 'resident') {
                    locationType = 'private_home';
                } else if (clientType === 'vacationer') {
                    locationType = 'hotel';
                }

                form.setData((prev) => ({
                    ...prev,
                    child_ids:
                        data.client.children?.map((c: any) => c.id) || [],
                    pet_ids: data.client.pets?.map((p: any) => p.id) || [],
                    location_type: locationType,
                    emergency_instructions:
                        data.client.emergency_instructions || '',
                    special_needs_notes: data.client.special_needs_notes || '',
                    sitter_preferences: data.client.sitter_preferences || [],
                    other_adults_present:
                        data.client.other_adults_present || '',
                    how_did_you_hear: data.client.how_did_you_hear || '',
                }));
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
        setSheetMode('create');

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
            child_ids: [],
            pet_ids: [],
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
        // Reset suggestions to prevent auto-focus
        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);
        setIsSheetOpen(true);
    };

    const openEditSheet = async (booking: Booking) => {
        setEditingBooking(booking);
        setSheetMode('edit');

        const matchedChildIds: number[] = [];
        const unmatchedChildren: any[] = [];
        const matchedPetIds: number[] = [];
        const unmatchedPets: any[] = [];

        // Use booking's existing children/pets directly - no need to fetch profile
        if (booking.children) {
            booking.children.forEach((snapChild) => {
                unmatchedChildren.push({
                    tempId: `snap-${Math.random().toString(36).substr(2, 9)}`,
                    ...snapChild,
                });
            });
        }

        if (booking.pets) {
            booking.pets.forEach((snapPet) => {
                unmatchedPets.push({
                    tempId: `snap-${Math.random().toString(36).substr(2, 9)}`,
                    ...snapPet,
                });
            });
        }

        const client = clients.find((c) => c.id === booking.client_id);

        if (client) {
            setSelectedClientName(client.name);
            setClientSuggestions([client] as unknown as Array<{
                id: number;
                name: string;
                [key: string]: unknown;
            }>);
        }

        const formData = {
            client_id: booking.client_id,
            service_type: booking.service_type,
            location_type: booking.location_type,
            start_datetime: formatDateTimeLocal(
                parseAsLocal(booking.start_datetime) as Date,
            ),
            end_datetime: formatDateTimeLocal(
                parseAsLocal(booking.end_datetime) as Date,
            ),
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
            new_children: unmatchedChildren,
            new_pets: unmatchedPets,
            child_ids: matchedChildIds,
            pet_ids: matchedPetIds,
            deleted_child_ids: [],
            deleted_pet_ids: [],
            save_children_pets_to_profile: true,
        };

        form.setData(formData);
        setNewChildren(unmatchedChildren);
        setNewPets(unmatchedPets);

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

        if (booking.hotel_id) {
            const hotel = hotels.find((h) => h.id === booking.hotel_id);

            if (hotel) {
                setSelectedHotelName(hotel.name);
                setHotelSuggestions([hotel] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }
        }

        if (booking.caregiver_id) {
            const caregiver = caregivers.find(
                (c) => c.id === booking.caregiver_id,
            );

            if (caregiver) {
                setSelectedCaregiverName(caregiver.name);
                setCaregiverSuggestions([caregiver] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }
        }

        // Reset suggestions to prevent auto-focus
        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);

        setIsSheetOpen(true);
    };

    const openDuplicateSheet = async (booking: Booking) => {
        setEditingBooking(null);
        setSheetMode('duplicate');

        let clientChildren: any[] = [];
        let clientPets: any[] = [];

        // Fetch latest client data if client exists
        if (booking.client_id) {
            const data = await fetchClientDataOnly(booking.client_id);

            if (data && data.client) {
                // Use client PROFILE data (latest) for children/pets
                const profileChildren = data.client.children || [];
                const profilePets = data.client.pets || [];

                // Convert client children to form format
                clientChildren = profileChildren.map((c: any) => ({
                    tempId: `new-${Date.now()}-${Math.random()}`,
                    name: c.name || '',
                    gender: c.gender || '',
                    birth_month: String(c.birth_month || ''),
                    birth_year: String(c.birth_year || ''),
                }));

                // Convert client pets to form format
                clientPets = profilePets.map((p: any) => ({
                    tempId: `new-${Date.now()}-${Math.random()}`,
                    name: p.name || '',
                    type: p.type || '',
                    breed: p.breed || '',
                    notes: p.notes || '',
                }));

                const client = clients.find((c) => c.id === booking.client_id);

                if (client) {
                    setSelectedClientName(client.name);
                    setClientSuggestions([client] as unknown as Array<{
                        id: number;
                        name: string;
                        [key: string]: unknown;
                    }>);
                }
            }
        }

        const formData = {
            client_id: booking.client_id,
            service_type: 'babysitter', // Reset - user needs to re-select
            location_type: booking.location_type,
            start_datetime: '',
            end_datetime: '',
            hotel_id: booking.hotel_id,
            address_id: booking.address_id,
            caregiver_id: null,
            special_considerations: [], // Reset
            caregiver_notes: '',
            notes_to_sitterwise: '',
            admin_notes: '',
            corporate_id: booking.corporate_id || '',
            sitter_preferences: data?.client?.sitter_preferences || [], // From client profile
            other_adults_present: data?.client?.other_adults_present || '', // From client profile
            emergency_instructions: '', // Reset (use from profile if needed)
            special_needs_notes: '', // Reset
            how_did_you_hear: data?.client?.how_did_you_hear || '', // From client profile
            requires_payment: booking.requires_payment,
            status: 'received', // Reset
            payment_status: 'pending', // Reset
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
            new_children: clientChildren, // From client PROFILE
            new_pets: clientPets, // From client PROFILE
            child_ids: [],
            pet_ids: [],
            deleted_child_ids: [],
            deleted_pet_ids: [],
            save_children_pets_to_profile: true, // Default to TRUE as confirmed
        };

        form.setData(formData);
        setNewChildren(clientChildren);
        setNewPets(clientPets);

        if (booking.hotel_id) {
            const hotel = hotels.find((h) => h.id === booking.hotel_id);

            if (hotel) {
                setSelectedHotelName(hotel.name);
                setHotelSuggestions([hotel] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }
        }

        setSelectedCaregiverName('');
        setCaregiverSuggestions([]);

        setAddressMode('select');
        setClientMode('select');
        setIsAddressLocked(false);
        setShowManualAddressInput(false);
        setAddressValue('');

        // Reset suggestions to prevent auto-focus
        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);

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

            return data;
        } catch (error) {
            console.error('Error fetching client data:', error);

            return null;
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

        // Submit with new children/pets data - backend handles sync to profile when checkbox is checked
        form.transform((data) => ({
            ...data,
            new_children: newChildren,
            new_pets: newPets,
            save_children_pets_to_profile: saveChildrenPetsToProfile,
        }));

        if (editingBooking) {
            form.put(`/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    console.log('Error updating booking:', errorMessage);
                },
            });
        } else {
            form.post('/bookings', {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    console.log('Error creating booking:', errorMessage);
                },
            });
        }
    };

    const handleDelete = () => {
        if (editingBooking) {
            setShowDeleteDialog(true);
        }
    };

    const handleConfirmDelete = () => {
        if (editingBooking) {
            form.delete(`/bookings/${editingBooking.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    setShowDeleteDialog(false);
                    applyFilters();
                },
                onError: (errors) => {
                    const errorMessage = Object.values(errors).join(', ');
                    console.log('Error deleting booking:', errorMessage);
                },
            });
        }
    };

    const handleCancelDelete = () => {
        setShowDeleteDialog(false);
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
            form.setData(
                'child_ids',
                form.data.child_ids.filter((childId: number) => childId !== id),
            );
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
            form.setData(
                'pet_ids',
                form.data.pet_ids.filter((petId: number) => petId !== id),
            );
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
                            {currentMonthBookings.length} bookings this month
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
                        <Button onClick={() => openCreateSheet()}>
                            Create Booking
                        </Button>
                    </div>
                </div>

                <div className="flex items-center justify-between gap-4">
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

                    {!isTableView && (
                        <div className="flex flex-wrap gap-3 text-xs">
                            {booking_statuses.map((status) => (
                                <div
                                    key={status.value}
                                    className="flex items-center gap-1.5"
                                >
                                    <span
                                        className={`inline-block h-3 w-3 rounded-[2px] border ${
                                            status.colors.bg
                                        } ${status.colors.border}`}
                                    />
                                    <span className="text-muted-foreground">
                                        {status.label}
                                    </span>
                                </div>
                            ))}
                        </div>
                    )}
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
                                    (a, b) =>
                                        new Date(a.start_datetime).getTime() -
                                        new Date(b.start_datetime).getTime(),
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
                                                    booking.service_type
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
                                                            openEditSheet(
                                                                booking,
                                                            )
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
                                                            {formatDisplayTime(
                                                                booking.start_datetime,
                                                            )}
                                                            -
                                                            {formatDisplayTime(
                                                                booking.end_datetime,
                                                            )}
                                                        </span>{' '}
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
                                        {isTodayOrFuture &&
                                            remainingCount <= 0 && (
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
                    ) : (
                        <div className="-mx-4 -mb-4 overflow-x-auto">
                            <table className="w-full text-left">
                                <thead>
                                    <tr className="bg-foreground">
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
                                <tbody className="bg-background">
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
                                                (a, b) =>
                                                    new Date(
                                                        a.start_datetime,
                                                    ).getTime() -
                                                    new Date(
                                                        b.start_datetime,
                                                    ).getTime(),
                                            )
                                            .map((booking) => {
                                                const statusKey =
                                                    booking.status?.toLowerCase() ||
                                                    'received';
                                                const statusObj =
                                                    booking_statuses.find(
                                                        (s) =>
                                                            s.value ===
                                                            statusKey,
                                                    ) ||
                                                    booking_statuses.find(
                                                        (s) =>
                                                            s.value ===
                                                            'received',
                                                    );
                                                const colors =
                                                    statusObj?.colors || {
                                                        bg: 'bg-blue-100',
                                                        text: 'text-blue-800',
                                                        border: 'border-blue-300',
                                                    };
                                                const isHotel =
                                                    booking.location_type ===
                                                    'hotel';
                                                const hotel = isHotel
                                                    ? hotels.find(
                                                          (h) =>
                                                              h.id ===
                                                              booking.hotel_id,
                                                      )
                                                    : null;
                                                const location = isHotel
                                                    ? hotel?.name
                                                    : booking.address_line1;
                                                const addressQuery =
                                                    isHotel && hotel
                                                        ? `${hotel.line1 || ''} ${hotel.line2 || ''} ${hotel.city || ''} ${hotel.state || ''} ${hotel.zip || ''}`.trim()
                                                        : `${booking.address_line1 || ''} ${booking.address_line2 || ''} ${booking.address_city || ''} ${booking.address_state || ''} ${booking.address_zip || ''}`.trim();
                                                const mapsUrl = addressQuery
                                                    ? `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(addressQuery)}`
                                                    : null;

                                                return (
                                                    <tr
                                                        key={booking.id}
                                                        className="border-b border-border transition hover:bg-blush"
                                                    >
                                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                                            {parseAsLocal(
                                                                booking.start_datetime,
                                                            )?.toLocaleDateString(
                                                                'en-US',
                                                                {
                                                                    month: 'short',
                                                                    day: 'numeric',
                                                                    year: 'numeric',
                                                                },
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3 text-sm font-medium text-ring">
                                                            {
                                                                booking.client
                                                                    .first_name
                                                            }{' '}
                                                            {
                                                                booking.client
                                                                    .last_name
                                                            }
                                                        </td>
                                                        <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                                            {formatDisplayTime(
                                                                booking.start_datetime,
                                                            )}{' '}
                                                            -{' '}
                                                            {formatDisplayTime(
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
                                                        <td className="px-4 py-3 text-sm text-foreground">
                                                            {booking.caregiver ? (
                                                                `${booking.caregiver.first_name} ${booking.caregiver.last_name}`
                                                            ) : (
                                                                <span className="text-muted-foreground italic">
                                                                    Unassigned
                                                                </span>
                                                            )}
                                                        </td>
                                                        <td className="px-4 py-3">
                                                            <span
                                                                className={`inline-flex items-center rounded-[3px] border px-2 py-0.5 text-[10px] font-semibold ${colors.bg} ${colors.text} ${colors.border}`}
                                                            >
                                                                {statusObj?.label ||
                                                                    statusKey}
                                                            </span>
                                                        </td>
                                                        <td className="px-4 py-3 text-right">
                                                            <div className="flex justify-end gap-2">
                                                                {(statusKey ===
                                                                    'completed' ||
                                                                    statusKey ===
                                                                        'pending') &&
                                                                    booking.payment_status !==
                                                                        'paid' && (
                                                                        <Button
                                                                            variant="ghost"
                                                                            size="icon"
                                                                            className="h-8 w-8 text-green-600 hover:bg-green-50 hover:text-green-700"
                                                                            onClick={() =>
                                                                                (window.location.href =
                                                                                    '/admin/bookings/charge?booking_id=' +
                                                                                    booking.id)
                                                                            }
                                                                            title="Charge"
                                                                        >
                                                                            <CreditCard className="h-4 w-4" />
                                                                        </Button>
                                                                    )}
                                                                <Button
                                                                    onClick={() =>
                                                                        openDuplicateSheet(
                                                                            booking,
                                                                        )
                                                                    }
                                                                    variant="outline"
                                                                >
                                                                    Duplicate
                                                                </Button>
                                                                <Button
                                                                    onClick={() =>
                                                                        openEditSheet(
                                                                            booking,
                                                                        )
                                                                    }
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

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent
                        side="right"
                        className="w-full overflow-y-auto sm:max-w-2xl"
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {sheetMode === 'edit' && 'Edit Booking'}
                                {sheetMode === 'duplicate' &&
                                    'Duplicate Booking'}
                                {sheetMode === 'create' && 'Create Booking'}
                            </SheetTitle>
                            <SheetDescription>
                                {sheetMode === 'edit' &&
                                    'Update booking details below.'}
                                {sheetMode === 'duplicate' &&
                                    'Create a copy of this booking.'}
                                {sheetMode === 'create' &&
                                    'Fill in the details to create a new booking.'}
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
                                sitter_preference_options={sitter_preferences}
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

                <Dialog
                    open={showDeleteDialog}
                    onOpenChange={setShowDeleteDialog}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Confirm Delete</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this booking?
                                This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={handleCancelDelete}
                            >
                                Cancel
                            </Button>
                            <Button
                                onClick={handleConfirmDelete}
                                disabled={form.processing}
                            >
                                {form.processing ? 'Deleting...' : 'Delete'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>
            </div>
        </AppLayout>
    );
}
