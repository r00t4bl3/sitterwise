import { ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { useState } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';

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

interface PersonalInfoSectionProps {
    form: any;
    clientMode: 'select' | 'input';
    setClientMode: (mode: 'select' | 'input') => void;
    addressMode: 'select' | 'input';
    setAddressMode: (mode: 'select' | 'input') => void;
    clientSuggestions: Array<{
        id: number;
        name: string;
        [key: string]: unknown;
    }>;
    clientAddresses: Array<{
        id: number;
        line1: string;
        line2?: string;
        city: string;
        state: string;
        zip: string;
    }>;
    clientChildren: Array<{
        id: number;
        name: string;
        gender: string | null;
        birth_year: number | null;
        birth_month: number | null;
    }>;
    clientPets: Array<{
        id: number;
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }>;
    newChildren: NewChild[];
    newPets: NewPet[];
    onAddChild: () => void;
    onRemoveChild: (tempId: string, id?: number) => void;
    onUpdateChild: (
        tempId: string,
        field: string,
        value: string | boolean,
    ) => void;
    onAddPet: () => void;
    onRemovePet: (tempId: string, id?: number) => void;
    onUpdatePet: (tempId: string, field: string, value: string) => void;
    saveChildrenPetsToProfile: boolean;
    onSaveChildrenPetsToProfileChange: (checked: boolean) => void;
    loadingSuggestions: boolean;
    selectedClientName: string;
    selectedClientType: string | null;
    handleClientSearch: (query: string) => void;
    handleClientChange: (clientId: number | null) => void;
    location_types: Array<{ value: string; label: string }>;
    sitter_preference_options: Array<{ value: string; label: string }>;
    client_type_options: Array<{ value: string; label: string }>;
    booking_attributes: Array<{
        id: number;
        name: string;
        slug: string;
        type: string;
        options: string[];
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
    hotelSuggestions: Array<{
        id: number;
        name: string;
        [key: string]: unknown;
    }>;
    selectedHotelName: string;
    handleHotelSearch: (query: string) => void;
    calculateAge: (
        birthYear: number | null,
        birthMonth: number | null,
    ) => string;
}

export function PersonalInfoSection({
    form,
    clientMode,
    setClientMode,
    addressMode,
    setAddressMode,
    clientSuggestions,
    clientAddresses,
    clientChildren,
    clientPets,
    newChildren,
    newPets,
    onAddChild,
    onRemoveChild,
    onUpdateChild,
    onAddPet,
    onRemovePet,
    onUpdatePet,
    saveChildrenPetsToProfile,
    onSaveChildrenPetsToProfileChange,
    loadingSuggestions,
    selectedClientName,
    selectedClientType,
    handleClientSearch,
    handleClientChange,
    location_types,
    sitter_preference_options,
    client_type_options,
    booking_attributes,
    hotels,
    hotelSuggestions,
    selectedHotelName,
    handleHotelSearch,
    calculateAge,
}: PersonalInfoSectionProps) {
    const [isOpen, setIsOpen] = useState(true);

    return (
        <details
            className="rounded-[3px] border border-border bg-card"
            open={isOpen}
            onToggle={(e) => setIsOpen(e.currentTarget.open)}
        >
            <summary className="flex cursor-pointer items-center justify-between bg-muted px-4 py-3 font-medium text-foreground">
                <span>Personal Info</span>
                {isOpen ? (
                    <ChevronUp className="h-4 w-4" />
                ) : (
                    <ChevronDown className="h-4 w-4" />
                )}
            </summary>
            <div className="space-y-4 p-4">
                <div>
                    <label className="text-sm font-medium text-foreground">
                        Client <span className="text-red-500">*</span>
                    </label>
                    <div className="mt-1">
                        <Autocomplete
                            value={form.data.client_id}
                            onChange={handleClientChange}
                            suggestions={clientSuggestions}
                            onSearch={handleClientSearch}
                            placeholder="Search client..."
                            loading={loadingSuggestions}
                            displayValue={selectedClientName}
                            showAddNew={true}
                            onAddNew={() => setClientMode('input')}
                        />
                    </div>
                </div>

                {clientMode === 'input' && (
                    <div className="space-y-3 rounded-[3px] border border-border p-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    First Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.new_client.first_name}
                                    onChange={(e) =>
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            first_name: e.target.value,
                                        })
                                    }
                                    placeholder="First Name"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Last Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.new_client.last_name}
                                    onChange={(e) =>
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            last_name: e.target.value,
                                        })
                                    }
                                    placeholder="Last Name"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Email <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="email"
                                value={form.data.new_client.email}
                                onChange={(e) =>
                                    form.setData('new_client', {
                                        ...form.data.new_client,
                                        email: e.target.value,
                                    })
                                }
                                placeholder="Email"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Cell Phone{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <input
                                type="text"
                                value={form.data.new_client.phone}
                                onChange={(e) =>
                                    form.setData('new_client', {
                                        ...form.data.new_client,
                                        phone: e.target.value,
                                    })
                                }
                                placeholder="Cell Phone"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                required
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Client Type{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.data.new_client.client_type}
                                onChange={(e) => {
                                    const newType = e.target.value;
                                    form.setData('new_client', {
                                        ...form.data.new_client,
                                        client_type: newType,
                                    });

                                    if (newType === 'resident') {
                                        form.setData(
                                            'location_type',
                                            'private_home',
                                        );
                                    } else if (newType === 'vacationer') {
                                        form.setData('location_type', 'hotel');
                                    }
                                }}
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                required
                            >
                                {client_type_options.map((type) => (
                                    <option key={type.value} value={type.value}>
                                        {type.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <Button
                            onClick={() => {
                                setClientMode('select');
                                form.setData('new_client', {
                                    first_name: '',
                                    last_name: '',
                                    email: '',
                                    phone: '',
                                    client_type: 'vacationer',
                                });
                            }}
                            variant="outline"
                            className="text-sm"
                        >
                            Cancel and select existing client
                        </Button>
                    </div>
                )}

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Location Type <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={form.data.location_type}
                        onChange={(e) =>
                            form.setData('location_type', e.target.value)
                        }
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        required
                    >
                        {location_types
                            .filter((type) => {
                                const clientType =
                                    form.data.client_id &&
                                    clientMode === 'select'
                                        ? selectedClientType
                                        : form.data.new_client?.client_type;

                                if (clientType === 'resident') {
                                    return type.value === 'private_home';
                                }

                                if (clientType === 'vacationer') {
                                    return (
                                        type.value === 'hotel' ||
                                        type.value === 'vacation_rental'
                                    );
                                }

                                return true;
                            })
                            .map((type) => (
                                <option key={type.value} value={type.value}>
                                    {type.label}
                                </option>
                            ))}
                    </select>
                </div>

                {form.data.location_type === 'private_home' &&
                    clientAddresses.length > 0 &&
                    addressMode === 'select' && (
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address
                            </label>
                            <select
                                value={form.data.address_id || ''}
                                onChange={(e) => {
                                    if (e.target.value === 'add_new') {
                                        setAddressMode('input');
                                        form.setData('address_id', null);
                                        form.setData('address_line1', '');
                                        form.setData('address_line2', '');
                                        form.setData('address_city', '');
                                        form.setData('address_state', '');
                                        form.setData('address_zip', '');
                                    } else if (e.target.value) {
                                        const addrId = Number(e.target.value);
                                        form.setData('address_id', addrId);
                                        const addr = clientAddresses.find(
                                            (a) => a.id === addrId,
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
                                        }
                                    } else {
                                        form.setData('address_id', null);
                                        form.setData('address_line1', '');
                                        form.setData('address_line2', '');
                                        form.setData('address_city', '');
                                        form.setData('address_state', '');
                                        form.setData('address_zip', '');
                                    }
                                }}
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            >
                                <option value="">Select address...</option>
                                <option value="add_new">
                                    + Add new address
                                </option>
                                {clientAddresses.map((addr) => (
                                    <option key={addr.id} value={addr.id}>
                                        {addr.line1}, {addr.city}, {addr.state}{' '}
                                        {addr.zip}
                                    </option>
                                ))}
                            </select>
                        </div>
                    )}

                {form.data.location_type === 'vacation_rental' && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Rental Platform
                        </label>
                        <select
                            value={form.data.rental_platform}
                            onChange={(e) =>
                                form.setData('rental_platform', e.target.value)
                            }
                            className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Select platform...</option>
                            {booking_attributes
                                .filter(
                                    (attr) => attr.slug === 'rental_platform',
                                )
                                .flatMap((attr) => attr.options || [])
                                .map((option) => (
                                    <option key={option} value={option}>
                                        {option.charAt(0).toUpperCase() +
                                            option.slice(1)}
                                    </option>
                                ))}
                        </select>
                    </div>
                )}

                {form.data.location_type === 'hotel' && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Hotel
                        </label>
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
                                    }
                                }}
                                suggestions={hotelSuggestions}
                                onSearch={handleHotelSearch}
                                placeholder="Search hotel..."
                                displayValue={selectedHotelName}
                            />
                        </div>
                    </div>
                )}

                <BookingAddressFields form={form} />

                <div>
                    <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-foreground">
                            Children
                        </label>
                        <button
                            type="button"
                            onClick={onAddChild}
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
                                {clientChildren.map((child) => (
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
                                                ? `${MONTH_ABBR[child.birth_month]} ${child.birth_year} (${calculateAge(
                                                      child.birth_year,
                                                      child.birth_month,
                                                  )})`
                                                : '-'}
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onRemoveChild('', child.id)
                                                }
                                                className="text-red-500 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {newChildren.map((child) => (
                                    <tr
                                        key={child.tempId}
                                        className="border-t border-border bg-muted/50"
                                    >
                                        <td className="px-3 py-2">
                                            <input
                                                type="text"
                                                value={child.name}
                                                onChange={(e) =>
                                                    onUpdateChild(
                                                        child.tempId,
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Name"
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <select
                                                value={child.gender}
                                                onChange={(e) =>
                                                    onUpdateChild(
                                                        child.tempId,
                                                        'gender',
                                                        e.target.value,
                                                    )
                                                }
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            >
                                                <option value="">Select</option>
                                                <option value="male">
                                                    Male
                                                </option>
                                                <option value="female">
                                                    Female
                                                </option>
                                            </select>
                                        </td>
                                        <td className="px-3 py-2">
                                            <div className="flex gap-1">
                                                <select
                                                    value={child.birth_month}
                                                    onChange={(e) =>
                                                        onUpdateChild(
                                                            child.tempId,
                                                            'birth_month',
                                                            e.target.value,
                                                        )
                                                    }
                                                    className="h-8 w-20 rounded-[3px] border border-input bg-background px-1 text-sm"
                                                >
                                                    <option value="">
                                                        Month
                                                    </option>
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
                                                        <option
                                                            key={m}
                                                            value={String(
                                                                i + 1,
                                                            )}
                                                        >
                                                            {m}
                                                        </option>
                                                    ))}
                                                </select>
                                                <input
                                                    type="text"
                                                    value={child.birth_year}
                                                    onChange={(e) =>
                                                        onUpdateChild(
                                                            child.tempId,
                                                            'birth_year',
                                                            e.target.value,
                                                        )
                                                    }
                                                    placeholder="Year"
                                                    className="h-8 w-16 rounded-[3px] border border-input bg-background px-2 text-sm"
                                                />
                                            </div>
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onRemoveChild(child.tempId)
                                                }
                                                className="text-red-500 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {clientChildren.length === 0 &&
                                    newChildren.length === 0 && (
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
                    {clientChildren.length === 0 &&
                        newChildren.length === 0 && (
                            <p className="text-sm text-destructive">
                                At least one child is required.
                            </p>
                        )}
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Special Needs / Allergies
                    </label>
                    <textarea
                        value={form.data.special_needs_notes || ''}
                        onChange={(e) =>
                            form.setData('special_needs_notes', e.target.value)
                        }
                        placeholder="Special needs notes"
                        className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                        rows={3}
                    />
                </div>

                {form.data.special_needs_notes && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Emergency Instructions
                        </label>
                        <textarea
                            value={form.data.emergency_instructions || ''}
                            onChange={(e) =>
                                form.setData(
                                    'emergency_instructions',
                                    e.target.value,
                                )
                            }
                            placeholder="What should your caregiver do in an emergency?"
                            className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                            rows={3}
                        />
                    </div>
                )}

                <div>
                    <div className="flex items-center justify-between">
                        <label className="text-sm font-medium text-foreground">
                            Pets
                        </label>
                        <button
                            type="button"
                            onClick={onAddPet}
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
                                {clientPets.map((pet) => (
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
                                                    onRemovePet('', pet.id)
                                                }
                                                className="text-red-500 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {newPets.map((pet) => (
                                    <tr
                                        key={pet.tempId}
                                        className="border-t border-border bg-muted/50"
                                    >
                                        <td className="px-3 py-2">
                                            <input
                                                type="text"
                                                value={pet.name}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'name',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Name"
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                type="text"
                                                value={pet.type}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'type',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Type"
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                type="text"
                                                value={pet.breed}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'breed',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Breed"
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <input
                                                type="text"
                                                value={pet.notes}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'notes',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Notes"
                                                className="h-8 w-full rounded-[3px] border border-input bg-background px-2 text-sm"
                                            />
                                        </td>
                                        <td className="px-3 py-2">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    onRemovePet(pet.tempId)
                                                }
                                                className="text-red-500 hover:text-red-700"
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                                {clientPets.length === 0 &&
                                    newPets.length === 0 && (
                                        <tr className="border-t border-border">
                                            <td
                                                colSpan={4}
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

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Other Adults Present
                    </label>
                    <input
                        type="text"
                        value={form.data.other_adults_present || ''}
                        onChange={(e) =>
                            form.setData('other_adults_present', e.target.value)
                        }
                        placeholder="Other adults present"
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                    />
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Sitter Preferences
                    </label>
                    <div className="mt-2 flex flex-wrap gap-2">
                        <div className="grid grid-cols-3 gap-4">
                            {sitter_preference_options.map((option) => (
                                <label
                                    key={option.value}
                                    className="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.sitter_preferences.includes(
                                            option.value,
                                        )}
                                        onChange={(e) => {
                                            const newPrefs = e.target.checked
                                                ? [
                                                      ...form.data
                                                          .sitter_preferences,
                                                      option.value,
                                                  ]
                                                : form.data.sitter_preferences.filter(
                                                      (pref: string) =>
                                                          pref !== option.value,
                                                  );
                                            form.setData(
                                                'sitter_preferences',
                                                newPrefs,
                                            );
                                        }}
                                        className="h-4 w-4 rounded border-input"
                                    />
                                    <span className="text-sm text-foreground">
                                        {option.label}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        How Did You Hear
                    </label>
                    <select
                        value={form.data.how_did_you_hear || ''}
                        onChange={(e) =>
                            form.setData('how_did_you_hear', e.target.value)
                        }
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                    >
                        <option value="">Select...</option>
                        <option value="concierge">Concierge</option>
                        <option value="friend_family">Friend/Family</option>
                        <option value="google">Google</option>
                        <option value="returning_client">
                            Returning Client
                        </option>
                        <option value="care_com">Care.com</option>
                        <option value="other">Other</option>
                    </select>
                </div>

                <label className="flex items-center gap-2">
                    <input
                        type="checkbox"
                        checked={saveChildrenPetsToProfile}
                        onChange={(e) =>
                            onSaveChildrenPetsToProfileChange(e.target.checked)
                        }
                        className="h-4 w-4 rounded border-input"
                    />
                    <span className="text-sm text-foreground">
                        Save changes to client profile
                    </span>
                </label>
            </div>
        </details>
    );
}
