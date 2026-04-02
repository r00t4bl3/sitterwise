import { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { Autocomplete } from '@/components/ui/autocomplete';

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
        special_needs: boolean;
    }>;
    clientPets: Array<{
        id: number;
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }>;
    loadingSuggestions: boolean;
    selectedClientName: string;
    handleClientSearch: (query: string) => void;
    handleClientChange: (clientId: number | null) => void;
    location_types: Array<{ value: string; label: string }>;
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
    loadingSuggestions,
    selectedClientName,
    handleClientSearch,
    handleClientChange,
    location_types,
    booking_attributes,
    hotels,
    hotelSuggestions,
    selectedHotelName,
    handleHotelSearch,
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
                                Cell Phone
                            </label>
                            <input
                                type="text"
                                value={form.data.new_client.cell_phone}
                                onChange={(e) =>
                                    form.setData('new_client', {
                                        ...form.data.new_client,
                                        cell_phone: e.target.value,
                                    })
                                }
                                placeholder="Cell Phone"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Client Type
                            </label>
                            <select
                                value={form.data.new_client.client_type}
                                onChange={(e) =>
                                    form.setData('new_client', {
                                        ...form.data.new_client,
                                        client_type: e.target.value,
                                    })
                                }
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            >
                                <option value="individual">Individual</option>
                                <option value="corporate">Corporate</option>
                            </select>
                        </div>
                        <button
                            type="button"
                            onClick={() => {
                                setClientMode('select');
                                form.setData('new_client', {
                                    first_name: '',
                                    last_name: '',
                                    email: '',
                                    cell_phone: '',
                                    client_type: 'individual',
                                });
                            }}
                            className="text-sm text-ring hover:text-foreground"
                        >
                            Cancel and select existing client
                        </button>
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
                    >
                        {location_types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                </div>

                {form.data.location_type === 'vacation_rental' && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Rental Platform
                        </label>
                        <select
                            value={form.data.vacation_rental_platform}
                            onChange={(e) =>
                                form.setData(
                                    'vacation_rental_platform',
                                    e.target.value,
                                )
                            }
                            className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        >
                            <option value="">Select platform...</option>
                            {booking_attributes
                                .filter(
                                    (attr) =>
                                        attr.slug ===
                                        'vacation_rental_platform',
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

                {form.data.location_type === 'vacation_rental' && (
                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address Line 1
                            </label>
                            <input
                                type="text"
                                value={form.data.booking_address.line1}
                                onChange={(e) =>
                                    form.setData('booking_address', {
                                        ...form.data.booking_address,
                                        line1: e.target.value,
                                    })
                                }
                                placeholder="Street address"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address Line 2
                            </label>
                            <input
                                type="text"
                                value={form.data.booking_address.line2}
                                onChange={(e) =>
                                    form.setData('booking_address', {
                                        ...form.data.booking_address,
                                        line2: e.target.value,
                                    })
                                }
                                placeholder="Apt, suite, etc. (optional)"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    City
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.city}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            city: e.target.value,
                                        })
                                    }
                                    placeholder="City"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    State
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.state}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            state: e.target.value,
                                        })
                                    }
                                    placeholder="State"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Zip
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.zip}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            zip: e.target.value,
                                        })
                                    }
                                    placeholder="Zip"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                        </div>
                    </div>
                )}

                {form.data.location_type === 'event_venue' && (
                    <div className="space-y-3">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address Line 1
                            </label>
                            <input
                                type="text"
                                value={form.data.booking_address.line1}
                                onChange={(e) =>
                                    form.setData('booking_address', {
                                        ...form.data.booking_address,
                                        line1: e.target.value,
                                    })
                                }
                                placeholder="Street address"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address Line 2
                            </label>
                            <input
                                type="text"
                                value={form.data.booking_address.line2}
                                onChange={(e) =>
                                    form.setData('booking_address', {
                                        ...form.data.booking_address,
                                        line2: e.target.value,
                                    })
                                }
                                placeholder="Apt, suite, etc. (optional)"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            />
                        </div>
                        <div className="grid grid-cols-3 gap-3">
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    City
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.city}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            city: e.target.value,
                                        })
                                    }
                                    placeholder="City"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    State
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.state}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            state: e.target.value,
                                        })
                                    }
                                    placeholder="State"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Zip
                                </label>
                                <input
                                    type="text"
                                    value={form.data.booking_address.zip}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            zip: e.target.value,
                                        })
                                    }
                                    placeholder="Zip"
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                            </div>
                        </div>
                    </div>
                )}

                {form.data.location_type === 'private_home' &&
                    clientAddresses.length === 0 && (
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address
                            </label>
                            <div className="mt-1 space-y-3">
                                <input
                                    type="text"
                                    value={form.data.booking_address.line1}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            line1: e.target.value,
                                        })
                                    }
                                    placeholder="Address Line 1"
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                                <input
                                    type="text"
                                    value={form.data.booking_address.line2}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            line2: e.target.value,
                                        })
                                    }
                                    placeholder="Address Line 2 (optional)"
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                                <div className="grid grid-cols-3 gap-3">
                                    <input
                                        type="text"
                                        value={form.data.booking_address.city}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                city: e.target.value,
                                            })
                                        }
                                        placeholder="City"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                    <input
                                        type="text"
                                        value={form.data.booking_address.state}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                state: e.target.value,
                                            })
                                        }
                                        placeholder="State"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                    <input
                                        type="text"
                                        value={form.data.booking_address.zip}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                zip: e.target.value,
                                            })
                                        }
                                        placeholder="Zip"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                            </div>
                        </div>
                    )}

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
                                    } else {
                                        form.setData(
                                            'address_id',
                                            e.target.value
                                                ? Number(e.target.value)
                                                : null,
                                        );
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

                {form.data.location_type === 'private_home' &&
                    clientAddresses.length > 0 &&
                    addressMode === 'input' && (
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Address
                            </label>
                            <div className="mt-1 space-y-3">
                                <input
                                    type="text"
                                    value={form.data.booking_address.line1}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            line1: e.target.value,
                                        })
                                    }
                                    placeholder="Address Line 1"
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                                <input
                                    type="text"
                                    value={form.data.booking_address.line2}
                                    onChange={(e) =>
                                        form.setData('booking_address', {
                                            ...form.data.booking_address,
                                            line2: e.target.value,
                                        })
                                    }
                                    placeholder="Address Line 2 (optional)"
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                />
                                <div className="grid grid-cols-3 gap-3">
                                    <input
                                        type="text"
                                        value={form.data.booking_address.city}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                city: e.target.value,
                                            })
                                        }
                                        placeholder="City"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                    <input
                                        type="text"
                                        value={form.data.booking_address.state}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                state: e.target.value,
                                            })
                                        }
                                        placeholder="State"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                    <input
                                        type="text"
                                        value={form.data.booking_address.zip}
                                        onChange={(e) =>
                                            form.setData('booking_address', {
                                                ...form.data.booking_address,
                                                zip: e.target.value,
                                            })
                                        }
                                        placeholder="Zip"
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                                    />
                                </div>
                                <button
                                    type="button"
                                    onClick={() => {
                                        setAddressMode('select');
                                        form.setData('booking_address', {
                                            line1: '',
                                            line2: '',
                                            city: '',
                                            state: '',
                                            zip: '',
                                        });
                                    }}
                                    className="text-sm text-ring hover:text-foreground"
                                >
                                    Cancel and select existing address
                                </button>
                            </div>
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
                                }}
                                suggestions={hotelSuggestions}
                                onSearch={handleHotelSearch}
                                placeholder="Search hotel..."
                                displayValue={selectedHotelName}
                            />
                        </div>
                        {form.data.hotel_id && (
                            <div className="mt-2 rounded-[3px] bg-muted p-3 text-sm text-muted-foreground">
                                {(() => {
                                    const hotel = hotels.find(
                                        (h) => h.id === form.data.hotel_id,
                                    );

                                    if (!hotel) {
                                        return null;
                                    }

                                    const parts = [
                                        hotel.line1,
                                        hotel.line2,
                                        hotel.city,
                                        hotel.state,
                                        hotel.zip,
                                    ].filter(Boolean);

                                    return parts.join(', ');
                                })()}
                            </div>
                        )}
                    </div>
                )}

                {clientChildren.length > 0 && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Children
                        </label>
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
                                            Birth Date
                                        </th>
                                        <th className="px-3 py-2 text-left font-medium">
                                            Special Needs
                                        </th>
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
                                                {child.birth_year
                                                    ? `${child.birth_month || ''} ${child.birth_year}`
                                                    : '-'}
                                            </td>
                                            <td className="px-3 py-2">
                                                {child.special_needs
                                                    ? 'Yes'
                                                    : 'No'}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}

                {clientPets.length > 0 && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Pets
                        </label>
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
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                    </div>
                )}
            </div>
        </details>
    );
}
