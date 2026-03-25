import { Head, Link, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Search } from 'lucide-react';
import AppLayout from '@/layouts/app-layout';
import { Rating } from '@/components/ui/rating';
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
    profile_photo_path: string | null;
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
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

export default function AdminCaregiversIndex() {
    const { caregivers, statuses, filters } = usePage<Props>().props;

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
                </div>

                <div className="flex gap-4">
                    <form method="get" className="flex flex-1 gap-2">
                        <div className="relative max-w-md flex-1">
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                name="search"
                                defaultValue={filters.search || ''}
                                placeholder="Search by name..."
                                className="h-10 w-full rounded-[3px] border border-input bg-background px-10 pr-4 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
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
                        <button
                            type="submit"
                            className="h-10 rounded-none bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90"
                        >
                            Filter
                        </button>
                    </form>
                </div>

                <div className="rounded-[6px] border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
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
                                    Photo
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
                                    className="border-b border-border transition hover:bg-accent/50"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/caregivers/${caregiver.id}`}
                                            className="text-sm font-medium text-ring hover:text-foreground hover:underline"
                                        >
                                            {caregiver.first_name}{' '}
                                            {caregiver.last_name}
                                        </Link>
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
                                                .filter(
                                                    (l) => l.pivot.is_preferred,
                                                )
                                                .slice(0, 2)
                                                .map((location) => (
                                                    <span
                                                        key={location.id}
                                                        className="text-xs font-medium text-foreground"
                                                    >
                                                        {location.name}
                                                    </span>
                                                ))}
                                            {caregiver.locations.filter(
                                                (l) => l.pivot.is_preferred,
                                            ).length === 0 && (
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
                                        {caregiver.profile_photo_path ? (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
                                                <img
                                                    src={
                                                        caregiver.profile_photo_path ===
                                                        'avatar.jpg'
                                                            ? '/avatar.jpg'
                                                            : `/storage/${caregiver.profile_photo_path}`
                                                    }
                                                    alt={`${caregiver.first_name} ${caregiver.last_name}`}
                                                    className="h-10 w-10 rounded-full object-cover"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
                                                <span className="text-lg font-medium text-amber-600">
                                                    {caregiver.first_name[0]}
                                                    {caregiver.last_name[0]}
                                                </span>
                                            </div>
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
                                if (link.label === '...') return null;
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
