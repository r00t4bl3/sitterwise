import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Rating } from '@/components/ui/rating';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { SpecialtyTag } from '@/components/ui/specialty-tag';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { calculateAgeFromDate } from '@/lib/age';
import { formatPhoneDisplay } from '@/lib/phone';
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
    value: string;
    label: string;
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
        profile_photo_url: string | null;
    };
    status: Status;
    phone: string | null;
    specialty_types: SpecialtyType[];
    locations: Location[];
    certifications: Array<{ id: number; certification_type_id: number }>;
    blocked_clients_count?: number;
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
        sort: string;
        direction: 'asc' | 'desc';
        blocked?: boolean;
    };
}

export default function CaregiversIndex() {
    const { caregivers, statuses, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status && filters.status !== 'all' ? filters.status : null,
    );
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const sortFieldRef = useRef(filters.sort || 'id');
    const sortDirRef = useRef<'asc' | 'desc'>(
        (filters.direction as 'asc' | 'desc') || 'asc',
    );
    const blockedOnlyRef = useRef(filters.blocked === true);

    const sortField = filters.sort || 'id';
    const sortDir = (filters.direction as 'asc' | 'desc') || 'asc';
    const blockedOnly = filters.blocked === true;

    // The blocked-caregiver column/filter is opt-in and hidden by default. Keep
    // the toggle sticky per admin; a deep link with ?blocked forces it visible.
    const [showBlocked, setShowBlocked] = useState(
        () =>
            (typeof window !== 'undefined' &&
                localStorage.getItem('caregivers_show_blocked') === '1') ||
            filters.blocked === true,
    );

    const applyFilters = (search: string, status: string | null) => {
        const params: Record<string, string> = {};

        if (search.trim()) {
            params.search = search.trim();
        }

        if (status) {
            params.status = status;
        }

        if (blockedOnlyRef.current) {
            params.blocked = '1';
        }

        if (sortFieldRef.current !== 'id' || sortDirRef.current !== 'asc') {
            params.sort = sortFieldRef.current;
            params.direction = sortDirRef.current;
        }

        router.get('/caregivers', params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleBlockedOnlyChange = (next: boolean) => {
        blockedOnlyRef.current = next;
        applyFilters(searchQuery, statusFilter);
    };

    const handleToggleShowBlocked = () => {
        const next = !showBlocked;
        setShowBlocked(next);

        if (typeof window !== 'undefined') {
            localStorage.setItem('caregivers_show_blocked', next ? '1' : '0');
        }

        // Hiding the column also drops the blocked-only filter so we never leave
        // the list filtered by something the admin can no longer see.
        if (!next && blockedOnlyRef.current) {
            blockedOnlyRef.current = false;
            applyFilters(searchQuery, statusFilter);
        }
    };

    const handleSort = (field: string) => {
        const newDir =
            field === sortField && sortDir === 'asc' ? 'desc' : 'asc';
        sortFieldRef.current = field;
        sortDirRef.current = newDir;
        applyFilters(searchQuery, statusFilter);
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters(value, statusFilter);
        }, 300);
    };

    const handleStatusChange = (status: string | null) => {
        setStatusFilter(status);
        applyFilters(searchQuery, status);
    };

    useEffect(() => {
        return () => clearTimeout(debounceTimer.current);
    }, []);

    useEffect(() => {
        sortFieldRef.current = filters.sort || 'id';
        sortDirRef.current = (filters.direction as 'asc' | 'desc') || 'asc';
        blockedOnlyRef.current = filters.blocked === true;
    }, [filters.sort, filters.direction, filters.blocked]);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregivers" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Caregivers
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {caregivers.total} caregivers
                            {statusFilter && (
                                <span className="ml-1">
                                    (
                                    {statuses.find(
                                        (s) => s.value === statusFilter,
                                    )?.label || statusFilter}
                                    )
                                </span>
                            )}
                            {searchQuery && (
                                <span className="ml-1">
                                    (search: "{searchQuery}")
                                </span>
                            )}
                        </p>
                    </div>
                    <Link href="/caregivers/create" className="btn-primary">
                        Add Caregiver
                    </Link>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by name..."
                            value={searchQuery}
                            onChange={(e) => handleSearchChange(e.target.value)}
                            className="h-8"
                        />
                        {searchQuery && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleSearchChange('')}
                                className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                type="button"
                            >
                                ×
                            </Button>
                        )}
                    </div>

                    <Select
                        value={statusFilter ?? 'all'}
                        onValueChange={(value) =>
                            handleStatusChange(value === 'all' ? null : value)
                        }
                    >
                        <SelectTrigger size="sm" className="w-[200px]">
                            <SelectValue placeholder="All statuses" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All statuses</SelectItem>
                            {statuses.map((status) => (
                                <SelectItem
                                    key={status.value}
                                    value={status.value}
                                >
                                    {status.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>

                    <div className="ml-auto flex items-center gap-2">
                        {showBlocked && (
                            <Button
                                variant={blockedOnly ? 'default' : 'outline'}
                                size="sm"
                                onClick={() =>
                                    handleBlockedOnlyChange(!blockedOnly)
                                }
                            >
                                Blocked only
                            </Button>
                        )}
                        <Button
                            variant={showBlocked ? 'default' : 'outline'}
                            size="sm"
                            onClick={handleToggleShowBlocked}
                        >
                            {showBlocked
                                ? 'Hide blocked info'
                                : 'Show blocked info'}
                        </Button>
                    </div>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('id')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        ID
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'id' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'id' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('last_name')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Name
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'last_name' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'last_name' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('rating')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Rating
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'rating' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'rating' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() =>
                                            handleSort('date_of_birth')
                                        }
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Age
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField ===
                                                        'date_of_birth' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField ===
                                                        'date_of_birth' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                {showBlocked && (
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                        <button
                                            onClick={() =>
                                                handleSort(
                                                    'blocked_clients_count',
                                                )
                                            }
                                            className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                        >
                                            Blocked by
                                            <span className="text-[9px] leading-none">
                                                <span
                                                    className={
                                                        sortField ===
                                                            'blocked_clients_count' &&
                                                        sortDir === 'asc'
                                                            ? ''
                                                            : 'opacity-30'
                                                    }
                                                >
                                                    ▲
                                                </span>
                                                <span
                                                    className={
                                                        sortField ===
                                                            'blocked_clients_count' &&
                                                        sortDir === 'desc'
                                                            ? ''
                                                            : 'opacity-30'
                                                    }
                                                >
                                                    ▼
                                                </span>
                                            </span>
                                        </button>
                                    </th>
                                )}
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Area
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Phone
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
                                    onClick={() =>
                                        router.visit(
                                            `/caregivers/${caregiver.id}`,
                                        )
                                    }
                                    className="cursor-pointer border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {caregiver.id}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
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
                                                size="sm"
                                            />
                                            <Link
                                                href={`/caregivers/${caregiver.id}`}
                                                onClick={(e) =>
                                                    e.stopPropagation()
                                                }
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
                                            ? calculateAgeFromDate(
                                                  caregiver.date_of_birth,
                                              )
                                            : '—'}
                                    </td>
                                    {showBlocked && (
                                        <td className="px-4 py-3 text-sm text-foreground">
                                            {(caregiver.blocked_clients_count ??
                                                0) > 0 ? (
                                                <span className="inline-flex min-w-6 items-center justify-center rounded-full bg-destructive/10 px-2 py-0.5 text-xs font-semibold text-destructive">
                                                    {
                                                        caregiver.blocked_clients_count
                                                    }
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                    )}
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
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {caregiver.phone ? (
                                            <a
                                                href={`tel:${caregiver.phone}`}
                                                className="text-primary hover:underline"
                                            >
                                                {formatPhoneDisplay(
                                                    caregiver.phone,
                                                )}
                                            </a>
                                        ) : (
                                            '—'
                                        )}
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
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <span
                                            onClick={(e) => e.stopPropagation()}
                                        >
                                            <Button asChild className="h-8">
                                                <Link
                                                    href={`/caregivers/${caregiver.id}`}
                                                >
                                                    View
                                                </Link>
                                            </Button>
                                        </span>
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
                                                ? 'bg-table-header text-white'
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
