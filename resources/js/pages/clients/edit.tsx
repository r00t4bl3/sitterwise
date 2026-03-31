import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { useState, type SubmitEventHandler } from 'react';
import AppLayout from '@/layouts/app-layout';
import { Spinner } from '@/components/ui/spinner';
import { ToasterMessage } from '@/components/toaster-message';
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
    id: number;
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
    id: number;
    name: string | null;
    gender: string | null;
    birth_month: number | null;
    birth_year: number | null;
    special_needs: boolean;
    special_needs_notes: string | null;
}

interface Pet {
    id: number;
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
    cell_phone: string;
    client_type: string;
    how_did_you_hear: string | null;
    sitter_preferences: string[] | null;
    other_adults_in_home: string | null;
    medical_info: string | null;
    emergency_instructions: string | null;
    caregiver_notes: string | null;
    user: {
        profile_photo_path: string | null;
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
    value: string;
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
            initial[def.id] = existing?.value === 'true' ? 'true' : 'false';
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
            photoForm.data.profile_photo = file;
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
        cell_phone: client.cell_phone,
        client_type: client.client_type,
        how_did_you_hear: client.how_did_you_hear || '',
        sitter_preferences: client.sitter_preferences || [],
        other_adults_in_home: client.other_adults_in_home || '',
        medical_info: client.medical_info || '',
        emergency_instructions: client.emergency_instructions || '',
        caregiver_notes: client.caregiver_notes || '',
        attributes: attributeValues,
    });

    const submit: SubmitEventHandler = (e) => {
        e.preventDefault();
        form.patch(`/clients/${client.id}`, {
            onSuccess: () => {},
        });
    };

    const handleAttributeChange = (attributeId: number, checked: boolean) => {
        setAttributeValues((prev) => ({
            ...prev,
            [attributeId]: checked ? 'true' : 'false',
        }));
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
                                    value={form.data.cell_phone}
                                    onChange={(e) =>
                                        form.setData(
                                            'cell_phone',
                                            e.target.value,
                                        )
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
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Other Adults in Home
                                </label>
                                <input
                                    type="text"
                                    value={form.data.other_adults_in_home}
                                    onChange={(e) =>
                                        form.setData(
                                            'other_adults_in_home',
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
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Additional Information
                        </h2>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Medical Info
                                </label>
                                <textarea
                                    value={form.data.medical_info}
                                    onChange={(e) =>
                                        form.setData(
                                            'medical_info',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    placeholder="Any medical information caregivers should know..."
                                />
                            </div>
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
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Admin Notes
                                </label>
                                <textarea
                                    value={form.data.caregiver_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'caregiver_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={3}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    placeholder="Internal notes about this client..."
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Link
                            href={`/clients/${client.id}`}
                            className="btn-secondary"
                        >
                            Cancel
                        </Link>
                        <button type="submit" className="btn-primary">
                            Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
