import { Link, Head } from '@inertiajs/react';
import {
    Calendar,
    MapPin,
    User,
    ArrowLeft,
    Phone,
    Mail,
    Heart,
    Building,
    Home,
    Building2,
    PartyPopper,
    Star,
} from 'lucide-react';
import React from 'react';
import AppLayout from '@/layouts/app-layout';
import { calculateAge } from '@/lib/age';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';

interface Booking {
    id: number;
    ulid: string;
    corporate_id: string | null;
    service_type: string;
    client_name: string;
    client_phone: string | null;
    client_email: string | null;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    hotel_id: number | null;
    hotel_name: string | null;
    location_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
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
    client_rating?: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    caregiver_rating?: {
        id: number;
        rating: number;
        comment: string | null;
    } | null;
    total_working_hour: number;
    paid_to_caregiver_hourly: number;
    paid_to_caregiver: number;
    reimbursement: number;
    reimbursement_description: string | null;
    bonus: number;
    tip: number;
    paid_to_caregiver_total: number;
}

interface PageProps {
    booking: Booking;
}

const getBreadcrumbTitle = (clientName: string) => [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Jobs',
        href: '/jobs',
    },
    {
        title: clientName,
        href: '#',
    },
];

export default function JobDetail({ booking }: PageProps) {
    const breadcrumbs = getBreadcrumbTitle(booking.client_name);

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

    const computedBase =
        Number(booking.paid_to_caregiver_hourly) *
        Number(booking.total_working_hour);
    const computedTotal = Number(
        (
            computedBase +
            Number(booking.reimbursement) +
            Number(booking.bonus) +
            Number(booking.tip)
        ).toFixed(2),
    );
    const storedTotal = Number(booking.paid_to_caregiver_total);
    const isConsistent =
        Number(computedTotal.toFixed(1)) === Number(storedTotal.toFixed(1));

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Jobs" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href="/jobs"
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <div>
                        <h1 className="text-2xl font-semibold text-foreground">
                            Job Details
                        </h1>
                        <p className="mt-1 text-sm text-muted-foreground">
                            View job details
                        </p>
                    </div>
                    {booking.corporate_id && (
                        <span className="ml-auto inline-flex items-center gap-1 rounded-md border border-border bg-muted px-3 py-1.5 text-sm font-medium text-foreground">
                            Corporate Job #: {booking.corporate_id}
                        </span>
                    )}
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="left-panel min-w-0">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Client Information
                            </h2>
                            <div className="space-y-3">
                                <div className="flex items-center gap-2">
                                    <User className="h-4 w-4 text-muted-foreground" />
                                    <span className="text-sm text-foreground">
                                        {booking.client_name}
                                    </span>
                                </div>
                                {booking.client_phone && (
                                    <div className="flex items-center gap-2">
                                        <Phone className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            <a
                                                href={`sms:${booking.client_phone}`}
                                                className="text-blue-500 hover:underline"
                                            >
                                                {formatPhoneDisplay(
                                                    booking.client_phone,
                                                )}
                                            </a>
                                        </span>
                                    </div>
                                )}
                                {booking.client_email && (
                                    <div className="flex min-w-0 items-center gap-2">
                                        <Mail className="h-4 w-4 shrink-0 text-muted-foreground" />
                                        <span className="min-w-0 text-sm break-all text-muted-foreground">
                                            {booking.client_email}
                                        </span>
                                    </div>
                                )}

                                {booking.service_type && (
                                    <div className="flex items-center gap-2">
                                        <Heart className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.service_type}
                                        </span>
                                    </div>
                                )}

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
                                        {formatDisplayDateInPT(
                                            booking.start_datetime,
                                        ) !==
                                        formatDisplayDateInPT(
                                            booking.end_datetime,
                                        )
                                            ? `${formatDisplayDateInPT(
                                                  booking.end_datetime,
                                              )} `
                                            : ''}
                                        {formatDisplayTimeInPT(
                                            booking.end_datetime,
                                        )}
                                    </span>
                                </div>

                                {booking.hotel_name && (
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.hotel_name}
                                        </span>
                                    </div>
                                )}

                                <div className="flex min-w-0 items-start gap-2">
                                    {React.createElement(
                                        getLocationIcon(booking.location_type),
                                        {
                                            className:
                                                'mt-0.5 h-4 w-4 shrink-0 text-muted-foreground',
                                        },
                                    )}
                                    <span className="min-w-0 text-sm break-words text-muted-foreground">
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${booking.address_line1} ${booking.address_line2 || ''} ${booking.address_city} ${booking.address_state} ${booking.address_zip}`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="break-words text-blue-500 hover:underline"
                                        >
                                            {booking.address_line1 && (
                                                <span>
                                                    {booking.address_line1}
                                                    ,{' '}
                                                </span>
                                            )}{' '}
                                            {booking.address_line2 && (
                                                <span>
                                                    {booking.address_line2}
                                                    ,{' '}
                                                </span>
                                            )}
                                            {booking.address_city && (
                                                <span>
                                                    {booking.address_city},{' '}
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
                                        </a>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div className="right-panel grid min-w-0 gap-6">
                            {booking.children_notes ? (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children
                                    </h2>
                                    <p className="text-sm text-muted-foreground">
                                        {booking.children_notes}
                                    </p>
                                </div>
                            ) : booking.children ? (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Children ({booking.children?.length})
                                    </h2>
                                    <div className="space-y-4">
                                        {booking.children &&
                                            booking.children.length > 0 && (
                                                <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                                    {booking.children.map(
                                                        (child, i) => (
                                                            <li key={i}>
                                                                {child.name} (
                                                                {calculateAge(
                                                                    child.birth_year,
                                                                    child.birth_month,
                                                                )}
                                                                )
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                    </div>
                                </div>
                            ) : null}

                            {booking.pets && (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Pets ({booking.pets?.length})
                                    </h2>
                                    <div className="space-y-4">
                                        {booking.pets &&
                                            booking.pets.length > 0 && (
                                                <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                                    {booking.pets.map(
                                                        (pet, i) => (
                                                            <li key={i}>
                                                                {pet.name} (
                                                                {pet.breed} /{' '}
                                                                {pet.type})
                                                            </li>
                                                        ),
                                                    )}
                                                </ul>
                                            )}
                                    </div>
                                </div>
                            )}

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
                                                            <span
                                                                key={i}
                                                                className="inline-flex items-center rounded-full bg-yellow-100 px-2 py-0.5 text-xs font-medium text-yellow-800"
                                                            >
                                                                {consideration}
                                                            </span>
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
                        </div>

                        {storedTotal > 0 && (
                            <div>
                                <h2 className="text-md mb-2 font-semibold text-foreground">
                                    Earnings
                                </h2>
                                {isConsistent ? (
                                    <div className="space-y-3">
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Base Pay (
                                                {Number(
                                                    booking.total_working_hour,
                                                )}
                                                h @ $
                                                {Number(
                                                    booking.paid_to_caregiver_hourly,
                                                ).toFixed(2)}
                                                /hr)
                                            </span>
                                            <span className="font-medium text-foreground">
                                                ${computedBase.toFixed(2)}
                                            </span>
                                        </div>
                                        {Number(booking.bonus) > 0 && (
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Bonus
                                                </span>
                                                <span className="font-medium text-green-600">
                                                    +$
                                                    {Number(
                                                        booking.bonus,
                                                    ).toFixed(2)}
                                                </span>
                                            </div>
                                        )}
                                        {Number(booking.reimbursement) > 0 && (
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Reimbursement
                                                    {booking.reimbursement_description
                                                        ? ` (${booking.reimbursement_description})`
                                                        : ''}
                                                </span>
                                                <span className="font-medium text-green-600">
                                                    +$
                                                    {Number(
                                                        booking.reimbursement,
                                                    ).toFixed(2)}
                                                </span>
                                            </div>
                                        )}
                                        {Number(booking.tip) > 0 && (
                                            <div className="flex items-center justify-between text-sm">
                                                <span className="text-muted-foreground">
                                                    Tip
                                                </span>
                                                <span className="font-medium text-green-600">
                                                    +$
                                                    {Number(
                                                        booking.tip,
                                                    ).toFixed(2)}
                                                </span>
                                            </div>
                                        )}
                                        <hr className="border-border" />
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="font-semibold text-foreground">
                                                Total Earnings
                                            </span>
                                            <span className="font-bold text-foreground">
                                                ${computedTotal.toFixed(2)}
                                            </span>
                                        </div>
                                    </div>
                                ) : (
                                    <div className="flex items-center justify-between rounded-lg border border-border bg-card p-4">
                                        <span className="text-sm font-medium text-foreground">
                                            Total Earnings
                                        </span>
                                        <span className="text-lg font-bold text-foreground">
                                            ${storedTotal.toFixed(2)}
                                        </span>
                                    </div>
                                )}
                            </div>
                        )}

                        <div className="mt-6">
                            <h2 className="mb-4 text-lg font-semibold text-foreground">
                                Reviews & Feedback
                            </h2>
                            <div className="space-y-4">
                                <div className="rounded-lg border border-border bg-card p-4">
                                    <h3 className="mb-2 text-sm font-medium text-foreground">
                                        Review from Caregiver
                                    </h3>
                                    {booking.client_rating ? (
                                        <div className="flex flex-col gap-2">
                                            <div className="flex items-center gap-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
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
                                                ))}
                                                <span className="ml-2 text-sm text-muted-foreground">
                                                    (
                                                    {
                                                        booking.client_rating!
                                                            .rating
                                                    }
                                                    /5)
                                                </span>
                                            </div>
                                            {booking.client_rating.comment && (
                                                <p className="text-sm text-muted-foreground italic">
                                                    "
                                                    {
                                                        booking.client_rating
                                                            .comment
                                                    }
                                                    "
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground italic">
                                            No review from caregiver yet.
                                        </p>
                                    )}
                                </div>

                                <div className="rounded-lg border border-border bg-card p-4">
                                    <h3 className="mb-2 text-sm font-medium text-foreground">
                                        Feedback from Client
                                    </h3>
                                    {booking.caregiver_rating ? (
                                        <div className="flex flex-col gap-2">
                                            <div className="flex items-center gap-1">
                                                {[1, 2, 3, 4, 5].map((star) => (
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
                                                ))}
                                                <span className="ml-2 text-sm text-muted-foreground">
                                                    (
                                                    {
                                                        booking
                                                            .caregiver_rating!
                                                            .rating
                                                    }
                                                    /5)
                                                </span>
                                            </div>
                                            {booking.caregiver_rating
                                                .comment && (
                                                <p className="text-sm text-muted-foreground italic">
                                                    "
                                                    {
                                                        booking.caregiver_rating
                                                            .comment
                                                    }
                                                    "
                                                </p>
                                            )}
                                        </div>
                                    ) : (
                                        <p className="text-sm text-muted-foreground italic">
                                            No feedback from client yet.
                                        </p>
                                    )}
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
