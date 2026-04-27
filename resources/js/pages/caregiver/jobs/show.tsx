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
} from 'lucide-react';
import React from 'react';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDate, formatDisplayTime } from '@/lib/datetime';

interface Booking {
    id: number;
    ulid: string;
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
    pets: Array<{
        name: string;
        type: string | null;
        breed: string | null;
        notes: string | null;
    }> | null;
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

    const calculateAge = (
        birthYear: number | null,
        birthMonth: number | null,
    ): string => {
        if (!birthYear && !birthMonth) {
            return 'Age unknown';
        }

        const now = new Date();
        const currentYear = now.getFullYear();
        const currentMonth = now.getMonth() + 1;

        const year = birthYear ?? currentYear;
        const month = birthMonth ?? 1;

        let years = currentYear - year;
        let months = currentMonth - month;

        if (months < 0) {
            years--;
            months += 12;
        }

        if (years < 0 || (years === 0 && months < 0)) {
            return 'Age unknown';
        }

        if (years === 0 && months === 0) {
            return 'Newborn';
        }

        if (years === 0) {
            return `${months} month${months !== 1 ? 's' : ''} old`;
        }

        if (months === 0) {
            return `${years} yr${years !== 1 ? 's' : ''} old`;
        }

        return `${years} yr${years !== 1 ? 's' : ''} ${months} mo${months !== 1 ? 's' : ''} old`;
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
                </div>

                <div className="rounded-lg border border-border bg-card p-6">
                    <div className="grid gap-6 lg:grid-cols-2">
                        <div className="left-panel">
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
                                                {booking.client_phone}
                                            </a>
                                        </span>
                                    </div>
                                )}
                                {booking.client_email && (
                                    <div className="flex items-center gap-2">
                                        <Mail className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
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
                                        {formatDisplayDate(
                                            booking.start_datetime,
                                        )}{' '}
                                        from{' '}
                                        {formatDisplayTime(
                                            booking.start_datetime,
                                        )}{' '}
                                        to{' '}
                                        {formatDisplayTime(booking.end_datetime)}
                                    </span>
                                </div>

                                {booking.hotel_id !== null && (
                                    <div className="flex items-center gap-2">
                                        <Building className="h-4 w-4 text-muted-foreground" />
                                        <span className="text-sm text-muted-foreground">
                                            {booking.hotel_name}
                                        </span>
                                    </div>
                                )}

                                <div className="flex items-center gap-2">
                                    {React.createElement(
                                        getLocationIcon(booking.location_type),
                                        {
                                            className:
                                                'mt-0.5 h-4 w-4 text-muted-foreground',
                                        },
                                    )}
                                    <span className="text-sm text-muted-foreground">
                                        <a
                                            href={`https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(`${booking.address_line1} ${booking.address_line2 || ''} ${booking.address_city} ${booking.address_state} ${booking.address_zip}`)}`}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-blue-500 hover:underline"
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

                        <div className="right-panel grid gap-6">
                            {booking.children && (
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
                            )}

                            {booking.pets && (
                                <div>
                                    <h2 className="text-md mb-2 font-semibold text-foreground">
                                        Pets ({booking.pets?.length})
                                    </h2>
                                    <div className="space-y-4">
                                        {booking.pets && booking.pets.length > 0 && (
                                            <ul className="list-inside list-disc space-y-1 text-sm text-muted-foreground">
                                                {booking.pets.map((pet, i) => (
                                                    <li key={i}>
                                                        {pet.name} ({pet.breed} / {pet.type})
                                                    </li>
                                                ))}
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
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}