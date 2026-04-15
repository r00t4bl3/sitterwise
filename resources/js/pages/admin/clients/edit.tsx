import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import type { SubmitEventHandler } from 'react';
import { toast } from 'sonner';
import { ToasterMessage } from '@/components/toaster-message';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
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
    email: string;
    phone: string;
    client_type: string;
    how_did_you_hear: string | null;
    sitter_preferences: string[] | null;
    other_adults_present: string | null;
    emergency_instructions: string | null;
    special_needs_notes: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
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

interface Props {
    [key: string]: unknown;
    client: Client;
    attribute_definitions: AttributeDefinition[];
    csrf_token: string;
}

export default function ClientEdit() {
    const { client, attribute_definitions, csrf_token } =
        usePage<Props>().props;

    const [currentProfilePhoto, setCurrentProfilePhoto] = useState(
        client.user.profile_photo_path,
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

    const handlePhotoFormChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        const file = e.target.files?.[0] ?? null;
        photoForm.setData('profile_photo', file);

        if (file && e.target.form) {
            e.target.form.requestSubmit();
        }
    };

    const submitPhotoForm: SubmitEventHandler = (e) => {
        e.preventDefault();

        if (photoForm.data.profile_photo === null) {
            return;
        }

        photoForm.post(`/clients/${client.id}/profile-photo`, {
            onSuccess: (page) => {
                const newPath = (page.props as any).client?.user
                    ?.profile_photo_path;

                if (newPath) {
                    setCurrentProfilePhoto(newPath);
                }

                photoForm.reset();
            },
        });
    };

    const form = useForm({
        first_name: client.first_name,
        last_name: client.last_name,
        phone: client.phone,
        client_type: client.client_type,
        how_did_you_hear: client.how_did_you_hear || '',
        sitter_preferences: client.sitter_preferences || [],
        other_adults_present: client.other_adults_present || '',
        emergency_instructions: client.emergency_instructions || '',
        special_needs_notes: client.special_needs_notes || '',
        attributes: attributeValues,
        children: client.children,
        pets: client.pets,
        addresses: client.addresses,
    });

    const submit: SubmitEventHandler = (e) => {
        e.preventDefault();

        form.patch(`/clients/${client.id}`, {
            onSuccess: () => {
                toast.success('Client updated successfully');
            },
            onError: () => {
                toast.error('Failed to update client');
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

    const sitterPreferenceOptions = [
        'college_aged',
        'seasoned',
        'baby_specialist',
        'special_needs_exp',
        'willing_to_swim',
    ];

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
                            {currentProfilePhoto ? (
                                <div className="group relative">
                                    <img
                                        src={
                                            currentProfilePhoto === 'avatar.jpg'
                                                ? '/avatar.jpg'
                                                : `/storage/${currentProfilePhoto}`
                                        }
                                        alt={`${client.first_name} ${client.last_name}`}
                                        className="h-16 w-16 rounded-full object-cover"
                                    />
                                    {photoForm.processing && (
                                        <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                                            <Spinner className="h-5 w-5" />
                                        </div>
                                    )}
                                    <label className="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            disabled={photoForm.processing}
                                            onChange={handlePhotoFormChange}
                                        />
                                        <span className="text-xs font-medium text-white">
                                            Change
                                        </span>
                                    </label>
                                </div>
                            ) : (
                                <div className="group relative">
                                    <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100 group-hover:bg-amber-200">
                                        <span className="text-2xl font-medium text-amber-600">
                                            {client.first_name[0]}
                                            {client.last_name[0]}
                                        </span>
                                    </div>
                                    {photoForm.processing && (
                                        <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                                            <Spinner className="h-5 w-5" />
                                        </div>
                                    )}
                                    <label className="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 opacity-0 transition-opacity group-hover:opacity-100">
                                        <input
                                            type="file"
                                            accept="image/*"
                                            className="hidden"
                                            disabled={photoForm.processing}
                                            onChange={handlePhotoFormChange}
                                        />
                                        <span className="text-xs font-medium text-white">
                                            Change
                                        </span>
                                    </label>
                                </div>
                            )}
                        </div>
                    </form>
                    <div>
                        <div>
                            <h1 className="text-2xl font-bold text-foreground">
                                {client.first_name} {client.last_name}
                            </h1>
                            <p className="text-muted-foreground">
                                Edit Client Profile
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <input type="hidden" name="_token" value={csrf_token} />

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    First Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Last Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Cell Phone{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Client Type{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.data.client_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'client_type',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                >
                                    <option value="vacationer">
                                        Vacationer
                                    </option>
                                    <option value="sd_resident">
                                        SD Resident
                                    </option>
                                    <option value="invoiced">Invoiced</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    How did you hear about us?
                                </label>
                                <select
                                    value={form.data.how_did_you_hear}
                                    onChange={(e) =>
                                        form.setData(
                                            'how_did_you_hear',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                >
                                    <option value="">Select an option</option>
                                    <option value="concierge">Concierge</option>
                                    <option value="friend_family">
                                        Friend/Family
                                    </option>
                                    <option value="google">Google</option>
                                    <option value="returning_client">
                                        Returning Client
                                    </option>
                                    <option value="care_com">Care.com</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <label className="text-sm font-medium text-foreground">
                                    Special Needs Notes
                                </label>
                                <textarea
                                    value={form.data.special_needs_notes || ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'special_needs_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Other Adults in Home
                                </label>
                                <input
                                    type="text"
                                    value={form.data.other_adults_present}
                                    onChange={(e) =>
                                        form.setData(
                                            'other_adults_present',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Sitter Preferences
                        </h2>
                        <div className="flex flex-wrap gap-4">
                            {sitterPreferenceOptions.map((pref) => (
                                <label
                                    key={pref}
                                    className="flex items-center gap-2"
                                >
                                    <input
                                        type="checkbox"
                                        checked={form.data.sitter_preferences.includes(
                                            pref,
                                        )}
                                        onChange={(e) =>
                                            handlePreferenceChange(
                                                pref,
                                                e.target.checked,
                                            )
                                        }
                                        className="h-4 w-4 rounded border-input text-primary"
                                    />
                                    <span className="text-sm text-foreground capitalize">
                                        {pref.replace(/_/g, ' ')}
                                    </span>
                                </label>
                            ))}
                        </div>
                    </div>

                    {attribute_definitions.length > 0 && (
                        <div className="rounded-[6px] border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Attributes
                            </h2>
                            <div className="flex flex-wrap gap-4">
                                {attribute_definitions.map((def) => (
                                    <label
                                        key={def.id}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={
                                                attributeValues[def.id] ===
                                                'true'
                                            }
                                            onChange={(e) =>
                                                handleAttributeChange(
                                                    def.id,
                                                    e.target.checked,
                                                )
                                            }
                                            className="h-4 w-4 rounded border-input text-primary"
                                        />
                                        <span className="text-sm text-foreground">
                                            {def.name}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Children ({form.data.children.length})
                            </h2>
                            <Button
                                variant="link"
                                type="button"
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
                                        key={child.id}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background p-3 sm:grid-cols-7"
                                    >
                                        <div className="sm:col-span-2">
                                            <input
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            />
                                        </div>
                                        <div>
                                            <select
                                                value={child.gender || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        gender:
                                                            e.target.value ||
                                                            null,
                                                    };
                                                    form.setData(
                                                        'children',
                                                        updated,
                                                    );
                                                }}
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            >
                                                <option value="">Gender</option>
                                                <option value="male">
                                                    Male
                                                </option>
                                                <option value="female">
                                                    Female
                                                </option>
                                                <option value="other">
                                                    Other
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <select
                                                value={child.birth_month || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.children,
                                                    ];
                                                    updated[index] = {
                                                        ...child,
                                                        birth_month: e.target
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            >
                                                <option value="">Month</option>
                                                {Array.from(
                                                    { length: 12 },
                                                    (_, i) => i + 1,
                                                ).map((month) => (
                                                    <option
                                                        key={month}
                                                        value={month}
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
                                                    </option>
                                                ))}
                                            </select>
                                        </div>
                                        <div>
                                            <input
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            />
                                        </div>
                                        <div className="flex items-center justify-end">
                                            <Button
                                                variant="ghost"
                                                size="sm"
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'children',
                                                        form.data.children.filter(
                                                            (c) =>
                                                                c.id !==
                                                                child.id,
                                                        ),
                                                    );
                                                }}
                                                className="text-destructive hover:text-destructive"
                                            >
                                                Remove
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

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Pets ({form.data.pets.length})
                            </h2>
                            <Button
                                variant="link"
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
                                        key={pet.id}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background p-3 sm:grid-cols-5"
                                    >
                                        <div>
                                            <input
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            />
                                        </div>
                                        <div>
                                            <select
                                                value={pet.type || ''}
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.pets,
                                                    ];
                                                    updated[index] = {
                                                        ...pet,
                                                        type: e.target.value,
                                                    };
                                                    form.setData(
                                                        'pets',
                                                        updated,
                                                    );
                                                }}
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            >
                                                <option value="">Type</option>
                                                <option value="dog">Dog</option>
                                                <option value="cat">Cat</option>
                                                <option value="bird">
                                                    Bird
                                                </option>
                                                <option value="fish">
                                                    Fish
                                                </option>
                                                <option value="other">
                                                    Other
                                                </option>
                                            </select>
                                        </div>
                                        <div>
                                            <input
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            />
                                        </div>
                                        <div className="flex items-center justify-end">
                                            <button
                                                type="button"
                                                onClick={() => {
                                                    form.setData(
                                                        'pets',
                                                        form.data.pets.filter(
                                                            (p) =>
                                                                p.id !== pet.id,
                                                        ),
                                                    );
                                                }}
                                                className="text-sm text-destructive hover:text-destructive"
                                            >
                                                Remove
                                            </button>
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

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <div className="mb-4 flex items-center justify-between">
                            <h2 className="font-serif text-lg font-semibold text-foreground">
                                Addresses ({form.data.addresses.length})
                            </h2>
                            <Button
                                type="button"
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
                                variant="link"
                            >
                                + Add Address
                            </Button>
                        </div>
                        {form.data.addresses.length > 0 ? (
                            <div className="space-y-4">
                                {form.data.addresses.map((address, index) => (
                                    <div
                                        key={address.id || `new-${index}`}
                                        className="grid grid-cols-1 gap-3 rounded-[3px] border border-border bg-background p-3 sm:grid-cols-2 lg:grid-cols-3"
                                    >
                                        <div>
                                            <input
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
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            />
                                        </div>
                                        <div>
                                            <select
                                                value={
                                                    address.location_type ||
                                                    'private_home'
                                                }
                                                onChange={(e) => {
                                                    const updated = [
                                                        ...form.data.addresses,
                                                    ];
                                                    updated[index] = {
                                                        ...address,
                                                        location_type:
                                                            e.target.value,
                                                    };
                                                    form.setData(
                                                        'addresses',
                                                        updated,
                                                    );
                                                }}
                                                className="h-9 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                            >
                                                <option value="private_home">
                                                    Private Home
                                                </option>
                                                <option value="hotel">
                                                    Hotel
                                                </option>
                                                <option value="vacation_rental">
                                                    Vacation Rental
                                                </option>
                                                <option value="event_venue">
                                                    Event Venue
                                                </option>
                                            </select>
                                        </div>
                                        <div className="flex items-center">
                                            <label className="flex items-center gap-2 text-sm">
                                                <input
                                                    type="checkbox"
                                                    checked={address.is_primary}
                                                    onChange={(e) => {
                                                        const updated =
                                                            form.data.addresses.map(
                                                                (a, i) => ({
                                                                    ...a,
                                                                    is_primary:
                                                                        i ===
                                                                        index
                                                                            ? e
                                                                                  .target
                                                                                  .checked
                                                                            : false,
                                                                }),
                                                            );
                                                        form.setData(
                                                            'addresses',
                                                            updated,
                                                        );
                                                    }}
                                                    className="h-4 w-4 rounded border-input"
                                                />
                                                Primary
                                            </label>
                                        </div>
                                        <div className="sm:col-span-2 lg:col-span-3">
                                            <AddressAutocomplete
                                                form={form}
                                                prefix={`addresses.${index}.`}
                                                label="Address"
                                            />
                                        </div>
                                        <div className="flex items-center justify-end sm:col-span-2 lg:col-span-3">
                                            <Button
                                                variant="link"
                                                size="sm"
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
                                                Remove
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

                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Additional Information
                        </h2>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Emergency Instructions
                                </label>
                                <textarea
                                    value={form.data.emergency_instructions}
                                    onChange={(e) =>
                                        form.setData(
                                            'emergency_instructions',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    placeholder="Emergency instructions for caregivers..."
                                />
                            </div>
                        </div>
                    </div>

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
