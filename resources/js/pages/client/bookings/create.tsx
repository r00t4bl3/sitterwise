import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import { ToasterMessage } from '@/components/toaster-message';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { autoSetEndDateTime, validateMinimumDuration } from '@/lib/datetime';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
    {
        title: 'Create',
        href: '/bookings/create',
    },
];

const MONTH_ABBR = [
    '',
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

interface NewChild {
    tempId: string;
    name: string;
    gender: string;
    birth_month: string;
    birth_year: string;
}

interface NewPet {
    tempId: string;
    name: string;
    type: string;
    breed: string;
    notes: string;
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
        return '';
    }

    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    let years = currentYear - birthYear;
    let months = currentMonth - (birthMonth || 1);

    if (months < 0) {
        years--;
        months += 12;
    }

    if (years === 0) {
        return `${months}m`;
    }

    return `${years}y ${months}m`;
}

function validateDatetime(start: string, end: string): string | null {
    if (!start && !end) {
        return null;
    }

    if (!start) {
        return 'Start date/time is required.';
    }

    if (!end) {
        return 'End date/time is required.';
    }

    const startDate = new Date(start);
    const endDate = new Date(end);
    const now = new Date();

    if (isNaN(startDate.getTime())) {
        return 'Invalid start date/time.';
    }

    if (isNaN(endDate.getTime())) {
        return 'Invalid end date/time.';
    }

    if (startDate < now) {
        return 'Start date/time must be in the future.';
    }

    if (endDate <= startDate) {
        return 'End date/time must be after start date/time.';
    }

    const diffMs = endDate.getTime() - startDate.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffHours < 4) {
        return 'Booking must be at least 4 hours long.';
    }

    return null;
}

export default function ClientBookingCreate() {
    const {
        service_types,
        location_types,
        children,
        pets,
        client_addresses,
        hotels,
        special_consideration_options,
        booking_attributes,
        sitter_preferences,
        discovery_sources,
    } = usePage().props as unknown as {
        service_types: Array<{ value: string; label: string }>;
        location_types: Array<{ value: string; label: string }>;
        children: Array<{
            id: number;
            name: string;
            gender: string | null;
            birth_year: number | null;
            birth_month: number | null;
        }>;
        pets: Array<{
            id: number;
            name: string;
            type: string | null;
            breed: string | null;
            notes: string | null;
        }>;
        client_addresses: Array<{
            id: number;
            line1: string;
            line2?: string;
            city: string;
            state: string;
            zip: string;
        }>;
        hotels: Array<{
            id: number;
            name: string;
            line1: string | null;
            line2: string | null;
            city: string | null;
            state: string | null;
            zip: string | null;
        }>;
        special_consideration_options: Array<{ value: string; label: string }>;
        booking_attributes: Array<{
            id: number;
            name: string;
            slug: string;
            type: string;
            options: string[];
        }>;
        sitter_preferences: Array<{ value: string; label: string }>;
        discovery_sources: Array<{ value: string; label: string }>;
    };

    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);

    const defaultEnd = new Date(tomorrow.getTime() + 4 * 60 * 60 * 1000);

    const form = useForm({
        service_type: 'babysitter',
        location_type: 'private_home',
        start_datetime: formatDateTimeLocal(tomorrow),
        end_datetime: formatDateTimeLocal(defaultEnd),
        address_id: null as number | null,
        hotel_id: null as number | null,
        rental_platform: '',
        address_line1: '',
        address_line2: '',
        address_city: '',
        address_state: '',
        address_zip: '',
        special_considerations: [] as string[],
        caregiver_notes: '',
        notes_to_sitterwise: '',
        sitter_preferences: [] as string[],
        other_adults_present: '',
        emergency_instructions: '',
        special_needs_notes: '',
        how_did_you_hear: '',
        save_children_pets_to_profile: true,
        new_children: [] as NewChild[],
        new_pets: [] as NewPet[],
        deleted_child_ids: [] as number[],
        deleted_pet_ids: [] as number[],
    });

    const [isPersonalInfoOpen, setIsPersonalInfoOpen] = useState(true);
    const [isBookingDetailsOpen, setIsBookingDetailsOpen] = useState(true);

    const [isAddressLocked, setIsAddressLocked] = useState(false);
    const [showManualAddressInput, setShowManualAddressInput] = useState(false);
    const [addressValue, setAddressValue] = useState('');
    const [hotelSearch, setHotelSearch] = useState('');

    const datetimeError = validateMinimumDuration(
        form.data.start_datetime,
        form.data.end_datetime,
    );

    const hotelSuggestions = hotels
        .filter((h) => h.name.toLowerCase().includes(hotelSearch.toLowerCase()))
        .map((h) => ({ id: h.id, name: h.name }));

    const selectedHotelName =
        hotels.find((h) => h.id === form.data.hotel_id)?.name || '';

    const handleAddChild = () => {
        const newChild: NewChild = {
            tempId: Math.random().toString(36).substr(2, 9),
            name: '',
            gender: '',
            birth_month: '',
            birth_year: '',
        };
        form.setData('new_children', [...form.data.new_children, newChild]);
    };

    const handleRemoveChild = (tempId: string, id?: number) => {
        if (id) {
            form.setData('deleted_child_ids', [
                ...form.data.deleted_child_ids,
                id,
            ]);
        } else {
            form.setData(
                'new_children',
                form.data.new_children.filter((c) => c.tempId !== tempId),
            );
        }
    };

    const handleUpdateChild = (
        tempId: string,
        field: string,
        value: string,
    ) => {
        const updated = form.data.new_children.map((c) =>
            c.tempId === tempId ? { ...c, [field]: value } : c,
        );
        form.setData('new_children', updated);
    };

    const handleAddPet = () => {
        const newPet: NewPet = {
            tempId: Math.random().toString(36).substr(2, 9),
            name: '',
            type: '',
            breed: '',
            notes: '',
        };
        form.setData('new_pets', [...form.data.new_pets, newPet]);
    };

    const handleRemovePet = (tempId: string, id?: number) => {
        if (id) {
            form.setData('deleted_pet_ids', [...form.data.deleted_pet_ids, id]);
        } else {
            form.setData(
                'new_pets',
                form.data.new_pets.filter((p) => p.tempId !== tempId),
            );
        }
    };

    const handleUpdatePet = (tempId: string, field: string, value: string) => {
        const updated = form.data.new_pets.map((p) =>
            p.tempId === tempId ? { ...p, [field]: value } : p,
        );
        form.setData('new_pets', updated);
    };

    const handleSubmit = () => {
        form.post('/bookings');
    };

    const handleSpecialConsiderationChange = (
        option: string,
        checked: boolean,
    ) => {
        const current = form.data.special_considerations;

        if (checked) {
            form.setData('special_considerations', [...current, option]);
        } else {
            form.setData(
                'special_considerations',
                current.filter((c) => c !== option),
            );
        }
    };

    const visibleChildren = children.filter(
        (c) => !form.data.deleted_child_ids.includes(c.id),
    );
    const visiblePets = pets.filter(
        (p) => !form.data.deleted_pet_ids.includes(p.id),
    );

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Create Booking" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-semibold text-foreground">
                        Create Booking
                    </h1>
                    <p className="mt-1 text-sm text-muted-foreground">
                        Book a caregiver for your upcoming needs
                    </p>
                </div>

                <div className="space-y-6">
                    {/* Personal Info Panel */}
                    <details
                        className="rounded-[3px] border border-border bg-card"
                        open={isPersonalInfoOpen}
                        onToggle={(e) =>
                            setIsPersonalInfoOpen(e.currentTarget.open)
                        }
                    >
                        <summary className="flex cursor-pointer items-center justify-between bg-muted px-4 py-3 font-medium text-foreground">
                            <span>Personal Info</span>
                            {isPersonalInfoOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </summary>
                        <div className="space-y-4 p-4">
                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Location Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={form.data.location_type}
                                    onValueChange={(value) =>
                                        form.setData('location_type', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select location type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {location_types.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            {form.data.location_type === 'private_home' &&
                                client_addresses.length > 0 &&
                                !showManualAddressInput && (
                                    <div>
                                        <Label className="text-sm font-medium text-foreground">
                                            Address
                                        </Label>
                                        <Select
                                            value={String(
                                                form.data.address_id || '',
                                            )}
                                            onValueChange={(value) => {
                                                if (value === 'add_new') {
                                                    setShowManualAddressInput(
                                                        true,
                                                    );
                                                    form.setData(
                                                        'address_id',
                                                        null,
                                                    );
                                                    form.setData(
                                                        'address_line1',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_line2',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_city',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_state',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_zip',
                                                        '',
                                                    );
                                                    setAddressValue('');
                                                    setIsAddressLocked(false);
                                                } else if (value) {
                                                    const addrId =
                                                        Number(value);
                                                    form.setData(
                                                        'address_id',
                                                        addrId,
                                                    );
                                                    const addr =
                                                        client_addresses.find(
                                                            (a) =>
                                                                a.id === addrId,
                                                        );

                                                    if (addr) {
                                                        form.setData(
                                                            'address_line1',
                                                            addr.line1,
                                                        );
                                                        form.setData(
                                                            'address_line2',
                                                            addr.line2 || '',
                                                        );
                                                        form.setData(
                                                            'address_city',
                                                            addr.city,
                                                        );
                                                        form.setData(
                                                            'address_state',
                                                            addr.state,
                                                        );
                                                        form.setData(
                                                            'address_zip',
                                                            addr.zip,
                                                        );
                                                        setAddressValue(
                                                            `${addr.line1}${
                                                                addr.line2
                                                                    ? `, ${addr.line2}`
                                                                    : ''
                                                            }, ${addr.city}, ${addr.state} ${addr.zip}`,
                                                        );
                                                        setIsAddressLocked(
                                                            true,
                                                        );
                                                    }
                                                } else {
                                                    form.setData(
                                                        'address_id',
                                                        null,
                                                    );
                                                    form.setData(
                                                        'address_line1',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_line2',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_city',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_state',
                                                        '',
                                                    );
                                                    form.setData(
                                                        'address_zip',
                                                        '',
                                                    );
                                                    setIsAddressLocked(false);
                                                    setAddressValue('');
                                                }
                                            }}
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select address..." />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="add_new">
                                                    + Enter manually
                                                </SelectItem>
                                                {client_addresses.map(
                                                    (addr) => (
                                                        <SelectItem
                                                            key={addr.id}
                                                            value={String(
                                                                addr.id,
                                                            )}
                                                        >
                                                            {addr.line1},{' '}
                                                            {addr.city},{' '}
                                                            {addr.state}{' '}
                                                            {addr.zip}
                                                        </SelectItem>
                                                    ),
                                                )}
                                            </SelectContent>
                                        </Select>
                                    </div>
                                )}

                            {form.data.location_type === 'vacation_rental' && (
                                <div>
                                    <Label className="text-sm font-medium text-foreground">
                                        Rental Platform
                                    </Label>
                                    <Select
                                        value={form.data.rental_platform}
                                        onValueChange={(value) =>
                                            form.setData(
                                                'rental_platform',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select platform..." />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {booking_attributes
                                                .filter(
                                                    (attr) =>
                                                        attr.slug ===
                                                        'vacation_rental_platform',
                                                )
                                                .flatMap(
                                                    (attr) =>
                                                        attr.options || [],
                                                )
                                                .map((option) => (
                                                    <SelectItem
                                                        key={option}
                                                        value={option}
                                                    >
                                                        {option
                                                            .charAt(0)
                                                            .toUpperCase() +
                                                            option.slice(1)}
                                                    </SelectItem>
                                                ))}
                                        </SelectContent>
                                    </Select>
                                </div>
                            )}

                            {form.data.location_type === 'hotel' && (
                                <div>
                                    <Label className="text-sm font-medium text-foreground">
                                        Hotel
                                    </Label>
                                    <div className="mt-1">
                                        <Autocomplete
                                            value={form.data.hotel_id}
                                            onChange={(id) => {
                                                form.setData('hotel_id', id);
                                                const hotel = hotels.find(
                                                    (h) => h.id === id,
                                                );

                                                if (hotel) {
                                                    form.setData(
                                                        'address_line1',
                                                        hotel.line1 || '',
                                                    );
                                                    form.setData(
                                                        'address_line2',
                                                        hotel.line2 || '',
                                                    );
                                                    form.setData(
                                                        'address_city',
                                                        hotel.city || '',
                                                    );
                                                    form.setData(
                                                        'address_state',
                                                        hotel.state || '',
                                                    );
                                                    form.setData(
                                                        'address_zip',
                                                        hotel.zip || '',
                                                    );
                                                    setAddressValue(
                                                        `${hotel.line1 || ''}${
                                                            hotel.line2
                                                                ? `, ${hotel.line2}`
                                                                : ''
                                                        }, ${hotel.city || ''}, ${hotel.state || ''} ${
                                                            hotel.zip || ''
                                                        }`.trim(),
                                                    );
                                                    setIsAddressLocked(true);
                                                }
                                            }}
                                            suggestions={hotelSuggestions}
                                            onSearch={setHotelSearch}
                                            placeholder="Search hotel..."
                                            displayValue={selectedHotelName}
                                        />
                                    </div>
                                </div>
                            )}

                            {(form.data.location_type !== 'private_home' ||
                                client_addresses.length === 0 ||
                                showManualAddressInput) && (
                                <BookingAddressFields
                                    form={form}
                                    isAddressLocked={isAddressLocked}
                                    addressValue={addressValue}
                                    onAddressLock={(
                                        locked,
                                        newAddressValue,
                                    ) => {
                                        setIsAddressLocked(locked);

                                        if (locked && newAddressValue) {
                                            setAddressValue(newAddressValue);
                                        }

                                        if (!locked) {
                                            setAddressValue('');
                                            setShowManualAddressInput(false);
                                        }
                                    }}
                                />
                            )}

                            <div>
                                <div className="flex items-center justify-between">
                                    <Label className="text-sm font-medium text-foreground">
                                        Children
                                    </Label>
                                    <button
                                        type="button"
                                        onClick={handleAddChild}
                                        className="flex items-center gap-1 text-xs text-ring hover:text-foreground"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Add Child
                                    </button>
                                </div>
                                <div className="mt-1 overflow-x-auto rounded-[3px] border border-border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Name
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Gender
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Birth (Age)
                                                </th>
                                                <th className="w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {visibleChildren.map((child) => (
                                                <tr
                                                    key={child.id}
                                                    className="border-t border-border"
                                                >
                                                    <td className="px-3 py-2">
                                                        {child.name}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {child.gender || '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {child.birth_month &&
                                                        child.birth_year
                                                            ? `${MONTH_ABBR[child.birth_month]} ${
                                                                  child.birth_year
                                                              } (${calculateAge(
                                                                  child.birth_year,
                                                                  child.birth_month,
                                                              )})`
                                                            : '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleRemoveChild(
                                                                    '',
                                                                    child.id,
                                                                )
                                                            }
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {form.data.new_children.map(
                                                (child) => (
                                                    <tr
                                                        key={child.tempId}
                                                        className="border-t border-border bg-muted/50"
                                                    >
                                                        <td className="px-3 py-2">
                                                            <Input
                                                                value={
                                                                    child.name
                                                                }
                                                                onChange={(e) =>
                                                                    handleUpdateChild(
                                                                        child.tempId,
                                                                        'name',
                                                                        e.target
                                                                            .value,
                                                                    )
                                                                }
                                                                placeholder="Name"
                                                            />
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <Select
                                                                value={
                                                                    child.gender
                                                                }
                                                                onValueChange={(
                                                                    value,
                                                                ) =>
                                                                    handleUpdateChild(
                                                                        child.tempId,
                                                                        'gender',
                                                                        value,
                                                                    )
                                                                }
                                                            >
                                                                <SelectTrigger>
                                                                    <SelectValue placeholder="Select" />
                                                                </SelectTrigger>
                                                                <SelectContent>
                                                                    <SelectItem value="male">
                                                                        Male
                                                                    </SelectItem>
                                                                    <SelectItem value="female">
                                                                        Female
                                                                    </SelectItem>
                                                                </SelectContent>
                                                            </Select>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <div className="flex gap-1">
                                                                <Select
                                                                    value={
                                                                        child.birth_month
                                                                    }
                                                                    onValueChange={(
                                                                        value,
                                                                    ) =>
                                                                        handleUpdateChild(
                                                                            child.tempId,
                                                                            'birth_month',
                                                                            value,
                                                                        )
                                                                    }
                                                                >
                                                                    <SelectTrigger className="w-20">
                                                                        <SelectValue placeholder="Month" />
                                                                    </SelectTrigger>
                                                                    <SelectContent>
                                                                        {[
                                                                            'Jan',
                                                                            'Feb',
                                                                            'Mar',
                                                                            'Apr',
                                                                            'May',
                                                                            'Jun',
                                                                            'Jul',
                                                                            'Aug',
                                                                            'Sep',
                                                                            'Oct',
                                                                            'Nov',
                                                                            'Dec',
                                                                        ].map(
                                                                            (
                                                                                m,
                                                                                i,
                                                                            ) => (
                                                                                <SelectItem
                                                                                    key={
                                                                                        m
                                                                                    }
                                                                                    value={String(
                                                                                        i +
                                                                                            1,
                                                                                    )}
                                                                                >
                                                                                    {
                                                                                        m
                                                                                    }
                                                                                </SelectItem>
                                                                            ),
                                                                        )}
                                                                    </SelectContent>
                                                                </Select>
                                                                <Input
                                                                    value={
                                                                        child.birth_year
                                                                    }
                                                                    onChange={(
                                                                        e,
                                                                    ) =>
                                                                        handleUpdateChild(
                                                                            child.tempId,
                                                                            'birth_year',
                                                                            e
                                                                                .target
                                                                                .value,
                                                                        )
                                                                    }
                                                                    placeholder="Year"
                                                                    className="w-16"
                                                                />
                                                            </div>
                                                        </td>
                                                        <td className="px-3 py-2">
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    handleRemoveChild(
                                                                        child.tempId,
                                                                    )
                                                                }
                                                                className="text-red-500 hover:text-red-700"
                                                            >
                                                                <Trash2 className="h-4 w-4" />
                                                            </button>
                                                        </td>
                                                    </tr>
                                                ),
                                            )}
                                            {visibleChildren.length === 0 &&
                                                form.data.new_children
                                                    .length === 0 && (
                                                    <tr className="border-t border-border">
                                                        <td
                                                            colSpan={4}
                                                            className="px-3 py-4 text-center text-muted-foreground"
                                                        >
                                                            No children added
                                                        </td>
                                                    </tr>
                                                )}
                                        </tbody>
                                    </table>
                                </div>
                                {visibleChildren.length === 0 &&
                                    form.data.new_children.length === 0 && (
                                        <p className="text-sm text-destructive">
                                            At least one child is required.
                                        </p>
                                    )}
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Special Needs / Allergies
                                </Label>
                                <Textarea
                                    value={form.data.special_needs_notes || ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'special_needs_notes',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Special needs notes"
                                />
                            </div>

                            {false && form.data.special_needs_notes && (
                                <div>
                                    <Label className="text-sm font-medium text-foreground">
                                        Emergency Instructions
                                    </Label>
                                    <Textarea
                                        value={
                                            form.data.emergency_instructions ||
                                            ''
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'emergency_instructions',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="What should your caregiver do in an emergency?"
                                    />
                                </div>
                            )}

                            <div>
                                <div className="flex items-center justify-between">
                                    <Label className="text-sm font-medium text-foreground">
                                        Pets
                                    </Label>
                                    <button
                                        type="button"
                                        onClick={handleAddPet}
                                        className="flex items-center gap-1 text-xs text-ring hover:text-foreground"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Add Pet
                                    </button>
                                </div>
                                <div className="mt-1 overflow-x-auto rounded-[3px] border border-border">
                                    <table className="w-full text-sm">
                                        <thead className="bg-muted">
                                            <tr>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Name
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Type
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Breed
                                                </th>
                                                <th className="px-3 py-2 text-left font-medium">
                                                    Notes
                                                </th>
                                                <th className="w-10"></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            {visiblePets.map((pet) => (
                                                <tr
                                                    key={pet.id}
                                                    className="border-t border-border"
                                                >
                                                    <td className="px-3 py-2">
                                                        {pet.name}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {pet.type || '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {pet.breed || '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        {pet.notes || '-'}
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleRemovePet(
                                                                    '',
                                                                    pet.id,
                                                                )
                                                            }
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {form.data.new_pets.map((pet) => (
                                                <tr
                                                    key={pet.tempId}
                                                    className="border-t border-border bg-muted/50"
                                                >
                                                    <td className="px-3 py-2">
                                                        <Input
                                                            value={pet.name}
                                                            onChange={(e) =>
                                                                handleUpdatePet(
                                                                    pet.tempId,
                                                                    'name',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Name"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Input
                                                            value={pet.type}
                                                            onChange={(e) =>
                                                                handleUpdatePet(
                                                                    pet.tempId,
                                                                    'type',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Type"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Input
                                                            value={pet.breed}
                                                            onChange={(e) =>
                                                                handleUpdatePet(
                                                                    pet.tempId,
                                                                    'breed',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Breed"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <Input
                                                            value={pet.notes}
                                                            onChange={(e) =>
                                                                handleUpdatePet(
                                                                    pet.tempId,
                                                                    'notes',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Notes"
                                                        />
                                                    </td>
                                                    <td className="px-3 py-2">
                                                        <button
                                                            type="button"
                                                            onClick={() =>
                                                                handleRemovePet(
                                                                    pet.tempId,
                                                                )
                                                            }
                                                            className="text-red-500 hover:text-red-700"
                                                        >
                                                            <Trash2 className="h-4 w-4" />
                                                        </button>
                                                    </td>
                                                </tr>
                                            ))}
                                            {visiblePets.length === 0 &&
                                                form.data.new_pets.length ===
                                                    0 && (
                                                    <tr className="border-t border-border">
                                                        <td
                                                            colSpan={5}
                                                            className="px-3 py-4 text-center text-muted-foreground"
                                                        >
                                                            No pets added
                                                        </td>
                                                    </tr>
                                                )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="other_adults_present"
                                    checked={!!form.data.other_adults_present}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'other_adults_present',
                                            checked ? '1' : '',
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="other_adults_present"
                                    className="text-sm font-medium"
                                >
                                    Other Adults Present
                                </Label>
                            </div>

                            <div className="grid gap-2">
                                <Label>Sitter Preferences</Label>
                                <div className="mt-2 flex flex-wrap gap-2">
                                    <div className="grid grid-cols-2 gap-4 sm:grid-cols-3">
                                        {sitter_preferences.map((option) => (
                                            <div
                                                key={option.value}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`pref-${option.value}`}
                                                    checked={form.data.sitter_preferences.includes(
                                                        option.value,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const newPrefs = checked
                                                            ? [
                                                                  ...form.data
                                                                      .sitter_preferences,
                                                                  option.value,
                                                              ]
                                                            : form.data.sitter_preferences.filter(
                                                                  (
                                                                      pref: string,
                                                                  ) =>
                                                                      pref !==
                                                                      option.value,
                                                              );
                                                        form.setData(
                                                            'sitter_preferences',
                                                            newPrefs,
                                                        );
                                                    }}
                                                />
                                                <Label
                                                    htmlFor={`pref-${option.value}`}
                                                    className="text-sm font-normal"
                                                >
                                                    {option.label}
                                                </Label>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="how_did_you_hear">
                                    How Did You Hear
                                </Label>
                                <Select
                                    value={form.data.how_did_you_hear || ''}
                                    onValueChange={(value) =>
                                        form.setData('how_did_you_hear', value)
                                    }
                                >
                                    <SelectTrigger id="how_did_you_hear">
                                        <SelectValue placeholder="Select an option" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {discovery_sources.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="save_to_profile"
                                    checked={
                                        form.data.save_children_pets_to_profile
                                    }
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'save_children_pets_to_profile',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="save_to_profile">
                                    Save changes to profile
                                </Label>
                            </div>
                        </div>
                    </details>

                    {/* Booking Details Panel */}
                    <details
                        className="rounded-[3px] border border-border bg-card"
                        open={isBookingDetailsOpen}
                        onToggle={(e) =>
                            setIsBookingDetailsOpen(e.currentTarget.open)
                        }
                    >
                        <summary className="flex cursor-pointer items-center justify-between bg-muted px-4 py-3 font-medium text-foreground">
                            <span>Booking Details</span>
                            {isBookingDetailsOpen ? (
                                <ChevronUp className="h-4 w-4" />
                            ) : (
                                <ChevronDown className="h-4 w-4" />
                            )}
                        </summary>
                        <div className="space-y-4 p-4">
                            <div className="grid gap-2">
                                <Label htmlFor="service_type">
                                    Service Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={form.data.service_type}
                                    onValueChange={(value) =>
                                        form.setData('service_type', value)
                                    }
                                >
                                    <SelectTrigger id="service_type">
                                        <SelectValue placeholder="Select service type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {service_types.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <div className="grid gap-2">
                                    <Label>
                                        Start DateTime{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <DateTimePicker
                                        value={form.data.start_datetime}
                                        onChange={(datetime) => {
                                            form.setData(
                                                'start_datetime',
                                                datetime,
                                            );

                                            if (datetime) {
                                                form.setData(
                                                    'end_datetime',
                                                    autoSetEndDateTime(
                                                        datetime,
                                                    ),
                                                );
                                            }
                                        }}
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label>
                                        End DateTime{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <DateTimePicker
                                        value={form.data.end_datetime}
                                        startTime={form.data.start_datetime}
                                        onChange={(datetime) => {
                                            form.setData(
                                                'end_datetime',
                                                datetime,
                                            );
                                        }}
                                    />
                                </div>
                                {datetimeError && (
                                    <div className="col-span-1 text-sm text-destructive sm:col-span-2">
                                        {datetimeError}
                                    </div>
                                )}
                            </div>

                            <div className="grid gap-2">
                                <Label>Special Considerations</Label>
                                <div className="mt-2 grid grid-cols-1 gap-4 sm:grid-cols-2">
                                    {special_consideration_options.map(
                                        (option) => (
                                            <div
                                                key={option.value}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`sc-${option.value}`}
                                                    checked={form.data.special_considerations.includes(
                                                        option.value,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        handleSpecialConsiderationChange(
                                                            option.value,
                                                            checked === true,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`sc-${option.value}`}
                                                    className="text-sm font-normal"
                                                >
                                                    {option.label}
                                                </Label>
                                            </div>
                                        ),
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Caregiver Notes
                                </Label>
                                <textarea
                                    value={form.data.caregiver_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'caregiver_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    placeholder="Any specific instructions for the caregiver..."
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Notes to Sitterwise
                                </Label>
                                <textarea
                                    value={form.data.notes_to_sitterwise}
                                    onChange={(e) =>
                                        form.setData(
                                            'notes_to_sitterwise',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    placeholder="Private notes for our team..."
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                                />
                            </div>

                            <div className="flex gap-2 pt-4">
                                <Button
                                    onClick={handleSubmit}
                                    disabled={
                                        form.processing || !!datetimeError
                                    }
                                    className="flex-1"
                                >
                                    {form.processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    {form.processing
                                        ? 'Creating...'
                                        : 'Create Booking'}
                                </Button>
                                <Button
                                    asChild
                                    variant="outline"
                                    className="flex-1"
                                >
                                    <Link href="/bookings">Cancel</Link>
                                </Button>
                            </div>
                        </div>
                    </details>
                </div>
            </div>
        </AppLayout>
    );
}
