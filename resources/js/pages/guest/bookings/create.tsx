import { Head, useForm, usePage } from '@inertiajs/react';
import { AlertCircle, ChevronDown, Plus } from 'lucide-react';
import { useState } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import BookingProgress from '@/components/booking-progress';
import InputError from '@/components/input-error';
import { ToasterMessage } from '@/components/toaster-message';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import GuestLayout from '@/layouts/guest-layout';
import { calculateAge, getChildBirthYearOptions } from '@/lib/age';
import { autoSetEndDateTime, formatDateTimeLocal, validateMinimumDuration } from '@/lib/datetime';
import { validatePhone } from '@/lib/phone';

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

interface DateEntry {
    id: string;
    start_datetime: string;
    end_datetime: string;
}

function validateForm(formData: Record<string, any>, isAddressLocked: boolean = false): Record<string, string> {
    const errors: Record<string, string> = {};

    if (!formData.client_first_name?.trim()) {
        errors.client_first_name = 'First name is required.';
    }

    if (!formData.client_last_name?.trim()) {
        errors.client_last_name = 'Last name is required.';
    }

    if (!formData.client_email?.trim()) {
        errors.client_email = 'Email is required.';
    } else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(formData.client_email)) {
        errors.client_email = 'Please enter a valid email address.';
    }

    if (!formData.client_phone?.trim()) {
        errors.client_phone = 'Phone is required.';
    } else {
        const phoneError = validatePhone(formData.client_phone);

        if (phoneError) {
            errors.client_phone = phoneError;
        }
    }

    if (formData.location_type !== 'hotel') {
        if (!isAddressLocked) {
            errors.address_line1 = 'Please select a valid San Diego area address from the suggestions.';
        }

        if (!formData.address_line1?.trim()) {
            errors.address_line1 = 'Address is required.';
        }

        if (!formData.address_city?.trim()) {
            errors.address_city = 'City is required.';
        }

        if (!formData.address_state?.trim()) {
            errors.address_state = 'State is required.';
        }

        if (!formData.address_zip?.trim()) {
            errors.address_zip = 'ZIP code is required.';
        }
    }

    if (formData.start_datetime && formData.end_datetime) {
        const start = new Date(formData.start_datetime);
        const end = new Date(formData.end_datetime);
        const diffHours = (end.getTime() - start.getTime()) / (1000 * 60 * 60);

        if (diffHours < 4) {
            errors.end_datetime = 'Booking must be at least 4 hours long.';
        }
    }

    const dateEntries = formData.dates || [];

    for (let i = 0; i < dateEntries.length; i++) {
        for (let j = i + 1; j < dateEntries.length; j++) {
            const aStart = new Date(dateEntries[i].start_datetime).getTime();
            const aEnd = new Date(dateEntries[i].end_datetime).getTime();
            const bStart = new Date(dateEntries[j].start_datetime).getTime();
            const bEnd = new Date(dateEntries[j].end_datetime).getTime();

            if (aStart < bEnd && bStart < aEnd) {
                errors.dates = `Date ${i + 1} overlaps with Date ${j + 1}. Please adjust the times.`;
                break;
            }
        }

        if (errors.dates) {
break;
}
    }

    if (formData.location_type === 'hotel') {
        if (!formData.hotel_id && !formData.hotel_name?.trim()) {
            errors.hotel_name = 'Please select or enter a hotel.';
        }
    }

    if (!formData.new_children?.length) {
        errors.new_children = 'Please add at least one child.';
    }

    return errors;
}

function generateId(): string {
    return Math.random().toString(36).substr(2, 9);
}

function findDateOverlaps(
    dates: DateEntry[],
): Record<string, string[]> {
    const overlaps: Record<string, string[]> = {};

    for (let i = 0; i < dates.length; i++) {
        for (let j = i + 1; j < dates.length; j++) {
            const a = dates[i];
            const b = dates[j];
            const aStart = new Date(a.start_datetime).getTime();
            const aEnd = new Date(a.end_datetime).getTime();
            const bStart = new Date(b.start_datetime).getTime();
            const bEnd = new Date(b.end_datetime).getTime();

            if (aStart < bEnd && bStart < aEnd) {
                const aLabel = `Date ${i + 1}`;
                const bLabel = `Date ${j + 1}`;

                (overlaps[a.id] ??= []).push(bLabel);
                (overlaps[b.id] ??= []).push(aLabel);
            }
        }
    }

    return overlaps;
}

function getPreferenceLabel(option: { value: string; label: string }): string {
    if (option.value === 'child_is_sick') {
        return 'Sick Day Care';
    }

    return option.label;
}

export default function GuestBookingCreate() {
    const {
        service_types,
        location_types,
        hotels,
        pet_types,
        booking_attributes,
        sitter_preferences,
        discovery_sources,
    } = usePage().props as unknown as {
        service_types: Array<{ value: string; label: string }>;
        location_types: Array<{ value: string; label: string }>;
        hotels: Array<{
            id: number;
            name: string;
            line1: string | null;
            line2: string | null;
            city: string | null;
            state: string | null;
            zip: string | null;
        }>;
        pet_types: Array<{ value: string; label: string }>;
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

    const [isFormSubmitting, setIsFormSubmitting] = useState(false);
    const tomorrow = new Date();
    tomorrow.setDate(tomorrow.getDate() + 1);
    tomorrow.setHours(9, 0, 0, 0);

    const defaultEnd = new Date(tomorrow.getTime() + 4 * 60 * 60 * 1000);
    const defaultStartStr = formatDateTimeLocal(tomorrow);
    const defaultEndStr = formatDateTimeLocal(defaultEnd);

    const form = useForm({
        client_first_name: '',
        client_last_name: '',
        client_email: '',
        client_phone: '',
        service_type: 'babysitter',
        location_type: 'private_home',
        start_datetime: defaultStartStr,
        end_datetime: defaultEndStr,
        dates: [{ start_datetime: defaultStartStr, end_datetime: defaultEndStr }] as Array<{ start_datetime: string; end_datetime: string }>,
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
        new_children: [
            { tempId: generateId(), name: '', gender: '', birth_month: '', birth_year: '' },
        ] as NewChild[],
        new_pets: [] as NewPet[],
    });

    const [dates, setDates] = useState<DateEntry[]>([
        {
            id: 'date-1',
            start_datetime: defaultStartStr,
            end_datetime: defaultEndStr,
        },
    ]);

    const [isAboutYouOpen, setIsAboutYouOpen] = useState(true);
    const [isBookingOpen, setIsBookingOpen] = useState(true);

    const [isAddressLocked, setIsAddressLocked] = useState(false);
    const [addressValue, setAddressValue] = useState('');
    const [hotelSearch, setHotelSearch] = useState('');
    const [showUnlistedHotel, setShowUnlistedHotel] = useState(false);
    const [validationErrors, setValidationErrors] = useState<Record<string, string>>({});

    const datetimeError = validateMinimumDuration(
        form.data.start_datetime,
        form.data.end_datetime,
    );

    const today = new Date();
    const todayDateStr = `${today.getFullYear()}-${String(today.getMonth() + 1).padStart(2, '0')}-${String(today.getDate()).padStart(2, '0')}`;
    const dateOverlaps = findDateOverlaps(dates);

    const sameDayWarning = dates.some(
        (d) => d.start_datetime?.startsWith(todayDateStr),
    );

    const hotelSuggestions = hotels
        .filter((h) => h.name.toLowerCase().includes(hotelSearch.toLowerCase()))
        .map((h) => ({ id: h.id, name: h.name }));

    const selectedHotelName =
        hotels.find((h) => h.id === form.data.hotel_id)?.name || '';

    const syncDatesToForm = (allDates: DateEntry[]) => {
        if (allDates.length > 0) {
            form.setData('start_datetime', allDates[0].start_datetime);
            form.setData('end_datetime', allDates[0].end_datetime);
            form.setData(
                'dates',
                allDates.map((d) => ({ start_datetime: d.start_datetime, end_datetime: d.end_datetime })),
            );
        }
    };

    const handleAddDate = () => {
        const nextDate = new Date(
            tomorrow.getTime() + dates.length * 24 * 60 * 60 * 1000,
        );
        const endDate = new Date(nextDate.getTime() + 4 * 60 * 60 * 1000);
        const newEntry: DateEntry = {
            id: generateId(),
            start_datetime: formatDateTimeLocal(nextDate),
            end_datetime: formatDateTimeLocal(endDate),
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

            if (field === 'start_datetime') {
                next.end_datetime = autoSetEndDateTime(value);
            }

            return next;
        });
        setDates(updated);
        syncDatesToForm(updated);
    };

    const handleAddChild = () => {
        const newChild: NewChild = {
            tempId: generateId(),
            name: '',
            gender: '',
            birth_month: '',
            birth_year: '',
        };
        form.setData('new_children', [...form.data.new_children, newChild]);
    };

    const handleRemoveChild = (tempId: string) => {
        form.setData(
            'new_children',
            form.data.new_children.filter((c) => c.tempId !== tempId),
        );
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
            tempId: generateId(),
            name: '',
            type: '',
            breed: '',
            notes: '',
        };
        form.setData('new_pets', [...form.data.new_pets, newPet]);
    };

    const handleRemovePet = (tempId: string) => {
        form.setData(
            'new_pets',
            form.data.new_pets.filter((p) => p.tempId !== tempId),
        );
    };

    const handleUpdatePet = (tempId: string, field: string, value: string) => {
        const updated = form.data.new_pets.map((p) =>
            p.tempId === tempId ? { ...p, [field]: value } : p,
        );
        form.setData('new_pets', updated);
    };

    const handleBlurValidate = (field: string) => {
        const allErrors = validateForm(form.data, isAddressLocked);
        setValidationErrors((prev) => {
            if (allErrors[field]) {
                return { ...prev, [field]: allErrors[field] };
            }

            const next = { ...prev };
            delete next[field];

            return next;
        });
    };

    const handleSubmit = () => {
        if (isFormSubmitting) {
            return;
        }

        const clientErrors = validateForm(form.data, isAddressLocked);
        setValidationErrors(clientErrors);

        if (Object.keys(clientErrors).length > 0) {
            const firstField = Object.keys(clientErrors)[0];
            const el = document.querySelector<HTMLElement>(
                `[name="${firstField}"], [data-field="${firstField}"]`,
            );
            el?.scrollIntoView({ behavior: 'smooth', block: 'center' });
            el?.focus();

            return;
        }

        setIsFormSubmitting(true);
        form.post('/book', {
            onSuccess: () => {
                setIsFormSubmitting(false);
            },
            onError: () => {
                setIsFormSubmitting(false);
            },
        });
    };

    const isFormIncomplete = Object.keys(validateForm(form.data, isAddressLocked)).length > 0;

    const hasNewChildren = form.data.new_children.length > 0;
    const hasNewPets = form.data.new_pets.length > 0;

    return (
        <GuestLayout>
            <Head title="Book a Caregiver" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* HERO */}
                <div className="py-3 pb-5 text-center">
                    <h1 className="mb-4 font-serif text-[32px] font-medium text-foreground">
                        It's you! We're so happy you're here.
                    </h1>
                    <span className="mb-4 block text-[26px] leading-none text-primary">
                        ♥
                    </span>
                    <p className="mb-2 text-[15px] text-muted-foreground">
                        Tell us about your family, your plans, and what you need
                        — we'll handle the rest.
                    </p>
                    <p className="text-[13px] text-muted-foreground italic">
                        Matching San Diego families with trusted caregivers
                        since 1981.
                    </p>
                </div>

                <BookingProgress currentStep={1} />

                {Object.keys(form.errors).length > 0 && (
                    <div className="flex items-start gap-3 rounded-lg border border-destructive bg-destructive/10 p-4 text-sm text-destructive">
                        <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                        <div>
                            <p className="mb-1 font-medium">
                                Please fix the following errors before continuing:
                            </p>
                            <ul className="list-inside list-disc space-y-0.5">
                                {Object.entries(form.errors).map(
                                    ([key, message]) => (
                                        <li key={key}>{message as string}</li>
                                    ),
                                )}
                            </ul>
                        </div>
                    </div>
                )}

                {/* CARD 1: ABOUT YOU */}
                <div className="overflow-hidden rounded-[3px] border border-border bg-card">
                    <button
                        type="button"
                        onClick={() => setIsAboutYouOpen(!isAboutYouOpen)}
                        className="flex w-full cursor-pointer items-center justify-between bg-teal-bg px-[22px] py-4 text-left"
                    >
                        <div>
                            <h2 className="m-0 font-serif text-base font-semibold text-foreground">
                                About You
                            </h2>
                            <p className="mt-[3px] text-xs text-muted-foreground italic">
                                So we know who to send the confirmation to.
                            </p>
                        </div>
                        <ChevronDown
                            className={`h-4 w-4 text-foreground transition-transform duration-200 ${isAboutYouOpen ? 'rotate-180' : ''}`}
                        />
                    </button>

                    {isAboutYouOpen && (
                        <div className="space-y-4 p-6">
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>
                                        First Name{' '}
                                        <span className="text-primary" aria-hidden="true">*</span>
                                    </Label>
                                    <Input
                                        aria-required="true"
                                        aria-invalid={
                                            !!(
                                                validationErrors.client_first_name ||
                                                form.errors.client_first_name
                                            )
                                        }
                                        value={form.data.client_first_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'client_first_name',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            handleBlurValidate(
                                                'client_first_name',
                                            )
                                        }
                                        placeholder="First name"
                                    />
                                    {(validationErrors.client_first_name ||
                                        form.errors.client_first_name) && (
                                        <InputError
                                            message={
                                                validationErrors.client_first_name ||
                                                form.errors.client_first_name
                                            }
                                        />
                                    )}
                                </div>
                                <div>
                                    <Label>
                                        Last Name{' '}
                                        <span className="text-primary" aria-hidden="true">*</span>
                                    </Label>
                                    <Input
                                        aria-required="true"
                                        aria-invalid={
                                            !!(
                                                validationErrors.client_last_name ||
                                                form.errors.client_last_name
                                            )
                                        }
                                        value={form.data.client_last_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'client_last_name',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            handleBlurValidate(
                                                'client_last_name',
                                            )
                                        }
                                        placeholder="Last name"
                                    />
                                    {(validationErrors.client_last_name ||
                                        form.errors.client_last_name) && (
                                        <InputError
                                            message={
                                                validationErrors.client_last_name ||
                                                form.errors.client_last_name
                                            }
                                        />
                                    )}
                                </div>
                            </div>

                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <Label>
                                        Email{' '}
                                        <span className="text-primary" aria-hidden="true">*</span>
                                    </Label>
                                    <Input
                                        type="email"
                                        aria-required="true"
                                        aria-invalid={
                                            !!(
                                                validationErrors.client_email ||
                                                form.errors.client_email
                                            )
                                        }
                                        value={form.data.client_email}
                                        onChange={(e) =>
                                            form.setData(
                                                'client_email',
                                                e.target.value,
                                            )
                                        }
                                        onBlur={() =>
                                            handleBlurValidate(
                                                'client_email',
                                            )
                                        }
                                        placeholder="your@email.com"
                                    />
                                    {(validationErrors.client_email ||
                                        form.errors.client_email) && (
                                        <InputError
                                            message={
                                                validationErrors.client_email ||
                                                form.errors.client_email
                                            }
                                        />
                                    )}
                                </div>
                                <div>
                                    <PhoneInput
                                        name="client_phone"
                                        value={form.data.client_phone}
                                        onChange={(v) =>
                                            form.setData('client_phone', v)
                                        }
                                        onBlur={() =>
                                            handleBlurValidate(
                                                'client_phone',
                                            )
                                        }
                                        error={
                                            validationErrors.client_phone ||
                                            form.errors.client_phone
                                        }
                                        required
                                    />
                                </div>
                            </div>

                            <div>
                                <Label>How did you find us?</Label>
                                <Select
                                    value={form.data.how_did_you_hear || ''}
                                    onValueChange={(value) =>
                                        form.setData('how_did_you_hear', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select..." />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {discovery_sources.map((source) => (
                                            <SelectItem
                                                key={source.value}
                                                value={source.value}
                                            >
                                                {source.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    )}
                </div>

                {/* CARD 2: ABOUT YOUR BOOKING */}
                <div className="overflow-hidden rounded-[3px] border border-border bg-card">
                    <button
                        type="button"
                        onClick={() => setIsBookingOpen(!isBookingOpen)}
                        className="flex w-full cursor-pointer items-center justify-between bg-teal-bg px-[22px] py-4 text-left"
                    >
                        <div>
                            <h2 className="m-0 font-serif text-base font-semibold text-foreground">
                                About Your Booking
                            </h2>
                            <p className="mt-[3px] text-xs text-muted-foreground italic">
                                The more you share, the better we can match.
                            </p>
                        </div>
                        <ChevronDown
                            className={`h-4 w-4 text-foreground transition-transform duration-200 ${isBookingOpen ? 'rotate-180' : ''}`}
                        />
                    </button>

                    {isBookingOpen && (
                        <div className="space-y-[26px] p-6">
                            {/* 3.1 WHEN & WHERE */}
                            <div className="border-l-[3px] border-logo-teal pl-[18px]">
                                <h3 className="mb-[14px] text-xs font-semibold tracking-[0.8px] text-foreground uppercase">
                                    When &amp; Where
                                </h3>

                                <div className="mb-[14px] grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <Label>
                                            Service Type{' '}
                                            <span className="text-primary">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={form.data.service_type}
                                            onValueChange={(value) =>
                                                form.setData(
                                                    'service_type',
                                                    value,
                                                )
                                            }
                                        >
                                            <SelectTrigger>
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
                                    <div>
                                        <Label>
                                            Location Type{' '}
                                            <span className="text-primary">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={form.data.location_type}
                                            onValueChange={(value) => {
                                                form.setData(
                                                    'location_type',
                                                    value,
                                                );

                                                if (value !== 'hotel') {
                                                    form.setData(
                                                        'hotel_id',
                                                        null,
                                                    );
                                                }

                                                if (
                                                    value !== 'vacation_rental'
                                                ) {
                                                    form.setData(
                                                        'rental_platform',
                                                        '',
                                                    );
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
                                </div>

                                {form.data.location_type ===
                                    'vacation_rental' && (
                                    <div className="mb-[14px]">
                                        <Label>Rental Platform</Label>
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
                                    <div className="mb-[14px]">
                                        <Label>Hotel</Label>
                                        {!showUnlistedHotel ? (
                                            <>
                                                <Autocomplete
                                                    value={form.data.hotel_id}
                                                    onChange={(id) => {
                                                        form.setData('hotel_id', id);
                                                        const hotel = hotels.find(
                                                            (h) => h.id === id,
                                                        );

                                                        if (hotel) {
                                                            form.setData(
                                                                'hotel_name',
                                                                hotel.name,
                                                            );
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
                                                                `${hotel.line1 || ''}${hotel.line2 ? `, ${hotel.line2}` : ''}, ${hotel.city || ''}, ${hotel.state || ''} ${hotel.zip || ''}`.trim(),
                                                            );
                                                            setIsAddressLocked(true);
                                                        }
                                                    }}
                                                    suggestions={hotelSuggestions}
                                                    onSearch={setHotelSearch}
                                                    placeholder="Search hotel..."
                                                    displayValue={selectedHotelName}
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowUnlistedHotel(true);
                                                        form.setData('hotel_id', null);
                                                        form.setData('hotel_name', '');
                                                    }}
                                                    className="mt-1 cursor-pointer text-sm text-primary hover:underline"
                                                >
                                                    My hotel is not listed
                                                </button>
                                            </>
                                        ) : (
                                            <>
                                                <Input
                                                    value={form.data.hotel_name}
                                                    onChange={(e) =>
                                                        form.setData('hotel_name', e.target.value)
                                                    }
                                                    placeholder="Enter hotel name"
                                                />
                                                <button
                                                    type="button"
                                                    onClick={() => {
                                                        setShowUnlistedHotel(false);
                                                        form.setData('hotel_name', '');
                                                    }}
                                                    className="mt-1 cursor-pointer text-sm text-primary hover:underline"
                                                >
                                                    Back to hotel list
                                                </button>
                                            </>
                                        )}
                                        {(validationErrors.hotel_name || form.errors.hotel_name) && (
                                            <InputError
                                                message={validationErrors.hotel_name || form.errors.hotel_name}
                                            />
                                        )}
                                    </div>
                                )}

                                {/* Date blocks */}
                                {dates.map((dateEntry, index) => (
                                    <div
                                        key={dateEntry.id}
                                        className="mb-[10px] rounded-[4px] border border-border bg-card p-[14px]"
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
                                        {dateOverlaps[dateEntry.id]?.length > 0 && (
                                            <p className="mt-2 text-xs text-amber-700">
                                                ⚠ This overlaps with{' '}
                                                {dateOverlaps[dateEntry.id].join(', ')}.
                                            </p>
                                        )}
                                    </div>
                                ))}

                                {sameDayWarning && (
                                    <p className="mt-3 rounded-[4px] border border-amber-300 bg-amber-50 px-3 py-2 text-xs text-amber-800">
                                        Heads up: this is a same-day booking —
                                        extra fees may apply and availability is
                                        limited.
                                    </p>
                                )}

                                <button
                                    type="button"
                                    onClick={handleAddDate}
                                    className="mt-1 w-full cursor-pointer rounded-[4px] border border-dashed border-logo-teal bg-card py-3 text-sm font-medium text-foreground transition-[background] duration-150 hover:bg-teal-bg"
                                >
                                    + Add another date
                                </button>

                                {datetimeError && (
                                    <p className="mt-2 text-sm text-destructive">
                                        {datetimeError}
                                    </p>
                                )}
                                {(validationErrors.dates ||
                                    form.errors.dates) && (
                                    <InputError
                                        message={
                                            validationErrors.dates ||
                                            form.errors.dates
                                        }
                                    />
                                )}
                                {(validationErrors.end_datetime ||
                                    form.errors.end_datetime) && (
                                    <InputError
                                        message={
                                            validationErrors.end_datetime ||
                                            form.errors.end_datetime
                                        }
                                    />
                                )}

                                {/* Address */}
                                <div className="mt-4">
                                    <BookingAddressFields
                                        form={form}
                                        isAddressLocked={isAddressLocked}
                                        addressValue={addressValue}
                                        errors={form.errors}
                                        onAddressLock={(
                                            locked,
                                            newAddressValue,
                                        ) => {
                                            setIsAddressLocked(locked);

                                            if (locked && newAddressValue) {
                                                setAddressValue(
                                                    newAddressValue,
                                                );
                                            }

                                            if (!locked) {
                                                setAddressValue('');
                                            }
                                        }}
                                    />
                                </div>

                                {/* Complex booking note */}
                                <div className="mt-4 rounded-[4px] border border-[#F0C5BA] bg-blush p-[14px_16px] text-xs leading-relaxed text-foreground italic">
                                    <strong className="font-medium not-italic">
                                        Need different locations or a more
                                        complex schedule?
                                    </strong>{' '}
                                    Add the details in{' '}
                                    <em>Notes to Sitterwise</em> at the bottom
                                    of the form, and our Care Team will take it
                                    from there.
                                </div>
                            </div>

                            {/* 3.2 WHO'S BEING CARED FOR */}
                            <div className="border-l-[3px] border-logo-teal pl-[18px]">
                                <h3 className="mb-[14px] text-xs font-semibold tracking-[0.8px] text-foreground uppercase">
                                    Who's Being Cared For
                                </h3>

                                <div className="mb-[10px] flex items-center justify-between">
                                    <Label className="text-sm font-medium text-foreground">
                                        Children
                                    </Label>
                                    <Button
                                        type="button"
                                        onClick={handleAddChild}
                                        size="xs"
                                    >
                                        <Plus className="h-3 w-3" />
                                        Add Child
                                    </Button>
                                </div>

                                {/* Child forms */}
                                {form.data.new_children.map((child) => (
                                    <div
                                        key={child.tempId}
                                        className="mb-[10px] rounded-[4px] border border-border p-4"
                                    >
                                        <div className="mb-2 flex items-start justify-between">
                                            <strong className="flex items-center gap-2 text-[14px] font-medium text-foreground">
                                                <span className="text-primary">
                                                    ♥
                                                </span>
                                                Add New Child
                                            </strong>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemoveChild(
                                                        child.tempId,
                                                    )
                                                }
                                                className="cursor-pointer border-none bg-none p-0 text-xs text-primary"
                                            >
                                                × Remove
                                            </button>
                                        </div>
                                        <p className="mb-[14px] text-xs text-muted-foreground italic">
                                            Birth month and year — we'll keep
                                            their age up to date as they grow.
                                        </p>
                                        <div className="grid grid-cols-1 gap-[10px] sm:grid-cols-[1.5fr_1fr_1fr_1fr_1fr] sm:items-end">
                                            <div>
                                                <Label className="text-[11px] font-semibold tracking-[0.4px] text-muted-foreground uppercase">
                                                    Name
                                                </Label>
                                                <Input
                                                    value={child.name}
                                                    onChange={(e) =>
                                                        handleUpdateChild(
                                                            child.tempId,
                                                            'name',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Name"
                                                />
                                            </div>
                                            <div>
                                                <Label className="text-[11px] font-semibold tracking-[0.4px] text-muted-foreground uppercase">
                                                    Gender
                                                </Label>
                                                <Select
                                                    value={child.gender}
                                                    onValueChange={(value) =>
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
                                            </div>
                                            <div>
                                                <Label className="text-[11px] font-semibold tracking-[0.4px] text-muted-foreground uppercase">
                                                    Month
                                                </Label>
                                                <Select
                                                    value={child.birth_month}
                                                    onValueChange={(value) =>
                                                        handleUpdateChild(
                                                            child.tempId,
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
                                                <Label className="text-[11px] font-semibold tracking-[0.4px] text-muted-foreground uppercase">
                                                    Year
                                                </Label>
                                                <Select
                                                    value={
                                                        child.birth_year || ''
                                                    }
                                                    onValueChange={(value) =>
                                                        handleUpdateChild(
                                                            child.tempId,
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
                                                                    key={year}
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
                                            {child.birth_month &&
                                                child.birth_year && (
                                                    <div>
                                                        <Label className="text-[11px] font-semibold tracking-[0.4px] text-muted-foreground uppercase">
                                                            Age
                                                        </Label>
                                                        <div className="pb-[11px] text-sm text-muted-foreground italic">
                                                            {calculateAge(
                                                                parseInt(
                                                                    child.birth_year,
                                                                ),
                                                                parseInt(
                                                                    child.birth_month,
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                        </div>
                                    </div>
                                ))}

                                {!hasNewChildren && (
                                    <div className="rounded-[4px] border border-dashed border-border bg-blush p-6 text-center">
                                        <p className="text-xs text-muted-foreground italic">
                                            Add each child so we can match the
                                            right caregiver
                                        </p>
                                    </div>
                                )}

                                {(validationErrors.new_children || form.errors.new_children) && (
                                    <InputError
                                        message={
                                            validationErrors.new_children ||
                                            form.errors.new_children
                                        }
                                    />
                                )}

                                {/* Special Needs */}
                                <div className="mt-4">
                                    <Label>Special Needs / Allergies</Label>
                                    <Textarea
                                        value={
                                            form.data.special_needs_notes || ''
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'special_needs_notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Allergies, medications, anything we should know..."
                                    />
                                </div>

                                {/* Hidden emergency instructions */}
                                {/* eslint-disable-next-line no-constant-binary-expression */}
                                {false && form.data.special_needs_notes && (
                                    <div className="mt-4">
                                        <Label>Emergency Instructions</Label>
                                        <Textarea
                                            value={
                                                form.data
                                                    .emergency_instructions ||
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
                            </div>

                            {/* 3.3 YOUR HOUSEHOLD */}
                            <div className="border-l-[3px] border-logo-teal pl-[18px]">
                                <h3 className="mb-[14px] text-xs font-semibold tracking-[0.8px] text-foreground uppercase">
                                    Your Household
                                </h3>

                                <div className="mb-[10px] flex items-center justify-between">
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

                                {form.data.new_pets.map((pet) => (
                                    <div
                                        key={pet.tempId}
                                        className="mb-4 rounded-lg border bg-card p-4"
                                    >
                                        <div className="mb-3 flex items-start justify-between">
                                            <p className="text-sm font-medium text-foreground">
                                                Add New Pet
                                            </p>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemovePet(pet.tempId)
                                                }
                                                className="cursor-pointer border-none bg-none text-xs text-primary"
                                            >
                                                × Remove
                                            </button>
                                        </div>
                                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                            <div>
                                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                    Name
                                                </Label>
                                                <Input
                                                    value={pet.name}
                                                    onChange={(e) =>
                                                        handleUpdatePet(
                                                            pet.tempId,
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
                                                    onValueChange={(value) =>
                                                        handleUpdatePet(
                                                            pet.tempId,
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
                                                                    {type.label}
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
                                                                pet.tempId,
                                                                'breed',
                                                                e.target.value,
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
                                                            pet.tempId,
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

                                {!hasNewPets && (
                                    <div className="mb-4 rounded-[4px] border border-dashed border-border bg-blush p-6 text-center">
                                        <p className="text-xs text-muted-foreground italic">
                                            Add any pets your caregiver should
                                            know about
                                        </p>
                                    </div>
                                )}

                                {/* Other adults present */}
                                <div className="mt-[18px]">
                                    <div className="flex items-start gap-[10px]">
                                        <Checkbox
                                            id="other_adults_present"
                                            checked={
                                                !!form.data.other_adults_present
                                            }
                                            onCheckedChange={(checked) =>
                                                form.setData(
                                                    'other_adults_present',
                                                    checked ? '1' : '',
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor="other_adults_present"
                                            className="cursor-pointer text-sm font-medium"
                                        >
                                            Will anyone else be home?
                                        </Label>
                                    </div>
                                    {form.data.other_adults_present === '1' && (
                                        <div className="mt-[10px] ml-[26px] rounded-r-[4px] border-l-[3px] border-logo-teal bg-teal-bg p-[12px_14px] text-xs leading-relaxed text-foreground italic">
                                            <strong className="font-medium not-italic">
                                                Please add a quick note below
                                            </strong>{' '}
                                            in <em>Notes for Caregiver</em>{' '}
                                            letting your caregiver know who else
                                            will be home (a spouse working from
                                            home, an older sibling, a
                                            grandparent, etc.).
                                        </div>
                                    )}
                                </div>
                            </div>

                            {/* 3.4 SITTER PREFERENCES */}
                            <div className="border-l-[3px] border-logo-teal pl-[18px]">
                                <h3 className="mb-[14px] text-xs font-semibold tracking-[0.8px] text-foreground uppercase">
                                    Sitter Preferences
                                </h3>
                                <p className="mb-3 text-xs text-muted-foreground italic">
                                    Any of these apply? Check what fits — we'll
                                    factor it into the match.
                                </p>
                                <div className="grid grid-cols-1 gap-[12px_18px] sm:grid-cols-2">
                                    {sitter_preferences.map((option) => (
                                        <label
                                            key={option.value}
                                            className="flex cursor-pointer items-center gap-2 text-sm text-foreground"
                                        >
                                            <Checkbox
                                                id={`pref-${option.value}`}
                                                checked={form.data.sitter_preferences.includes(
                                                    option.value,
                                                )}
                                                onCheckedChange={(checked) => {
                                                    const newPrefs = checked
                                                        ? [
                                                              ...form.data
                                                                  .sitter_preferences,
                                                              option.value,
                                                          ]
                                                        : form.data.sitter_preferences.filter(
                                                              (pref) =>
                                                                  pref !==
                                                                  option.value,
                                                          );
                                                    form.setData(
                                                        'sitter_preferences',
                                                        newPrefs,
                                                    );
                                                }}
                                            />
                                            {getPreferenceLabel(option)}
                                        </label>
                                    ))}
                                </div>
                            </div>

                            {/* 3.5 NOTES */}
                            <div className="border-l-[3px] border-logo-teal pl-[18px]">
                                <h3 className="mb-[14px] text-xs font-semibold tracking-[0.8px] text-foreground uppercase">
                                    Notes
                                </h3>
                                <div>
                                    <Label>Notes for Caregiver</Label>
                                    <Textarea
                                        value={form.data.caregiver_notes || ''}
                                        onChange={(e) =>
                                            form.setData(
                                                'caregiver_notes',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Plans for the day, dress code, what to bring, anything to expect — pool time, a movie night, attending an event together..."
                                    />
                                </div>
                                <div className="mt-[14px]">
                                    <Label>Notes to Sitterwise</Label>
                                    <Textarea
                                        value={
                                            form.data.notes_to_sitterwise || ''
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'notes_to_sitterwise',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Anything you'd like our Care Team to know — including extra dates or different locations"
                                    />
                                </div>
                            </div>
                        </div>
                    )}
                </div>

                {/* SUBMIT */}
                <div className="mt-6 flex flex-col items-end gap-3">
                    <p className="max-w-[480px] text-right text-xs text-muted-foreground italic">
                        Next, you'll add a payment method to confirm your
                        reservation. We'll begin matching as soon as it's on
                        file.
                    </p>
                    <Button
                        onClick={handleSubmit}
                        aria-disabled={isFormIncomplete || isFormSubmitting || undefined}
                    >
                        {isFormSubmitting ? <Spinner /> : null}
                        {isFormSubmitting
                            ? 'Submitting...'
                            : 'Continue to Payment'}
                    </Button>
                </div>
            </div>
        </GuestLayout>
    );
}
