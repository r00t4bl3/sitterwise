import {
    ChevronDown,
    ChevronUp,
} from 'lucide-react';
import { useState } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { Textarea } from '@/components/ui/textarea';
import { formatDisplayDateTimeRangeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
import { BookingChildrenSection } from './booking-children-section';
import { BookingPetsSection } from './booking-pets-section';
import { ClientInfoPanel } from './client-info-panel';
import { NotifyCaregiversSheet } from './notify-caregivers-sheet';
import type { Booking } from './types';

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
    editingBooking: Booking | null;
    clientMode: 'select' | 'input';
    setClientMode: (mode: 'select' | 'input') => void;
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
    bookingChildren: NewChild[];
    bookingPets: NewPet[];
    onAddChild: () => void;
    onRemoveChild: (tempId: string) => void;
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
    sitter_preferences: Array<{ value: string; label: string }>;
    client_types: Array<{ value: string; label: string }>;
    discovery_sources: Array<{ value: string; label: string }>;
    caregiverSuggestions: Array<{
        id: number;
        name: string;
        age?: number | null;
        matchIcons?: string[];
        hasBeenNotified?: boolean;
    }>;
    caregiverAllIds: number[];
    caregiverTotal: number;
    caregiverCurrentPage: number;
    caregiverLastPage: number;
    loadingCaregiverRecommendations: boolean;
    loadingMoreCaregivers: boolean;
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
    calculateAge: (birthYear: number | null, birthMonth: number | null) => string;
    pet_types: Array<{ value: string; label: string }>;
    isAddressLocked: boolean;
    setIsAddressLocked: (locked: boolean) => void;
    showManualAddressInput: boolean;
    setShowManualAddressInput: (show: boolean) => void;
    addressValue: string;
    setAddressValue: (value: string) => void;
    onOpenNotifySheet?: () => void;
    onLoadMoreCaregivers?: (ageFilter?: string) => void;
    onAgeFilterChange?: (filter: string) => void;
    sheetMode?: string;
}

export function PersonalInfoSection({
    form,
    editingBooking,
    clientMode,
    setClientMode,
    clientSuggestions,
    clientAddresses,
    bookingChildren,
    bookingPets,
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
    sitter_preferences,
    client_types,
    discovery_sources,
    hotels,
    hotelSuggestions,
    selectedHotelName,
    handleHotelSearch,
    calculateAge,
    pet_types,
    isAddressLocked,
    setIsAddressLocked,
    showManualAddressInput,
    setShowManualAddressInput,
    addressValue,
    setAddressValue,
    caregiverSuggestions,
    caregiverAllIds,
    caregiverTotal,
    caregiverCurrentPage,
    caregiverLastPage,
    loadingCaregiverRecommendations,
    loadingMoreCaregivers,
    onOpenNotifySheet,
    onLoadMoreCaregivers,
    onAgeFilterChange,
    sheetMode,
}: PersonalInfoSectionProps) {
    const [isOpen, setIsOpen] = useState(false);

    const [notifySheetOpen, setNotifySheetOpen] = useState(false);

    const [showUnlistedHotel, setShowUnlistedHotel] = useState(false);

    return (
        <>
            {editingBooking && (
                <div className="mb-4 flex items-center justify-between border-b border-border pb-4">
                    <div>
                        <p className="font-semibold">
                            {(() => {
                                const client = editingBooking.client ?? editingBooking.booking_group?.client;

                                return `${client?.first_name ?? ''} ${client?.last_name ?? ''} - `;
                            })()}
                            {(editingBooking.client ?? editingBooking.booking_group?.client)?.phone ? (
                                <a
                                    href={`tel:${(editingBooking.client ?? editingBooking.booking_group?.client)?.phone}`}
                                    className="text-primary hover:underline"
                                >
                                    {formatPhoneDisplay((editingBooking.client ?? editingBooking.booking_group?.client)?.phone ?? '')}
                                </a>
                            ) : 'No phone'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {formatDisplayDateTimeRangeInPT(
                                form.data.start_datetime,
                                form.data.end_datetime,
                            )}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {editingBooking.booking_group?.children_notes
                                ? editingBooking.booking_group?.children_notes
                                : editingBooking.booking_group?.children &&
                                    editingBooking.booking_group?.children.length > 0
                                  ? editingBooking.booking_group?.children.map(
                                        (child, index) => (
                                            <span key={`child-${index}`}>
                                                {child.name}
                                                {child.birth_month &&
                                                child.birth_year
                                                    ? ` (${calculateAge(
                                                          child.birth_year,
                                                          child.birth_month,
                                                      )})`
                                                    : ''}
                                                {index <
                                                editingBooking.booking_group?.children!
                                                    .length -
                                                    1
                                                    ? ', '
                                                    : ''}
                                            </span>
                                        ),
                                    )
                                  : '(No children)'}
                            {editingBooking.booking_group?.pets &&
                                editingBooking.booking_group?.pets.length > 0 &&
                                ` • ${editingBooking.booking_group?.pets.length} pet${editingBooking.booking_group?.pets.length > 1 ? 's' : ''}`}
                        </p>
                    </div>
                    {form.data.status === 'received' &&
                        sheetMode !== 'duplicate' && (
                            <Button
                                size="sm"
                                onClick={() => {
                                    onOpenNotifySheet?.();
                                    setNotifySheetOpen(true);
                                }}
                            >
                                Notify Caregivers
                            </Button>
                        )}
                </div>
            )}

            {editingBooking && (
                <ClientInfoPanel client={(editingBooking.client ?? editingBooking.booking_group?.client) as any} />
            )}

            {editingBooking && (
                <NotifyCaregiversSheet
                    open={notifySheetOpen}
                    onOpenChange={setNotifySheetOpen}
                    bookingId={editingBooking.id}
                    initialCaregiverId={form.data.caregiver_id}
                    caregiverSuggestions={caregiverSuggestions}
                    caregiverAllIds={caregiverAllIds}
                    caregiverTotal={caregiverTotal}
                    caregiverCurrentPage={caregiverCurrentPage}
                    caregiverLastPage={caregiverLastPage}
                    loadingCaregiverRecommendations={loadingCaregiverRecommendations}
                    loadingMoreCaregivers={loadingMoreCaregivers}
                    onLoadMoreCaregivers={onLoadMoreCaregivers}
                    onAgeFilterChange={onAgeFilterChange}
                />
            )}

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
                        <Label
                            className={
                                form.errors.client_id ? 'text-destructive' : ''
                            }
                        >
                            Client <span className="text-red-500">*</span>
                        </Label>
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
                        {form.errors.client_id && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.client_id}
                            </p>
                        )}
                    </div>

                    {clientMode === 'input' && (
                        <div className="space-y-3 rounded-[3px] border border-border p-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label
                                        className={
                                            form.errors['new_client.first_name']
                                                ? 'text-destructive'
                                                : ''
                                        }
                                    >
                                        First Name{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        value={form.data.new_client.first_name}
                                        onChange={(e) =>
                                            form.setData('new_client', {
                                                ...form.data.new_client,
                                                first_name: e.target.value,
                                            })
                                        }
                                        placeholder="First Name"
                                        aria-invalid={
                                            !!form.errors[
                                                'new_client.first_name'
                                            ]
                                        }
                                    />
                                    {form.errors['new_client.first_name'] && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {
                                                form.errors[
                                                    'new_client.first_name'
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                                <div>
                                    <Label
                                        className={
                                            form.errors['new_client.last_name']
                                                ? 'text-destructive'
                                                : ''
                                        }
                                    >
                                        Last Name{' '}
                                        <span className="text-red-500">*</span>
                                    </Label>
                                    <Input
                                        value={form.data.new_client.last_name}
                                        onChange={(e) =>
                                            form.setData('new_client', {
                                                ...form.data.new_client,
                                                last_name: e.target.value,
                                            })
                                        }
                                        placeholder="Last Name"
                                        aria-invalid={
                                            !!form.errors[
                                                'new_client.last_name'
                                            ]
                                        }
                                    />
                                    {form.errors['new_client.last_name'] && (
                                        <p className="mt-1 text-sm text-destructive">
                                            {
                                                form.errors[
                                                    'new_client.last_name'
                                                ]
                                            }
                                        </p>
                                    )}
                                </div>
                            </div>

                            <div>
                                <Label
                                    className={`text-sm font-medium ${form.errors['new_client.email'] ? 'text-destructive' : 'text-foreground'}`}
                                >
                                    Email{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    type="email"
                                    value={form.data.new_client.email}
                                    onChange={(e) =>
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            email: e.target.value,
                                        })
                                    }
                                    placeholder="Email"
                                    aria-invalid={
                                        !!form.errors['new_client.email']
                                    }
                                />
                                {form.errors['new_client.email'] && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {form.errors['new_client.email']}
                                    </p>
                                )}
                            </div>
                            <div>
                                <PhoneInput
                                    value={form.data.new_client.phone}
                                    onChange={(value) =>
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            phone: value,
                                        })
                                    }
                                    name="new_client.phone"
                                    label="Cell Phone"
                                    placeholder="Cell Phone"
                                    required
                                    error={form.errors['new_client.phone']}
                                />
                            </div>
                            <div>
                                <Label
                                    className={`text-sm font-medium ${form.errors['new_client.client_type'] ? 'text-destructive' : 'text-foreground'}`}
                                >
                                    Client Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={
                                        form.data.new_client.client_type || ''
                                    }
                                    onValueChange={(value) => {
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            client_type: value,
                                        });

                                        if (value === 'resident') {
                                            form.setData(
                                                'location_type',
                                                'private_home',
                                            );
                                        } else if (value === 'vacationer') {
                                            form.setData(
                                                'location_type',
                                                'hotel',
                                            );
                                        }
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select client type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {client_types.map((type) => (
                                            <SelectItem
                                                key={type.value}
                                                value={type.value}
                                            >
                                                {type.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {form.errors['new_client.client_type'] && (
                                    <p className="mt-1 text-sm text-destructive">
                                        {form.errors['new_client.client_type']}
                                    </p>
                                )}
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
                        <Label
                            className={`text-sm font-medium ${form.errors.location_type ? 'text-destructive' : 'text-foreground'}`}
                        >
                            Location Type{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <Select
                            value={form.data.location_type || ''}
                            onValueChange={(value) =>
                                form.setData('location_type', value)
                            }
                        >
                            <SelectTrigger>
                                <SelectValue placeholder="Select location type" />
                            </SelectTrigger>
                            <SelectContent>
                                {location_types
                                    .filter((type) => {
                                        const clientType =
                                            form.data.client_id &&
                                            clientMode === 'select'
                                                ? selectedClientType
                                                : form.data.new_client
                                                      ?.client_type;

                                        if (clientType === 'resident') {
                                            return (
                                                type.value === 'private_home'
                                            );
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
                                        <SelectItem
                                            key={type.value}
                                            value={type.value}
                                        >
                                            {type.label}
                                        </SelectItem>
                                    ))}
                            </SelectContent>
                        </Select>
                        {form.errors.location_type && (
                            <p className="mt-1 text-sm text-destructive">
                                {form.errors.location_type}
                            </p>
                        )}
                    </div>

                    {form.data.location_type === 'private_home' &&
                        !editingBooking &&
                        clientAddresses.length > 0 &&
                        !showManualAddressInput && (
                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Address
                                </Label>
                                <Select
                                    value={
                                        form.data.address_id?.toString() || ''
                                    }
                                    onValueChange={(value) => {
                                        if (value === 'add_new') {
                                            setShowManualAddressInput(true);
                                            form.setData('address_id', null);
                                            form.setData('address_line1', '');
                                            form.setData('address_line2', '');
                                            form.setData('address_city', '');
                                            form.setData('address_state', '');
                                            form.setData('address_zip', '');
                                            setAddressValue('');
                                            setIsAddressLocked(false);
                                        } else if (value) {
                                            const addrId = Number(value);
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
                                                setAddressValue(
                                                    `${addr.line1}${
                                                        addr.line2
                                                            ? `, ${addr.line2}`
                                                            : ''
                                                    }, ${addr.city}, ${addr.state} ${addr.zip}`,
                                                );
                                                setIsAddressLocked(true);
                                            }
                                        } else {
                                            form.setData('address_id', null);
                                            form.setData('address_line1', '');
                                            form.setData('address_line2', '');
                                            form.setData('address_city', '');
                                            form.setData('address_state', '');
                                            form.setData('address_zip', '');
                                            setIsAddressLocked(false);
                                            setAddressValue('');
                                        }

                                        form.setData('address_id', value);
                                    }}
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select address" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="add_new">
                                            + Enter manually
                                        </SelectItem>
                                        {clientAddresses.map((addr) => (
                                            <SelectItem
                                                key={addr.id}
                                                value={addr.id.toString()}
                                            >
                                                {addr.line1}, {addr.city},{' '}
                                                {addr.state} {addr.zip}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        )}

                    {form.data.location_type === 'private_home' &&
                        !editingBooking &&
                        clientAddresses.length === 0 &&
                        !isAddressLocked && (
                            <div className="text-sm text-muted-foreground">
                                No saved addresses. Please enter address below.
                            </div>
                        )}

                    {form.data.location_type === 'event_venue' && (
                        <div className="text-sm text-muted-foreground">
                            Please enter event venue address below.
                        </div>
                    )}

                    {form.data.location_type === 'hotel' && (
                        <div>
                            <Label
                                className={`text-sm font-medium ${form.errors.hotel_id ? 'text-destructive' : 'text-foreground'}`}
                            >
                                Hotel
                            </Label>
                            <div className="mt-1">
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
                                                    form.setData('hotel_name', hotel.name);
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
                                                        }, ${hotel.city || ''}, ${hotel.state || ''} ${hotel.zip || ''}`.trim(),
                                                    );
                                                    setIsAddressLocked(true);
                                                }
                                            }}
                                            suggestions={hotelSuggestions}
                                            onSearch={handleHotelSearch}
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
                                            value={form.data.hotel_name || ''}
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
                            </div>
                            {(form.errors.hotel_id || form.errors.hotel_name) && (
                                <p className="mt-1 text-sm text-destructive">
                                    {form.errors.hotel_id || form.errors.hotel_name}
                                </p>
                            )}
                        </div>
                    )}

                    {(form.data.location_type !== 'private_home' ||
                        clientAddresses.length === 0 ||
                        showManualAddressInput ||
                        editingBooking) && (
                        <BookingAddressFields
                            form={form}
                            isAddressLocked={isAddressLocked}
                            addressValue={addressValue}
                            onAddressLock={(locked, newAddressValue) => {
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

                    <BookingChildrenSection
                        children={bookingChildren}
                        onAdd={onAddChild}
                        onRemove={onRemoveChild}
                        onUpdate={onUpdateChild}
                        calculateAge={calculateAge}
                        serviceType={form.data.service_type}
                    />

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
                                value={form.data.emergency_instructions || ''}
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

                    <BookingPetsSection
                        pets={bookingPets}
                        onAdd={onAddPet}
                        onRemove={onRemovePet}
                        onUpdate={onUpdatePet}
                        petTypes={pet_types}
                    />

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
                            <div className="grid grid-cols-3 gap-4">
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
                                            onCheckedChange={(checked) => {
                                                const newPrefs = checked
                                                    ? [
                                                          ...form.data
                                                              .sitter_preferences,
                                                          option.value,
                                                      ]
                                                    : form.data.sitter_preferences.filter(
                                                          (pref: string) =>
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

                    <div className="flex items-center gap-2">
                        <Checkbox
                            id="save_to_profile"
                            checked={saveChildrenPetsToProfile}
                            onCheckedChange={(checked) =>
                                onSaveChildrenPetsToProfileChange(
                                    checked === true,
                                )
                            }
                        />
                        <Label htmlFor="save_to_profile">
                            Save changes to client profile
                        </Label>
                    </div>
                </div>
            </details>
        </>
    );
}
