import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, ChevronDown } from 'lucide-react';
import { useState } from 'react';
import type { SubmitEventHandler } from 'react';
import type { FormEventHandler } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Collapsible,
    CollapsibleContent,
    CollapsibleTrigger,
} from '@/components/ui/collapsible';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
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
    svg_icon: string | null;
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

interface Education {
    id: number;
    education_type: string;
    school_name: string;
    graduation_year: number | null;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    date_of_birth: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
    rating: number | null;
    biography: string | null;
    notes: string | null;
    status_id: number;
    specialty_type_ids: number[];
    location_ids: number[];
    preferred_location_id: number | null;
    attributes: CaregiverAttribute[];
    certifications: Certification[];
    educations: Education[];
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

export default function CaregiverEdit() {
    const {
        caregiver,
        statuses,
        specialty_types,
        locations,
        attribute_definitions,
        certification_types,
    } = usePage<Props>().props;

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
    const [educations, setEducations] = useState<Education[]>(
        caregiver.educations,
    );

    const [specialtiesOpen, setSpecialtiesOpen] = useState(false);
    const [locationsOpen, setLocationsOpen] = useState(false);
    const [attributesOpen, setAttributesOpen] = useState(false);
    const [certificationsOpen, setCertificationsOpen] = useState(false);
    const [educationsOpen, setEducationsOpen] = useState(false);
    const [currentProfilePhoto, setCurrentProfilePhoto] = useState(
        caregiver.user.profile_photo_path,
    );

    const photoForm = useForm<{ profile_photo: File | null }>({
        profile_photo: null,
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

        photoForm.post(`/caregivers/${caregiver.id}/profile-photo`, {
            onSuccess: (page) => {
                const newPath = (page.props as any).caregiver?.user
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
        address_line1: caregiver.address_line1 || '',
        address_line2: caregiver.address_line2 || '',
        address_city: caregiver.address_city || '',
        address_state: caregiver.address_state || '',
        address_zip: caregiver.address_zip || '',
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
        educations: caregiver.educations,
    });

    const addEducation = () => {
        setEducations([
            ...educations,
            {
                id: Date.now(),
                education_type: 'high_school',
                school_name: '',
                graduation_year: null,
            },
        ]);
    };

    const removeEducation = (id: number) => {
        setEducations(educations.filter((e) => e.id !== id));
    };

    const updateEducation = (
        id: number,
        field: keyof Education,
        value: string | number | null,
    ) => {
        setEducations(
            educations.map((e) => (e.id === id ? { ...e, [field]: value } : e)),
        );
    };

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
            educations: educations,
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
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
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
                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div className="space-y-2">
                                <Label htmlFor="first_name">First Name</Label>
                                <Input
                                    id="first_name"
                                    type="text"
                                    name="first_name"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="last_name">Last Name</Label>
                                <Input
                                    id="last_name"
                                    type="text"
                                    name="last_name"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="email">Email</Label>
                                <Input
                                    id="email"
                                    type="email"
                                    value={caregiver.email || ''}
                                    disabled
                                />
                            </div>
                            <div className="space-y-2">
                                <Label htmlFor="phone">Phone</Label>
                                <Input
                                    id="phone"
                                    type="text"
                                    name="phone"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                />
                            </div>
                            <div className="sm:col-span-2">
                                <AddressAutocomplete
                                    form={form}
                                    label="Address"
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Date of Birth</Label>
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
                            <div className="space-y-2">
                                <Label htmlFor="rating">Rating (0-5)</Label>
                                <Input
                                    id="rating"
                                    type="number"
                                    name="rating"
                                    value={form.data.rating}
                                    onChange={(e) =>
                                        form.setData('rating', e.target.value)
                                    }
                                    min="0"
                                    max="5"
                                    step="0.01"
                                />
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="biography">Biography</Label>
                                <Textarea
                                    id="biography"
                                    name="biography"
                                    value={form.data.biography || ''}
                                    onChange={(e) =>
                                        form.setData(
                                            'biography',
                                            e.target.value,
                                        )
                                    }
                                    rows={4}
                                />
                            </div>
                            <div className="space-y-2 sm:col-span-2">
                                <Label htmlFor="notes">Notes</Label>
                                <Textarea
                                    id="notes"
                                    name="notes"
                                    value={form.data.notes || ''}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={3}
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Status</Label>
                                <Select
                                    value={form.data.status_id}
                                    onValueChange={(value) =>
                                        form.setData('status_id', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem
                                                key={status.id}
                                                value={status.id.toString()}
                                            >
                                                {status.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>
                    </div>

                    <div className="mt-6 border border-border bg-card p-6">
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
                                    <div
                                        key={specialty.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`specialty-${specialty.id}`}
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
                                        <Label
                                            htmlFor={`specialty-${specialty.id}`}
                                            className="font-normal"
                                        >
                                            {specialty.name}
                                        </Label>
                                    </div>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 border border-border bg-card p-6">
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
                                    <div
                                        key={location.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`location-${location.id}`}
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
                                        <Label
                                            htmlFor={`location-${location.id}`}
                                            className="font-normal"
                                        >
                                            {location.name}
                                        </Label>
                                        {selectedLocationIds.includes(
                                            location.id,
                                        ) && (
                                            <div className="ml-4 flex items-center gap-2">
                                                <input
                                                    type="radio"
                                                    id={`preferred-${location.id}`}
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
                                                    className="h-4 w-4 border-primary text-primary focus:ring-primary"
                                                />
                                                <Label
                                                    htmlFor={`preferred-${location.id}`}
                                                    className="text-xs font-normal text-muted-foreground"
                                                >
                                                    Preferred
                                                </Label>
                                            </div>
                                        )}
                                    </div>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 border border-border bg-card p-6">
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
                                    <div
                                        key={def.id}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`attribute-${def.id}`}
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
                                        <Label
                                            htmlFor={`attribute-${def.id}`}
                                            className="font-normal"
                                        >
                                            {def.name}
                                        </Label>
                                    </div>
                                ))}
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 border border-border bg-card p-6">
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
                                        className="flex flex-col gap-4 rounded border border-border p-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="max-w-xs flex-1">
                                                <Select
                                                    value={cert.certification_type_id?.toString()}
                                                    onValueChange={(value) =>
                                                        updateCertification(
                                                            cert.id,
                                                            'certification_type_id',
                                                            parseInt(value, 10),
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select certification" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {certification_types.map(
                                                            (type) => (
                                                                <SelectItem
                                                                    key={
                                                                        type.id
                                                                    }
                                                                    value={type.id.toString()}
                                                                >
                                                                    {type.name}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    removeCertification(cert.id)
                                                }
                                                variant="link"
                                                className="text-destructive"
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                        <div className="flex gap-4">
                                            <div className="flex-1 space-y-2">
                                                <Label className="text-xs">
                                                    Expiration Date
                                                </Label>
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
                                            <div className="flex items-center gap-2 pt-6">
                                                <Checkbox
                                                    id={`verified-${cert.id}`}
                                                    checked={!!cert.verified_at}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        updateCertification(
                                                            cert.id,
                                                            'verified_at',
                                                            checked
                                                                ? new Date()
                                                                      .toISOString()
                                                                      .split(
                                                                          'T',
                                                                      )[0]
                                                                : null,
                                                        )
                                                    }
                                                />
                                                <Label
                                                    htmlFor={`verified-${cert.id}`}
                                                    className="font-normal"
                                                >
                                                    Verified
                                                </Label>
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <Button
                                    variant="link"
                                    type="button"
                                    onClick={addCertification}
                                >
                                    + Add Certification
                                </Button>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 border border-border bg-card p-6">
                        <Collapsible
                            open={educationsOpen}
                            onOpenChange={setEducationsOpen}
                        >
                            <CollapsibleTrigger className="flex w-full items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Education
                                </h2>
                                <ChevronDown
                                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                                        educationsOpen ? 'rotate-180' : ''
                                    }`}
                                />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="mt-4 space-y-4">
                                {educations.map((edu) => (
                                    <div
                                        key={edu.id}
                                        className="flex flex-col gap-4 rounded border border-border p-3"
                                    >
                                        <div className="flex items-center justify-between">
                                            <div className="max-w-xs flex-1">
                                                <Select
                                                    value={edu.education_type}
                                                    onValueChange={(value) =>
                                                        updateEducation(
                                                            edu.id,
                                                            'education_type',
                                                            value,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Select education" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem value="high_school">
                                                            High School
                                                        </SelectItem>
                                                        <SelectItem value="college">
                                                            College
                                                        </SelectItem>
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    removeEducation(edu.id)
                                                }
                                                variant="link"
                                                className="text-destructive"
                                            >
                                                Remove
                                            </Button>
                                        </div>
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`school-${edu.id}`}
                                                    className="text-xs"
                                                >
                                                    School Name
                                                </Label>
                                                <Input
                                                    id={`school-${edu.id}`}
                                                    type="text"
                                                    value={edu.school_name}
                                                    onChange={(e) =>
                                                        updateEducation(
                                                            edu.id,
                                                            'school_name',
                                                            e.target.value,
                                                        )
                                                    }
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label
                                                    htmlFor={`year-${edu.id}`}
                                                    className="text-xs"
                                                >
                                                    Graduation Year
                                                </Label>
                                                <Input
                                                    id={`year-${edu.id}`}
                                                    type="number"
                                                    value={
                                                        edu.graduation_year ||
                                                        ''
                                                    }
                                                    onChange={(e) =>
                                                        updateEducation(
                                                            edu.id,
                                                            'graduation_year',
                                                            e.target.value
                                                                ? parseInt(
                                                                      e.target
                                                                          .value,
                                                                      10,
                                                                  )
                                                                : null,
                                                        )
                                                    }
                                                    placeholder="e.g. 2020"
                                                />
                                            </div>
                                        </div>
                                    </div>
                                ))}
                                <Button
                                    variant="link"
                                    type="button"
                                    onClick={addEducation}
                                >
                                    + Add Education
                                </Button>
                            </CollapsibleContent>
                        </Collapsible>
                    </div>

                    <div className="mt-4 flex justify-end gap-3">
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() =>
                                (window.location.href = `/caregivers/${caregiver.id}`)
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
