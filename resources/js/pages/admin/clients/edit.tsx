import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Trash2 } from 'lucide-react';
import { useState, useEffect, useRef } from 'react';
import type { SubmitEventHandler } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Clients',
        href: '/clients',
    },
    {
        title: 'Edit Client',
        href: '#',
    },
];

interface Address {
    id: number | null;
    label: string | null;
    location_type: string;
    line1: string;
    line2: string | null;
    city: string;
    state: string;
    zip: string;
    is_primary: boolean;
}

interface Child {
    id: number | null;
    name: string | null;
    gender: string | null;
    birth_month: number | null;
    birth_year: number | null;
}

interface Pet {
    id: number | null;
    name: string | null;
    type: string;
    breed: string | null;
    notes: string | null;
}

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    biography: string;
    email: string;
    phone: string;
    client_type: string;
    how_did_you_hear: string | null;
    sitter_preferences: string[] | null;
    other_adults_present: string | null;
    emergency_instructions: string | null;
    special_needs_notes: string | null;
    notes: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
    addresses: Address[];
    children: Child[];
    pets: Pet[];
    attributes: ClientAttribute[];
}

interface ClientAttribute {
    id: number;
    name: string;
    slug: string;
    type: string;
    value: string | boolean;
}

interface AttributeDefinition {
    id: number;
    name: string;
    slug: string;
    type: string;
}

interface CaregiverSelect {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
}

interface Props {
    [key: string]: unknown;
    client: Client;
    attribute_definitions: AttributeDefinition[];
    sitter_preferences: Array<{ value: string; label: string }>;
    client_types: Array<{ value: string; label: string }>;
    discovery_sources: Array<{ value: string; label: string }>;
    caregivers: CaregiverSelect[];
    favorite_caregivers: CaregiverSelect[];
    blocked_caregivers: CaregiverSelect[];
    pet_types: Array<{ value: string; label: string }>;
}

export default function ClientEdit() {
    const {
        client,
        attribute_definitions,
        sitter_preferences,
        client_types,
        discovery_sources,
        caregivers,
        favorite_caregivers,
        blocked_caregivers,
        pet_types,
    } = usePage<Props>().props;

    const [favoriteIds, setFavoriteIds] = useState<number[]>(
        favorite_caregivers?.map((c) => c.id) ?? [],
    );
    const [blockedIds, setBlockedIds] = useState<number[]>(
        blocked_caregivers?.map((c) => c.id) ?? [],
    );
    const [searchFav, setSearchFav] = useState('');
    const [searchBlock, setSearchBlock] = useState('');

    const [currentProfilePhoto, setCurrentProfilePhoto] = useState(
        client.user?.profile_photo_path ?? null,
    );

    const [attributeValues, setAttributeValues] = useState<
        Record<number, string>
    >(() => {
        const initial: Record<number, string> = {};
        attribute_definitions.forEach((def) => {
            const existing = client.attributes.find((a) => a.id === def.id);
            const isTrue =
                existing?.value === 'true' ||
                existing?.value === '1' ||
                existing?.value === true;
            initial[def.id] = isTrue ? 'true' : 'false';
        });

        return initial;
    });

    const photoForm = useForm({
        profile_photo: null as File | null,
    });

    const photoFormRef = useRef(photoForm);

    const handlePhotoFormChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;

        if (file) {
            photoForm.setData('profile_photo', file);
        }

        // Reset input value to allow same file to be selected again
        if (e.target) {
            e.target.value = '';
        }
    };

    useEffect(() => {
        photoFormRef.current = photoForm;
    }, [photoForm]);

    useEffect(() => {
        const form = photoFormRef.current;

        if (form.data.profile_photo) {
            form.post(`/clients/${client.id}/profile-photo`, {
                onSuccess: (page) => {
                    const newPath = (page.props as any).client?.user
                        ?.profile_photo_path;

                    if (newPath) {
                        setCurrentProfilePhoto(newPath);
                    }

                    form.reset();
                },
                onError: (errors) => {
                    if (errors.profile_photo) {
                        console.error(
                            'Photo upload error:',
                            errors.profile_photo,
                        );
                    }

                    form.reset();
                },
            });
        }
    }, [photoForm.data.profile_photo, client.id]);

    const submitPhotoForm: SubmitEventHandler = (e) => {
        e.preventDefault();
        // The upload is now handled by the useEffect above
    };

    const form = useForm({
        first_name: client.first_name,
        last_name: client.last_name,
        biography: client.biography,
        phone: client.phone,
        client_type: client.client_type,
        how_did_you_hear: client.how_did_you_hear || '',
        sitter_preferences: client.sitter_preferences || [],
        other_adults_present: client.other_adults_present || '',
        emergency_instructions: client.emergency_instructions || '',
        special_needs_notes: client.special_needs_notes || '',
        notes: client.notes || '',
        attributes: attributeValues,
        children: client.children,
        pets: client.pets,
        addresses: client.addresses,
        favorite_caregiver_ids: [] as number[],
        blocked_caregiver_ids: [] as number[],
    });

    const addFavorite = (id: number) => {
        if (!favoriteIds.includes(id)) {
            setFavoriteIds([...favoriteIds, id]);
        }
    };

    const removeFavorite = (id: number) => {
        setFavoriteIds(favoriteIds.filter((x) => x !== id));
    };

    const addBlocked = (id: number) => {
        if (!blockedIds.includes(id)) {
            setBlockedIds([...blockedIds, id]);
        }
    };

    const removeBlocked = (id: number) => {
        setBlockedIds(blockedIds.filter((x) => x !== id));
    };

    const filteredFavCaregivers = (caregivers ?? []).filter(
        (c) =>
            !favoriteIds.includes(c.id) &&
            !blockedIds.includes(c.id) &&
            `${c.first_name} ${c.last_name}`
                .toLowerCase()
                .includes(searchFav.toLowerCase()),
    );

    const filteredBlockCaregivers = (caregivers ?? []).filter(
        (c) =>
            !favoriteIds.includes(c.id) &&
            !blockedIds.includes(c.id) &&
            `${c.first_name} ${c.last_name}`
                .toLowerCase()
                .includes(searchBlock.toLowerCase()),
    );

    const submit: SubmitEventHandler = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            favorite_caregiver_ids: favoriteIds,
            blocked_caregiver_ids: blockedIds,
        }));

        form.patch(`/clients/${client.id}`, {
            onSuccess: () => {
                console.log('Client updated successfully');
                // toast.success('Client updated successfully');
            },
            onError: () => {
                // toast.error('Failed to update client');
            },
        });
    };

    const handleAttributeChange = (attributeId: number, checked: boolean) => {
        const newValues = {
            ...attributeValues,
            [attributeId]: checked ? 'true' : 'false',
        };
        setAttributeValues(newValues);
        form.setData('attributes', newValues);
    };

    const handlePreferenceChange = (pref: string, checked: boolean) => {
        if (checked) {
            form.setData('sitter_preferences', [
                ...form.data.sitter_preferences,
                pref,
            ]);
        } else {
            form.setData(
                'sitter_preferences',
                form.data.sitter_preferences.filter((p: string) => p !== pref),
            );
        }
    };

    const allErrors = form.errors as Record<string, string>;

    const getAddrError = (index: number, field: string) =>
        allErrors[`addresses.${index}.${field}`];

    const getChildError = (index: number, field: string) =>
        allErrors[`children.${index}.${field}`];

    const getPetError = (index: number, field: string) =>
        allErrors[`pets.${index}.${field}`];

    const hasPrefixedError = (prefix: string) =>
        Object.keys(allErrors).some((key) => key.startsWith(prefix));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`Edit ${client.first_name} ${client.last_name}`} />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <form onSubmit={submitPhotoForm}>
                        <div className="flex items-center gap-4">
                            <Link
                                href={`/clients/${client.id}`}
                                className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                            >
                                <ArrowLeft className="h-5 w-5" />
                            </Link>
                            <div className="group relative">
                                <UserAvatar
                                    profile_photo_url={
                                        client.user?.profile_photo_url ?? null
                                    }
                                    profile_photo_path={currentProfilePhoto}
                                    name={`${client.first_name} ${client.last_name}`}
                                    size="md"
                                    className="size-10 md:size-16"
                                />
                                {photoForm.processing && (
                                    <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                                        <Spinner className="h-5 w-5" />
                                    </div>
                                )}
                                <Label className="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        disabled={photoForm.processing}
                                        onChange={handlePhotoFormChange}
                                    />
                                    <span className="text-xs font-medium text-white">
                                        Change
                                    </span>
                                </Label>
                            </div>
                        </div>
                    </form>
                    <div>
                        <div>
                            <h1 className="text-xl font-bold text-foreground md:text-2xl">
                                {client.first_name} {client.last_name}
                            </h1>
                            <p className="hidden text-muted-foreground md:block">
                                Edit Client Profile
                            </p>
                            {photoForm.errors.profile_photo && (
                                <p className="mt-1 text-xs font-medium text-destructive">
                                    {photoForm.errors.profile_photo}
                                </p>
                            )}
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="first_name">
                                    First Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    aria-invalid={!!form.errors.first_name}
                                />
                                {form.errors.first_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.first_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="last_name">
                                    Last Name{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    aria-invalid={!!form.errors.last_name}
                                />
                                {form.errors.last_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.last_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <PhoneInput
                                    value={form.data.phone}
                                    onChange={(value) =>
                                        form.setData('phone', value)
                                    }
                                    name="phone"
                                    label="Cell Phone"
                                    required
                                    error={form.errors.phone}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>
                                    Client Type{' '}
                                    <span className="text-red-500">*</span>
                                </Label>
                                <Select
                                    value={form.data.client_type}
                                    onValueChange={(value) =>
                                        form.setData('client_type', value)
                                    }
                                >
                                    <SelectTrigger
                                        aria-invalid={!!form.errors.client_type}
                                    >
                                        <SelectValue placeholder="Select type" />
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
                                {form.errors.client_type && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.client_type}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="how_did_you_hear">
                                    How did you hear about us?
                                </Label>
                                <Select
                                    value={form.data.how_did_you_hear}
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
                                {form.errors.how_did_you_hear && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.how_did_you_hear}
                                    </p>
                                )}
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
                                <Label htmlFor="other_adults_present">
                                    Other Adults in Home
                                </Label>
                                {form.errors.other_adults_present && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.other_adults_present}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="biography">Biography</Label>
                                <Textarea
                                    id="biography"
                                    value={form.data.biography || ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'biography',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    aria-invalid={!!form.errors.biography}
                                />
                                {form.errors.biography && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.biography}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="special_needs_notes">
                                    Special Needs Notes
                                </Label>
                                <Textarea
                                    id="special_needs_notes"
                                    value={form.data.special_needs_notes || ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'special_needs_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                />
                                {form.errors.special_needs_notes && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.special_needs_notes}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <Label htmlFor="notes">Admin Notes</Label>
                                <Textarea
                                    id="notes"
                                    value={form.data.notes || ''}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={3}
                                    placeholder="Internal notes, not visible to client"
                                    aria-invalid={!!form.errors.notes}
                                />
                                {form.errors.notes && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.notes}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Sitter Preferences
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                            {sitter_preferences.map((pref) => (
                                <div
                                    key={pref.value}
                                    className="flex items-center gap-2"
                                >
                                    <Checkbox
                                        id={`pref-${pref.value}`}
                                        checked={form.data.sitter_preferences.includes(
                                            pref.value,
                                        )}
                                        onCheckedChange={(checked) =>
                                            handlePreferenceChange(
                                                pref.value,
                                                checked as boolean,
                                            )
                                        }
                                    />
                                    <Label
                                        htmlFor={`pref-${pref.value}`}
                                        className="text-sm"
                                    >
                                        {pref.label}
                                    </Label>
                                </div>
                            ))}
                        </div>
                        {form.errors.sitter_preferences && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.sitter_preferences}
                            </p>
                        )}
                    </div>

                    {attribute_definitions.length > 0 && (
                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Attributes
                            </h2>
                            <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                                {attribute_definitions.map((def) => (
                                    <div
                                        key={def.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`attr-${def.id}`}
                                            checked={
                                                attributeValues[def.id] ===
                                                'true'
                                            }
                                            onCheckedChange={(checked) =>
                                                handleAttributeChange(
                                                    def.id,
                                                    checked as boolean,
                                                )
                                            }
                                        />
                                        <Label htmlFor={`attr-${def.id}`}>
                                            {def.name}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                            {form.errors.attributes && (
                                <p className="mt-2 text-sm text-destructive">
                                    {form.errors.attributes}
                                </p>
                            )}
                        </div>
                    )}

                    <div className="border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Children ({form.data.children.length})
                            </h2>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    form.setData('children', [
                                        ...form.data.children,
                                        {
                                            id: null,
                                            name: '',
                                            gender: null,
                                            birth_month: null,
                                            birth_year: null,
                                        },
                                    ]);
                                }}
                            >
                                + Add Child
                            </Button>
                        </div>
                        {form.data.children.length > 0 ? (
                            <div className="space-y-4">
                                {form.data.children.map((child, index) => (
                                    <div
                                        key={child.id || `child-${index}`}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background px-4 py-2 lg:grid-cols-7"
                                    >
                                        <div className="space-y-1 sm:col-span-2">
                                            <Input
                                                type="text"
                                                value={child.name || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        name: e.target.value,
                                                    };
                                                    form.setData(
                                                        'children',
                                                        updated,
                                                    );
                                                }}
                                                placeholder="Name"
                                                aria-invalid={
                                                    !!getChildError(
                                                        index,
                                                        'name',
                                                    )
                                                }
                                            />
                                            {getChildError(index, 'name') && (
                                                <p className="text-sm text-destructive">
                                                    {getChildError(
                                                        index,
                                                        'name',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Select
                                                value={child.gender || ''}
                                                onValueChange={(value) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        gender: value || null,
                                                    };
                                                    form.setData(
                                                        'children',
                                                        updated,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger
                                                    aria-invalid={
                                                        !!getChildError(
                                                            index,
                                                            'gender',
                                                        )
                                                    }
                                                >
                                                    <SelectValue placeholder="Gender" />
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
                                            {getChildError(index, 'gender') && (
                                                <p className="text-sm text-destructive">
                                                    {getChildError(
                                                        index,
                                                        'gender',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Select
                                                value={
                                                    child.birth_month?.toString() ||
                                                    ''
                                                }
                                                onValueChange={(value) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        birth_month: value
                                                            ? parseInt(value)
                                                            : null,
                                                    };
                                                    form.setData(
                                                        'children',
                                                        updated,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger
                                                    aria-invalid={
                                                        !!getChildError(
                                                            index,
                                                            'birth_month',
                                                        )
                                                    }
                                                >
                                                    <SelectValue placeholder="Month" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {Array.from(
                                                        { length: 12 },
                                                        (_, i) => i + 1,
                                                    ).map((month) => (
                                                        <SelectItem
                                                            key={month}
                                                            value={month.toString()}
                                                        >
                                                            {new Date(
                                                                2000,
                                                                month - 1,
                                                            ).toLocaleString(
                                                                'default',
                                                                {
                                                                    month: 'short',
                                                                },
                                                            )}
                                                        </SelectItem>
                                                    ))}
                                                </SelectContent>
                                            </Select>
                                            {getChildError(
                                                index,
                                                'birth_month',
                                            ) && (
                                                <p className="text-sm text-destructive">
                                                    {getChildError(
                                                        index,
                                                        'birth_month',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Input
                                                type="number"
                                                value={child.birth_year || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        birth_year: e.target
                                                            .value
                                                            ? parseInt(
                                                                  e.target
                                                                      .value,
                                                              )
                                                            : null,
                                                    };
                                                    form.setData(
                                                        'children',
                                                        updated,
                                                    );
                                                }}
                                                placeholder="Year"
                                                aria-invalid={
                                                    !!getChildError(
                                                        index,
                                                        'birth_year',
                                                    )
                                                }
                                            />
                                            {getChildError(
                                                index,
                                                'birth_year',
                                            ) && (
                                                <p className="text-sm text-destructive">
                                                    {getChildError(
                                                        index,
                                                        'birth_year',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center text-sm text-muted-foreground">
                                            {calculateAge(
                                                child.birth_year,
                                                child.birth_month,
                                            )}
                                        </div>
                                        <div className="flex items-center justify-end">
                                            <Button
                                                size="sm"
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'children',
                                                        form.data.children.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No children on file
                            </p>
                        )}
                    </div>

                    <div className="border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Pets ({form.data.pets.length})
                            </h2>
                            <Button
                                size="sm"
                                type="button"
                                onClick={() => {
                                    form.setData('pets', [
                                        ...form.data.pets,
                                        {
                                            id: null,
                                            name: '',
                                            type: '',
                                            breed: null,
                                            notes: null,
                                        },
                                    ]);
                                }}
                            >
                                + Add Pet
                            </Button>
                        </div>
                        {form.data.pets.length > 0 ? (
                            <div className="space-y-4">
                                {form.data.pets.map((pet, index) => (
                                    <div
                                        key={pet.id || `pet-${index}`}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background px-4 py-2 sm:grid-cols-5"
                                    >
                                        <div className="space-y-1 sm:col-span-2">
                                            <Input
                                                type="text"
                                                value={pet.name || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.pets,
                                                    ];
                                                    updated[index] = {
                                                        ...pet,
                                                        name: e.target.value,
                                                    };
                                                    form.setData(
                                                        'pets',
                                                        updated,
                                                    );
                                                }}
                                                placeholder="Name"
                                                aria-invalid={
                                                    !!getPetError(index, 'name')
                                                }
                                            />
                                            {getPetError(index, 'name') && (
                                                <p className="text-sm text-destructive">
                                                    {getPetError(index, 'name')}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Select
                                                value={pet.type || ''}
                                                onValueChange={(value) => {
                                                    const updated = [
                                                        ...form.data.pets,
                                                    ];
                                                    updated[index] = {
                                                        ...pet,
                                                        type: value,
                                                    };
                                                    form.setData(
                                                        'pets',
                                                        updated,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger
                                                    aria-invalid={
                                                        !!getPetError(
                                                            index,
                                                            'type',
                                                        )
                                                    }
                                                >
                                                    <SelectValue placeholder="Type" />
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
                                            {getPetError(index, 'type') && (
                                                <p className="text-sm text-destructive">
                                                    {getPetError(index, 'type')}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Input
                                                type="text"
                                                value={pet.breed || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.pets,
                                                    ];
                                                    updated[index] = {
                                                        ...pet,
                                                        breed:
                                                            e.target.value ||
                                                            null,
                                                    };
                                                    form.setData(
                                                        'pets',
                                                        updated,
                                                    );
                                                }}
                                                placeholder="Breed"
                                                aria-invalid={
                                                    !!getPetError(
                                                        index,
                                                        'breed',
                                                    )
                                                }
                                            />
                                            {getPetError(index, 'breed') && (
                                                <p className="text-sm text-destructive">
                                                    {getPetError(
                                                        index,
                                                        'breed',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-end">
                                            <Button
                                                size="sm"
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'pets',
                                                        form.data.pets.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No pets on file
                            </p>
                        )}
                    </div>

                    <div className="border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Addresses ({form.data.addresses.length})
                            </h2>
                            <Button
                                type="button"
                                size="sm"
                                onClick={() => {
                                    form.setData('addresses', [
                                        ...form.data.addresses,
                                        {
                                            id: null,
                                            label: '',
                                            location_type: 'private_home',
                                            line1: '',
                                            line2: '',
                                            city: '',
                                            state: '',
                                            zip: '',
                                            is_primary:
                                                form.data.addresses.length ===
                                                0,
                                        },
                                    ]);
                                }}
                            >
                                + Add Address
                            </Button>
                        </div>
                        {form.data.addresses.length > 0 ? (
                            <div className="space-y-4">
                                {form.data.addresses.map((address, index) => (
                                    <div
                                        key={address.id || `address-${index}`}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background px-4 py-2 sm:grid-cols-2 lg:grid-cols-4"
                                    >
                                        <div className="lg:col space-y-1 sm:col-span-2">
                                            <Input
                                                type="text"
                                                value={address.label || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.addresses,
                                                    ];
                                                    updated[index] = {
                                                        ...address,
                                                        label: e.target.value,
                                                    };
                                                    form.setData(
                                                        'addresses',
                                                        updated,
                                                    );
                                                }}
                                                placeholder="Label (e.g., Home, Hotel)"
                                                aria-invalid={
                                                    !!getAddrError(
                                                        index,
                                                        'label',
                                                    )
                                                }
                                            />
                                            {getAddrError(index, 'label') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'label',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="space-y-1">
                                            <Select
                                                value={
                                                    address.location_type ||
                                                    'private_home'
                                                }
                                                onValueChange={(value) => {
                                                    const updated = [
                                                        ...form.data.addresses,
                                                    ];
                                                    updated[index] = {
                                                        ...address,
                                                        location_type: value,
                                                    };
                                                    form.setData(
                                                        'addresses',
                                                        updated,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger
                                                    aria-invalid={
                                                        !!getAddrError(
                                                            index,
                                                            'location_type',
                                                        )
                                                    }
                                                >
                                                    <SelectValue placeholder="Location Type" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="private_home">
                                                        Private Home
                                                    </SelectItem>
                                                    <SelectItem value="hotel">
                                                        Hotel
                                                    </SelectItem>
                                                    <SelectItem value="vacation_rental">
                                                        Vacation Rental
                                                    </SelectItem>
                                                    <SelectItem value="event_venue">
                                                        Event Venue
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {getAddrError(
                                                index,
                                                'location_type',
                                            ) && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'location_type',
                                                    )}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-center justify-start sm:justify-center lg:justify-end">
                                            <div className="flex h-11 items-center gap-2">
                                                <Checkbox
                                                    id={`address-primary-${index}`}
                                                    checked={address.is_primary}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const updated =
                                                            form.data.addresses.map(
                                                                (a, i) => ({
                                                                    ...a,
                                                                    is_primary:
                                                                        i ===
                                                                        index
                                                                            ? (checked as boolean)
                                                                            : false,
                                                                }),
                                                            );
                                                        form.setData(
                                                            'addresses',
                                                            updated,
                                                        );
                                                    }}
                                                />
                                                <Label
                                                    htmlFor={`address-primary-${index}`}
                                                >
                                                    Primary
                                                </Label>
                                            </div>
                                        </div>
                                        <div className="space-y-1 sm:col-span-2 lg:col-span-3">
                                            <AddressAutocomplete
                                                form={form}
                                                prefix={`addresses.${index}.`}
                                                label="Address"
                                            />
                                            {getAddrError(index, 'line1') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'line1',
                                                    )}
                                                </p>
                                            )}
                                            {getAddrError(index, 'line2') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'line2',
                                                    )}
                                                </p>
                                            )}
                                            {getAddrError(index, 'city') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'city',
                                                    )}
                                                </p>
                                            )}
                                            {getAddrError(index, 'state') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(
                                                        index,
                                                        'state',
                                                    )}
                                                </p>
                                            )}
                                            {getAddrError(index, 'zip') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(index, 'zip')}
                                                </p>
                                            )}
                                            {getAddrError(index, 'id') && (
                                                <p className="text-sm text-destructive">
                                                    {getAddrError(index, 'id')}
                                                </p>
                                            )}
                                        </div>
                                        <div className="flex items-end justify-end sm:col-span-2 lg:col-auto">
                                            <Button
                                                size="sm"
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'addresses',
                                                        form.data.addresses.filter(
                                                            (_, i) =>
                                                                i !== index,
                                                        ),
                                                    );
                                                }}
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="text-sm text-muted-foreground">
                                No addresses on file
                            </p>
                        )}
                    </div>

                    <div className="border border-green-200 bg-green-50 p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-green-800">
                                Favorite Caregivers ({favoriteIds.length})
                            </h2>
                        </div>
                        {favoriteIds.length > 0 ? (
                            <div className="mb-4 space-y-2">
                                {favoriteIds.map((id) => {
                                    const c = (caregivers ?? []).find(
                                        (cg) => cg.id === id,
                                    );

                                    if (!c) {
                                        return null;
                                    }

                                    return (
                                        <div
                                            key={id}
                                            className="flex items-center justify-between rounded-[3px] border border-green-200 bg-white px-4 py-2"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="text-sm font-medium text-foreground">
                                                    {c.first_name} {c.last_name}
                                                </span>
                                            </div>
                                            <Button
                                                size="sm"
                                                type="button"
                                                onClick={() =>
                                                    removeFavorite(id)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="mb-4 text-sm text-muted-foreground">
                                No favorite caregivers
                            </p>
                        )}
                        <div>
                            <Input
                                type="text"
                                placeholder="Search caregivers to add..."
                                value={searchFav}
                                onChange={(e) => setSearchFav(e.target.value)}
                                className="mb-2"
                            />
                            {searchFav && filteredFavCaregivers.length > 0 && (
                                <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-green-200 bg-white">
                                    {filteredFavCaregivers
                                        .slice(0, 5)
                                        .map((c) => (
                                            <button
                                                key={c.id}
                                                type="button"
                                                onClick={() => {
                                                    addFavorite(c.id);
                                                    setSearchFav('');
                                                }}
                                                className="flex w-full items-center gap-2 p-2 text-left hover:bg-green-50"
                                            >
                                                <span className="text-sm font-medium text-foreground">
                                                    {c.first_name} {c.last_name}
                                                </span>
                                            </button>
                                        ))}
                                </div>
                            )}
                            {searchFav &&
                                filteredFavCaregivers.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No caregivers found
                                    </p>
                                )}
                        </div>
                        {form.errors.favorite_caregiver_ids && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.favorite_caregiver_ids}
                            </p>
                        )}
                        {hasPrefixedError('favorite_caregiver_ids.') &&
                            Object.entries(form.errors)
                                .filter(([k]) =>
                                    k.startsWith('favorite_caregiver_ids.'),
                                )
                                .map(([key, msg]) => (
                                    <p
                                        key={key}
                                        className="text-sm text-destructive"
                                    >
                                        {msg}
                                    </p>
                                ))}
                    </div>

                    <div className="border border-red-200 bg-red-50 p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-red-800">
                                Blocked Caregivers ({blockedIds.length})
                            </h2>
                        </div>
                        {blockedIds.length > 0 ? (
                            <div className="mb-4 space-y-2">
                                {blockedIds.map((id) => {
                                    const c = (caregivers ?? []).find(
                                        (cg) => cg.id === id,
                                    );

                                    if (!c) {
                                        return null;
                                    }

                                    return (
                                        <div
                                            key={id}
                                            className="flex items-center justify-between rounded-[3px] border border-red-200 bg-white px-4 py-2"
                                        >
                                            <div className="flex items-center gap-3">
                                                <span className="text-sm font-medium text-foreground">
                                                    {c.first_name} {c.last_name}
                                                </span>
                                            </div>
                                            <Button
                                                size="sm"
                                                type="button"
                                                onClick={() =>
                                                    removeBlocked(id)
                                                }
                                            >
                                                <Trash2 className="h-4 w-4" />
                                            </Button>
                                        </div>
                                    );
                                })}
                            </div>
                        ) : (
                            <p className="mb-4 text-sm text-muted-foreground">
                                No blocked caregivers
                            </p>
                        )}
                        <div>
                            <Input
                                type="text"
                                placeholder="Search caregivers to block..."
                                value={searchBlock}
                                onChange={(e) => setSearchBlock(e.target.value)}
                                className="mb-2"
                            />
                            {searchBlock &&
                                filteredBlockCaregivers.length > 0 && (
                                    <div className="max-h-40 space-y-1 overflow-y-auto rounded border border-red-200 bg-white">
                                        {filteredBlockCaregivers
                                            .slice(0, 5)
                                            .map((c) => (
                                                <button
                                                    key={c.id}
                                                    type="button"
                                                    onClick={() => {
                                                        addBlocked(c.id);
                                                        setSearchBlock('');
                                                    }}
                                                    className="flex w-full items-center gap-2 p-2 text-left hover:bg-red-50"
                                                >
                                                    <span className="font-medium text-foreground">
                                                        {c.first_name}{' '}
                                                        {c.last_name}
                                                    </span>
                                                </button>
                                            ))}
                                    </div>
                                )}
                            {searchBlock &&
                                filteredBlockCaregivers.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No caregivers found
                                    </p>
                                )}
                        </div>
                        {form.errors.blocked_caregiver_ids && (
                            <p className="mt-2 text-sm text-destructive">
                                {form.errors.blocked_caregiver_ids}
                            </p>
                        )}
                        {hasPrefixedError('blocked_caregiver_ids.') &&
                            Object.entries(form.errors)
                                .filter(([k]) =>
                                    k.startsWith('blocked_caregiver_ids.'),
                                )
                                .map(([key, msg]) => (
                                    <p
                                        key={key}
                                        className="text-sm text-destructive"
                                    >
                                        {msg}
                                    </p>
                                ))}
                    </div>

                    {/* eslint-disable-next-line no-constant-binary-expression */}
                    {false && (
                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Additional Information
                            </h2>
                            <div className="space-y-4">
                                <div className="space-y-2">
                                    <Label htmlFor="emergency_instructions">
                                        Emergency Instructions
                                    </Label>
                                    <Textarea
                                        id="emergency_instructions"
                                        value={form.data.emergency_instructions}
                                        onChange={(e) =>
                                            form.setData(
                                                'emergency_instructions',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                        placeholder="Emergency instructions for caregivers..."
                                    />
                                </div>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() =>
                                (window.location.href = `/clients/${client.id}`)
                            }
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing ? <Spinner /> : null}
                            {form.processing ? 'Saving...' : 'Save Changes'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
