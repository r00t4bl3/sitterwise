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
import { calculateAge, getChildBirthYearOptions } from '@/lib/age';
import {
    autoSetEndDateTime,
    formatUtcStringFromPt,
    todayInPT,
    validateMinimumDuration,
} from '@/lib/datetime';
import { serviceRequiresChild } from '@/lib/service-types';
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

interface Child {
    id: number | string;
    name: string;
    gender: string;
    birth_month: string;
    birth_year: string;
}

interface DateEntry {
    id: string;
    start_datetime: string;
    end_datetime: string;
}

interface Pet {
    id: number | string;
    name: string;
    type: string;
    breed: string;
    notes: string;
}

function generateDateId(): string {
    return `date-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
}

function findDateOverlaps(dates: DateEntry[]): Record<string, string[]> {
    const overlaps: Record<string, string[]> = {};

    for (let i = 0; i < dates.length; i++) {
        for (let j = i + 1; j < dates.length; j++) {
            const a = dates[i];
            const b = dates[j];

            if (
                a.start_datetime < b.end_datetime &&
                b.start_datetime < a.end_datetime
            ) {
                if (!overlaps[a.id]) {
                    overlaps[a.id] = [];
                }

                if (!overlaps[b.id]) {
                    overlaps[b.id] = [];
                }

                overlaps[a.id].push(`Date ${j + 1}`);
                overlaps[b.id].push(`Date ${i + 1}`);
            }
        }
    }

    return overlaps;
}

function generateTempId(): number {
    return -Date.now();
}

function convertChildToEditable(child: {
    id: number;
    name: string;
    gender: string | null;
    birth_year: number | null;
    birth_month: number | null;
}): Child {
    return {
        id: child.id,
        name: child.name,
        gender: child.gender || '',
        birth_month: child.birth_month ? String(child.birth_month) : '',
        birth_year: child.birth_year ? String(child.birth_year) : '',
    };
}

function convertPetToEditable(pet: {
    id: number;
    name: string;
    type: string | null;
    breed: string | null;
    notes: string | null;
}): Pet {
    return {
        id: pet.id,
        name: pet.name,
        type: pet.type?.toLowerCase() || '',
        breed: pet.breed || '',
        notes: pet.notes || '',
    };
}

export default function ClientBookingCreate() {
    const {
        service_types,
        location_types,
        children,
        pets,
        client_addresses,
        hotels,
        pet_types,
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
            state: string;
            zip: string | null;
        }>;
        pet_types: Array<{ value: string; label: string }>;
        sitter_preferences: Array<{ value: string; label: string }>;
        discovery_sources: Array<{ value: string; label: string }>;
    };

    // Default to tomorrow 9:00 AM - 1:00 PM on the PT wall-clock. The Date is
    // built so its local components equal the PT date/time; formatUtcStringFromPt
    // reads those as PT, keeping the default correct in any browser timezone.
    const [ptYear, ptMonth, ptDay] = todayInPT().split('-').map(Number);
    const tomorrow = new Date(ptYear, ptMonth - 1, ptDay + 1, 9, 0, 0, 0);
    const defaultEnd = new Date(
        tomorrow.getFullYear(),
        tomorrow.getMonth(),
        tomorrow.getDate(),
        13,
        0,
        0,
        0,
    );

    const initialChildren: Child[] = children.map(convertChildToEditable);
    const initialPets: Pet[] = pets.map(convertPetToEditable);

    const defaultStartStr = formatUtcStringFromPt(tomorrow);
    const defaultEndStr = formatUtcStringFromPt(defaultEnd);

    const form = useForm({
        service_type: '',
        location_type: '',
        new_children: initialChildren as Child[],
        new_pets: initialPets as Pet[],
        start_datetime: defaultStartStr,
        end_datetime: defaultEndStr,
        dates: [
            { start_datetime: defaultStartStr, end_datetime: defaultEndStr },
        ] as Array<{ start_datetime: string; end_datetime: string }>,
        address_id: null as number | null,
        hotel_id: null as number | null,
        hotel_name: '',
        rental_platform: '',
        address_line1: '',
        address_line2: '',
        address_city: '',
        address_state: '',
        address_zip: '',
        caregiver_notes: '',
        notes_to_sitterwise: '',
        sitter_preferences: [] as string[],
        other_adults_present: '',
        emergency_instructions: '',
        special_needs_notes: '',
        how_did_you_hear: '',
        save_children_pets_to_profile: true,
        deleted_child_ids: [] as number[],
        deleted_pet_ids: [] as number[],
    });

    const [dates, setDates] = useState<DateEntry[]>([
        {
            id: generateDateId(),
            start_datetime: defaultStartStr,
            end_datetime: defaultEndStr,
        },
    ]);

    const [isPersonalInfoOpen, setIsPersonalInfoOpen] = useState(true);
    const [isBookingDetailsOpen, setIsBookingDetailsOpen] = useState(true);

    const [isAddressLocked, setIsAddressLocked] = useState(false);
    const [showManualAddressInput, setShowManualAddressInput] = useState(false);
    const [addressValue, setAddressValue] = useState('');
    const [hotelSearch, setHotelSearch] = useState('');
    const [showUnlistedHotel, setShowUnlistedHotel] = useState(false);

    const bookingMinimumHours = Number(
        usePage().props.booking_minimum_hours ?? 4,
    );

    const datetimeError = validateMinimumDuration(
        form.data.start_datetime,
        form.data.end_datetime,
        bookingMinimumHours,
    );

    const hotelSuggestions = hotels
        .filter((h) => h.name.toLowerCase().includes(hotelSearch.toLowerCase()))
        .map((h) => ({ id: h.id, name: h.name }));

    const selectedHotelName =
        hotels.find((h) => h.id === form.data.hotel_id)?.name || '';

    const handleAddChild = () => {
        const newChild: Child = {
            id: generateTempId(),
            name: '',
            gender: '',
            birth_month: '',
            birth_year: '',
        };
        form.setData('new_children', [...form.data.new_children, newChild]);
    };

    const handleRemoveChild = (id: number | string) => {
        // Existing profile children have a real positive id — record them for
        // deletion so they're removed from the profile (and thus the booking's
        // children snapshot). New (temp negative id) entries are just dropped.
        if (typeof id === 'number' && id > 0) {
            form.setData('deleted_child_ids', [
                ...form.data.deleted_child_ids,
                id,
            ]);
        }
        form.setData(
            'new_children',
            form.data.new_children.filter((c) => c.id !== id),
        );
    };

    const handleUpdateChild = (
        id: number | string,
        field: string,
        value: string,
    ) => {
        const updated = form.data.new_children.map((c) =>
            c.id === id ? { ...c, [field]: value } : c,
        );
        form.setData('new_children', updated);
    };

    const handleAddPet = () => {
        const newPet: Pet = {
            id: generateTempId(),
            name: '',
            type: '',
            breed: '',
            notes: '',
        };
        form.setData('new_pets', [...form.data.new_pets, newPet]);
    };

    const handleRemovePet = (id: number | string) => {
        if (typeof id === 'number' && id > 0) {
            form.setData('deleted_pet_ids', [...form.data.deleted_pet_ids, id]);
        }
        form.setData(
            'new_pets',
            form.data.new_pets.filter((p) => p.id !== id),
        );
    };

    const handleUpdatePet = (
        id: number | string,
        field: string,
        value: string,
    ) => {
        const updated = form.data.new_pets.map((p) =>
            p.id === id ? { ...p, [field]: value } : p,
        );
        form.setData('new_pets', updated);
    };

    const handleSubmit = () => {
        form.post('/bookings');
    };

    const dateOverlaps = findDateOverlaps(dates);

    const syncDatesToForm = (allDates: DateEntry[]) => {
        if (allDates.length > 0) {
            form.setData('start_datetime', allDates[0].start_datetime);
            form.setData('end_datetime', allDates[0].end_datetime);
            form.setData(
                'dates',
                allDates.map((d) => ({
                    start_datetime: d.start_datetime,
                    end_datetime: d.end_datetime,
                })),
            );
        }
    };

    const handleAddDate = () => {
        const nextDate = new Date(
            tomorrow.getFullYear(),
            tomorrow.getMonth(),
            tomorrow.getDate() + dates.length,
            9,
            0,
            0,
            0,
        );
        const endDate = new Date(
            nextDate.getFullYear(),
            nextDate.getMonth(),
            nextDate.getDate(),
            13,
            0,
            0,
            0,
        );
        const newEntry: DateEntry = {
            id: generateDateId(),
            start_datetime: formatUtcStringFromPt(nextDate),
            end_datetime: formatUtcStringFromPt(endDate),
        };
        const updated = [...dates, newEntry];
        setDates(updated);
        syncDatesToForm(updated);
    };

    const handleRemoveDate = (id: string) => {
        const updated = dates.filter((d) => d.id !== id);
        setDates(updated);
        syncDatesToForm(updated);
    };

    const handleUpdateDate = (
        id: string,
        field: 'start_datetime' | 'end_datetime',
        value: string,
    ) => {
        const updated = dates.map((d) => {
            if (d.id !== id) {
                return d;
            }

            const next = { ...d, [field]: value };

            if (field === 'start_datetime' && id === dates[0]?.id) {
                next.end_datetime = autoSetEndDateTime(
                    value,
                    bookingMinimumHours,
                );
            }

            return next;
        });
        setDates(updated);
        syncDatesToForm(updated);
    };

    const hasChildren = form.data.new_children.length > 0;
    const hasPets = form.data.new_pets.length > 0;

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
                                    onValueChange={(value) => {
                                        form.setData('location_type', value);

                                        if (value !== 'hotel') {
                                            // The locked address (if any)
                                            // belonged to the hotel — a
                                            // non-hotel booking must not
                                            // keep it.
                                            if (form.data.hotel_id) {
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
                                                form.setData('address_zip', '');
                                                setAddressValue('');
                                                setIsAddressLocked(false);
                                            }

                                            form.setData('hotel_id', null);
                                            form.setData('hotel_name', '');
                                        }
                                    }}
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

                            {form.data.location_type === 'hotel' && (
                                <div>
                                    <Label className="text-sm font-medium text-foreground">
                                        Hotel
                                    </Label>
                                    <div className="mt-1">
                                        {!showUnlistedHotel ? (
                                            <>
                                                <Autocomplete
                                                    value={form.data.hotel_id}
                                                    onChange={(id) => {
                                                        form.setData(
                                                            'hotel_id',
                                                            id,
                                                        );
                                                        const hotel =
                                                            hotels.find(
                                                                (h) =>
                                                                    h.id === id,
                                                            );

                                                        if (hotel) {
                                                            form.setData(
                                                                'hotel_name',
                                                                hotel.name,
                                                            );
                                                            form.setData(
                                                                'address_line1',
                                                                hotel.line1 ||
                                                                    '',
                                                            );
                                                            form.setData(
                                                                'address_line2',
                                                                hotel.line2 ||
                                                                    '',
                                                            );
                                                            form.setData(
                                                                'address_city',
                                                                hotel.city ||
                                                                    '',
                                                            );
                                                            form.setData(
                                                                'address_state',
                                                                hotel.state ||
                                                                    '',
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
                                                                    hotel.zip ||
                                                                    ''
                                                                }`.trim(),
                                                            );
                                                            setIsAddressLocked(
                                                                true,
                                                            );
                                                        }
                                                    }}
                                                    suggestions={
                                                        hotelSuggestions
                                                    }
                                                    onSearch={setHotelSearch}
                                                    placeholder="Search hotel..."
                                                    displayValue={
                                                        selectedHotelName
                                                    }
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowUnlistedHotel(
                                                            true,
                                                        );
                                                        form.setData(
                                                            'hotel_id',
                                                            null,
                                                        );
                                                        form.setData(
                                                            'hotel_name',
                                                            '',
                                                        );
                                                        // A custom hotel has no
                                                        // address on file — clear
                                                        // the auto-filled one and
                                                        // unlock the fields so the
                                                        // client can enter it.
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
                                                        setIsAddressLocked(
                                                            false,
                                                        );
                                                    }}
                                                    className="mt-1 cursor-pointer text-sm text-primary hover:underline"
                                                >
                                                    My hotel is not listed
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                <Input
                                                    value={
                                                        form.data.hotel_name ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        form.setData(
                                                            'hotel_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Enter hotel name"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowUnlistedHotel(
                                                            false,
                                                        );
                                                        form.setData(
                                                            'hotel_name',
                                                            '',
                                                        );
                                                    }}
                                                    className="mt-1 cursor-pointer text-sm text-primary hover:underline"
                                                >
                                                    Back to hotel list
                                                </button>
                                            </>
                                        )}
                                    </div>
                                    {(form.errors.hotel_id ||
                                        form.errors.hotel_name) && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {form.errors.hotel_id ||
                                                form.errors.hotel_name}
                                        </p>
                                    )}
                                </div>
                            )}

                            {(form.data.location_type !== 'private_home' ||
                                client_addresses.length === 0 ||
                                showManualAddressInput) && (
                                <BookingAddressFields
                                    form={form}
                                    isAddressLocked={isAddressLocked}
                                    addressValue={addressValue}
                                    canEdit={
                                        !(
                                            form.data.location_type ===
                                                'hotel' && !!form.data.hotel_id
                                        )
                                    }
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
                                    <Button
                                        size="xs"
                                        type="button"
                                        onClick={handleAddChild}
                                    >
                                        <Plus className="h-3 w-3" />
                                        Add Child
                                    </Button>
                                </div>
                                <div className="mt-1 grid gap-4">
                                    {form.data.new_children.map((child) => (
                                        <div
                                            key={child.id}
                                            className="rounded-lg border bg-card p-4"
                                        >
                                            <div className="mb-3 flex items-start justify-between">
                                                <p className="text-sm font-medium text-foreground">
                                                    Add New Child
                                                </p>
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        handleRemoveChild(
                                                            child.id,
                                                        )
                                                    }
                                                    size="sm"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-5">
                                                <div className="sm:col-span-1">
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Name
                                                    </Label>
                                                    <Input
                                                        value={child.name}
                                                        onChange={(e) =>
                                                            handleUpdateChild(
                                                                child.id,
                                                                'name',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Name"
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Gender
                                                    </Label>
                                                    <Select
                                                        value={child.gender}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            handleUpdateChild(
                                                                child.id,
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
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Month
                                                    </Label>
                                                    <Select
                                                        value={
                                                            child.birth_month ||
                                                            ''
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            handleUpdateChild(
                                                                child.id,
                                                                'birth_month',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
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
                                                            ].map((m, i) => (
                                                                <SelectItem
                                                                    key={m}
                                                                    value={String(
                                                                        i + 1,
                                                                    )}
                                                                >
                                                                    {m}
                                                                </SelectItem>
                                                            ))}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Year
                                                    </Label>
                                                    <Select
                                                        value={
                                                            child.birth_year ||
                                                            ''
                                                        }
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            handleUpdateChild(
                                                                child.id,
                                                                'birth_year',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Year" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {getChildBirthYearOptions().map(
                                                                (year) => (
                                                                    <SelectItem
                                                                        key={
                                                                            year
                                                                        }
                                                                        value={String(
                                                                            year,
                                                                        )}
                                                                    >
                                                                        {year}
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Age
                                                    </Label>
                                                    <div className="mt-3 text-sm text-foreground">
                                                        {calculateAge(
                                                            child.birth_year
                                                                ? parseInt(
                                                                      child.birth_year,
                                                                  )
                                                                : null,
                                                            child.birth_month
                                                                ? parseInt(
                                                                      child.birth_month,
                                                                  )
                                                                : null,
                                                        )}
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {!hasChildren && (
                                        <div className="rounded-lg border border-dashed bg-card/50 p-8 text-center">
                                            <p className="text-sm text-muted-foreground">
                                                No children added
                                            </p>
                                        </div>
                                    )}
                                </div>
                                {!hasChildren &&
                                    serviceRequiresChild(
                                        form.data.service_type,
                                    ) && (
                                        <p className="mt-2 text-sm text-destructive">
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

                            {/* eslint-disable-next-line no-constant-binary-expression */}
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
                                    <Button
                                        type="button"
                                        onClick={handleAddPet}
                                        size="xs"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Add Pet
                                    </Button>
                                </div>
                                <div className="mt-1 grid gap-4">
                                    {form.data.new_pets.map((pet) => (
                                        <div
                                            key={pet.id}
                                            className="rounded-lg border bg-card p-4"
                                        >
                                            <div className="mb-3 flex items-start justify-between">
                                                <p className="text-sm font-medium text-foreground">
                                                    Add New Pet
                                                </p>
                                                <Button
                                                    type="button"
                                                    onClick={() =>
                                                        handleRemovePet(pet.id)
                                                    }
                                                    size="sm"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </div>
                                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                                <div className="sm:col-span-1">
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Name
                                                    </Label>
                                                    <Input
                                                        value={pet.name}
                                                        onChange={(e) =>
                                                            handleUpdatePet(
                                                                pet.id,
                                                                'name',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Name"
                                                    />
                                                </div>
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Type
                                                    </Label>
                                                    <Select
                                                        value={pet.type}
                                                        onValueChange={(
                                                            value,
                                                        ) =>
                                                            handleUpdatePet(
                                                                pet.id,
                                                                'type',
                                                                value,
                                                            )
                                                        }
                                                    >
                                                        <SelectTrigger>
                                                            <SelectValue placeholder="Select type" />
                                                        </SelectTrigger>
                                                        <SelectContent>
                                                            {pet_types.map(
                                                                (type) => (
                                                                    <SelectItem
                                                                        key={
                                                                            type.value
                                                                        }
                                                                        value={
                                                                            type.value
                                                                        }
                                                                    >
                                                                        {
                                                                            type.label
                                                                        }
                                                                    </SelectItem>
                                                                ),
                                                            )}
                                                        </SelectContent>
                                                    </Select>
                                                </div>
                                                {pet.type === 'dog' && (
                                                    <div>
                                                        <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                            Breed
                                                        </Label>
                                                        <Input
                                                            value={pet.breed}
                                                            onChange={(e) =>
                                                                handleUpdatePet(
                                                                    pet.id,
                                                                    'breed',
                                                                    e.target
                                                                        .value,
                                                                )
                                                            }
                                                            placeholder="Breed"
                                                        />
                                                    </div>
                                                )}
                                                <div>
                                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                        Notes
                                                    </Label>
                                                    <Input
                                                        value={pet.notes}
                                                        onChange={(e) =>
                                                            handleUpdatePet(
                                                                pet.id,
                                                                'notes',
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Notes"
                                                    />
                                                </div>
                                            </div>
                                        </div>
                                    ))}
                                    {!hasPets && (
                                        <div className="rounded-lg border border-dashed bg-card/50 p-8 text-center">
                                            <p className="text-sm text-muted-foreground">
                                                No pets added
                                            </p>
                                        </div>
                                    )}
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

                            <div className="space-y-3">
                                {dates.map((dateEntry, index) => (
                                    <div
                                        key={dateEntry.id}
                                        className="rounded-[4px] border border-border bg-card p-[14px]"
                                    >
                                        <div className="mb-[10px] flex items-center justify-between">
                                            <span className="text-xs font-semibold tracking-[0.5px] text-foreground uppercase">
                                                Date {index + 1}
                                            </span>
                                            {index > 0 && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        handleRemoveDate(
                                                            dateEntry.id,
                                                        )
                                                    }
                                                    className="cursor-pointer border-none bg-none p-0 text-xs text-primary"
                                                >
                                                    × Remove
                                                </button>
                                            )}
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <Label>
                                                    Start Date/Time{' '}
                                                    <span className="text-primary">
                                                        *
                                                    </span>
                                                </Label>
                                                <DateTimePicker
                                                    value={
                                                        dateEntry.start_datetime
                                                    }
                                                    onChange={(value) => {
                                                        if (value) {
                                                            handleUpdateDate(
                                                                dateEntry.id,
                                                                'start_datetime',
                                                                value,
                                                            );
                                                        }
                                                    }}
                                                />
                                            </div>
                                            <div>
                                                <Label>
                                                    End Date/Time{' '}
                                                    <span className="text-primary">
                                                        *
                                                    </span>
                                                </Label>
                                                <DateTimePicker
                                                    value={
                                                        dateEntry.end_datetime
                                                    }
                                                    startTime={
                                                        dateEntry.start_datetime
                                                    }
                                                    minDurationHours={
                                                        bookingMinimumHours
                                                    }
                                                    onChange={(value) => {
                                                        if (value) {
                                                            handleUpdateDate(
                                                                dateEntry.id,
                                                                'end_datetime',
                                                                value,
                                                            );
                                                        }
                                                    }}
                                                />
                                            </div>
                                        </div>
                                        {dateOverlaps[dateEntry.id]?.length >
                                            0 && (
                                            <p className="mt-2 text-xs text-amber-700">
                                                ⚠ This overlaps with{' '}
                                                {dateOverlaps[
                                                    dateEntry.id
                                                ].join(', ')}
                                                .
                                            </p>
                                        )}
                                    </div>
                                ))}

                                <button
                                    type="button"
                                    onClick={handleAddDate}
                                    className="w-full cursor-pointer rounded-[4px] border border-dashed border-logo-teal bg-card py-3 text-sm font-medium text-foreground transition-[background] duration-150 hover:bg-teal-bg"
                                >
                                    + Add another date
                                </button>

                                {datetimeError && (
                                    <div className="text-sm text-destructive">
                                        {datetimeError}
                                    </div>
                                )}
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
