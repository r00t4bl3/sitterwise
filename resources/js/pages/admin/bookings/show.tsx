import { Link, Head, useForm } from '@inertiajs/react';
import {
    Calendar,
    ExternalLink,
    MapPin,
    Star,
    User,
    Phone,
    Mail,
    Heart,
    ArrowLeft,
    Building,
    Home,
    Building2,
    PartyPopper,
    Split,
    UserPlus,
} from 'lucide-react';
import React, { useState } from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
import { ReplaceCaregiverSheet } from './replace-caregiver-sheet';

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    client_id: number;
    client_name: string;
    client_phone: string | null;
    client_email: string | null;
    caregiver_id: number | null;
    caregiver_name: string | null;
    assignment_resolution: string | null;
    hotel_id: number | null;
    hotel_name: string | null;
    location_type: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    start_datetime: string;
    end_datetime: string;
    status: string;
    charge_to_client: number | null;
    paid_to_caregiver: number | null;
    sitterwise_cut: number | null;
    tip: number | null;
    reimbursement: number | null;
    special_considerations: string[] | null;
    caregiver_notes: string | null;
    children: Array<{
        name: string;
        gender: string | null;
        birth_year: number | null;
        birth_month: number | null;
    }> | null;
    children_notes: string | null;
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
    client_rating: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    caregiver_rating: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    booking_group: {
        id: number;
        bookings_count: number;
        sibling_bookings: Array<{
            id: number;
            ulid: string;
            start_datetime: string;
            end_datetime: string;
            status: string;
            caregiver_name: string | null;
        }>;
    } | null;
}

interface BookingStatus {
    value: string;
    label: string;
    colors: {
        bg: string;
        text: string;
        border: string;
    };
}

interface PageProps {
    booking: Booking;
    booking_statuses: BookingStatus[];
    caregiver_suggestions: Array<{
        id: number;
        name: string;
        age?: number | null;
        matchIcons?: string[];
        hasBeenNotified?: boolean;
    }>;
    caregiver_all_ids: number[];
    caregiver_total: number;
}

const breadcrumbs = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Bookings',
        href: '/bookings',
    },
    {
        title: 'Booking Details',
        href: '#',
    },
];

export default function BookingDetail({
    booking,
    booking_statuses,
    caregiver_suggestions,
}: PageProps) {
    const [splitDialogOpen, setSplitDialogOpen] = useState(false);
    const [cancelDialogOpen, setCancelDialogOpen] = useState(false);
    const [replaceSheetOpen, setReplaceSheetOpen] = useState(false);

    const cancelForm = useForm({ reason: '' });

    const deleteForm = useForm({});
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false);
    const [deleteConfirmText, setDeleteConfirmText] = useState('');

    const submitDelete = () => {
        deleteForm.delete(`/bookings/${booking.ulid}`, {
            preserveScroll: true,
            onSuccess: () => {
                setDeleteDialogOpen(false);
                setDeleteConfirmText('');
            },
        });
    };

    const submitCancel = () => {
        cancelForm.post(`/bookings/${booking.ulid}/cancel`, {
            preserveScroll: true,
            onSuccess: () => {
                setCancelDialogOpen(false);
                cancelForm.reset();
            },
        });
    };

    const currentSiblingIds = booking.booking_group
        ? [booking.id, ...booking.booking_group.sibling_bookings.map((s) => s.id)]
        : [];

    const splitForm = useForm({
        booking_ids: [booking.id],
    });

    const toggleSplitBooking = (id: number) => {
        const current = splitForm.data.booking_ids;

        if (current.includes(id)) {
            splitForm.setData('booking_ids', current.filter((i) => i !== id));
        } else {
            splitForm.setData('booking_ids', [...current, id]);
        }
    };

    const submitSplit = () => {
        splitForm.post(`/bookings/groups/${booking.booking_group?.id}/split`, {
            preserveScroll: true,
        });
    };
    const buildGoogleMapsUrl = () => {
        const parts = [
            booking.address_line1,
            booking.address_city,
            booking.address_state,
            booking.address_zip,
        ].filter(Boolean);

        if (parts.length === 0) {
            return null;
        }

        return `https://www.google.com/maps/search/${encodeURIComponent(parts.join(', '))}`;
    };

    const formatCurrency = (amount: number | null): string | null => {
        if (amount === null || amount === undefined) {
            return null;
        }

        return `$${Number(amount).toFixed(2)}`;
    };

    const getLocationIcon = (locationType: string) => {
        switch (locationType) {
            case 'hotel':
                return Building;
            case 'private_home':
                return Home;
            case 'vacation_rental':
                return Building2;
            case 'event_venue':
                return PartyPopper;
            default:
                return MapPin;
        }
    };

    const mapsUrl = buildGoogleMapsUrl();

    const feeItems = [
        { label: 'Charge to Client', value: booking.charge_to_client },
        { label: 'Paid to Caregiver', value: booking.paid_to_caregiver },
        { label: 'Sitterwise Cut', value: booking.sitterwise_cut },
        { label: 'Tip', value: booking.tip },
        { label: 'Reimbursement', value: booking.reimbursement },
    ].filter((f) => f.value !== null && f.value !== undefined);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Booking Details" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href="/bookings"
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Booking Details
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            View booking details
                        </p>
                    </div>
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="left-panel">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Booking Information
                            </h2>
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <Heart className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-foreground">
                                        {booking.service_type}
                                    </span>
                                    <StatusBadge
                                        status={booking.status}
                                        bookingStatuses={booking_statuses}
                                    />
                                    {booking.booking_group && booking.booking_group.bookings_count > 1 && (
                                        <Badge variant="outline" className="text-xs">
                                            Multi-Day ({booking.booking_group.bookings_count})
                                        </Badge>
                                    )}
                                </div>

                                <div className="flex items-center gap-2">
                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {formatDisplayDateInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        from{' '}
                                        {formatDisplayTimeInPT(
                                            booking.start_datetime,
                                        )}{' '}
                                        to{' '}
                                        {formatDisplayTimeInPT(
                                            booking.end_datetime,
                                        )}
                                    </span>
                                </div>

                                {booking.booking_group && booking.booking_group.bookings_count > 1 && (
                                    <div className="ml-6 border-l-2 border-border pl-3 space-y-1.5">
                                        {booking.booking_group.sibling_bookings.map((sibling) => (
                                            <Link
                                                key={sibling.id}
                                                href={`/bookings/${sibling.ulid}`}
                                                className="flex items-center justify-between rounded px-2 py-1 text-xs hover:bg-accent transition-colors"
                                            >
                                                <span className="text-muted-foreground">
                                                    {formatDisplayDateInPT(sibling.start_datetime)}{' '}
                                                    {formatDisplayTimeInPT(sibling.start_datetime)} - {formatDisplayTimeInPT(sibling.end_datetime)}
                                                </span>
                                                <div className="flex items-center gap-2">
                                                    {sibling.caregiver_name && (
                                                        <span className="text-muted-foreground">{sibling.caregiver_name}</span>
                                                    )}
                                                    <StatusBadge
                                                        status={sibling.status}
                                                        bookingStatuses={booking_statuses}
                                                    />
                                                </div>
                                            </Link>
                                        ))}
                                        <Dialog open={splitDialogOpen} onOpenChange={setSplitDialogOpen}>
                                            <DialogTrigger asChild>
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    className="mt-2 text-xs"
                                                >
                                                    <Split className="mr-1 h-3 w-3" />
                                                    Split Group
                                                </Button>
                                            </DialogTrigger>
                                            <DialogContent>
                                                <DialogHeader>
                                                    <DialogTitle>Split Group</DialogTitle>
                                                    <DialogDescription>
                                                        Select which dates to move to a new group. The current booking cannot be unchecked.
                                                        Extracted bookings will reset to "received" status and clear caregiver assignment.
                                                    </DialogDescription>
                                                </DialogHeader>
                                                <div className="space-y-3 py-4">
                                                    {currentSiblingIds.map((id) => {
                                                        const isCurrent = id === booking.id;
                                                        const sib = id === booking.id
                                                            ? null
                                                            : booking.booking_group?.sibling_bookings.find((s) => s.id === id);

                                                        return (
                                                            <div key={id} className="flex items-center gap-3">
                                                                <Checkbox
                                                                    id={`split-${id}`}
                                                                    checked={splitForm.data.booking_ids.includes(id)}
                                                                    disabled={isCurrent}
                                                                    onCheckedChange={() => toggleSplitBooking(id)}
                                                                />
                                                                <Label
                                                                    htmlFor={`split-${id}`}
                                                                    className={`text-sm ${isCurrent ? 'font-medium' : 'text-muted-foreground'}`}
                                                                >
                                                                    {isCurrent ? (
                                                                        <span>
                                                                            {formatDisplayDateInPT(booking.start_datetime)}{' '}
                                                                            {formatDisplayTimeInPT(booking.start_datetime)} - {formatDisplayTimeInPT(booking.end_datetime)}
                                                                            <span className="ml-2 text-xs text-muted-foreground">(this booking)</span>
                                                                        </span>
                                                                    ) : sib ? (
                                                                        <span>
                                                                            {formatDisplayDateInPT(sib.start_datetime)}{' '}
                                                                            {formatDisplayTimeInPT(sib.start_datetime)} - {formatDisplayTimeInPT(sib.end_datetime)}
                                                                            {sib.caregiver_name && (
                                                                                <span className="ml-2 text-xs text-muted-foreground">({sib.caregiver_name})</span>
                                                                            )}
                                                                        </span>
                                                                    ) : null}
                                                                </Label>
                                                            </div>
                                                        );
                                                    })}
                                                </div>
                                                <DialogFooter>
                                                    <Button
                                                        variant="outline"
                                                        onClick={() => setSplitDialogOpen(false)}
                                                    >
                                                        Cancel
                                                    </Button>
                                                    <Button
                                                        onClick={submitSplit}
                                                        disabled={splitForm.processing || splitForm.data.booking_ids.length === 0}
                                                    >
                                                        {splitForm.processing ? 'Splitting...' : 'Split Group'}
                                                    </Button>
                                                </DialogFooter>
                                            </DialogContent>
                                        </Dialog>
                                    </div>
                                )}

                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    <Link
                                        href={`/clients/${booking.client_id}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        {booking.client_name}
                                    </Link>
                                </div>

                                {booking.caregiver_name && (
                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        <Link
                                            href={`/caregivers/${booking.caregiver_id}`}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {booking.caregiver_name}
                                        </Link>
                                        {booking.assignment_resolution && (
                                            <Badge variant="outline" className="text-xs">
                                                {{
                                                    'backed_out': 'Backed Out',
                                                    'backed_out_excused': 'Backed Out (Excused)',
                                                    'reassigned': 'Reassigned',
                                                    'completed': 'Completed',
                                                    'no_show': 'No-Show',
                                                    'cancelled_by_sitterwise': 'Cancelled',
                                                }[booking.assignment_resolution] ?? booking.assignment_resolution}
                                            </Badge>
                                        )}
                                        {booking.status !== 'cancelled' && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                className="h-6 text-[11px]"
                                                onClick={() => setReplaceSheetOpen(true)}
                                            >
                                                <UserPlus className="mr-1 h-3 w-3" />
                                                Replace
                                            </Button>
                                        )}
                                    </div>
                                )}

                                {booking.client_phone && (
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <a
                                        href={`tel:${booking.client_phone}`}
                                        className="text-sm text-primary hover:underline"
                                    >
                                        {formatPhoneDisplay(booking.client_phone)}
                                        </a>
                                    </div>
                                )}

                                {booking.client_email && (
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <a
                                            href={`mailto:${booking.client_email}`}
                                            className="text-sm text-primary hover:underline"
                                        >
                                            {booking.client_email}
                                        </a>
                                    </div>
                                )}

                                {booking.hotel_name && (
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.hotel_name}
                                        </span>
                                    </div>
                                )}

                                {mapsUrl && (
                                    <div className="flex items-start gap-2">
                                        {React.createElement(
                                            getLocationIcon(
                                                booking.location_type,
                                            ),
                                            {
                                                className:
                                                    'mt-0.5 h-4 w-4 text-muted-foreground',
                                            },
                                        )}
                                        <a
                                            href={mapsUrl}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="flex items-start gap-1 text-sm text-primary hover:underline"
                                        >
                                            <span>
                                                {booking.address_line1 && (
                                                    <span>
                                                        {booking.address_line1}
                                                        {booking.address_line2 && (
                                                            <span>
                                                                ,{' '}
                                                                {
                                                                    booking.address_line2
                                                                }
                                                            </span>
                                                        )}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_city && (
                                                    <span>
                                                        {booking.address_city}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_state && (
                                                    <span>
                                                        {booking.address_state}
                                                        ,{' '}
                                                    </span>
                                                )}
                                                {booking.address_zip && (
                                                    <span>
                                                        {booking.address_zip}
                                                    </span>
                                                )}
                                            </span>
                                            <ExternalLink className="mt-0.5 h-3 w-3 shrink-0" />
                                        </a>
                                    </div>
                                )}
                            </div>

                            {booking.children_notes ? (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {booking.children_notes}
                                    </p>
                                </div>
                            ) : booking.children &&
                              booking.children.length > 0 ? (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children ({booking.children.length})
                                    </h2>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {booking.children.map((child, i) => (
                                            <li key={i}>
                                                {child.name} (
                                                {calculateAge(
                                                    child.birth_year,
                                                    child.birth_month,
                                                )}
                                                )
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            ) : null}

                            {booking.pets && booking.pets.length > 0 && (
                                <div className="mt-6">
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Pets ({booking.pets.length})
                                    </h2>
                                    <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                        {booking.pets.map((pet, i) => (
                                            <li key={i}>
                                                {pet.name} ({pet.breed} /{' '}
                                                {pet.type})
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                        </div>

                        <div className="right-panel grid gap-6">
                            <div>
                                <h2 className="text-md mb-2 font-semibold text-foreground">
                                    Notes & Considerations
                                </h2>
                                <div className="space-y-3">
                                    {booking.special_considerations &&
                                        booking.special_considerations.length >
                                            0 && (
                                            <div>
                                                <h3 className="mb-1 text-sm font-medium text-foreground">
                                                    Special Considerations
                                                </h3>
                                                <div className="flex flex-wrap gap-1">
                                                    {booking.special_considerations.map(
                                                        (consideration, i) => (
                                                            <Badge
                                                                key={i}
                                                                variant="outline"
                                                                className="border-yellow-500 text-yellow-700"
                                                            >
                                                                {consideration}
                                                            </Badge>
                                                        ),
                                                    )}
                                                </div>
                                            </div>
                                        )}
                                    {booking.caregiver_notes && (
                                        <div>
                                            <h3 className="mb-1 text-sm font-medium text-foreground">
                                                Notes for Caregiver
                                            </h3>
                                            <p className="text-sm text-muted-foreground">
                                                {booking.caregiver_notes}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </div>

                            {feeItems.length > 0 && (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Fees
                                    </h2>
                                    <div className="space-y-2">
                                        {feeItems.map((item) => (
                                            <div
                                                key={item.label}
                                                className="flex items-center justify-between"
                                            >
                                                <span className="text-sm text-muted-foreground">
                                                    {item.label}
                                                </span>
                                                <span className="text-sm font-medium text-foreground">
                                                    {formatCurrency(item.value)}
                                                </span>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

                            <div className="mt-6">
                                <h2 className="mb-4 text-lg font-semibold text-foreground">
                                    Reviews & Feedback
                                </h2>
                                <div className="space-y-4">
                                    <div className="rounded-lg border border-border bg-card p-4">
                                        <h3 className="mb-2 text-sm font-medium text-foreground">
                                            Feedback from Client
                                        </h3>
                                        {booking.client_rating ? (
                                            <div className="flex flex-col gap-2">
                                                <div className="flex items-center gap-1">
                                                    {[1, 2, 3, 4, 5].map(
                                                        (star) => (
                                                            <Star
                                                                key={star}
                                                                className={`h-5 w-5 ${
                                                                    star <=
                                                                    booking
                                                                        .client_rating!
                                                                        .rating
                                                                        ? 'fill-yellow-400 text-yellow-400'
                                                                        : 'text-gray-300'
                                                                }`}
                                                            />
                                                        ),
                                                    )}
                                                    <span className="ml-2 text-sm text-muted-foreground">
                                                        (
                                                        {
                                                            booking
                                                                .client_rating
                                                                .rating
                                                        }
                                                        /5)
                                                    </span>
                                                </div>
                                                {booking.client_rating
                                                    .comment && (
                                                    <p className="text-sm text-muted-foreground italic">
                                                        &quot;
                                                        {
                                                            booking
                                                                .client_rating
                                                                .comment
                                                        }
                                                        &quot;
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                No feedback from client yet.
                                            </p>
                                        )}
                                    </div>

                                    <div className="rounded-lg border border-border bg-card p-4">
                                        <h3 className="mb-2 text-sm font-medium text-foreground">
                                            Review from Caregiver
                                        </h3>
                                        {booking.caregiver_rating ? (
                                            <div className="flex flex-col gap-2">
                                                <div className="flex items-center gap-1">
                                                    {[1, 2, 3, 4, 5].map(
                                                        (star) => (
                                                            <Star
                                                                key={star}
                                                                className={`h-5 w-5 ${
                                                                    star <=
                                                                    booking
                                                                        .caregiver_rating!
                                                                        .rating
                                                                        ? 'fill-yellow-400 text-yellow-400'
                                                                        : 'text-gray-300'
                                                                }`}
                                                            />
                                                        ),
                                                    )}
                                                    <span className="ml-2 text-sm text-muted-foreground">
                                                        (
                                                        {
                                                            booking
                                                                .caregiver_rating
                                                                .rating
                                                        }
                                                        /5)
                                                    </span>
                                                </div>
                                                {booking.caregiver_rating
                                                    .comment && (
                                                    <p className="text-sm text-muted-foreground italic">
                                                        &quot;
                                                        {
                                                            booking
                                                                .caregiver_rating
                                                                .comment
                                                        }
                                                        &quot;
                                                    </p>
                                                )}
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                No review from caregiver yet.
                                            </p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    {booking.status !== 'cancelled' && (
                        <>
                            <Button
                                variant="outline"
                                onClick={() => setReplaceSheetOpen(true)}
                            >
                                <UserPlus className="mr-1 h-4 w-4" />
                                Replace Caregiver
                            </Button>
                            <Button
                                variant="destructive"
                                onClick={() => setCancelDialogOpen(true)}
                            >
                                Cancel Booking
                            </Button>
                        </>
                    )}
                    <Button
                        variant="destructive"
                        onClick={() => setDeleteDialogOpen(true)}
                    >
                        Delete Booking
                    </Button>
                    <Button asChild>
                        <Link href="/bookings">Back to Bookings</Link>
                    </Button>
                </div>
            </div>

            <ReplaceCaregiverSheet
                open={replaceSheetOpen}
                onOpenChange={setReplaceSheetOpen}
                bookingId={booking.id}
                currentCaregiverName={booking.caregiver_name}
                caregiverSuggestions={caregiver_suggestions}
            />

            <Dialog open={cancelDialogOpen} onOpenChange={setCancelDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Cancel Booking</DialogTitle>
                        <DialogDescription>
                            This action will mark the booking as cancelled, zero out all financial fields, and release the caregiver. This cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="grid gap-2">
                            <Label htmlFor="reason">Cancellation Reason</Label>
                            <Textarea
                                id="reason"
                                value={cancelForm.data.reason}
                                onChange={(e) => cancelForm.setData('reason', e.target.value)}
                                placeholder="Explain why this booking is being cancelled..."
                                rows={3}
                            />
                            {cancelForm.errors.reason && (
                                <p className="text-sm text-destructive">{cancelForm.errors.reason}</p>
                            )}
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setCancelDialogOpen(false);
                                cancelForm.reset();
                            }}
                        >
                            Keep Booking
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitCancel}
                            disabled={cancelForm.processing}
                        >
                            {cancelForm.processing ? 'Cancelling...' : 'Cancel Booking'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog open={deleteDialogOpen} onOpenChange={setDeleteDialogOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>Delete Booking</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this booking?
                        </DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4 py-4">
                        <div className="rounded-lg border border-destructive/20 bg-destructive/5 p-3 text-sm text-destructive">
                            <ul className="list-disc space-y-1 pl-4">
                                <li>This action is permanent and cannot be undone.</li>
                                <li>
                                    All related data — reviews, ratings,
                                    transactions — will also be deleted.
                                </li>
                            </ul>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="delete-confirm">
                                Type <span className="font-mono font-bold">DELETE</span> to confirm:
                            </Label>
                            <Input
                                id="delete-confirm"
                                value={deleteConfirmText}
                                onChange={(e) => setDeleteConfirmText(e.target.value)}
                                placeholder="DELETE"
                                className={deleteConfirmText === 'DELETE' ? 'border-destructive' : ''}
                            />
                        </div>
                    </div>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => {
                                setDeleteDialogOpen(false);
                                setDeleteConfirmText('');
                            }}
                        >
                            Cancel
                        </Button>
                        <Button
                            variant="destructive"
                            onClick={submitDelete}
                            disabled={deleteConfirmText !== 'DELETE' || deleteForm.processing}
                        >
                            {deleteForm.processing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
