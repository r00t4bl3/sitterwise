import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { parseAsLocal } from '@/lib/datetime';
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
    service_types: Array<{ value: string; label: string }>;
    location_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{
        value: string;
        label: string;
        colors: { bg: string; text: string; border: string };
    }>;
    payment_statuses: Array<{ value: string; label: string }>;
    special_consideration_options: Array<{ value: string; label: string }>;
    booking_attributes: Array<{
        id: number;
        name: string;
        slug: string;
        type: string;
        options: string[];
    }>;
    sitter_preference_options: Array<{ value: string; label: string }>;
    client_type_options?: Array<{ value: string; label: string }>;
}

interface ClientAddress {
    id: number;
    line1: string;
    line2?: string;
    city: string;
    state: string;
    zip: string;
}

interface ClientChild {
    id: number;
    name: string;
    gender: string | null;
    birth_year: number | null;
    birth_month: number | null;
}

interface ClientPet {
    id: number;
    name: string;
    type: string | null;
    breed: string | null;
    notes: string | null;
}

type SheetMode = 'create' | 'edit' | 'duplicate';

interface FormData {
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
}

function formatDateTimeLocal(date: Date): string {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
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
    special_consideration_options,
    booking_attributes,
    sitter_preference_options,
    client_type_options = [
        { value: 'resident', label: 'San Diego Resident' },
        { value: 'vacationer', label: 'Vacationer' },
        { value: 'invoiced', label: 'Invoiced' },
    ],
}: UseBookingSheetProps) {
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingBooking, setEditingBooking] = useState<Booking | null>(null);
    const [sheetMode, setSheetMode] = useState<SheetMode>('create');
    const [showDeleteDialog, setShowDeleteDialog] = useState(false);

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

    const [selectedClientName, setSelectedClientName] = useState('');
    const [selectedHotelName, setSelectedHotelName] = useState('');
    const [selectedCaregiverName, setSelectedCaregiverName] = useState('');
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
    const [saveChildrenPetsToProfile, setSaveChildrenPetsToProfile] = useState(true);

    const form = useForm<FormData>({
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

    const populateCaregiverSuggestions = async () => {
        if (!editingBooking?.client_id) {
            return;
        }

        try {
            const params = new URLSearchParams({
                client_id: editingBooking.client_id.toString(),
            });

            if (editingBooking.id) {
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

            const response = await fetch(
                `/bookings/recommended-caregivers?${params}`,
            );
            const data = await response.json();
            setCaregiverSuggestions(data);
        } catch (error) {
            console.error('Error fetching recommended caregivers:', error);
            setCaregiverSuggestions(
                caregivers.map((c) => ({
                    id: c.id,
                    name: c.name,
                })),
            );
        }
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

    const fetchClientDataOnly = async (
        clientId: number,
        skipCaregiverFetch = false,
    ) => {
        try {
            const response = await fetch(`/clients/${clientId}/data`);
            const data = await response.json();
            setClientAddresses(data.client.addresses || []);
            setClientChildren(data.client.children || []);
            setClientPets(data.client.pets || []);
            setSelectedClientType(data.client.client_type || null);

            if (!skipCaregiverFetch) {
                fetchRecommendedCaregivers(clientId);
            }

            return data;
        } catch (error) {
            console.error('Error fetching client data:', error);

            return null;
        }
    };

    const fetchRecommendedCaregivers = async (clientId: number) => {
        try {
            const params = new URLSearchParams({
                client_id: clientId.toString(),
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
            setCaregiverSuggestions(
                caregivers as unknown as Array<{
                    id: number;
                    name: string;
                    [key: string]: unknown;
                }>,
            );
        }
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
        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);
        setIsSheetOpen(true);
    };

    const openEditSheet = async (booking: Booking) => {
        setEditingBooking(booking);
        setSheetMode('edit');

        try {
            const response = await fetch(`/bookings/${booking.id}`, {
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json'
                },
            });
            const fullBooking = await response.json();
            setEditingBooking(fullBooking);
        } catch (error) {
            console.error('Error fetching booking details:', error);
        }

        const unmatchedChildren: any[] = [];
        const unmatchedPets: any[] = [];

        if (booking.children) {
            booking.children.forEach((snapChild) => {
                unmatchedChildren.push({
                    tempId: `snap-${Math.random().toString(36).substr(2, 9)}`,
                    name: snapChild.name || '',
                    gender: snapChild.gender || '',
                    birth_month: String(snapChild.birth_month || ''),
                    birth_year: String(snapChild.birth_year || ''),
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
            child_ids: [],
            pet_ids: [],
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
        let clientData: any = null;

        if (booking.client_id) {
            const data = await fetchClientDataOnly(booking.client_id, true);
            clientData = data;

            if (data && data.client) {
                const profileChildren = data.client.children || [];
                const profilePets = data.client.pets || [];

                clientChildren = profileChildren.map((c: any) => ({
                    tempId: `new-${Date.now()}-${Math.random()}`,
                    name: c.name || '',
                    gender: c.gender || '',
                    birth_month: String(c.birth_month || ''),
                    birth_year: String(c.birth_year || ''),
                }));

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
            service_type: 'babysitter',
            location_type: booking.location_type,
            start_datetime: booking.start_datetime,
            end_datetime: booking.end_datetime,
            hotel_id: booking.hotel_id,
            address_id: booking.address_id,
            caregiver_id: null,
            special_considerations: booking.special_considerations || [],
            caregiver_notes: '',
            notes_to_sitterwise: '',
            admin_notes: '',
            corporate_id: booking.corporate_id || '',
            sitter_preferences: clientData?.client?.sitter_preferences || [],
            other_adults_present: clientData?.client?.other_adults_present || '',
            emergency_instructions: '',
            special_needs_notes: '',
            how_did_you_hear: clientData?.client?.how_did_you_hear || '',
            requires_payment: booking.requires_payment,
            status: 'received',
            payment_status: 'pending',
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
            new_children: clientChildren,
            new_pets: clientPets,
            child_ids: [],
            pet_ids: [],
            deleted_child_ids: [],
            deleted_pet_ids: [],
            save_children_pets_to_profile: true,
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

        setAddressMode('select');
        setClientMode('select');
        setIsAddressLocked(false);
        setShowManualAddressInput(false);
        setAddressValue('');

        setClientSuggestions([]);
        setHotelSuggestions([]);
        setCaregiverSuggestions([]);

        setIsSheetOpen(true);
    };

    const handleSubmit = () => {
        const start = form.data.start_datetime;
        const end = form.data.end_datetime;

        if (!start || !end) {
            return;
        }

        const startDate = new Date(start);
        const endDate = new Date(end);
        const now = new Date();

        if (startDate < now) {
            return;
        }

        if (endDate <= startDate) {
            return;
        }

        const diffMs = endDate.getTime() - startDate.getTime();
        const diffHours = diffMs / (1000 * 60 * 60);

        if (diffHours < 4) {
            return;
        }

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

    return {
        isSheetOpen,
        setIsSheetOpen,
        editingBooking,
        sheetMode,
        showDeleteDialog,
        setShowDeleteDialog,
        form,
        clientSuggestions,
        setClientSuggestions,
        hotelSuggestions,
        setHotelSuggestions,
        caregiverSuggestions,
        setCaregiverSuggestions,
        clientAddresses,
        clientChildren,
        clientPets,
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
        deletedChildIds,
        deletedPetIds,
        newChildren,
        newPets,
        saveChildrenPetsToProfile,
        setSaveChildrenPetsToProfile,
        client_type_options,
        booking_attributes,
        sitter_preference_options,
        service_types,
        location_types,
        booking_statuses,
        payment_statuses,
        special_consideration_options,
        hotels,
        handleClientSearch,
        handleHotelSearch,
        handleCaregiverSearch,
        handleClientChange,
        handleSpecialConsiderationChange,
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
        openCreateSheet,
        openEditSheet,
        openDuplicateSheet,
        populateCaregiverSuggestions,
    };
}

export type { UseBookingSheetReturn };