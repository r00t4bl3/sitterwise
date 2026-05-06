import { useForm } from '@inertiajs/react';
import { BadgeCheck, ChevronDown, ChevronUp, Plus, Trash2 } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { ClientInfoPanel } from './client-info-panel';
import type { Booking } from './types';

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
    isAddressLocked: boolean;
    setIsAddressLocked: (locked: boolean) => void;
    showManualAddressInput: boolean;
    setShowManualAddressInput: (show: boolean) => void;
    addressValue: string;
    setAddressValue: (value: string) => void;
    caregiverSuggestions: Array<{
        id: number;
        name: string;
        [key: string]: unknown;
    }>;
    onOpenNotifySheet?: () => void;
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
    sitter_preference_options,
    client_type_options,
    booking_attributes,
    hotels,
    hotelSuggestions,
    selectedHotelName,
    handleHotelSearch,
    calculateAge,
    isAddressLocked,
    setIsAddressLocked,
    showManualAddressInput,
    setShowManualAddressInput,
    addressValue,
    setAddressValue,
    caregiverSuggestions,
    onOpenNotifySheet,
}: PersonalInfoSectionProps) {
    const [isOpen, setIsOpen] = useState(false);

    const [notifySheetOpen, setNotifySheetOpen] = useState(false);
    const [selectedCaregivers, setSelectedCaregivers] = useState<number[]>([]);
    const [processing, setProcessing] = useState(false);

    // Create a ref to keep track of the current selected caregivers
    const selectedCaregiversRef = useRef(selectedCaregivers);

    // Update the ref whenever selectedCaregivers changes
    useEffect(() => {
        // console.log('Updating selectedCaregiversRef to:', selectedCaregivers);
        selectedCaregiversRef.current = selectedCaregivers;
    }, [selectedCaregivers]);

    const notifyForm = useForm({
        caregiver_ids: [] as number[],
    });

    const toggleCaregiver = (id: number) => {
        console.log('Toggling caregiver ID:', id);
        console.log(
            'Current selected caregivers before toggle:',
            selectedCaregivers,
        );
        setSelectedCaregivers((prev) => {
            const newState = prev.includes(id)
                ? prev.filter((c) => c !== id)
                : [...prev, id];
            console.log('New state after toggle:', newState);

            return newState;
        });
    };

    const handleNotify = () => {
        if (selectedCaregivers.length === 0) {
            return;
        }

        setProcessing(true);
        notifyForm.setData('caregiver_ids', selectedCaregivers);
        notifyForm.post(`/bookings/${editingBooking!.id}/notify`, {
            onSuccess: () => {
                setProcessing(false);
                setNotifySheetOpen(false);
                setSelectedCaregivers([]);
                notifyForm.setData('caregiver_ids', []);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    return (
        <>
            {editingBooking && (
                <div className="mb-4 flex items-center justify-between border-b border-border pb-4">
                    <div>
                        <p className="font-semibold">
                            {editingBooking.client.first_name}{' '}
                            {editingBooking.client.last_name} -{' '}
                            {(editingBooking.client.phone && (
                                <a
                                    href={`tel:${editingBooking.client.phone}`}
                                    className="text-primary hover:underline"
                                >
                                    {editingBooking.client.phone}
                                </a>
                            )) ||
                                'No phone'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {(() => {
                                const start = new Date(
                                    form.data.start_datetime,
                                );
                                const end = new Date(form.data.end_datetime);
                                const isSameDay =
                                    start.getFullYear() === end.getFullYear() &&
                                    start.getMonth() === end.getMonth() &&
                                    start.getDate() === end.getDate();

                                const dateOptions: Intl.DateTimeFormatOptions =
                                    {
                                        month: 'short',
                                        day: 'numeric',
                                        year: 'numeric',
                                    };
                                const timeOptions: Intl.DateTimeFormatOptions =
                                    {
                                        hour: 'numeric',
                                        minute: '2-digit',
                                        hour12: true,
                                    };

                                if (isSameDay) {
                                    return `${start.toLocaleDateString('en-US', dateOptions)} ${start.toLocaleTimeString('en-US', timeOptions)} - ${end.toLocaleTimeString('en-US', timeOptions)}`;
                                }

                                return `${start.toLocaleDateString('en-US', dateOptions)} ${start.toLocaleTimeString('en-US', timeOptions)} - ${end.toLocaleDateString('en-US', dateOptions)} ${end.toLocaleTimeString('en-US', timeOptions)}`;
                            })()}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {editingBooking.children && editingBooking.children.length > 0
                                ? editingBooking.children.map((child, index) => (
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
                                          editingBooking.children!.length - 1
                                              ? ', '
                                              : ''}
                                      </span>
                                  ))
                                : '(No children)'}
                            {editingBooking.pets &&
                                editingBooking.pets.length > 0 &&
                                ` • ${editingBooking.pets.length} pet${editingBooking.pets.length > 1 ? 's' : ''}`}
                        </p>
                    </div>
                    {form.data.status === 'received' && (
                        <Button
                            size="sm"
                            onClick={() => {
                                const currentId = form.data.caregiver_id;
                                setSelectedCaregivers(
                                    currentId ? [currentId] : [],
                                );
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
                <ClientInfoPanel client={editingBooking.client} />
            )}

            <Sheet open={notifySheetOpen} onOpenChange={setNotifySheetOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-md"
                >
                    <SheetHeader className="shrink-0">
                        <SheetTitle>Notify Caregivers</SheetTitle>
                        <SheetDescription>
                            Select caregivers to notify about this booking.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="flex-1 space-y-4 overflow-y-auto px-4">
                        {caregiverSuggestions.map((caregiver) => {
                            const badge = (caregiver as any).matchBadge;
                            const hasBeenNotified = (caregiver as any)
                                .hasBeenNotified;
                            const colorClasses: Record<string, string> = {
                                green: 'bg-green-100 text-green-800',
                                yellow: 'bg-yellow-100 text-yellow-800',
                                orange: 'bg-orange-100 text-orange-800',
                                blue: 'bg-blue-100 text-blue-800',
                            };

                            return (
                                <Label
                                    key={caregiver.id}
                                    className="flex items-center justify-between gap-2 rounded-lg border border-border p-3"
                                >
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id={`cg-${caregiver.id}`}
                                            checked={selectedCaregivers.includes(
                                                caregiver.id,
                                            )}
                                            onCheckedChange={() =>
                                                toggleCaregiver(caregiver.id)
                                            }
                                        />
                                        <Label
                                            htmlFor={`cg-${caregiver.id}`}
                                            className="flex text-sm font-medium"
                                        >
                                            {caregiver.name}
                                            {hasBeenNotified && (
                                                <span
                                                    className="ml-2 text-green-500"
                                                    title="Already notified"
                                                >
                                                    <BadgeCheck className="h-5 w-5" />
                                                </span>
                                            )}
                                        </Label>
                                    </div>
                                    {badge && (
                                        <span
                                            className={`rounded-full px-2 py-0.5 text-xs font-medium ${
                                                colorClasses[badge.color] ||
                                                'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {badge.label}
                                        </span>
                                    )}
                                </Label>
                            );
                        })}
                    </div>

                    <div className="mt-4 flex shrink-0 gap-2 border-t border-border px-4 py-6">
                        <Button
                            onClick={handleNotify}
                            disabled={processing}
                            className="flex-1"
                        >
                            {processing && <Spinner className="size-4" />}
                            Send Notification
                        </Button>
                        <Button
                            variant="outline"
                            onClick={() => setNotifySheetOpen(false)}
                        >
                            Cancel
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>

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
                        <Label>
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
                    </div>

                    {clientMode === 'input' && (
                        <div className="space-y-3 rounded-[3px] border border-border p-4">
                            <div className="grid grid-cols-2 gap-3">
                                <div>
                                    <Label>
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
                                    />
                                </div>
                                <div>
                                    <Label>
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
                                    />
                                </div>
                            </div>

                            <div>
                                <Label className="text-sm font-medium text-foreground">
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
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium text-foreground">
                                    Cell Phone{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    value={form.data.new_client.phone}
                                    onChange={(e) =>
                                        form.setData('new_client', {
                                            ...form.data.new_client,
                                            phone: e.target.value,
                                        })
                                    }
                                    placeholder="Cell Phone"
                                    required
                                />
                            </div>
                            <div>
                                <Label className="text-sm font-medium text-foreground">
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
                                        {client_type_options.map((type) => (
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
                        <Label className="text-sm font-medium text-foreground">
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

                    {form.data.location_type === 'vacation_rental' && (
                        <div>
                            <Label className="text-sm font-medium text-foreground">
                                Rental Platform
                            </Label>
                            <Select
                                value={form.data.rental_platform || ''}
                                onValueChange={(value) =>
                                    form.setData('rental_platform', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select platform" />
                                </SelectTrigger>
                                <SelectContent>
                                    {booking_attributes
                                        .filter(
                                            (attr) =>
                                                attr.slug ===
                                                'vacation_rental_platform',
                                        )
                                        .flatMap((attr) => attr.options || [])
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

                    {form.data.location_type === 'event_venue' && (
                        <div className="text-sm text-muted-foreground">
                            Please enter event venue address below.
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
                            </div>
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

                    <div>
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium text-foreground">
                                Children
                            </Label>
                            <Button
                                type="button"
                                onClick={onAddChild}
                                size="xs"
                            >
                                <Plus className="h-3 w-3" />
                                Add Child
                            </Button>
                        </div>
                        <div className="mt-1 grid gap-4">
                            {bookingChildren.map((child) => (
                                <div
                                    key={child.tempId}
                                    className="rounded-lg border bg-card p-4"
                                >
                                    <div className="mb-3 flex items-start justify-between">
                                        <p className="text-sm font-medium text-foreground">
                                            {child.name || 'Add New Child'}
                                        </p>
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                onRemoveChild(child.tempId)
                                            }
                                            size="sm"
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </div>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        <div className="sm:col-span-1 md:col-auto">
                                            <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                Name
                                            </Label>
                                            <Input
                                                value={child.name}
                                                onChange={(e) =>
                                                    onUpdateChild(
                                                        child.tempId,
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
                                                value={child.gender || ''}
                                                onValueChange={(value) =>
                                                    onUpdateChild(
                                                        child.tempId,
                                                        'gender',
                                                        value,
                                                    )
                                                }
                                            >
                                                <SelectTrigger>
                                                    <SelectValue placeholder="Select gender" />
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

                                        <div className="flex flex-row gap-4 sm:col-span-2">
                                            <div className="grow">
                                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                    Month
                                                </Label>
                                                <Select
                                                    value={child.birth_month || ''}
                                                    onValueChange={(value) =>
                                                        onUpdateChild(
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
                                                        {MONTH_ABBR.map(
                                                            (monthAbbr, index) => {
                                                                if (index === 0) {
                                                                    return null;
                                                                }

                                                                return (
                                                                    <SelectItem
                                                                        key={
                                                                            monthAbbr
                                                                        }
                                                                        value={String(
                                                                            index,
                                                                        )}
                                                                    >
                                                                        {monthAbbr}
                                                                    </SelectItem>
                                                                );
                                                            },
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grow">
                                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                    Year
                                                </Label>
                                                <Select
                                                    value={child.birth_year || ''}
                                                    onValueChange={(value) =>
                                                        onUpdateChild(
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
                                                        {Array.from(
                                                            {
                                                                length:
                                                                    new Date().getFullYear() -
                                                                    (new Date().getFullYear() - 17) +
                                                                    1,
                                                            },
                                                            (_, i) =>
                                                                new Date().getFullYear() - 17 + i,
                                                        )
                                                            .reverse()
                                                            .map((year) => (
                                                                <SelectItem
                                                                    key={year}
                                                                    value={String(
                                                                        year,
                                                                    )}
                                                                >
                                                                    {year}
                                                                </SelectItem>
                                                            ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <div className="grow">
                                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                    Age
                                                </Label>
                                                <p className="text-sm text-foreground h-11 flex items-center">
                                                    {child.birth_year
                                                        ? calculateAge(
                                                            parseInt(child.birth_year) || null,
                                                            parseInt(child.birth_month) || null,
                                                        )
                                                        : '-'}
                                                </p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}
                            {bookingChildren.length === 0 && (
                                    <div className="rounded-lg border border-dashed bg-card/50 p-8 text-center">
                                        <p className="text-sm text-muted-foreground">
                                            No children added
                                        </p>
                                    </div>
                                )}
                        </div>
                        {bookingChildren.length === 0 && (
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

                    <div>
                        <div className="flex items-center justify-between">
                            <Label className="text-sm font-medium text-foreground">
                                Pets
                            </Label>
                            <Button type="button" onClick={onAddPet} size="xs">
                                <Plus className="h-3 w-3" />
                                Add Pet
                            </Button>
                        </div>
                        <div className="mt-1 grid gap-4">
                            {bookingPets.map((pet) => (
                                <div
                                    key={pet.tempId}
                                    className="rounded-lg border bg-card p-4"
                                >
                                    <div className="mb-3 flex items-start justify-between">
                                        <p className="text-sm font-medium text-foreground">
                                            {pet.name || 'Add New Pet'}
                                        </p>
                                        <Button
                                            type="button"
                                            onClick={() =>
                                                onRemovePet(pet.tempId)
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
                                                    onUpdatePet(
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
                                            <Input
                                                value={pet.type || ''}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'type',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Type"
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                Breed
                                            </Label>
                                            <Input
                                                value={pet.breed || ''}
                                                onChange={(e) =>
                                                    onUpdatePet(
                                                        pet.tempId,
                                                        'breed',
                                                        e.target.value,
                                                    )
                                                }
                                                placeholder="Breed"
                                            />
                                        </div>
                                        <div>
                                            <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                Notes
                                            </Label>
                                            <Input
                                                value={pet.notes || ''}
                                                onChange={(e) =>
                                                    onUpdatePet(
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
                            {bookingPets.length === 0 && (
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
                            <div className="grid grid-cols-3 gap-4">
                                {sitter_preference_options.map((option) => (
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
                                <SelectItem value="concierge">
                                    Concierge
                                </SelectItem>
                                <SelectItem value="friend_family">
                                    Friend/Family
                                </SelectItem>
                                <SelectItem value="google">Google</SelectItem>
                                <SelectItem value="returning_client">
                                    Returning Client
                                </SelectItem>
                                <SelectItem value="care_com">
                                    Care.com
                                </SelectItem>
                                <SelectItem value="other">Other</SelectItem>
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
