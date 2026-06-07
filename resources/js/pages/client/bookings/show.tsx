import { Link, Head } from '@inertiajs/react';
import {
    Calendar,
    ExternalLink,
    MapPin,
    User,
    Phone,
    Mail,
    Heart,
    ArrowLeft,
    Building,
    Home,
    Building2,
    PartyPopper,
    Star,
} from 'lucide-react';
import React from 'react';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';

interface SiblingBooking {
    id: number;
    ulid: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    caregiver_name: string | null;
}

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
    has_review: boolean;
    charge_to_client_hourly: number | null;
    total_working_hour: number | null;
    charge_to_client: number | null;
    paid_to_caregiver: number | null;
    sitterwise_cut: number | null;
    tip: number | null;
    reimbursement: number | null;
    reimbursement_description: string | null;
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
    booking_group: {
        id: number;
        bookings_count: number;
        sibling_bookings: SiblingBooking[];
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
}: PageProps) {
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

    const hasHourlyRate = booking.charge_to_client_hourly != null;
    const hasHours = booking.total_working_hour != null;
    const hasReimbursement = booking.reimbursement != null;
    const hasTotal = booking.charge_to_client != null;

    const showFeesSection = hasHourlyRate || hasHours || hasReimbursement || hasTotal;

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
                            View your booking details
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
                                    </div>
                                )}

                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-muted-foreground">
                                        {booking.client_name}
                                    </span>
                                </div>

                                {booking.caregiver_name && (
                                    <div className="flex items-center gap-2">
                                        <User className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.caregiver_name}
                                        </span>
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
                                                                className="border-yellow-600/30 text-yellow-600 dark:text-yellow-400"
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

                            {showFeesSection && (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Fees
                                    </h2>
                                    <div className="space-y-2">
                                        {hasHourlyRate && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">
                                                    Hourly Rate
                                                </span>
                                                <span className="text-sm font-medium text-foreground">
                                                    {formatCurrency(booking.charge_to_client_hourly)}/hr
                                                </span>
                                            </div>
                                        )}
                                        {hasHours && (
                                            <div className="flex items-center justify-between">
                                                <span className="text-sm text-muted-foreground">
                                                    Hours
                                                </span>
                                                <span className="text-sm font-medium text-foreground">
                                                    {Number(booking.total_working_hour).toFixed(1)}
                                                </span>
                                            </div>
                                        )}
                                        {hasReimbursement && (
                                            <>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm text-muted-foreground">
                                                        Reimbursement
                                                    </span>
                                                    <span className="text-sm font-medium text-foreground">
                                                        {formatCurrency(booking.reimbursement)}
                                                    </span>
                                                </div>
                                                {booking.reimbursement_description && (
                                                    <p className="-mt-1 text-right text-xs text-muted-foreground">
                                                        {booking.reimbursement_description}
                                                    </p>
                                                )}
                                            </>
                                        )}
                                        {hasTotal && (
                                            <>
                                                <hr className="border-border" />
                                                <div className="flex items-center justify-between">
                                                    <span className="text-sm font-medium text-foreground">
                                                        Total
                                                    </span>
                                                    <span className="text-sm font-bold text-foreground">
                                                        {formatCurrency(booking.charge_to_client)}
                                                    </span>
                                                </div>
                                            </>
                                        )}
                                    </div>
                                </div>
                            )}
                        </div>
                    </div>
                </div>

                <div className="flex justify-end gap-2">
                    {(booking.status === 'completed' ||
                        booking.status === 'paid') &&
                        !booking.has_review && (
                            <Button asChild>
                                <Link href={`/reviews/${booking.ulid}`}>
                                    <Star className="mr-2 h-4 w-4" />
                                    Write Review
                                </Link>
                            </Button>
                        )}
                    <Button variant="secondary" asChild>
                        <Link href="/bookings">Back to Bookings</Link>
                    </Button>
                </div>
            </div>
        </AppLayout>
    );
}
