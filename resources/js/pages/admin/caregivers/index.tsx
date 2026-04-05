import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState } from 'react';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Rating } from '@/components/ui/rating';
import { SpecialtyTag } from '@/components/ui/specialty-tag';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    pivot: {
        is_preferred: boolean;
    };
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    rating: number | null;
    date_of_birth: string | null;
    user: {
        profile_photo_path: string | null;
    };
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
    certifications: Array<{ id: number; certification_type_id: number }>;
}

interface Props {
    [key: string]: unknown;
    caregivers: {
        data: Caregiver[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    statuses: Status[];
    filters: {
        search: string | null;
        status: string | null;
    };
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

export default function CaregiversIndex() {
    const { caregivers, statuses, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [suggestions, setSuggestions] = useState<
        Array<{ id: number; name: string; status: Status | null }>
    >([]);
    const [isLoading, setIsLoading] = useState(false);
    const [selectedCaregiverId, setSelectedCaregiverId] = useState<
        number | null
    >(null);

    const handleCaregiverSearch = async (query: string) => {
        if (query.trim().length < 2) {
            setSuggestions([]);

            return;
        }

        setIsLoading(true);

        try {
            const params = new URLSearchParams({ q: query });
            const response = await fetch(
                `/caregivers/search-suggestions?${params}`,
            );
            const data: Caregiver[] = await response.json();
            setSuggestions(
                data.map((c) => ({
                    id: c.id,
                    name: `${c.first_name} ${c.last_name}`,
                    status: c.status,
                })),
            );
        } catch (error) {
            console.error('Search error:', error);
        } finally {
            setIsLoading(false);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregivers" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Caregivers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {caregivers.total} caregivers total
                        </p>
                    </div>
                    <Link href="/caregivers/create" className="btn-primary">
                        Add Caregiver
                    </Link>
                </div>

                <div className="flex gap-4">
                    <form method="get" className="flex flex-1 gap-2">
                        <div className="relative max-w-md flex-1">
                            <Autocomplete
                                value={selectedCaregiverId}
                                onChange={setSelectedCaregiverId}
                                suggestions={suggestions}
                                onSearch={handleCaregiverSearch}
                                placeholder="Search by name..."
                                loading={isLoading}
                                displayValue={searchQuery}
                                onItemClick={(item) => {
                                    window.location.href = `/caregivers/${item.id}`;
                                }}
                                renderItem={(item) => {
                                    const caregiver = item as {
                                        id: number;
                                        name: string;
                                        status: Status | null;
                                    };

                                    return (
                                        <div className="flex items-center justify-between">
                                            <span className="text-sm text-foreground">
                                                {item.name}
                                            </span>
                                            {caregiver.status && (
                                                <StatusBadge
                                                    status={caregiver.status}
                                                />
                                            )}
                                        </div>
                                    );
                                }}
                            />
                        </div>
                        <select
                            name="status"
                            defaultValue={filters.status || ''}
                            className="h-10 rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                        >
                            <option value="">All Statuses</option>
                            {statuses.map((status) => (
                                <option key={status.id} value={status.id}>
                                    {status.name}
                                </option>
                            ))}
                        </select>
                        <button type="submit" className="btn-primary">
                            Filter
                        </button>
                    </form>
                </div>

                <div className="rounded-[6px] border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    ID
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Rating
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Age
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Area
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Expertise
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Certified
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {caregivers.data.map((caregiver) => (
                                <tr
                                    key={caregiver.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {caregiver.id}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            {caregiver.user
                                                .profile_photo_path ? (
                                                <img
                                                    src={
                                                        caregiver.user
                                                            .profile_photo_path ===
                                                        'avatar.jpg'
                                                            ? '/avatar.jpg'
                                                            : `/storage/${caregiver.user.profile_photo_path}`
                                                    }
                                                    alt=""
                                                    className="h-8 w-8 rounded-full object-cover"
                                                />
                                            ) : (
                                                <div className="flex h-8 w-8 items-center justify-center rounded-full bg-amber-100">
                                                    <span className="text-xs font-medium text-amber-600">
                                                        {
                                                            caregiver
                                                                .first_name[0]
                                                        }
                                                        {caregiver.last_name[0]}
                                                    </span>
                                                </div>
                                            )}
                                            <Link
                                                href={`/caregivers/${caregiver.id}`}
                                                className="text-sm font-medium text-ring hover:text-foreground hover:underline"
                                            >
                                                {caregiver.first_name}{' '}
                                                {caregiver.last_name}
                                            </Link>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {caregiver.rating ? (
                                            <Rating
                                                value={caregiver.rating}
                                                showScore={false}
                                                size="sm"
                                            />
                                        ) : (
                                            <span className="text-sm text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {caregiver.date_of_birth
                                            ? calculateAge(
                                                  caregiver.date_of_birth,
                                              )
                                            : '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {caregiver.locations
                                                .slice(0, 3)
                                                .map((location) => (
                                                    <span
                                                        key={location.id}
                                                        className="flex items-center text-xs"
                                                    >
                                                        {location.svg_icon ? (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <span className="flex h-4 w-4 cursor-pointer items-center justify-center">
                                                                        <svg
                                                                            viewBox="0 0 24 24"
                                                                            className="h-4 w-4"
                                                                            style={{
                                                                                fill: location
                                                                                    .pivot
                                                                                    .is_preferred
                                                                                    ? '#3a9a9c'
                                                                                    : '#c2e5e5',
                                                                            }}
                                                                        >
                                                                            <path
                                                                                d={
                                                                                    location.svg_icon.match(
                                                                                        /d="([^"]+)"/,
                                                                                    )?.[1] ||
                                                                                    ''
                                                                                }
                                                                            />
                                                                        </svg>
                                                                    </span>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {
                                                                        location.name
                                                                    }
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        ) : (
                                                            <span
                                                                className={
                                                                    location
                                                                        .pivot
                                                                        .is_preferred
                                                                        ? 'font-medium text-foreground'
                                                                        : 'text-muted-foreground'
                                                                }
                                                            >
                                                                {location.name}
                                                            </span>
                                                        )}
                                                    </span>
                                                ))}
                                            {caregiver.locations.length ===
                                                0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {caregiver.specialty_types.map(
                                                (specialty) => (
                                                    <SpecialtyTag
                                                        key={specialty.id}
                                                        name={specialty.name}
                                                    />
                                                ),
                                            )}
                                            {caregiver.specialty_types
                                                .length === 0 && (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        {caregiver.certifications.some(
                                            (c) => c.id === 4,
                                        ) ? (
                                            <span className="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-xs font-medium text-blue-800">
                                                Care.com
                                            </span>
                                        ) : (
                                            <span className="text-xs text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={`/caregivers/${caregiver.id}`}
                                            className="text-sm font-medium text-ring hover:text-foreground"
                                        >
                                            View
                                        </Link>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {caregivers.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {caregivers.current_page} of{' '}
                            {caregivers.last_page}
                        </p>
                        <div className="flex gap-1">
                            {caregivers.links.map((link, index) => {
                                if (link.label === '...') {
                                    return null;
                                }

                                const isPrev =
                                    link.label.includes('Previous') ||
                                    link.label.includes('&laquo;');
                                const isNext =
                                    link.label.includes('Next') ||
                                    link.label.includes('&raquo;');

                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`flex h-8 w-8 items-center justify-center rounded text-sm ${
                                            link.active
                                                ? 'bg-foreground text-white'
                                                : 'border border-border text-muted-foreground hover:bg-accent'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    >
                                        {isPrev ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : isNext ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            link.label
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
