import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft, Check, Eye, EyeOff } from 'lucide-react';
import { useState } from 'react';
import type { SubmitEventHandler } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { UserAvatar } from '@/components/user-avatar';
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
        title: 'Client Details',
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
}

interface Pet {
    id: number;
    name: string | null;
    type: string;
    breed: string | null;
    notes: string | null;
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

interface FavoriteCaregiver {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
}

interface TypeChange {
    id: number;
    previous_type: string;
    new_type: string;
    reason: string | null;
    changed_at: string;
    admin: {
        name: string;
    } | null;
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
    biography: string | null;
    special_needs_notes: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
    addresses: Address[];
    children: Child[];
    pets: Pet[];
    attributes: Attribute[];
    favorite_caregivers: FavoriteCaregiver[];
    type_changes: TypeChange[];
}

interface Props {
    [key: string]: unknown;
    client: Client;
}

function ClientTypeBadge({ type }: { type: string }) {
    const colors: Record<string, { bg: string; text: string }> = {
        resident: { bg: '#DBEAFE', text: '#1E40AF' },
        vacationer: { bg: '#FEF3C7', text: '#B45309' },
        invoiced: { bg: '#E0E7FF', text: '#3730A3' },
    };

    const style = colors[type] || { bg: '#F3F4F6', text: '#374151' };
    const labels: Record<string, string> = {
        resident: 'San Diego Resident',
        vacationer: 'Vacationer',
        invoiced: 'Invoiced',
    };

    return (
        <span
            className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{
                backgroundColor: style.bg,
                color: style.text,
            }}
        >
            {labels[type] || type}
        </span>
    );
}

function calculateAgeInMonths(
    birthMonth: number | null,
    birthYear: number | null,
): number | null {
    if (!birthYear || birthMonth === null) {
        return null;
    }

    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    const totalMonths =
        (currentYear - birthYear) * 12 + (currentMonth - birthMonth);

    return totalMonths;
}

function getAgeDisplay(
    birthMonth: number | null,
    birthYear: number | null,
): string {
    const monthNames = [
        'January',
        'February',
        'March',
        'April',
        'May',
        'June',
        'July',
        'August',
        'September',
        'October',
        'November',
        'December',
    ];

    if (birthYear) {
        const totalMonths = calculateAgeInMonths(birthMonth, birthYear);

        if (totalMonths !== null) {
            if (totalMonths < 12) {
                return totalMonths === 1
                    ? '1 month old'
                    : `${totalMonths} months old`;
            }

            const years = Math.floor(totalMonths / 12);
            const remainingMonths = totalMonths % 12;

            if (remainingMonths === 0) {
                return years === 1 ? '1 year old' : `${years} years old`;
            }

            const yearLabel = years === 1 ? 'year' : 'years';
            const monthLabel = remainingMonths === 1 ? 'month' : 'months';

            return `${years} ${yearLabel}, ${remainingMonths} ${monthLabel} old`;
        }

        return `Born in ${birthYear}`;
    }

    if (birthMonth) {
        return `Born in ${monthNames[birthMonth - 1]}`;
    }

    return 'Age not specified';
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

export default function ClientShow() {
    const { client } = usePage<Props>().props;
    const [isPasswordSheetOpen, setIsPasswordSheetOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);

    const passwordForm = useForm<{
        new_password: string;
        new_password_confirmation: string;
    }>({
        new_password: '',
        new_password_confirmation: '',
    });

    const handlePasswordReset: SubmitEventHandler = (e) => {
        e.preventDefault();
        passwordForm.post(`/clients/${client.id}/password`, {
            onSuccess: () => {
                setIsPasswordSheetOpen(false);
                passwordForm.reset();
            },
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${client.first_name} ${client.last_name}`} />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/clients"
                            className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div className="flex items-center gap-4">
                            <UserAvatar
                                profile_photo_url={
                                    client.user.profile_photo_url
                                }
                                profile_photo_path={
                                    client.user.profile_photo_path
                                }
                                name={`${client.first_name} ${client.last_name}`}
                                size="lg"
                            />
                            <div>
                                <h1 className="text-2xl font-bold text-foreground">
                                    {client.first_name} {client.last_name}
                                </h1>
                                <p className="text-muted-foreground">
                                    Client Profile
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="flex gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => setIsPasswordSheetOpen(true)}
                        >
                            Reset Password
                        </Button>
                        <Link
                            href={`/clients/${client.id}/edit`}
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
                                client.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handlePasswordReset}
                            className="mt-6 space-y-6 px-4"
                        >
                            <div className="space-y-2">
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
                            <div className="space-y-2">
                                <Label htmlFor="new_password_confirmation">
                                    Confirm Password
                                </Label>
                                <Input
                                    id="new_password_confirmation"
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
                                    variant="secondary"
                                    onClick={() =>
                                        setIsPasswordSheetOpen(false)
                                    }
                                    className="w-full"
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
                                    {client.email}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {client.phone}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Client Type
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    <ClientTypeBadge
                                        type={client.client_type}
                                    />
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    How did you hear about us
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {client.how_did_you_hear || '—'}
                                </p>
                            </div>
                            {client.client_type !== 'vacationer' && client.biography && (
                                <div className="sm:col-span-2">
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Biography
                                    </p>
                                    <p className="text-sm text-foreground">
                                        {client.biography}
                                    </p>
                                </div>
                            )}
                        </div>

                        {client.children.length > 0 && (
                            <div className="mt-6 border-t border-border pt-6">
                                <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                    Children ({client.children.length})
                                </h3>
                                <div className="space-y-3">
                                    {client.children.map((child) => (
                                        <div
                                            key={child.id}
                                            className="rounded-[3px] border border-border bg-background p-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">
                                                    {child.name || 'Unnamed'}
                                                </span>
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {child.gender ||
                                                    'Gender not specified'}{' '}
                                                •{' '}
                                                {getAgeDisplay(
                                                    child.birth_month,
                                                    child.birth_year,
                                                )}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {client.pets.length > 0 && (
                            <div className="mt-6 border-t border-border pt-6">
                                <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                    Pets ({client.pets.length})
                                </h3>
                                <div className="space-y-3">
                                    {client.pets.map((pet) => (
                                        <div
                                            key={pet.id}
                                            className="rounded-[3px] border border-border bg-background p-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">
                                                    {pet.name || pet.type}
                                                </span>
                                                <span className="text-sm text-muted-foreground capitalize">
                                                    {pet.type}
                                                </span>
                                            </div>
                                            {pet.breed && (
                                                <p className="text-sm text-muted-foreground">
                                                    {pet.breed}
                                                </p>
                                            )}
                                            {pet.notes && (
                                                <p className="mt-1 text-sm text-foreground">
                                                    {pet.notes}
                                                </p>
                                            )}
                                        </div>
                                    ))}
                                </div>
                            </div>
                        )}

                        {client.attributes &&
                            client.attributes.filter(
                                (a) =>
                                    a.value === 'true' ||
                                    a.value === '1' ||
                                    a.value === true,
                            ).length > 0 && (
                                <div className="mt-6 border-t border-border pt-6">
                                    <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Attributes
                                    </h3>
                                    <div className="grid gap-2 sm:grid-cols-2">
                                        {client.attributes
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
                                                        attr
                                                            .attribute_definition
                                                            .name
                                                    }
                                                    value={attr.value}
                                                />
                                            ))}
                                    </div>
                                </div>
                            )}
                    </div>

                    <div className="space-y-6">
                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Addresses
                            </h2>
                            {client.addresses.length > 0 ? (
                                <div className="space-y-3">
                                    {client.addresses.map((address) => (
                                        <div
                                            key={address.id}
                                            className="rounded-[3px] border border-border bg-background p-3"
                                        >
                                            <div className="flex items-center justify-between">
                                                <span className="font-medium text-foreground">
                                                    {address.label ||
                                                        address.location_type}
                                                </span>
                                                {address.is_primary && (
                                                    <span className="text-xs text-muted-foreground">
                                                        Primary
                                                    </span>
                                                )}
                                            </div>
                                            <p className="text-sm text-muted-foreground">
                                                {address.line1}
                                                {address.line2 &&
                                                    `, ${address.line2}`}
                                            </p>
                                            <p className="text-sm text-muted-foreground">
                                                {address.city}, {address.state}{' '}
                                                {address.zip}
                                            </p>
                                        </div>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No addresses on file
                                </p>
                            )}
                        </div>

                        {client.sitter_preferences &&
                            client.sitter_preferences.length > 0 && (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Sitter Preferences
                                    </h2>
                                    <div className="flex flex-wrap gap-2">
                                        {client.sitter_preferences.map(
                                            (pref) => (
                                                <span
                                                    key={pref}
                                                    className="rounded-full bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground"
                                                >
                                                    {pref.replace(/_/g, ' ')}
                                                </span>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                        {client.special_needs_notes && (
                            <div className="border border-red-200 bg-red-50 p-6">
                                <h2 className="mb-2 font-serif text-lg font-semibold text-red-800">
                                    Special Needs
                                </h2>
                                <p className="text-sm text-red-700">
                                    {client.special_needs_notes}
                                </p>
                            </div>
                        )}

                        {client.favorite_caregivers &&
                            client.favorite_caregivers.length > 0 && (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Favorite Caregivers
                                    </h2>
                                    <div className="space-y-3">
                                        {client.favorite_caregivers.map(
                                            (caregiver) => (
                                                <Link
                                                    key={caregiver.id}
                                                    href={`/caregivers/${caregiver.id}`}
                                                    className="flex items-center gap-3 rounded-[3px] border border-border bg-background p-3 hover:bg-accent"
                                                >
                                                    <UserAvatar
                                                        profile_photo_url={
                                                            caregiver.user
                                                                .profile_photo_url
                                                        }
                                                        profile_photo_path={
                                                            caregiver.user
                                                                .profile_photo_path
                                                        }
                                                        name={`${caregiver.first_name} ${caregiver.last_name}`}
                                                        size="md"
                                                    />
                                                    <span className="font-medium text-foreground">
                                                        {caregiver.first_name}{' '}
                                                        {caregiver.last_name}
                                                    </span>
                                                </Link>
                                            ),
                                        )}
                                    </div>
                                </div>
                            )}

                        {client.type_changes &&
                            client.type_changes.length > 0 && (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Type Change History
                                    </h2>
                                    <div className="space-y-3">
                                        {client.type_changes.map((tc) => (
                                            <div
                                                key={tc.id}
                                                className="rounded-[3px] border border-border bg-background p-3"
                                            >
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <span className="text-sm font-medium text-foreground">
                                                            {tc.previous_type
                                                                .replace(
                                                                    '_',
                                                                    ' ',
                                                                )
                                                                .replace(
                                                                    /\b\w/g,
                                                                    (c) =>
                                                                        c.toUpperCase(),
                                                                )}
                                                        </span>
                                                        <Check className="h-4 w-4 text-green-600" />
                                                        <span className="text-sm font-medium text-foreground">
                                                            {tc.new_type
                                                                .replace(
                                                                    '_',
                                                                    ' ',
                                                                )
                                                                .replace(
                                                                    /\b\w/g,
                                                                    (c) =>
                                                                        c.toUpperCase(),
                                                                )}
                                                        </span>
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">
                                                        {new Date(
                                                            tc.changed_at,
                                                        ).toLocaleDateString()}
                                                    </span>
                                                </div>
                                                {tc.reason && (
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {tc.reason}
                                                    </p>
                                                )}
                                                {tc.admin && (
                                                    <p className="mt-1 text-xs text-muted-foreground">
                                                        Changed by{' '}
                                                        {tc.admin.name}
                                                    </p>
                                                )}
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
