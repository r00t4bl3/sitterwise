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
    special_needs: boolean;
    special_needs_notes: string | null;
}

export interface ClientPet {
    id: number;
    name: string;
    type: string | null;
    breed: string | null;
    notes: string | null;
}

export interface Booking {
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
    attributeDefinitions?: Array<{
        pivot: {
            attribute_definition_id: number;
            value: string;
        };
    }>;
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
    booking_address: {
        id: number;
        line1: string;
        line2: string | null;
        city: string;
        state: string;
        zip: string;
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
    service_types: Array<{ value: string; label: string }>;
    location_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    special_consideration_options: Array<{ value: string; label: string }>;
    booking_attributes: Array<{
        id: number;
        name: string;
        slug: string;
        type: string;
        options: string[];
    }>;
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
    sitter_preferences: string;
    other_adults_in_home: string;
    medical_info: string;
    emergency_instructions: string;
    comped: boolean;
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
        cell_phone: string;
        client_type: string;
    };
}

export interface NewClientData {
    first_name: string;
    last_name: string;
    email: string;
    cell_phone: string;
    client_type: string;
}
