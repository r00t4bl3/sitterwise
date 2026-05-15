import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle,
    MinusCircle,
    MoreVertical,
    Shield,
    Eye,
    EyeOff,
} from 'lucide-react';
import type { SubmitEventHandler } from 'react';
import { useState } from 'react';
import { RatingInput } from '@/components/rating-input';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Rating } from '@/components/ui/rating';
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
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { calculateAgeFromDate } from '@/lib/age';
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
    notes: string;
    expiration_date: string;
    verified_at: string;
}

interface Status {
    id: number;
    name: string;
    label: string;
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

interface Education {
    id: number;
    education_type: string;
    school_name: string;
    graduation_year: number | null;
}

interface Location {
    id: number;
    name: string;
    svg_icon: string | null;
    is_preferred: boolean;
}

interface CaregiverApplication {
    id: number;
    submitted_at: string;
    data: {
        personal?: {
            first_name: string;
            last_name: string;
            address: string;
        };
        sponsor?: {
            first_name: string;
            last_name: string;
            email: string;
        };
    };
}

interface Agreement {
    id: number;
    pdf_path: string;
    type: string;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    slug: string;
    email: string;
    phone: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    date_of_birth: string;
    date_of_birth_raw: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
    rating: number | null;
    admin_rating: number | null;
    biography: string | null;
    notes: string | null;
    stripe_account_id: string | null;
    stripe_charges_enabled: boolean | null;
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
    certifications: Certification[];
    attributes: Attribute[];
    educations: Education[];
    applications: CaregiverApplication[];
    agreements: Agreement[];
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    statuses: Status[];
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
            {status.label}
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

function StripeBadge({ isConnected }: { isConnected: boolean | null }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                isConnected
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
            }`}
        >
            {isConnected ? (
                <CheckCircle className="h-3.5 w-3.5" />
            ) : (
                <MinusCircle className="h-3.5 w-3.5" />
            )}
            Stripe {isConnected ? 'Connected' : 'Not Connected'}
        </span>
    );
}

export default function CaregiverShow() {
    const { caregiver, statuses } = usePage<Props>().props;
    const [isStatusUpdating, setIsStatusUpdating] = useState(false);
    const [isPasswordSheetOpen, setIsPasswordSheetOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const adminRatingForm = useForm({
        admin_rating: caregiver.admin_rating || 0,
    });

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

    const handleAdminRatingUpdate = () => {
        adminRatingForm.put(`/caregivers/${caregiver.id}/admin-rating`, {
            preserveScroll: true,
        });
    };

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
                        <UserAvatar
                            profile_photo_url={caregiver.user.profile_photo_url}
                            profile_photo_path={
                                caregiver.user.profile_photo_path
                            }
                            name={`${caregiver.first_name} ${caregiver.last_name}`}
                            size="md"
                            className="size-10 md:size-16"
                        />
                        <div>
                            <h1 className="text-xl font-bold text-foreground md:text-2xl">
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="hidden text-muted-foreground md:block">
                                Caregiver Profile
                            </p>
                        </div>
                    </div>
                    <div className="hidden gap-2 xl:flex">
                        <Link
                            href={`/caregivers/${caregiver.id}/jobs`}
                            className="btn-secondary"
                        >
                            View Jobs
                        </Link>
                        <Link
                            href={`/availabilities/${caregiver.id}`}
                            className="btn-secondary"
                        >
                            View Availability
                        </Link>
                        <Link
                            href={`/bio/${caregiver.slug}`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="btn-secondary"
                        >
                            View Public Profile
                        </Link>
                        <Button
                            onClick={() => setIsPasswordSheetOpen(true)}
                            variant="secondary"
                        >
                            Reset Password
                        </Button>
                        <Link
                            href={`/caregivers/${caregiver.id}/edit`}
                            className="btn-primary"
                        >
                            Edit
                        </Link>
                    </div>
                    <div className="xl:hidden">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="icon">
                                    <MoreVertical className="h-5 w-5" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/caregivers/${caregiver.id}/jobs`}
                                    >
                                        View Jobs
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/availabilities/${caregiver.id}`}
                                    >
                                        View Availability
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/bio/${caregiver.slug}`}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                    >
                                        View Public Profile
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setIsPasswordSheetOpen(true)}
                                >
                                    Reset Password
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/caregivers/${caregiver.id}/edit`}
                                    >
                                        Edit
                                    </Link>
                                </DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
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
                            className="space-y-4 px-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="new_password">
                                    New Password
                                </Label>
                                <div className="relative">
                                    <Input
                                        id="new_password"
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
                                        className="pr-10"
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
                            <div className="grid gap-2">
                                <Label htmlFor="confirm_password">
                                    Confirm Password
                                </Label>
                                <Input
                                    id="confirm_password"
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
                            <div className="mt-10 w-full space-y-2">
                                <Button
                                    type="submit"
                                    disabled={passwordForm.processing}
                                    className="w-full"
                                >
                                    {passwordForm.processing
                                        ? 'Resetting...'
                                        : 'Reset Password'}
                                </Button>
                                <Button
                                    type="button"
                                    onClick={() =>
                                        setIsPasswordSheetOpen(false)
                                    }
                                    variant="outline"
                                    className="mt-2 w-full"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                <div className="grid gap-6 lg:grid-cols-3">
                    <div className="border border-border bg-card p-6 lg:col-span-2">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Email
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.email && (
                                        <a
                                            href={`mailto:${caregiver.email}`}
                                            className="text-primary hover:underline"
                                        >
                                            {caregiver.email}
                                        </a>
                                    )}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {caregiver.phone && (
                                        <a
                                            href={`tel:${caregiver.phone}`}
                                            className="text-primary hover:underline"
                                        >
                                            {caregiver.phone}
                                        </a>
                                    )}
                                </p>
                            </div>
                            <div className="sm:col-span-2">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Address
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {caregiver.address_line1}
                                            {caregiver.address_line2 &&
                                                `, ${caregiver.address_line2}`}
                                            {caregiver.address_city &&
                                                `, ${caregiver.address_city}`}
                                            {caregiver.address_state &&
                                                `, ${caregiver.address_state}`}
                                            {caregiver.address_zip &&
                                                ` ${caregiver.address_zip}`}
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
                                        ? `${calculateAgeFromDate(caregiver.date_of_birth_raw)} years old`
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
                                Education
                            </h3>
                            <div className="space-y-3">
                                {caregiver.educations.map((edu) => {
                                    const educationTypeLabels: Record<
                                        string,
                                        string
                                    > = {
                                        high_school: 'High School',
                                        college: 'College',
                                    };

                                    return (
                                        <div key={edu.id}>
                                            <p className="text-sm font-medium text-foreground">
                                                {edu.school_name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                {educationTypeLabels[
                                                    edu.education_type
                                                ] || edu.education_type}
                                                {edu.graduation_year &&
                                                    ` • ${edu.graduation_year}`}
                                            </p>
                                        </div>
                                    );
                                })}
                                {caregiver.educations.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No education
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
                        <div className="border border-border bg-card p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Status
                                </h2>
                                <div className="flex flex-col items-end gap-2">
                                    <StatusBadge status={caregiver.status} />
                                    <StripeBadge
                                        isConnected={
                                            caregiver.stripe_charges_enabled
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="status_id">Change Status</Label>
                                <Select
                                    value={statusForm.data.status_id.toString()}
                                    onValueChange={(value) =>
                                        statusForm.setData(
                                            'status_id',
                                            Number(value),
                                        )
                                    }
                                    disabled={statusForm.processing}
                                >
                                    <SelectTrigger id="status_id">
                                        <SelectValue placeholder="Select status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {statuses.map((status) => (
                                            <SelectItem
                                                key={status.id}
                                                value={status.id.toString()}
                                            >
                                                {status.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                                {statusForm.errors.status_id && (
                                    <p className="mt-1 text-xs text-red-500">
                                        {statusForm.errors.status_id}
                                    </p>
                                )}
                                <Button
                                    onClick={handleStatusUpdate}
                                    disabled={statusForm.processing}
                                    className="w-full"
                                >
                                    {isStatusUpdating ? <Spinner /> : null}
                                    {isStatusUpdating
                                        ? 'Updating...'
                                        : 'Update Status'}
                                </Button>
                            </div>
                        </div>

                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Admin Rating
                            </h2>
                            <p className="mb-4 text-xs text-muted-foreground">
                                Global rating visible to admins only.
                            </p>
                            <div className="space-y-4">
                                <RatingInput
                                    value={adminRatingForm.data.admin_rating}
                                    onChange={(val) =>
                                        adminRatingForm.setData(
                                            'admin_rating',
                                            val,
                                        )
                                    }
                                    error={adminRatingForm.errors.admin_rating}
                                />
                                <Button
                                    onClick={handleAdminRatingUpdate}
                                    disabled={adminRatingForm.processing}
                                    className="w-full"
                                    variant="outline"
                                >
                                    {adminRatingForm.processing
                                        ? 'Saving...'
                                        : 'Save Admin Rating'}
                                </Button>
                            </div>
                        </div>

                        <div className="border border-border bg-card p-6">
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
                                            {cert.notes && (
                                                <p className="text-xs text-muted-foreground">
                                                    Note: {cert.notes}
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

                        {/* Application Section */}
                        {caregiver.applications &&
                            caregiver.applications.length > 0 && (
                                <div className="mt-6 border-t border-border pt-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Application
                                    </h2>
                                    {caregiver.applications.map((app) => (
                                        <div key={app.id} className="space-y-4">
                                            <p className="text-sm text-muted-foreground">
                                                Submitted: {app.submitted_at}
                                            </p>

                                            {/* Display application data */}
                                            {app.data && (
                                                <div className="space-y-3">
                                                    {app.data.personal && (
                                                        <div>
                                                            <h4 className="mb-2 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                                                Personal Info
                                                            </h4>
                                                            <p className="text-sm">
                                                                {
                                                                    app.data
                                                                        .personal
                                                                        .first_name
                                                                }{' '}
                                                                {
                                                                    app.data
                                                                        .personal
                                                                        .last_name
                                                                }
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    app.data
                                                                        .personal
                                                                        .address
                                                                }
                                                            </p>
                                                        </div>
                                                    )}

                                                    {app.data.sponsor && (
                                                        <div>
                                                            <h4 className="mb-2 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                                                Sponsor
                                                            </h4>
                                                            <p className="text-sm">
                                                                {
                                                                    app.data
                                                                        .sponsor
                                                                        .first_name
                                                                }{' '}
                                                                {
                                                                    app.data
                                                                        .sponsor
                                                                        .last_name
                                                                }
                                                            </p>
                                                            <p className="text-sm text-muted-foreground">
                                                                {
                                                                    app.data
                                                                        .sponsor
                                                                        .email
                                                                }
                                                            </p>
                                                        </div>
                                                    )}
                                                </div>
                                            )}

                                            {/* Agreements */}
                                            {caregiver.agreements &&
                                                caregiver.agreements.length >
                                                    0 && (
                                                    <div className="mt-4">
                                                        <h4 className="mb-2 text-sm font-medium tracking-wider text-muted-foreground uppercase">
                                                            Agreements
                                                        </h4>
                                                        <div className="flex gap-2">
                                                            {caregiver.agreements.map(
                                                                (agreement) => (
                                                                    <a
                                                                        key={
                                                                            agreement.id
                                                                        }
                                                                        href={`/storage/${agreement.pdf_path}`}
                                                                        target="_blank"
                                                                        rel="noopener noreferrer"
                                                                        className="btn-secondary text-sm"
                                                                    >
                                                                        Download{' '}
                                                                        {agreement.type ===
                                                                        'verification'
                                                                            ? 'Verification'
                                                                            : 'Agreement'}{' '}
                                                                        PDF
                                                                    </a>
                                                                ),
                                                            )}
                                                        </div>
                                                    </div>
                                                )}
                                        </div>
                                    ))}
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
