import { Head, Link, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import { useState, type FormEventHandler } from 'react';
import AppLayout from '@/layouts/app-layout';
import { DatePicker } from '@/components/ui/date-picker';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Manage Caregivers',
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
    caregiver: Caregiver;
    statuses: Status[];
    specialty_types: SpecialtyType[];
    locations: Location[];
    attribute_definitions: AttributeDefinition[];
    certification_types: CertificationType[];
}

export default function AdminCaregiverEdit() {
    const props = usePage().props as unknown as Props & { csrf_token: string };
    const {
        caregiver,
        statuses,
        csrf_token,
        specialty_types,
        locations,
        attribute_definitions,
        certification_types,
    } = props;

    const [data, setData] = useState({
        first_name: caregiver.first_name,
        last_name: caregiver.last_name,
        phone: caregiver.phone || '',
        address: caregiver.address || '',
        date_of_birth: caregiver.date_of_birth || '',
        rating: caregiver.rating?.toString() || '',
        biography: caregiver.biography || '',
        notes: caregiver.notes || '',
        status_id: caregiver.status_id.toString(),
    });

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

    const [processing, setProcessing] = useState(false);

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
        setProcessing(true);

        const form = e.currentTarget;
        const formData = new FormData(form as HTMLFormElement);

        selectedSpecialtyIds.forEach((id) => {
            formData.append('specialty_type_ids[]', id.toString());
        });

        selectedLocationIds.forEach((id) => {
            formData.append('location_ids[]', id.toString());
        });

        if (preferredLocationId) {
            formData.append(
                'preferred_location_id',
                preferredLocationId.toString(),
            );
        }

        Object.entries(attributeValues).forEach(([key, value]) => {
            formData.append(`attribute_values[${key}]`, value);
        });

        certifications.forEach((cert) => {
            formData.append(
                `certifications[${cert.id}][certification_type_id]`,
                cert.certification_type_id.toString(),
            );
            if (cert.expiration_date) {
                formData.append(
                    `certifications[${cert.id}][expiration_date]`,
                    cert.expiration_date,
                );
            }
            if (cert.verified_at) {
                formData.append(
                    `certifications[${cert.id}][verified_at]`,
                    cert.verified_at,
                );
            }
        });

        fetch((form as HTMLFormElement).action, {
            method: 'POST',
            body: formData,
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
            },
        })
            .then((response) => {
                if (response.ok) {
                    window.location.href = `/caregivers/${caregiver.id}`;
                }
            })
            .finally(() => {
                setProcessing(false);
            });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`Edit ${caregiver.first_name} ${caregiver.last_name}`}
            />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href={`/caregivers/${caregiver.id}`}
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    {caregiver.profile_photo_path ? (
                        <img
                            src={
                                caregiver.profile_photo_path === 'avatar.jpg'
                                    ? '/avatar.jpg'
                                    : `/storage/${caregiver.profile_photo_path}`
                            }
                            alt={`${caregiver.first_name} ${caregiver.last_name}`}
                            className="h-16 w-16 rounded-full object-cover"
                        />
                    ) : (
                        <div className="flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                            <span className="text-2xl font-medium text-amber-600">
                                {caregiver.first_name[0]}
                                {caregiver.last_name[0]}
                            </span>
                        </div>
                    )}
                    <div>
                        <h1 className="text-2xl font-bold text-foreground">
                            Edit {caregiver.first_name} {caregiver.last_name}
                        </h1>
                        <p className="text-muted-foreground">
                            Caregiver Profile
                        </p>
                    </div>
                </div>

                <form
                    method="post"
                    action={`/caregivers/${caregiver.id}`}
                    onSubmit={submit}
                >
                    <input type="hidden" name="_token" value={csrf_token} />
                    <input type="hidden" name="_method" value="PATCH" />

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
                                        value={data.first_name}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                first_name: e.target.value,
                                            })
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
                                        value={data.last_name}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                last_name: e.target.value,
                                            })
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
                                        value={data.phone}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                phone: e.target.value,
                                            })
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
                                        value={data.address}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                address: e.target.value,
                                            })
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
                                            value={data.date_of_birth}
                                            onChange={(date) =>
                                                setData({
                                                    ...data,
                                                    date_of_birth: date,
                                                })
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
                                        value={data.rating}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                rating: e.target.value,
                                            })
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
                                        value={data.biography}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                biography: e.target.value,
                                            })
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
                                        value={data.notes}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                notes: e.target.value,
                                            })
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
                                        value={data.status_id}
                                        onChange={(e) =>
                                            setData({
                                                ...data,
                                                status_id: e.target.value,
                                            })
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
                            disabled={processing}
                            className="rounded-[3px] bg-primary px-4 py-2 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save Changes'}
                        </button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
