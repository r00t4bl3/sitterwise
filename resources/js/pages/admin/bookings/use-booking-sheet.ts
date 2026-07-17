import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { formatUtcStringFromPt, todayInPT } from '@/lib/datetime';
import type { Booking } from './types';

export interface UseBookingSheetProps {
    clients: Array<{ id: number; name: string; [key: string]: unknown }>;
    hotels: Array<{
        id: number;
        name: string;
        line1: string | null;
        line2: string | null;
        city: string | null;
        state: string | null;
        zip: string | null;
    }>;
    caregivers: Array<{ id: number; name: string; [key: string]: unknown }>;
    service_types: Array<{
        value: string;
        label: string;
        requires_payment_method?: boolean;
    }>;
    location_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{
        value: string;
        label: string;
        colors: { bg: string; text: string; border: string };
    }>;
    payment_statuses: Array<{ value: string; label: string }>;
    booking_attributes: Array<{
        id: number;
        name: string;
        slug: string;
        type: string;
        options: string[];
    }>;
    sitter_preferences: Array<{ value: string; label: string }>;
    client_types: Array<{ value: string; label: string }>;
    discovery_sources: Array<{ value: string; label: string }>;
    pet_types: Array<{ value: string; label: string }>;
}

interface ClientAddress {
    id: number;
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
}

type SheetMode = 'create' | 'edit' | 'duplicate';

interface FormData {
    client_id: number | null;
    service_type: string;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    dates: Array<{ start_datetime: string; end_datetime: string }>;
    hotel_id: number | null;
    hotel_name: string | null;
    address_id: number | null;
    caregiver_id: number | null;
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
    save_children_pets_to_profile: boolean;
    children_notes: string;
}

type UseBookingSheetReturn = ReturnType<typeof useBookingSheet>;

export function useBookingSheet({
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
}: UseBookingSheetProps) {
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const [editingBooking, setEditingBooking] = useState<Booking | null>(null);
    const [sheetMode, setSheetMode] = useState<SheetMode>('create');
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);
    const [showPastBookingDialog, setShowPastBookingDialog] = useState(false);
    const [showNoPaymentDialog, setShowNoPaymentDialog] = useState(false);
    const [pendingNotifyAfter, setPendingNotifyAfter] = useState(false);
    const [
        selectedClientHasPaymentCapability,
        setSelectedClientHasPaymentCapability,
    ] = useState(true);

    const [clientSuggestions, setClientSuggestions] = useState<
        Array<{ id: number; name: string; [key: string]: unknown }>
    >([]);
    const [hotelSuggestions, setHotelSuggestions] = useState<
        Array<{ id: number; name: string; [key: string]: unknown }>
    >([]);
    const [caregiverSuggestions, setCaregiverSuggestions] = useState<
        Array<{
            id: number;
            name: string;
            age: number | null;
            matchIcons: string[];
            hasBeenNotified: boolean;
            [key: string]: unknown;
        }>
    >([]);
    const [caregiverAllIds, setCaregiverAllIds] = useState<number[]>([]);
    const [caregiverTotal, setCaregiverTotal] = useState(0);
    const [caregiverCurrentPage, setCaregiverCurrentPage] = useState(1);
    const [caregiverLastPage, setCaregiverLastPage] = useState(1);
    const [
        loadingCaregiverRecommendations,
        setLoadingCaregiverRecommendations,
    ] = useState(false);
    const [loadingMoreCaregivers, setLoadingMoreCaregivers] = useState(false);
    const [caregiverSearch, setCaregiverSearch] = useState('');
    const [caregiverSpanishOnly, setCaregiverSpanishOnly] = useState(false);
    const [clientAddresses, setClientAddresses] = useState<ClientAddress[]>([]);
    const [bookingChildren, setBookingChildren] = useState<
        Array<{
            tempId: string;
            name: string;
            gender: string;
            birth_month: string;
            birth_year: string;
        }>
    >([]);
    const [bookingPets, setBookingPets] = useState<
        Array<{
            tempId: string;
            name: string;
            type: string;
            breed: string;
            notes: string;
        }>
    >([]);
    const [, setAddressMode] = useState<'select' | 'input'>('select');
    const [clientMode, setClientMode] = useState<'select' | 'input'>('select');
    const [selectedClientType, setSelectedClientType] = useState<string | null>(
        null,
    );
    const [loadingSuggestions, setLoadingSuggestions] = useState(false);

    const [selectedClientName, setSelectedClientName] = useState('');
    const [selectedHotelName, setSelectedHotelName] = useState('');
    const [selectedCaregiverName, setSelectedCaregiverName] = useState('');
    const [isAddressLocked, setIsAddressLocked] = useState(false);
    const [showManualAddressInput, setShowManualAddressInput] = useState(false);
    const [addressValue, setAddressValue] = useState('');

    const [saveChildrenPetsToProfile, setSaveChildrenPetsToProfile] =
        useState(true);

    const form = useForm<FormData>({
        client_id: null,
        service_type: 'babysitter',
        location_type: 'private_home',
        start_datetime: '',
        end_datetime: '',
        dates: [],
        hotel_id: null,
        hotel_name: '',
        address_id: null,
        caregiver_id: null,
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
        save_children_pets_to_profile: true,
        children_notes: '',
    });

    const populateCaregiverSuggestions = async (
        page = 1,
        ageFilter = 'all',
        search = '',
        spanishOnly = caregiverSpanishOnly,
    ) => {
        const clientId =
            editingBooking?.booking_group?.client_id ?? form.data.client_id;

        if (!clientId) {
            return;
        }

        if (page === 1) {
            setLoadingCaregiverRecommendations(true);
        }

        try {
            const params = new URLSearchParams({
                client_id: clientId.toString(),
                page: page.toString(),
                per_page: '20',
                age_filter: ageFilter,
            });
            params.append('search', search);

            if (spanishOnly) {
                params.append('spanish_only', '1');
            }

            if (editingBooking?.id) {
                params.append('booking_id', editingBooking.id.toString());
            }

            if (form.data.service_type) {
                params.append('service_type', form.data.service_type);
            }

            if (form.data.start_datetime) {
                params.append('start_datetime', form.data.start_datetime);
            }

            if (form.data.end_datetime) {
                params.append('end_datetime', form.data.end_datetime);
            }

            const addressCity =
                editingBooking?.booking_group?.address_city ||
                form.data.address_city;

            if (addressCity) {
                params.append('address_city', addressCity);
            }

            const response = await fetch(
                `/bookings/recommended-caregivers?${params}`,
            );
            const json = await response.json();
            const data = json.data as unknown as Array<{
                id: number;
                name: string;
                age: number | null;

                matchIcons: string[];
                hasBeenNotified: boolean;
                [key: string]: unknown;
            }>;

            if (page === 1) {
                setCaregiverSuggestions(data);
                setCaregiverAllIds(json.all_ids as number[]);
            } else {
                setCaregiverSuggestions((prev) => [...prev, ...data]);
            }

            setCaregiverTotal(json.meta.total as number);
            setCaregiverCurrentPage(json.meta.current_page as number);
            setCaregiverLastPage(json.meta.last_page as number);
        } catch (error) {
            console.error('Error fetching recommended caregivers:', error);

            if (page === 1) {
                setCaregiverSuggestions(
                    caregivers.map((c) => ({
                        id: c.id,
                        name: c.name,
                        age: null,
                        matchIcons: [],
                        hasBeenNotified: false,
                    })) as unknown as Array<{
                        id: number;
                        name: string;
                        age: number | null;

                        matchIcons: string[];
                        hasBeenNotified: boolean;
                        [key: string]: unknown;
                    }>,
                );
            }
        } finally {
            if (page === 1) {
                setLoadingCaregiverRecommendations(false);
            }
        }
    };

    const fetchRecommendedCaregivers = async (clientId: number) => {
        try {
            const params = new URLSearchParams({
                client_id: clientId.toString(),
                page: '1',
                per_page: '20',
                age_filter: 'all',
            });

            if (form.data.service_type) {
                params.append('service_type', form.data.service_type);
            }

            if (form.data.start_datetime) {
                params.append('start_datetime', form.data.start_datetime);
            }

            if (form.data.end_datetime) {
                params.append('end_datetime', form.data.end_datetime);
            }

            if (form.data.address_city) {
                params.append('address_city', form.data.address_city);
            }

            const response = await fetch(
                `/bookings/recommended-caregivers?${params}`,
            );

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const json = await response.json();
            const data = json.data as unknown as Array<{
                id: number;
                name: string;
                age: number | null;

                matchIcons: string[];
                hasBeenNotified: boolean;
                [key: string]: unknown;
            }>;

            setCaregiverSuggestions(data);
            setCaregiverAllIds(json.all_ids as number[]);
            setCaregiverTotal(json.meta.total as number);
            setCaregiverCurrentPage(json.meta.current_page as number);
            setCaregiverLastPage(json.meta.last_page as number);
        } catch (error) {
            console.error('Error fetching recommended caregivers:', error);
            setCaregiverSuggestions(
                caregivers.map((c) => ({
                    id: c.id,
                    name: c.name,
                    age: null,
                    matchIcons: [],
                    hasBeenNotified: false,
                })) as unknown as Array<{
                    id: number;
                    name: string;
                    age: number | null;

                    matchIcons: string[];
                    hasBeenNotified: boolean;
                    [key: string]: unknown;
                }>,
            );
        }
    };

    const loadMoreCaregivers = async (ageFilter = 'all') => {
        if (
            loadingMoreCaregivers ||
            caregiverCurrentPage >= caregiverLastPage
        ) {
            return;
        }

        setLoadingMoreCaregivers(true);
        await populateCaregiverSuggestions(
            caregiverCurrentPage + 1,
            ageFilter,
            caregiverSearch,
        );
        setLoadingMoreCaregivers(false);
    };

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
                caregivers.map((c) => ({
                    id: c.id,
                    name: c.name,
                    age: null,
                    matchIcons: [],
                    hasBeenNotified: false,
                })) as unknown as Array<{
                    id: number;
                    name: string;
                    age: number | null;

                    matchIcons: string[];
                    hasBeenNotified: boolean;
                    [key: string]: unknown;
                }>,
            );

            return;
        }

        const filtered = caregivers.filter((c) =>
            c.name.toLowerCase().includes(query.toLowerCase()),
        );
        setCaregiverSuggestions(
            filtered.map((c) => ({
                id: c.id,
                name: c.name,
                age: null,
                matchIcons: [],
                hasBeenNotified: false,
            })) as unknown as Array<{
                id: number;
                name: string;
                age: number | null;
                tier: number;
                tierLabel: string;
                matchIcons: string[];
                hasBeenNotified: boolean;
                [key: string]: unknown;
            }>,
        );
    };

    const fetchClientDataOnly = async (
        clientId: number,
        skipCaregiverFetch = false,
    ) => {
        try {
            const response = await fetch(`/clients/${clientId}/data`);
            const data = await response.json();
            setClientAddresses(data.client.addresses || []);
            setSelectedClientType(data.client.client_type || null);
            setSelectedClientHasPaymentCapability(
                !!data.client.has_payment_capability,
            );

            if (!skipCaregiverFetch) {
                fetchRecommendedCaregivers(clientId);
            }

            return data;
        } catch (error) {
            console.error('Error fetching client data:', error);

            return null;
        }
    };

    const handleClientChange = async (clientId: number | null) => {
        // Re-pointing an existing booking to a different client moves EVERY
        // booking in its group and swaps the family details in the form.
        // Require explicit confirmation and never auto-sync the (new) family
        // data back to a profile as part of the same edit.
        const originalClientId = editingBooking?.booking_group?.client_id ?? null;

        if (
            sheetMode === 'edit' &&
            clientId !== null &&
            originalClientId !== null &&
            clientId !== originalClientId
        ) {
            const groupCount =
                editingBooking?.booking_group?.bookings_count ?? 1;
            const confirmed = window.confirm(
                `Change this booking's client?\n\nThis will move ${groupCount > 1 ? `ALL ${groupCount} bookings in this group` : 'this booking'} to the selected client, and the children/pets/address fields below will be replaced with the new client's profile data.`,
            );

            if (!confirmed) {
                return;
            }

            form.setData('save_children_pets_to_profile', false);
        }

        form.setData((prev) => ({
            ...prev,
            client_id: clientId,
            address_id: null,
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

                const clientChildren =
                    data.client.children?.map((c: any) => ({
                        tempId: `client-${c.id}`,
                        name: c.name || '',
                        gender: c.gender || '',
                        birth_month: c.birth_month ? String(c.birth_month) : '',
                        birth_year: c.birth_year ? String(c.birth_year) : '',
                    })) || [];
                const clientPets =
                    data.client.pets?.map((p: any) => ({
                        tempId: `client-${p.id}`,
                        name: p.name || '',
                        type: p.type?.toLowerCase() || '',
                        breed: p.breed || '',
                        notes: p.notes || '',
                    })) || [];

                setBookingChildren(clientChildren);
                setBookingPets(clientPets);

                form.setData((prev) => ({
                    ...prev,
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
            setSelectedClientType(null);
            setBookingChildren([]);
            setBookingPets([]);
            setCaregiverSuggestions(
                caregivers.map((c) => ({
                    id: c.id,
                    name: c.name,
                    age: null,
                    matchIcons: [],
                    hasBeenNotified: false,
                })) as unknown as Array<{
                    id: number;
                    name: string;
                    age: number | null;

                    matchIcons: string[];
                    hasBeenNotified: boolean;
                    [key: string]: unknown;
                }>,
            );
        }
    };

    const openCreateSheet = (date?: string) => {
        form.clearErrors();
        setEditingBooking(null);
        setSheetMode('create');
        setSelectedClientHasPaymentCapability(true);
        setShowNoPaymentDialog(false);

        // Build the default start/end as PT wall-clock (9:00 AM - 1:00 PM) so it
        // matches the DateTimePicker/backend UTC convention. formatUtcStringFromPt
        // reads the Date's local components as PT, so this stays correct no matter
        // what timezone the admin's browser is in.
        let defaultStart: Date;
        let defaultEnd: Date;

        if (date) {
            const [dy, dm, dd] = date.split('-').map(Number);
            defaultStart = new Date(dy, dm - 1, dd, 9, 0, 0, 0);
            defaultEnd = new Date(dy, dm - 1, dd, 13, 0, 0, 0);
        } else {
            const [y, m, d] = todayInPT().split('-').map(Number);
            defaultStart = new Date(y, m - 1, d + 1, 9, 0, 0, 0);
            defaultEnd = new Date(y, m - 1, d + 1, 13, 0, 0, 0);
        }

        form.setData({
            client_id: null,
            service_type: 'babysitter',
            location_type: 'private_home',
            start_datetime: formatUtcStringFromPt(defaultStart),
            end_datetime: formatUtcStringFromPt(defaultEnd),
            dates: [
                {
                    start_datetime: formatUtcStringFromPt(defaultStart),
                    end_datetime: formatUtcStringFromPt(defaultEnd),
                },
            ],
            hotel_id: null,
            hotel_name: '',
            address_id: null,
            caregiver_id: null,
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
            save_children_pets_to_profile: true,
            children_notes: '',
        });
        setClientAddresses([]);
        setBookingChildren([]);
        setBookingPets([]);
        setAddressMode('select');
        setClientMode('select');
        setIsAddressLocked(false);
        setShowManualAddressInput(false);
        setAddressValue('');
        setSelectedClientName('');
        setSelectedHotelName('');
        setSelectedCaregiverName('');
        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);
        setIsSheetOpen(true);
    };

    const proceedWithEditSheet = async (booking: Booking) => {
        form.clearErrors();
        setEditingBooking(booking);
        setSheetMode('edit');
        setIsLoading(true);
        setIsSheetOpen(true);

        try {
            const response = await fetch(`/bookings/${booking.id}`, {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
            });
            const fullBooking = await response.json();

            const client =
                clients?.find(
                    (c) => c.id === booking.booking_group?.client_id,
                ) ?? null;

            setSelectedClientName('');
            setClientSuggestions([]);

            if (client) {
                setSelectedClientName(client.name);
                setClientSuggestions([client] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }

            setSelectedClientType(null);

            if (fullBooking.client) {
                setSelectedClientType(fullBooking.client.client_type || null);
            }

            setClientAddresses([]);

            if (fullBooking.client?.addresses) {
                setClientAddresses(
                    fullBooking.client.addresses.map((addr: any) => ({
                        id: addr.id,
                        line1: addr.line1 || '',
                        line2: addr.line2 || null,
                        city: addr.city || '',
                        state: addr.state || '',
                        zip: addr.zip || '',
                    })),
                );
            }

            const hotel = hotels.find((h) => h.id === fullBooking.hotel_id);

            setSelectedHotelName('');
            setHotelSuggestions([]);

            if (hotel) {
                setSelectedHotelName(hotel.name);
                setHotelSuggestions([hotel] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            } else if (fullBooking.hotel) {
                setSelectedHotelName(fullBooking.hotel.name);
                setHotelSuggestions([fullBooking.hotel] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }

            const caregiver = caregivers.find(
                (cg) => cg.id === fullBooking.caregiver_id,
            );

            setSelectedCaregiverName('');
            setCaregiverSuggestions([]);

            if (caregiver) {
                setSelectedCaregiverName(caregiver.name);
                setCaregiverSuggestions([
                    {
                        ...caregiver,
                        age: null,
                        matchIcons: [],
                        hasBeenNotified: false,
                    },
                ] as unknown as Array<{
                    id: number;
                    name: string;
                    age: number | null;

                    matchIcons: string[];
                    hasBeenNotified: boolean;
                    [key: string]: unknown;
                }>);
            }

            form.setData({
                client_id: fullBooking.client_id,
                service_type: fullBooking.service_type,
                location_type: fullBooking.location_type,
                start_datetime: fullBooking.start_datetime,
                end_datetime: fullBooking.end_datetime,
                dates: [
                    {
                        start_datetime: fullBooking.start_datetime,
                        end_datetime: fullBooking.end_datetime,
                    },
                ],
                hotel_id: fullBooking.hotel_id,
                hotel_name: fullBooking.hotel_name || '',
                address_id: fullBooking.address_id,
                caregiver_id: fullBooking.caregiver_id,
                caregiver_notes: fullBooking.caregiver_notes || '',
                notes_to_sitterwise: fullBooking.notes_to_sitterwise || '',
                admin_notes: fullBooking.admin_notes || '',
                corporate_id: fullBooking.corporate_id || '',
                how_did_you_hear: fullBooking.how_did_you_hear || '',
                sitter_preferences: fullBooking.sitter_preferences || [],
                other_adults_present: fullBooking.other_adults_present || '',
                emergency_instructions:
                    fullBooking.emergency_instructions || '',
                special_needs_notes: fullBooking.special_needs_notes || '',
                requires_payment: fullBooking.requires_payment,
                status: fullBooking.status,
                payment_status: fullBooking.payment_status,
                rental_platform: fullBooking.rental_platform || null,
                address_line1: fullBooking.address_line1 || '',
                address_line2: fullBooking.address_line2 || '',
                address_city: fullBooking.address_city || '',
                address_state: fullBooking.address_state || '',
                address_zip: fullBooking.address_zip || '',
                new_client: {
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    client_type: 'individual',
                },
                new_children: [],
                new_pets: [],
                save_children_pets_to_profile: true,
                children_notes: fullBooking.children_notes || '',
            });

            setEditingBooking(fullBooking);
            setBookingChildren(
                fullBooking.children?.map((child: any, index: number) => ({
                    tempId: `existing-${index}`,
                    name: child.name || '',
                    gender: child.gender || '',
                    birth_month: child.birth_month
                        ? String(child.birth_month)
                        : '',
                    birth_year: child.birth_year
                        ? String(child.birth_year)
                        : '',
                })) || [],
            );
            setBookingPets(
                fullBooking.pets?.map((pet: any, index: number) => ({
                    tempId: `existing-${index}`,
                    name: pet.name || '',
                    type: pet.type?.toLowerCase() || '',
                    breed: pet.breed || '',
                    notes: pet.notes || '',
                })) || [],
            );

            const addressParts = [
                fullBooking.address_line1,
                fullBooking.address_line2,
                fullBooking.address_city,
                fullBooking.address_state,
                fullBooking.address_zip,
            ].filter(Boolean);
            setAddressValue(addressParts.join(', '));

            setIsAddressLocked(false);

            if (fullBooking.address_line1) {
                setIsAddressLocked(true);
            }

            setIsLoading(false);
        } catch (error) {
            console.error('Error fetching booking details:', error);
            setIsSheetOpen(false);
            setIsLoading(false);
        }
    };

    const openEditSheet = async (booking: Booking) => {
        const endDate = new Date(booking.end_datetime);

        if (endDate < new Date()) {
            setEditingBooking(booking);
            setShowPastBookingDialog(true);

            return;
        }

        await proceedWithEditSheet(booking);
    };

    const handleConfirmPastBooking = async () => {
        setShowPastBookingDialog(false);

        if (editingBooking) {
            await proceedWithEditSheet(editingBooking);
        }
    };

    const handleCancelPastBooking = () => {
        setShowPastBookingDialog(false);
        setEditingBooking(null);
    };

    const openDuplicateSheet = async (booking: Booking) => {
        form.clearErrors();
        setEditingBooking(null);
        setSheetMode('duplicate');
        setIsLoading(true);
        setIsSheetOpen(true);

        try {
            const response = await fetch(`/bookings/${booking.id}`, {
                headers: {
                    'Content-Type': 'application/json',
                    Accept: 'application/json',
                },
            });
            const fullBooking = await response.json();

            fullBooking.status = 'received';
            fullBooking.payment_status = 'pending';
            fullBooking.caregiver_id = null;
            fullBooking.caregiver_notes = '';
            fullBooking.notes_to_sitterwise = '';
            fullBooking.admin_notes = '';

            setEditingBooking(fullBooking);

            const clientChildren =
                fullBooking.children?.map((child: any, index: number) => ({
                    tempId: `new-child-${Date.now()}-${index}`,
                    name: child.name || '',
                    gender: child.gender || '',
                    birth_month: child.birth_month
                        ? String(child.birth_month)
                        : '',
                    birth_year: child.birth_year
                        ? String(child.birth_year)
                        : '',
                })) || [];

            const clientPets =
                fullBooking.pets?.map((pet: any, index: number) => ({
                    tempId: `new-pet-${Date.now()}-${index}`,
                    name: pet.name || '',
                    type: pet.type?.toLowerCase() || '',
                    breed: pet.breed || '',
                    notes: pet.notes || '',
                })) || [];

            const client = booking.booking_group?.client_id
                ? (clients?.find(
                      (c) => c.id === booking.booking_group?.client_id,
                  ) ?? null)
                : null;

            setSelectedClientName('');
            setClientSuggestions([]);

            if (client) {
                setSelectedClientName(client.name);
                setClientSuggestions([client] as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>);
            }

            const formData = {
                client_id: fullBooking.client_id,
                service_type: fullBooking.service_type,
                location_type: fullBooking.location_type,
                start_datetime: fullBooking.start_datetime,
                end_datetime: fullBooking.end_datetime,
                dates: [
                    {
                        start_datetime: fullBooking.start_datetime,
                        end_datetime: fullBooking.end_datetime,
                    },
                ],
                hotel_id: fullBooking.hotel_id,
                hotel_name: fullBooking.hotel_name || '',
                address_id: fullBooking.address_id,
                caregiver_id: null,
                caregiver_notes: '',
                notes_to_sitterwise: '',
                admin_notes: '',
                corporate_id: fullBooking.corporate_id || '',
                sitter_preferences: fullBooking.sitter_preferences || [],
                other_adults_present: fullBooking.other_adults_present || '',
                emergency_instructions: '',
                special_needs_notes: '',
                how_did_you_hear: fullBooking.how_did_you_hear || '',
                requires_payment: fullBooking.requires_payment,
                status: 'received',
                payment_status: 'pending',
                rental_platform: fullBooking.rental_platform || null,
                address_line1: fullBooking.address_line1 || '',
                address_line2: fullBooking.address_line2 || '',
                address_city: fullBooking.address_city || '',
                address_state: fullBooking.address_state || '',
                address_zip: fullBooking.address_zip || '',
                new_client: {
                    first_name: '',
                    last_name: '',
                    email: '',
                    phone: '',
                    client_type: 'individual',
                },
                new_children: clientChildren,
                new_pets: clientPets,
                save_children_pets_to_profile: true,
                children_notes: fullBooking.children_notes || '',
            };

            form.setData(formData);
            setBookingChildren(clientChildren);
            setBookingPets(clientPets);

            setSelectedHotelName('');
            setHotelSuggestions([]);

            if (booking.booking_group?.hotel_id) {
                const hotel = hotels.find(
                    (h) => h.id === booking.booking_group?.hotel_id,
                );

                if (hotel) {
                    setSelectedHotelName(hotel.name);
                    setHotelSuggestions([hotel] as unknown as Array<{
                        id: number;
                        name: string;
                        [key: string]: unknown;
                    }>);
                } else if (fullBooking.hotel) {
                    setSelectedHotelName(fullBooking.hotel.name);
                    setHotelSuggestions([
                        fullBooking.hotel,
                    ] as unknown as Array<{
                        id: number;
                        name: string;
                        [key: string]: unknown;
                    }>);
                }
            }

            setSelectedCaregiverName('');

            const bookingAddress = fullBooking.address_line1;
            const groupAddress = booking.booking_group?.address_line1;
            const hasDirectAddress = !!bookingAddress || !!groupAddress;

            if (hasDirectAddress) {
                const addressParts = [
                    bookingAddress || booking.booking_group?.address_line1,
                    fullBooking.address_line2 ||
                        booking.booking_group?.address_line2,
                    fullBooking.address_city ||
                        booking.booking_group?.address_city,
                    fullBooking.address_state ||
                        booking.booking_group?.address_state,
                    fullBooking.address_zip ||
                        booking.booking_group?.address_zip,
                ].filter(Boolean);
                setAddressValue(addressParts.join(', '));
                setShowManualAddressInput(true);
                setIsAddressLocked(true);
                setAddressMode('input');
            } else {
                setAddressMode('select');
                setClientMode('select');
                setIsAddressLocked(false);
                setShowManualAddressInput(false);
                setAddressValue('');
            }

            setCaregiverSuggestions([]);

            setIsSheetOpen(true);
            setIsLoading(false);
        } catch (error) {
            console.error('Error fetching booking details:', error);
            setIsLoading(false);

            return;
        }
    };

    const performSubmit = (notifyAfter = false) => {
        form.transform((data) => ({
            ...data,
            new_children: bookingChildren.map((c) => ({
                name: c.name,
                gender: c.gender || '',
                birth_month: c.birth_month || '',
                birth_year: c.birth_year || '',
            })),
            new_pets: bookingPets.map((p) => ({
                name: p.name,
                type: p.type || '',
                breed: p.breed || '',
                notes: p.notes || '',
            })),
            save_children_pets_to_profile: saveChildrenPetsToProfile,
            notify_after: notifyAfter,
        }));

        if (sheetMode === 'edit') {
            form.put(`/bookings/${editingBooking!.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
            });
        } else {
            form.post('/bookings', {
                onSuccess: () => {
                    setIsSheetOpen(false);
                },
            });
        }
    };

    const handleSubmit = (notifyAfter = false) => {
        const start = form.data.start_datetime;
        const end = form.data.end_datetime;

        if (!start || !end) {
            return;
        }

        const startDate = new Date(start);
        const endDate = new Date(end);

        if (endDate <= startDate) {
            return;
        }

        const diffMs = endDate.getTime() - startDate.getTime();
        const diffHours = diffMs / (1000 * 60 * 60);

        if (diffHours < 4) {
            return;
        }

        // Creating (or duplicating) a booking for a client with no payment
        // method on file: warn before saving, but only for service types that
        // are actually charged via Stripe (invoiced/comped jobs never need one).
        if (sheetMode !== 'edit') {
            const serviceOption = service_types.find(
                (s) => s.value === form.data.service_type,
            );
            const needsPaymentMethod =
                serviceOption?.requires_payment_method ?? false;

            if (needsPaymentMethod && !selectedClientHasPaymentCapability) {
                setPendingNotifyAfter(notifyAfter);
                setShowNoPaymentDialog(true);

                return;
            }
        }

        performSubmit(notifyAfter);
    };

    const confirmSubmitWithoutPayment = () => {
        setShowNoPaymentDialog(false);
        performSubmit(pendingNotifyAfter);
    };

    const handleDelete = () => {
        if (sheetMode === 'edit') {
            setShowDeleteDialog(true);
        }
    };

    const handleConfirmDelete = () => {
        if (sheetMode === 'edit') {
            form.delete(`/bookings/${editingBooking!.id}`, {
                onSuccess: () => {
                    setIsSheetOpen(false);
                    setShowDeleteDialog(false);
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

    const handleAddChild = () => {
        setBookingChildren([
            ...bookingChildren,
            {
                tempId: `new-${Date.now()}`,
                name: '',
                gender: '',
                birth_month: '',
                birth_year: '',
            },
        ]);
    };

    const handleRemoveChild = (tempId: string) => {
        setBookingChildren(bookingChildren.filter((c) => c.tempId !== tempId));
    };

    const handleUpdateChild = (
        tempId: string,
        field: string,
        value: string | boolean,
    ) => {
        setBookingChildren(
            bookingChildren.map((c) =>
                c.tempId === tempId ? { ...c, [field]: value } : c,
            ),
        );
    };

    const handleAddPet = () => {
        setBookingPets([
            ...bookingPets,
            {
                tempId: `new-${Date.now()}`,
                name: '',
                type: '',
                breed: '',
                notes: '',
            },
        ]);
    };

    const handleRemovePet = (tempId: string) => {
        setBookingPets(bookingPets.filter((p) => p.tempId !== tempId));
    };

    const handleUpdatePet = (tempId: string, field: string, value: string) => {
        setBookingPets(
            bookingPets.map((p) =>
                p.tempId === tempId ? { ...p, [field]: value } : p,
            ),
        );
    };

    return {
        isSheetOpen,
        setIsSheetOpen,
        isLoading,
        editingBooking,
        sheetMode,
        showDeleteDialog,
        setShowDeleteDialog,
        showPastBookingDialog,
        showNoPaymentDialog,
        setShowNoPaymentDialog,
        confirmSubmitWithoutPayment,
        form,
        clientSuggestions,
        setClientSuggestions,
        hotelSuggestions,
        setHotelSuggestions,
        caregiverSuggestions,
        setCaregiverSuggestions,
        clientAddresses,
        bookingChildren,
        bookingPets,
        clientMode,
        setClientMode,
        selectedClientType,
        loadingSuggestions,
        selectedClientName,
        selectedHotelName,
        selectedCaregiverName,
        isAddressLocked,
        setIsAddressLocked,
        showManualAddressInput,
        setShowManualAddressInput,
        addressValue,
        setAddressValue,
        saveChildrenPetsToProfile,
        setSaveChildrenPetsToProfile,
        client_types,
        discovery_sources,
        booking_attributes,
        sitter_preferences,
        service_types,
        location_types,
        pet_types,
        booking_statuses,
        payment_statuses,
        hotels,
        handleClientSearch,
        handleHotelSearch,
        handleCaregiverSearch,
        handleClientChange,
        handleAddChild,
        handleRemoveChild,
        handleUpdateChild,
        handleAddPet,
        handleRemovePet,
        handleUpdatePet,
        handleSubmit,
        handleDelete,
        handleConfirmDelete,
        handleCancelDelete,
        handleConfirmPastBooking,
        handleCancelPastBooking,
        openCreateSheet,
        openEditSheet,
        openDuplicateSheet,
        populateCaregiverSuggestions,
        loadMoreCaregivers,
        onAgeFilterChange: (filter: string) =>
            populateCaregiverSuggestions(1, filter, caregiverSearch),
        onSearchChange: (query: string, filter: string) => {
            setCaregiverSearch(query);
            populateCaregiverSuggestions(1, filter, query);
        },
        onSpanishOnlyChange: (value: boolean, filter: string) => {
            setCaregiverSpanishOnly(value);
            populateCaregiverSuggestions(1, filter, caregiverSearch, value);
        },
        caregiverAllIds,
        caregiverTotal,
        caregiverCurrentPage,
        caregiverLastPage,
        loadingCaregiverRecommendations,
        loadingMoreCaregivers,
    };
}

export type { UseBookingSheetReturn };
