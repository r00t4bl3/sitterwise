import { useForm } from '@inertiajs/react';
import {
    BadgeCheck,
    Baby,
    Briefcase,
    CalendarCheck,
    ChevronDown,
    ChevronUp,
    Heart,
    History,
    MapPin,
    MapPinCheckInside,
    Plus,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import { BookingAddressFields } from '@/components/booking-address-fields';
import { Autocomplete } from '@/components/ui/autocomplete';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { getChildBirthYearOptions } from '@/lib/age';
import { formatDisplayDateTimeRangeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
import { ClientInfoPanel } from './client-info-panel';
import type { Booking } from './types';

const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'short' });
const MONTH_ABBR = [
    '',
    ...Array.from({ length: 12 }, (_, i) =>
        monthFormatter.format(new Date(2000, i)),
    ),
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
    pet_types: Array<{ value: string; label: string }>;
    isAddressLocked: boolean;
    setIsAddressLocked: (locked: boolean) => void;
    showManualAddressInput: boolean;
    setShowManualAddressInput: (show: boolean) => void;
    addressValue: string;
    setAddressValue: (value: string) => void;
    onOpenNotifySheet?: () => void;
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
    onOpenNotifySheet,
    sheetMode,
}: PersonalInfoSectionProps) {
    const [isOpen, setIsOpen] = useState(false);

    const [notifySheetOpen, setNotifySheetOpen] = useState(false);

    const [showUnlistedHotel, setShowUnlistedHotel] = useState(false);

    const [ageFilter, setAgeFilter] = useState<'all' | 'younger' | 'seasoned'>('all');

    const notifyForm = useForm({
        caregiver_ids: [] as number[],
    });

    const toggleCaregiver = (id: number) => {
        const current = notifyForm.data.caregiver_ids;
        const next = current.includes(id)
            ? current.filter((c) => c !== id)
            : [...current, id];

        notifyForm.setData('caregiver_ids', next);
    };

    const handleNotify = () => {
        if (notifyForm.data.caregiver_ids.length === 0) {
            return;
        }

        notifyForm.post(`/bookings/${editingBooking!.id}/notify`, {
            onSuccess: () => {
                setNotifySheetOpen(false);
                notifyForm.reset();
            },
        });
    };

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
                                    const currentId = form.data.caregiver_id;
                                    notifyForm.setData(
                                        'caregiver_ids',
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
                <ClientInfoPanel client={(editingBooking.client ?? editingBooking.booking_group?.client) as any} />
            )}

            <Sheet open={notifySheetOpen} onOpenChange={setNotifySheetOpen}>
                <SheetContent
                    side="right"
                    className="flex w-full flex-col sm:max-w-lg"
                >
                    <SheetHeader className="shrink-0 pb-0">
                        <div className="flex items-center justify-between">
                            <div className="space-y-1">
                                <SheetTitle>Notify Caregivers</SheetTitle>
                                <SheetDescription>
                                    Select caregivers to notify about this
                                    booking.
                                </SheetDescription>
                            </div>
                        </div>
                    </SheetHeader>

                    <div className="flex items-center gap-2 border-b border-border px-4 py-2">
                        <button
                            type="button"
                            onClick={() => setAgeFilter('all')}
                            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'all'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            All
                        </button>
                        <button
                            type="button"
                            onClick={() => setAgeFilter('younger')}
                            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'younger'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            Younger (18-34)
                        </button>
                        <button
                            type="button"
                            onClick={() => setAgeFilter('seasoned')}
                            className={`rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'seasoned'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            Seasoned (35+)
                        </button>
                        <div className="ml-auto">
                            <Button
                                variant="link"
                                size="sm"
                                onClick={() => {
                                    const filtered = caregiverSuggestions.filter(
                                        (cg) => {
                                            if (ageFilter === 'all') return true;
                                            if (cg.age == null) return true;
                                            return ageFilter === 'younger'
                                                ? cg.age < 35
                                                : cg.age >= 35;
                                        },
                                    );
                                    notifyForm.setData(
                                        'caregiver_ids',
                                        notifyForm.data.caregiver_ids
                                            .length === filtered.length
                                            ? []
                                            : filtered.map((cg) => cg.id),
                                    );
                                }}
                            >
                                {notifyForm.data.caregiver_ids.length ===
                                caregiverSuggestions.filter((cg) => {
                                    if (ageFilter === 'all') return true;
                                    if (cg.age == null) return true;
                                    return ageFilter === 'younger'
                                        ? cg.age < 35
                                        : cg.age >= 35;
                                }).length
                                    ? 'Deselect All'
                                    : 'Select All'}
                            </Button>
                        </div>
                    </div>

                    <div className="flex-1 space-y-2 overflow-y-auto px-4">
                        {(() => {
                            const filteredCaregivers =
                                caregiverSuggestions.filter((cg) => {
                                    if (ageFilter === 'all') return true;
                                    if (cg.age == null) return true;
                                    return ageFilter === 'younger'
                                        ? cg.age < 35
                                        : cg.age >= 35;
                                });

                            const ICON_MAP: Record<
                                string,
                                React.ElementType
                            > = {
                                favorited: Heart,
                                previous_work: History,
                                available: CalendarCheck,
                                specialty: Baby,
                                location_preferred: MapPinCheckInside,
                                location_willing: MapPin,
                                recent_work: Briefcase,
                            };

                            const ICON_TOOLTIPS: Record<string, string> = {
                                favorited: 'Favorited by client',
                                previous_work:
                                    'Previously worked with this family',
                                available: 'Available for booking dates',
                                specialty: 'Specializes in this age group',
                                location_preferred: 'Based in booking area',
                                location_willing:
                                    'Willing to travel to booking area',
                                recent_work: 'Actively working recently',
                            };

                            return filteredCaregivers.map((caregiver) => {
                                const hasBeenNotified = (caregiver as any)
                                    .hasBeenNotified;
                                const matchIcons = (
                                    caregiver as any
                                ).matchIcons as string[] | undefined;

                                return (
                                    <Label
                                        key={caregiver.id}
                                        className={`flex items-center justify-between gap-2 rounded-lg border border-border p-3 hover:cursor-pointer hover:bg-blush ${notifyForm.data.caregiver_ids.includes(caregiver.id) && `bg-blush`}`}
                                    >
                                        <div className="flex items-center gap-2">
                                            <Checkbox
                                                id={`cg-${caregiver.id}`}
                                                checked={notifyForm.data.caregiver_ids.includes(
                                                    caregiver.id,
                                                )}
                                                onCheckedChange={() =>
                                                    toggleCaregiver(
                                                        caregiver.id,
                                                    )
                                                }
                                            />
                                            <div className="flex flex-row items-center gap-2">
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
                                                {matchIcons &&
                                                    matchIcons.length > 0 && (
                                                        <div className="flex items-center gap-1">
                                                            {matchIcons.map(
                                                                (
                                                                    iconKey: string,
                                                                ) => {
                                                                    const IconComponent =
                                                                        ICON_MAP[
                                                                            iconKey
                                                                        ];
                                                                    const tooltip =
                                                                        ICON_TOOLTIPS[
                                                                            iconKey
                                                                        ];

                                                                    if (
                                                                        !IconComponent
                                                                    ) {
                                                                        return null;
                                                                    }

                                                                    return (
                                                                        <Tooltip
                                                                            key={
                                                                                iconKey
                                                                            }
                                                                        >
                                                                            <TooltipTrigger asChild>
                                                                                <span className="flex cursor-default items-center">
                                                                                    <IconComponent className="h-4 w-4 text-muted-foreground" />
                                                                                </span>
                                                                            </TooltipTrigger>
                                                                            <TooltipContent>
                                                                                {
                                                                                    tooltip
                                                                                }
                                                                            </TooltipContent>
                                                                        </Tooltip>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                    )}
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {caregiver.age && (
                                                <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-800">
                                                    {caregiver.age}y
                                                </span>
                                            )}
                                        </div>
                                    </Label>
                                );
                            });
                        })()}
                    </div>

                    <div className="mt-4 flex shrink-0 gap-2 border-t border-border px-4 py-6">
                        <Button
                            onClick={handleNotify}
                            disabled={notifyForm.processing}
                            className="flex-1"
                        >
                            {notifyForm.processing && (
                                <Spinner className="size-4" />
                            )}
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
                                                    value={
                                                        child.birth_month || ''
                                                    }
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
                                                            (
                                                                monthAbbr,
                                                                index,
                                                            ) => {
                                                                if (
                                                                    index === 0
                                                                ) {
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
                                                                        {
                                                                            monthAbbr
                                                                        }
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
                                                    value={
                                                        child.birth_year || ''
                                                    }
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
                                            <div className="grow">
                                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                                    Age
                                                </Label>
                                                <p className="flex h-11 items-center text-sm text-foreground">
                                                    {child.birth_year
                                                        ? calculateAge(
                                                              parseInt(
                                                                  child.birth_year,
                                                              ) || null,
                                                              parseInt(
                                                                  child.birth_month,
                                                              ) || null,
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
                        {bookingChildren.length === 0 &&
                            form.data.service_type !==
                                'group_childcare_invoiced' && (
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
                                            <Select
                                                value={pet.type || ''}
                                                onValueChange={(value) =>
                                                    onUpdatePet(
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
                                                    {pet_types.map((type) => (
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
                                        {pet.type === 'dog' && (
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
                                        )}
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
