import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Shield, Eye, EyeOff } from 'lucide-react';
import type { SubmitEventHandler } from 'react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Rating } from '@/components/ui/rating';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
    SheetFooter,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
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
        title: 'Caregiver Details',
        href: '#',
    },
];

interface CertificationType {
    id: number;
    name: string;
}

interface Certification {
    id: number;
    certification_type: CertificationType;
    expiration_date: string;
    verified_at: string;
}

interface Status {
    id: number;
    name: string;
    color: string;
}

interface SpecialtyType {
    id: number;
    name: string;
}

interface AttributeDefinition {
    id: number;
    name: string;
    slug: string;
}

interface Attribute {
    id: number;
    attribute_definition: AttributeDefinition;
    value: string | boolean;
}

interface Location {
    id: number;
    name: string;
    svg_icon: string | null;
    is_preferred: boolean;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    address: string;
    date_of_birth: string;
    date_of_birth_raw: string | null;
    user: {
        profile_photo_path: string | null;
    };
    rating: number | null;
    biography: string | null;
    notes: string | null;
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
    certifications: Certification[];
    attributes: Attribute[];
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    statuses: Status[];
}

function calculateAge(dateOfBirth: string): number {
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
        age--;
    }

    return age;
}

function StatusBadge({ status }: { status: Status }) {
    return (
        <span
            className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{
                backgroundColor: status.color + '20',
                color: status.color,
            }}
        >
            {status.name}
        </span>
    );
}

function SpecialtyTag({ name }: { name: string }) {
    const colors: Record<string, { bg: string; text: string }> = {
        Babies: { bg: '#E0F7FA', text: '#006064' },
        Toddlers: { bg: '#E8F5E9', text: '#2E7D32' },
        Preschool: { bg: '#FFF3E0', text: '#E65100' },
        'School Age': { bg: '#EDE7F6', text: '#4527A0' },
        'Special Needs': { bg: '#FCE4EC', text: '#880E4F' },
    };
    const style = colors[name] || { bg: '#E8F5F5', text: '#1B3A5C' };

    return (
        <span
            className="inline-block rounded-[10px] px-2 py-0.5 text-[10px] font-medium"
            style={{ backgroundColor: style.bg, color: style.text }}
        >
            {name}
        </span>
    );
}

function AttributeBadge({
    name,
    value,
}: {
    name: string;
    value: string | boolean;
}) {
    const isTrue = value === 'true' || value === '1' || value === true;

    return (
        <div className="flex items-center gap-2">
            {isTrue && <Check className="h-4 w-4 text-green-600" />}
            <span
                className={`text-sm ${isTrue ? 'text-foreground' : 'text-muted-foreground'}`}
            >
                {name}
            </span>
        </div>
    );
}

export default function CaregiverShow() {
    const { caregiver, statuses } = usePage<Props>().props;
    const [isStatusUpdating, setIsStatusUpdating] = useState(false);
    const [isPasswordSheetOpen, setIsPasswordSheetOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const statusForm = useForm<{ status_id: number }>({
        status_id: caregiver.status.id,
    });

    const passwordForm = useForm<{
        new_password: string;
        new_password_confirmation: string;
    }>({
        new_password: '',
        new_password_confirmation: '',
    });

    const handleStatusUpdate = () => {
        setIsStatusUpdating(true);
        statusForm.patch(`/caregivers/${caregiver.id}`, {
            onSuccess: () => {
                setIsStatusUpdating(false);
            },
            onError: () => {
                setIsStatusUpdating(false);
            },
        });
    };

    const handlePasswordReset: SubmitEventHandler = (e) => {
        e.preventDefault();
        passwordForm.post(`/caregivers/${caregiver.id}/password`, {
            onSuccess: () => {
                setIsPasswordSheetOpen(false);
                passwordForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${caregiver.first_name} ${caregiver.last_name}`} />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/caregivers"
                            className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        {caregiver.user.profile_photo_path ? (
                            <img
                                src={
                                    caregiver.user.profile_photo_path ===
                                    'avatar.jpg'
                                        ? '/avatar.jpg'
                                        : `/storage/${caregiver.user.profile_photo_path}`
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
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="text-muted-foreground">
                                Caregiver Profile
                            </p>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <button
                            onClick={() => setIsPasswordSheetOpen(true)}
                            className="btn-secondary"
                        >
                            Reset Password
                        </button>
                        <Link
                            href={`/caregivers/${caregiver.id}/edit`}
                            className="btn-primary"
                        >
                            Edit
                        </Link>
                    </div>
                </div>

                <Sheet
                    open={isPasswordSheetOpen}
                    onOpenChange={setIsPasswordSheetOpen}
                >
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>Reset Password</SheetTitle>
                            <SheetDescription>
                                Enter and confirm a new password for this
                                caregiver.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handlePasswordReset}
                            className="mt-4 space-y-4 px-4"
                        >
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    New Password
                                </label>
                                <div className="relative">
                                    <input
                                        type={
                                            showPassword ? 'text' : 'password'
                                        }
                                        value={passwordForm.data.new_password}
                                        onChange={(e) =>
                                            passwordForm.setData(
                                                'new_password',
                                                e.target.value,
                                            )
                                        }
                                        className="h-10 w-full rounded-[3px] border border-input bg-background px-3 pr-10 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                        required
                                    />
                                    <button
                                        type="button"
                                        onClick={() =>
                                            setShowPassword(!showPassword)
                                        }
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground"
                                    >
                                        {showPassword ? (
                                            <EyeOff className="h-4 w-4" />
                                        ) : (
                                            <Eye className="h-4 w-4" />
                                        )}
                                    </button>
                                </div>
                                {passwordForm.errors.new_password && (
                                    <p className="text-sm text-destructive">
                                        {passwordForm.errors.new_password}
                                    </p>
                                )}
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Confirm Password
                                </label>
                                <input
                                    type={showPassword ? 'text' : 'password'}
                                    value={
                                        passwordForm.data
                                            .new_password_confirmation
                                    }
                                    onChange={(e) =>
                                        passwordForm.setData(
                                            'new_password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {passwordForm.errors
                                    .new_password_confirmation && (
                                    <p className="text-sm text-destructive">
                                        {
                                            passwordForm.errors
                                                .new_password_confirmation
                                        }
                                    </p>
                                )}
                            </div>
                            <div>
                                <button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="btn-primary w-full"
                                >
                                    {passwordForm.processing
                                        ? 'Resetting...'
                                        : 'Reset Password'}
                                </button>
                                <button
                                    type="button"
                                    onClick={() =>
                                        setIsPasswordSheetOpen(false)
                                    }
                                    className="btn-secondary mt-2 w-full"
                                >
                                    Cancel
                                </button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="rounded-[6px] border border-border bg-card p-6 lg:col-span-2">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Email
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.email}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.phone || '—'}
                                </p>
                            </div>
                            <div className="sm:col-span-2">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Address
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {caregiver.address || '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Rating
                                        </p>
                                        {caregiver.rating ? (
                                            <Rating
                                                value={caregiver.rating}
                                                size="md"
                                            />
                                        ) : (
                                            <p className="text-sm text-muted-foreground">
                                                —
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Date of Birth
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.date_of_birth || '—'}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Age
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.date_of_birth_raw
                                        ? `${calculateAge(caregiver.date_of_birth_raw)} years old`
                                        : '—'}
                                </p>
                            </div>
                            {caregiver.biography && (
                                <div className="sm:col-span-2">
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Biography
                                    </p>
                                    <p className="text-sm text-foreground">
                                        {caregiver.biography}
                                    </p>
                                </div>
                            )}
                            {caregiver.notes && (
                                <div className="sm:col-span-2">
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Notes
                                    </p>
                                    <p className="text-sm text-foreground">
                                        {caregiver.notes}
                                    </p>
                                </div>
                            )}
                        </div>

                        <div className="mt-6 border-t border-border pt-6">
                            <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Specialties
                            </h3>
                            <div className="flex flex-wrap gap-2">
                                {caregiver.specialty_types.map((specialty) => (
                                    <SpecialtyTag
                                        key={specialty.id}
                                        name={specialty.name}
                                    />
                                ))}
                                {caregiver.specialty_types.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No specialties
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 border-t border-border pt-6">
                            <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Locations
                            </h3>
                            <div className="space-y-2">
                                {caregiver.locations.map((location) => (
                                    <div
                                        key={location.id}
                                        className={`flex items-center gap-2 ${location.is_preferred ? 'font-medium text-foreground' : 'text-muted-foreground'}`}
                                    >
                                        <span
                                            className={`h-2 w-2 rounded-full ${location.is_preferred ? 'bg-ring' : 'bg-border'}`}
                                        />
                                        {location.svg_icon ? (
                                            <span
                                                className="h-4 w-4"
                                                dangerouslySetInnerHTML={{
                                                    __html: location.svg_icon,
                                                }}
                                            />
                                        ) : (
                                            location.name
                                        )}
                                        {location.is_preferred && (
                                            <span className="text-xs text-ring">
                                                (Preferred)
                                            </span>
                                        )}
                                    </div>
                                ))}
                                {caregiver.locations.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No locations
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="mt-6 border-t border-border pt-6">
                            <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Attributes
                            </h3>
                            <div className="grid gap-2 sm:grid-cols-2">
                                {caregiver.attributes
                                    .filter(
                                        (a) =>
                                            a.value === 'true' ||
                                            a.value === '1' ||
                                            a.value === true,
                                    )
                                    .map((attr) => (
                                        <AttributeBadge
                                            key={attr.id}
                                            name={
                                                attr.attribute_definition.name
                                            }
                                            value={attr.value}
                                        />
                                    ))}
                                {caregiver.attributes.filter(
                                    (a) =>
                                        a.value === 'true' ||
                                        a.value === '1' ||
                                        a.value === true,
                                ).length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No attributes
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="space-y-6">
                        <div className="rounded-[6px] border border-border bg-card p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Status
                                </h2>
                                <StatusBadge status={caregiver.status} />
                            </div>

                            <div className="space-y-3">
                                <label className="block">
                                    <span className="text-sm text-muted-foreground">
                                        Change Status
                                    </span>
                                    <select
                                        value={statusForm.data.status_id}
                                        onChange={(e) =>
                                            statusForm.setData(
                                                'status_id',
                                                Number(e.target.value),
                                            )
                                        }
                                        disabled={statusForm.processing}
                                        className="mt-1 block w-full rounded-[3px] border border-border bg-card px-3 py-2 text-sm outline-none focus:border-ring disabled:opacity-50"
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
                                    {statusForm.errors.status_id && (
                                        <p className="mt-1 text-xs text-red-500">
                                            {statusForm.errors.status_id}
                                        </p>
                                    )}
                                </label>
                                <button
                                    type="button"
                                    onClick={handleStatusUpdate}
                                    disabled={statusForm.processing}
                                    className="btn-primary w-full"
                                >
                                    {isStatusUpdating ? <Spinner /> : null}
                                    {isStatusUpdating
                                        ? 'Updating...'
                                        : 'Update Status'}
                                </button>
                            </div>
                        </div>

                        <div className="rounded-[6px] border border-border bg-card p-6">
                            <h2 className="mb-4 flex items-center gap-2 font-serif text-lg font-semibold text-foreground">
                                <Shield className="h-5 w-5" />
                                Certifications
                            </h2>
                            <div className="space-y-3">
                                {caregiver.certifications.map((cert) => (
                                    <div
                                        key={cert.id}
                                        className="flex items-center justify-between"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-foreground">
                                                {cert.certification_type.name}
                                            </p>
                                            {cert.expiration_date && (
                                                <p className="text-xs text-muted-foreground">
                                                    Expires:{' '}
                                                    {cert.expiration_date}
                                                </p>
                                            )}
                                        </div>
                                        {cert.verified_at && (
                                            <span className="flex items-center gap-1 text-xs text-green-600">
                                                <Check className="h-3 w-3" />
                                                Verified
                                            </span>
                                        )}
                                    </div>
                                ))}
                                {caregiver.certifications.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No certifications
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
