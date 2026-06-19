export interface Client {
    id: number;
    name: string;
    email: string;
}

export interface Hotel {
    id: number;
    name: string;
    city: string | null;
    line1: string | null;
    line2: string | null;
    state: string | null;
    zip: string | null;
}

export interface Caregiver {
    id: number;
    name: string;
}

export interface ClientAddress {
    id: number;
    line1: string;
    city: string;
    state: string;
    zip: string;
}

export interface ClientChild {
    id: number;
    name: string;
    gender: string | null;
    birth_month: number | null;
    birth_year: number | null;
}

export interface ClientPet {
    id: number;
    name: string;
    type: string | null;
    breed: string | null;
    notes: string | null;
}

export interface BookingGroup {
    id: number;
    client_id: number;
    service_type: string;
    location_type: string;
    rental_platform: string | null;
    client_first_name: string | null;
    client_last_name: string | null;
    client_phone: string | null;
    client_email: string | null;
    address_id: number | null;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    hotel_id: number | null;
    hotel_name: string | null;
    children: Array<{
        name: string;
        gender: string | null;
        birth_month: number | null;
        birth_year: number | null;
    }> | null;
    children_notes: string | null;
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
    sitter_preferences: string[] | null;
    other_adults_present: string | null;
    emergency_instructions: string | null;
    special_needs_notes: string | null;
    how_did_you_hear: string | null;
    caregiver_notes: string | null;
    notes_to_sitterwise: string | null;
    admin_notes: string | null;
    corporate_id: string | null;
    requires_payment: boolean;
    special_considerations: string[] | null;
    bookings_count?: number;
    sibling_bookings?: Array<{
        id: number;
        ulid: string;
        start_datetime: string;
        end_datetime: string;
        status: string;
        caregiver_name: string | null;
    }>;
    client?: {
        id: number;
        first_name: string;
        last_name: string;
        phone: string | null;
        biography: string | null;
        user: {
            name: string;
            profile_photo_path: string | null;
        };
        children_count?: number;
        pets_count?: number;
        children?: Array<{
            id: number;
            name: string;
            gender: string | null;
            birth_month: number | null;
            birth_year: number | null;
        }>;
        pets?: Array<{
            id: number;
            name: string;
            type: string | null;
            breed: string | null;
            notes: string | null;
        }>;
    };
    hotel?: {
        id: number;
        name: string;
    } | null;
}

export interface Booking {
    id: number;
    ulid: string;
    booking_group_id: number;
    start_datetime: string;
    end_datetime: string;
    status: string;
    total_amount: number;
    payment_status: string;
    caregiver_id: number | null;
    service_type_label?: string;
    attributeDefinitions?: Array<{
        pivot: {
            attribute_definition_id: number;
            value: string;
        };
    }>;
    booking_group: BookingGroup;
    client?: {
        id: number;
        first_name: string;
        last_name: string;
        phone: string | null;
        biography: string | null;
        user: {
            name: string;
            profile_photo_path: string | null;
        };
        children_count?: number;
        pets_count?: number;
        children?: Array<{
            id: number;
            name: string;
            gender: string | null;
            birth_month: number | null;
            birth_year: number | null;
        }>;
        pets?: Array<{
            id: number;
            name: string;
            type: string | null;
            breed: string | null;
            notes: string | null;
        }>;
        favorite_caregivers?: Array<{
            id: number;
            first_name: string;
            last_name: string;
            user: {
                profile_photo_path: string | null;
                profile_photo_url: string | null;
            };
        }>;
        blocked_caregivers?: Array<{
            id: number;
            first_name: string;
            last_name: string;
            user: {
                profile_photo_path: string | null;
                profile_photo_url: string | null;
            };
        }>;
        previous_caregivers?: Array<{
            id: number;
            first_name: string;
            last_name: string;
            user: {
                profile_photo_path: string | null;
                profile_photo_url: string | null;
            };
        }>;
    };
    hotel?: {
        id: number;
        name: string;
    } | null;
    caregiver?: {
        id: number;
        first_name: string;
        last_name: string;
        user: {
            name: string;
        };
    } | null;
}

export interface Props {
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
    clients_with_payment_capability: number[];
    service_types: Array<{ value: string; label: string }>;
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
    pet_types: Array<{ value: string; label: string }>;
    discovery_sources: Array<{ value: string; label: string }>;
}

export interface BookingFormData {
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
}

export interface NewClientData {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    client_type: string;
}
