import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    ChevronDown,
    CheckCircle2,
    AlertCircle,
} from 'lucide-react';
import { SubmitEventHandler, useState, type FormEventHandler } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DatePicker } from '@/components/ui/date-picker';
import { Checkbox } from '@/components/ui/checkbox';
import { Spinner } from '@/components/ui/spinner';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Caregivers',
        href: '/caregivers',
    },
    {
        title: 'Edit Caregiver',
        href: '#',
    },
];

interface Status {
    id: number;
    name: string;
    color: string;
}

interface SpecialtyType {
    id: number;
    name: string;
}

interface Location {
    id: number;
    name: string;
}

interface AttributeDefinition {
    id: number;
    name: string;
    slug: string;
}

interface CaregiverAttribute {
    id: number;
    attribute_definition_id: number;
    name: string;
    slug: string;
    value: string;
}

interface CertificationType {
    id: number;
    name: string;
}

interface Certification {
    id: number;
    certification_type_id: number;
    certification_type: {
        id: number;
        name: string;
    };
    expiration_date: string | null;
    verified_at: string | null;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    address: string;
    date_of_birth: string | null;
    profile_photo_path: string | null;
    rating: number | null;
    biography: string | null;
    notes: string | null;
    status_id: number;
    specialty_type_ids: number[];
    location_ids: number[];
    preferred_location_id: number | null;
    attributes: CaregiverAttribute[];
    certifications: Certification[];
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    statuses: Status[];
    specialty_types: SpecialtyType[];
    locations: Location[];
    attribute_definitions: AttributeDefinition[];
    certification_types: CertificationType[];
}

export default function AdminCaregiverEdit() {
    const {
        caregiver,
        statuses,
        specialty_types,
        locations,
        attribute_definitions,
        certification_types,
    } = usePage<Props>().props;

    const flash = (usePage().props as Record<string, unknown>).flash as Record<
        string,
        string
    > | null;

    const [selectedSpecialtyIds, setSelectedSpecialtyIds] = useState<number[]>(
        caregiver.specialty_type_ids,
    );
    const [selectedLocationIds, setSelectedLocationIds] = useState<number[]>(
        caregiver.location_ids,
    );
    const [preferredLocationId, setPreferredLocationId] = useState<
        number | null
    >(caregiver.preferred_location_id);
    const [attributeValues, setAttributeValues] = useState<
        Record<number, string>
    >(() => {
        const initial: Record<number, string> = {};
        attribute_definitions.forEach((def) => {
            const existing = caregiver.attributes.find(
                (a) => a.attribute_definition_id === def.id,
            );
            initial[def.id] = existing?.value === 'true' ? 'true' : 'false';
        });
        return initial;
    });
    const [certifications, setCertifications] = useState<Certification[]>(
        caregiver.certifications,
    );

    const [specialtiesOpen, setSpecialtiesOpen] = useState(false);
    const [locationsOpen, setLocationsOpen] = useState(false);
    const [attributesOpen, setAttributesOpen] = useState(false);
    const [certificationsOpen, setCertificationsOpen] = useState(false);
    const [currentProfilePhoto, setCurrentProfilePhoto] = useState(
        caregiver.profile_photo_path,
    );

    const photoForm = useForm<{ profile_photo: File | null }>({
        profile_photo: null,
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

        photoForm.post(`/caregivers/${caregiver.id}/profile-photo`, {
            onSuccess: (page) => {
                const newPath = (page.props as any).caregiver
                    ?.profile_photo_path;
                if (newPath) {
                    setCurrentProfilePhoto(newPath);
                }
                photoForm.reset();
            },
        });
    };

    const form = useForm({
        first_name: caregiver.first_name,
        last_name: caregiver.last_name,
        phone: caregiver.phone || '',
        address: caregiver.address || '',
        date_of_birth: caregiver.date_of_birth || '',
        rating: caregiver.rating?.toString() || '',
        biography: caregiver.biography || '',
        notes: caregiver.notes || '',
        status_id: caregiver.status_id.toString(),
        profile_photo: null as File | null,
        specialty_type_ids: caregiver.specialty_type_ids,
        location_ids: caregiver.location_ids,
        preferred_location_id: caregiver.preferred_location_id,
        attribute_values: (() => {
            const initial: Record<number, string> = {};
            attribute_definitions.forEach((def) => {
                const existing = caregiver.attributes.find(
                    (a) => a.attribute_definition_id === def.id,
                );
                initial[def.id] = existing?.value === 'true' ? 'true' : 'false';
            });
            return initial;
        })(),
        certifications: caregiver.certifications,
    });

    const addCertification = () => {
        if (certification_types.length > 0) {
            setCertifications([
                ...certifications,
                {
                    id: Date.now(),
                    certification_type_id: certification_types[0].id,
                    certification_type: {
                        id: certification_types[0].id,
                        name: certification_types[0].name,
                    },
                    expiration_date: null,
                    verified_at: null,
                },
            ]);
        }
    };

    const removeCertification = (id: number) => {
        setCertifications(certifications.filter((c) => c.id !== id));
    };

    const updateCertification = (
        id: number,
        field: string,
        value: string | number | null,
    ) => {
        setCertifications(
            certifications.map((c) =>
                c.id === id ? { ...c, [field]: value } : c,
            ),
        );
    };

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        form.transform((data) => ({
            ...data,
            specialty_type_ids: selectedSpecialtyIds,
            location_ids: selectedLocationIds,
            preferred_location_id: preferredLocationId,
            attribute_values: attributeValues,
            certifications: certifications,
        }));

        form.patch(`/caregivers/${caregiver.id}`, {
            onSuccess: () => {
                // Inertia automatically handles redirect on success
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Edit ${caregiver.first_name} ${caregiver.last_name}`}
            />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {flash?.success && (
                    <Alert className="border-green-200 bg-green-50 text-green-800">
                        <CheckCircle2 className="h-4 w-4" />
                        <AlertTitle>Success</AlertTitle>
                        <AlertDescription>{flash.success}</AlertDescription>
                    </Alert>
                )}
                {flash?.error && (
                    <Alert variant="destructive">
                        <AlertCircle className="h-4 w-4" />
                        <AlertTitle>Error</AlertTitle>
                        <AlertDescription>{flash.error}</AlertDescription>
                    </Alert>
                )}
                <div className="flex items-center gap-4">
                    <form onSubmit={submitPhotoForm}>
                        <div className="flex items-center gap-4">
                            <Link
                                href={`/caregivers/${caregiver.id}`}
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
                                        alt={`${caregiver.first_name} ${caregiver.last_name}`}
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
                                            {caregiver.first_name[0]}
                                            {caregiver.last_name[0]}
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
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="text-muted-foreground">
                                Edit Caregiver Profile
                            </p>
                        </div>
                    </div>
                </div>

                <form onSubmit={submit}>
                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        First Name
                                    </span>
                                    <input
                                        type="text"
                                        name="first_name"
                                        value={form.data.first_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'first_name',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                        required
                                    />
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Last Name
                                    </span>
                                    <input
                                        type="text"
                                        name="last_name"
                                        value={form.data.last_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'last_name',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                        required
                                    />
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Email
                                    </span>
                                    <input
                                        type="email"
                                        value={caregiver.email || ''}
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-muted px-3 py-2 text-sm text-muted-foreground"
                                        disabled
                                    />
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Phone
                                    </span>
                                    <input
                                        type="text"
                                        name="phone"
                                        value={form.data.phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'phone',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    />
                                </label>
                            </div>
                            <div className="sm:col-span-2">
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Address
                                    </span>
                                    <input
                                        type="text"
                                        name="address"
                                        value={form.data.address}
                                        onChange={(e) =>
                                            form.setData(
                                                'address',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    />
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Date of Birth
                                    </span>
                                    <div className="mt-1">
                                        <DatePicker
                                            name="date_of_birth"
                                            value={form.data.date_of_birth}
                                            onChange={(date) =>
                                                form.setData(
                                                    'date_of_birth',
                                                    date || '',
                                                )
                                            }
                                            placeholder="Select date of birth"
                                        />
                                    </div>
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Rating (0-5)
                                    </span>
                                    <input
                                        type="number"
                                        name="rating"
                                        value={form.data.rating}
                                        onChange={(e) =>
                                            form.setData(
                                                'rating',
                                                e.target.value,
                                            )
                                        }
                                        min="0"
                                        max="5"
                                        step="0.01"
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    />
                                </label>
                            </div>
                            <div className="sm:col-span-2">
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Biography
                                    </span>
                                    <textarea
                                        name="biography"
                                        value={form.data.biography}
                                        onChange={(e) =>
                                            form.setData(
                                                'biography',
                                                e.target.value,
                                            )
                                        }
                                        rows={4}
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    />
                                </label>
                            </div>
                            <div className="sm:col-span-2">
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </span>
                                    <textarea
                                        name="notes"
                                        value={form.data.notes}
                                        onChange={(e) =>
                                            form.setData(
                                                'notes',
                                                e.target.value,
                                            )
                                        }
                                        rows={3}
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    />
                                </label>
                            </div>
                            <div>
                                <label className="block">
                                    <span className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Status
                                    </span>
                                    <select
                                        name="status_id"
                                        value={form.data.status_id}
                                        onChange={(e) =>
                                            form.setData(
                                                'status_id',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm text-foreground outline-none focus:border-ring"
                                    >
                                        {statuses.map((status) => (
                                            <option
                                                key={status.id}
                                                value={status.id}
                                            >
                                                {status.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 rounded-[6px] border border-border bg-card p-6">
                        <Collapsible
                            open={specialtiesOpen}
                            onOpenChange={setSpecialtiesOpen}
                        >
                            <CollapsibleTrigger className="flex w-full items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Specialties
                                </h2>
                                <ChevronDown
                                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                                        specialtiesOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 space-y-2">
                                {specialty_types.map((specialty) => (
                                    <label
                                        key={specialty.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={selectedSpecialtyIds.includes(
                                                specialty.id,
                                            )}
                                            onCheckedChange={(checked) => {
                                                if (checked) {
                                                    setSelectedSpecialtyIds([
                                                        ...selectedSpecialtyIds,
                                                        specialty.id,
                                                    ]);
                                                } else {
                                                    setSelectedSpecialtyIds(
                                                        selectedSpecialtyIds.filter(
                                                            (id) =>
                                                                id !==
                                                                specialty.id,
                                                        ),
                                                    );
                                                }
                                            }}
                                        />
                                        <span className="text-sm text-foreground">
                                            {specialty.name}
                                        </span>
                                    </label>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 rounded-[6px] border border-border bg-card p-6">
                        <Collapsible
                            open={locationsOpen}
                            onOpenChange={setLocationsOpen}
                        >
                            <CollapsibleTrigger className="flex w-full items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Locations
                                </h2>
                                <ChevronDown
                                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                                        locationsOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 space-y-2">
                                {locations.map((location) => (
                                    <label
                                        key={location.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={selectedLocationIds.includes(
                                                location.id,
                                            )}
                                            onCheckedChange={(checked) => {
                                                if (checked) {
                                                    setSelectedLocationIds([
                                                        ...selectedLocationIds,
                                                        location.id,
                                                    ]);
                                                } else {
                                                    setSelectedLocationIds(
                                                        selectedLocationIds.filter(
                                                            (id) =>
                                                                id !==
                                                                location.id,
                                                        ),
                                                    );
                                                    if (
                                                        preferredLocationId ===
                                                        location.id
                                                    ) {
                                                        setPreferredLocationId(
                                                            null,
                                                        );
                                                    }
                                                }
                                            }}
                                        />
                                        <span className="text-sm text-foreground">
                                            {location.name}
                                        </span>
                                        {selectedLocationIds.includes(
                                            location.id,
                                        ) && (
                                            <label className="ml-4 flex items-center gap-1 text-xs text-muted-foreground">
                                                <input
                                                    type="radio"
                                                    name="preferred_location"
                                                    checked={
                                                        preferredLocationId ===
                                                        location.id
                                                    }
                                                    onChange={() =>
                                                        setPreferredLocationId(
                                                            location.id,
                                                        )
                                                    }
                                                    className="accent-primary"
                                                />
                                                Preferred
                                            </label>
                                        )}
                                    </label>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 rounded-[6px] border border-border bg-card p-6">
                        <Collapsible
                            open={attributesOpen}
                            onOpenChange={setAttributesOpen}
                        >
                            <CollapsibleTrigger className="flex w-full items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Attributes
                                </h2>
                                <ChevronDown
                                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                                        attributesOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 space-y-2">
                                {attribute_definitions.map((def) => (
                                    <label
                                        key={def.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            checked={
                                                attributeValues[def.id] ===
                                                'true'
                                            }
                                            onCheckedChange={(checked) => {
                                                setAttributeValues({
                                                    ...attributeValues,
                                                    [def.id]: checked
                                                        ? 'true'
                                                        : 'false',
                                                });
                                            }}
                                        />
                                        <span className="text-sm text-foreground">
                                            {def.name}
                                        </span>
                                    </label>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 rounded-[6px] border border-border bg-card p-6">
                        <Collapsible
                            open={certificationsOpen}
                            onOpenChange={setCertificationsOpen}
                        >
                            <CollapsibleTrigger className="flex w-full items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Certifications
                                </h2>
                                <ChevronDown
                                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                                        certificationsOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 space-y-4">
                                {certifications.map((cert) => (
                                    <div
                                        key={cert.id}
                                        className="flex flex-col gap-2 rounded border border-border p-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <select
                                                value={cert.certification_type_id?.toString()}
                                                onChange={(e) =>
                                                    updateCertification(
                                                        cert.id,
                                                        'certification_type_id',
                                                        parseInt(
                                                            e.target.value,
                                                            10,
                                                        ),
                                                    )
                                                }
                                                className="rounded-[3px] border border-border bg-card px-2 py-1 text-sm text-foreground outline-none focus:border-ring"
                                            >
                                                {certification_types.map(
                                                    (type) => (
                                                        <option
                                                            key={type.id}
                                                            value={type.id}
                                                        >
                                                            {type.name}
                                                        </option>
                                                    ),
                                                )}
                                            </select>
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    removeCertification(cert.id)
                                                }
                                                className="text-xs text-destructive hover:underline"
                                            >
                                                Remove
                                            </button>
                                        </div>
                                        <div className="flex gap-4">
                                            <div className="flex-1">
                                                <label className="text-xs text-muted-foreground">
                                                    Expiration Date
                                                </label>
                                                <DatePicker
                                                    value={
                                                        cert.expiration_date ||
                                                        undefined
                                                    }
                                                    onChange={(date) =>
                                                        updateCertification(
                                                            cert.id,
                                                            'expiration_date',
                                                            date || null,
                                                        )
                                                    }
                                                    placeholder="Select date"
                                                />
                                            </div>
                                            <label className="flex items-center gap-2">
                                                <input
                                                    type="checkbox"
                                                    checked={!!cert.verified_at}
                                                    onChange={(e) =>
                                                        updateCertification(
                                                            cert.id,
                                                            'verified_at',
                                                            e.target.checked
                                                                ? new Date()
                                                                      .toISOString()
                                                                      .split(
                                                                          'T',
                                                                      )[0]
                                                                : null,
                                                        )
                                                    }
                                                    className="accent-primary"
                                                />
                                                <span className="text-sm text-foreground">
                                                    Verified
                                                </span>
                                            </label>
                                        </div>
                                    </div>
                                ))}
                                <button
                                    type="button"
                                    onClick={addCertification}
                                    className="text-sm text-primary hover:underline"
                                >
                                    + Add Certification
                                </button>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 flex justify-end gap-3">
                        <Link
                            href={`/caregivers/${caregiver.id}`}
                            className="rounded-[3px] border border-border bg-card px-4 py-2 text-sm font-medium text-foreground hover:bg-accent"
                        >
                            Cancel
                        </Link>
                        <button
                            type="submit"
                            disabled={form.processing}
                            className="flex items-center gap-2 rounded-[3px] bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            {form.processing ? <Spinner /> : null}
                            {form.processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
