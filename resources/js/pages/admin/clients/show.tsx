import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    CalendarPlus,
    Check,
    CreditCard,
    Eye,
    EyeOff,
    MoreVertical,
    Trash2,
} from 'lucide-react';
import { useState } from 'react';
import type { SubmitEventHandler } from 'react';
import { StripeCardInput } from '@/components/stripe/stripe-card-element';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
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
import { formatDisplayDateShortInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
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
    } | null;
}

interface BlockedCaregiver {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
}

interface PreviousCaregiver {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
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

interface PaymentMethod {
    id: number;
    brand: string;
    last4: string;
    exp_month: number;
    exp_year: number;
    is_default: boolean;
    status: string;
}

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    client_type: string;
    how_did_you_hear: string | null;
    how_did_you_hear_label: string | null;
    sitter_preferences: string[] | null;
    sitter_preferences_labels: string[];
    biography: string | null;
    special_needs_notes: string | null;
    notes: string | null;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    } | null;
    addresses: Address[];
    children: Child[];
    pets: Pet[];
    attributes: Attribute[];
    favorite_caregivers: FavoriteCaregiver[];
    blocked_caregivers: BlockedCaregiver[];
    previous_caregivers: PreviousCaregiver[];
    type_changes: TypeChange[];
    has_payment_method: boolean;
    payment_methods: PaymentMethod[];
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
    const [isPaymentSheetOpen, setIsPaymentSheetOpen] = useState(false);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [paymentMethodToDelete, setPaymentMethodToDelete] = useState<
        number | null
    >(null);
    const [showPassword, setShowPassword] = useState(false);
    const [paymentMethodId, setPaymentMethodId] = useState<string | null>(null);

    const passwordForm = useForm<{
        new_password: string;
        new_password_confirmation: string;
    }>({
        new_password: '',
        new_password_confirmation: '',
    });

    const paymentForm = useForm({
        payment_method_id: '',
    });

    const setDefaultForm = useForm({});
    const deletePaymentForm = useForm({});

    const handlePasswordReset: SubmitEventHandler = (e) => {
        e.preventDefault();
        passwordForm.post(`/clients/${client.id}/password`, {
            onSuccess: () => {
                setIsPasswordSheetOpen(false);
                passwordForm.reset();
            },
        });
    };

    const handlePaymentSubmit: SubmitEventHandler = (e) => {
        e.preventDefault();
        paymentForm.post(`/clients/${client.id}/payment-method`, {
            onSuccess: () => {
                setIsPaymentSheetOpen(false);
                setPaymentMethodId(null);
                paymentForm.reset();
            },
        });
    };

    const handlePaymentMethodReady = (pmId: string | null) => {
        setPaymentMethodId(pmId);
        paymentForm.setData('payment_method_id', pmId || '');
    };

    const handleSetDefault = (pmId: number) => {
        setDefaultForm.patch(
            `/clients/${client.id}/payment-method/${pmId}/default`,
            {
                preserveScroll: true,
            },
        );
    };

    const handleDeletePaymentMethod = (pmId: number) => {
        setPaymentMethodToDelete(pmId);
        setIsDeleteDialogOpen(true);
    };

    const confirmDelete = () => {
        if (paymentMethodToDelete) {
            deletePaymentForm.delete(
                `/clients/${client.id}/payment-method/${paymentMethodToDelete}`,
                {
                    preserveScroll: true,
                    onSuccess: () => {
                        setIsDeleteDialogOpen(false);
                        setPaymentMethodToDelete(null);
                    },
                },
            );
        }
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
                                    client.user?.profile_photo_url ?? null
                                }
                                profile_photo_path={
                                    client.user?.profile_photo_path ?? null
                                }
                                name={`${client.first_name} ${client.last_name}`}
                                size="md"
                                className="size-10 md:size-16"
                            />
                            <div>
                                <h1 className="text-xl font-bold text-foreground md:text-2xl">
                                    {client.first_name} {client.last_name}
                                </h1>
                                <p className="hidden text-muted-foreground md:block">
                                    Client Profile
                                </p>
                            </div>
                        </div>
                    </div>
                    <div className="hidden gap-2 xl:flex">
                        <Link
                            href={`/bookings?client=${client.id}`}
                            className="btn-secondary flex items-center gap-2"
                        >
                            <CalendarPlus className="h-4 w-4" />
                            Create Booking
                        </Link>
                        <Link
                            href={`/clients/${client.id}/bookings`}
                            className="btn-secondary"
                        >
                            View Bookings
                        </Link>
                        <Button
                            variant="secondary"
                            onClick={() => setIsPaymentSheetOpen(true)}
                            className="flex items-center gap-2"
                        >
                            <CreditCard className="h-4 w-4" />
                            {client.has_payment_method
                                ? 'Manage Payment Method'
                                : 'Add Payment Method'}
                        </Button>
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
                                        href={`/bookings?client=${client.id}`}
                                    >
                                        Create Booking
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link
                                        href={`/clients/${client.id}/bookings`}
                                    >
                                        View Bookings
                                    </Link>
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setIsPaymentSheetOpen(true)}
                                >
                                    {client.has_payment_method
                                        ? 'Manage Payment Method'
                                        : 'Add Payment Method'}
                                </DropdownMenuItem>
                                <DropdownMenuItem
                                    onClick={() => setIsPasswordSheetOpen(true)}
                                >
                                    Reset Password
                                </DropdownMenuItem>
                                <DropdownMenuItem asChild>
                                    <Link href={`/clients/${client.id}/edit`}>
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

                <Sheet
                    open={isPaymentSheetOpen}
                    onOpenChange={setIsPaymentSheetOpen}
                >
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>
                                {client.has_payment_method
                                    ? 'Manage Payment Method'
                                    : 'Add Payment Method'}
                            </SheetTitle>
                            <SheetDescription>
                                {client.has_payment_method
                                    ? 'View existing or add a new payment method for this client.'
                                    : 'Securely add a new payment method for this client.'}
                            </SheetDescription>
                        </SheetHeader>

                        {client.payment_methods.length > 0 && (
                            <div className="space-y-3 px-4">
                                <p className="text-sm font-medium text-foreground">
                                    Current Payment Methods
                                </p>
                                {client.payment_methods.map((method) => (
                                    <div
                                        key={method.id}
                                        className="flex items-center justify-between rounded-md border border-border bg-background p-3"
                                    >
                                        <div className="flex items-center gap-3">
                                            <CreditCard className="h-5 w-5 text-muted-foreground" />
                                            <div>
                                                <p className="text-sm font-medium capitalize">
                                                    {method.brand} ••••{' '}
                                                    {method.last4}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    Expires {method.exp_month}/
                                                    {method.exp_year}
                                                </p>
                                            </div>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            {method.is_default ? (
                                                <span className="text-[10px] font-bold tracking-wider text-primary uppercase">
                                                    Default
                                                </span>
                                            ) : (
                                                <DropdownMenu>
                                                    <DropdownMenuTrigger
                                                        asChild
                                                    >
                                                        <Button
                                                            variant="ghost"
                                                            size="sm"
                                                            className="h-8 w-8 p-0"
                                                        >
                                                            <MoreVertical className="h-4 w-4" />
                                                        </Button>
                                                    </DropdownMenuTrigger>
                                                    <DropdownMenuContent align="end">
                                                        <DropdownMenuItem
                                                            disabled={
                                                                setDefaultForm.processing
                                                            }
                                                            onClick={() =>
                                                                handleSetDefault(
                                                                    method.id,
                                                                )
                                                            }
                                                        >
                                                            <Check className="mr-2 h-4 w-4" />
                                                            Set as Default
                                                        </DropdownMenuItem>
                                                        <DropdownMenuItem
                                                            disabled={
                                                                deletePaymentForm.processing
                                                            }
                                                            onClick={() =>
                                                                handleDeletePaymentMethod(
                                                                    method.id,
                                                                )
                                                            }
                                                            className="text-destructive focus:text-destructive"
                                                        >
                                                            <Trash2 className="mr-2 h-4 w-4" />
                                                            Remove
                                                        </DropdownMenuItem>
                                                    </DropdownMenuContent>
                                                </DropdownMenu>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        )}

                        <form
                            onSubmit={handlePaymentSubmit}
                            className="mt-8 space-y-6 px-4"
                        >
                            <div className="space-y-4">
                                <p className="text-sm font-medium text-foreground">
                                    Add New Card
                                </p>
                                <div className="rounded-md border border-border p-4">
                                    <StripeCardInput
                                        onPaymentMethodReady={
                                            handlePaymentMethodReady
                                        }
                                        error={
                                            paymentForm.errors.payment_method_id
                                        }
                                    />
                                </div>
                                <p className="text-xs text-muted-foreground">
                                    Card details are securely processed and
                                    stored by Stripe.
                                </p>
                            </div>

                            <div className="mt-10 w-full space-y-2">
                                <Button
                                    type="submit"
                                    disabled={
                                        paymentForm.processing ||
                                        !paymentMethodId
                                    }
                                    className="w-full"
                                >
                                    {paymentForm.processing
                                        ? 'Adding...'
                                        : 'Add Payment Method'}
                                </Button>
                                <Button
                                    type="button"
                                    variant="secondary"
                                    onClick={() => setIsPaymentSheetOpen(false)}
                                    className="w-full"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                <Dialog
                    open={isDeleteDialogOpen}
                    onOpenChange={setIsDeleteDialogOpen}
                >
                    <DialogContent>
                        <DialogHeader>
                            <DialogTitle>Remove Payment Method</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to remove this payment
                                method? This action cannot be undone.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="secondary"
                                onClick={() => setIsDeleteDialogOpen(false)}
                            >
                                Cancel
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={confirmDelete}
                                disabled={deletePaymentForm.processing}
                            >
                                {deletePaymentForm.processing
                                    ? 'Removing...'
                                    : 'Remove'}
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

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
                                    {client.email && (
                                        <a
                                            href={`mailto:${client.email}`}
                                            className="text-primary hover:underline"
                                        >
                                            {client.email}
                                        </a>
                                    )}
                                </p>
                            </div>
                            <div>
                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                    Phone
                                </p>
                                <p className="text-sm font-medium text-foreground">
                                    {client.phone && (
                                        <a
                                            href={`tel:${client.phone}`}
                                            className="text-primary hover:underline"
                                        >
                                            {formatPhoneDisplay(client.phone)}
                                        </a>
                                    )}
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
                                    {client.how_did_you_hear_label ||
                                        client.how_did_you_hear ||
                                        '—'}
                                </p>
                            </div>

                            {client.sitter_preferences &&
                                client.sitter_preferences.length > 0 && (
                                    <div className="sm:col-span-2">
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Sitter Preferences
                                        </p>
                                        <div className="flex flex-wrap gap-2">
                                            {client.sitter_preferences_labels.map(
                                                (label) => (
                                                    <span
                                                        key={label}
                                                        className="rounded-full bg-secondary px-3 py-1 text-xs font-medium text-secondary-foreground"
                                                    >
                                                        {label}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    </div>
                                )}

                            {client.client_type !== 'vacationer' &&
                                client.biography && (
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
                            <div className="mt-6 border-t border-border pt-3">
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
                            <div className="border-t border-border pt-6">
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

                        {client.notes && (
                            <div className="border border-amber-200 bg-amber-50 p-6">
                                <h2 className="mb-2 font-serif text-lg font-semibold text-amber-800">
                                    Admin Notes
                                </h2>
                                <p className="text-sm text-amber-700">
                                    {client.notes}
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
                                                                ?.profile_photo_url ??
                                                            null
                                                        }
                                                        profile_photo_path={
                                                            caregiver.user
                                                                ?.profile_photo_path ??
                                                            null
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

                        {client.previous_caregivers &&
                            client.previous_caregivers.length > 0 && (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Previous Caregivers
                                    </h2>
                                    <div className="space-y-3">
                                        {client.previous_caregivers.map(
                                            (caregiver) => (
                                                <Link
                                                    key={caregiver.id}
                                                    href={`/caregivers/${caregiver.id}`}
                                                    className="flex items-center gap-3 rounded-[3px] border border-border bg-background p-3 hover:bg-accent"
                                                >
                                                    <UserAvatar
                                                        profile_photo_url={
                                                            caregiver.user
                                                                ?.profile_photo_url ??
                                                            null
                                                        }
                                                        profile_photo_path={
                                                            caregiver.user
                                                                ?.profile_photo_path ??
                                                            null
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

                        {client.blocked_caregivers &&
                            client.blocked_caregivers.length > 0 && (
                                <div className="border border-red-200 bg-red-50 p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-red-800">
                                        Blocked Caregivers
                                    </h2>
                                    <div className="space-y-3">
                                        {client.blocked_caregivers.map(
                                            (caregiver) => (
                                                <div
                                                    key={caregiver.id}
                                                    className="flex items-center gap-3 rounded-[3px] border border-red-200 bg-white p-3"
                                                >
                                                    <UserAvatar
                                                        profile_photo_url={
                                                            caregiver.user
                                                                ?.profile_photo_url ??
                                                            null
                                                        }
                                                        profile_photo_path={
                                                            caregiver.user
                                                                ?.profile_photo_path ??
                                                            null
                                                        }
                                                        name={`${caregiver.first_name} ${caregiver.last_name}`}
                                                        size="md"
                                                    />
                                                    <span className="font-medium text-foreground">
                                                        {caregiver.first_name}{' '}
                                                        {caregiver.last_name}
                                                    </span>
                                                </div>
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
                                                        {formatDisplayDateShortInPT(
                                                            tc.changed_at,
                                                        )}
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
