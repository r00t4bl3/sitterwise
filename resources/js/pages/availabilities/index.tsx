import { Head, Link, usePage } from '@inertiajs/react';
import { InfiniteScroll } from '@inertiajs/react';
import { Sunrise, Sun, Moon } from 'lucide-react';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { SpecialtyTag } from '@/components/ui/specialty-tag';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Availability',
        href: '/availabilities',
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

interface Availability {
    id: number;
    caregiver_id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
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
    certifications: Array<{ id: number }>;
    availabilities: Availability[];
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
        has_more: boolean;
    };
    timeSlots: Array<{ value: string; label: string }>;
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

function formatDateHeader(dateString: string): { day: string; date: string } {
    const date = new Date(dateString);
    const day = date.toLocaleDateString('en-US', { weekday: 'short' });
    const dayNum = date.getDate();
    return { day: day, date: dayNum.toString() };
}

function formatTimeSlots(
    slots: string[],
    timeSlots: Array<{ value: string; label: string }>,
): string {
    return slots
        .map((slot) => {
            const found = timeSlots.find((ts) => ts.value === slot);
            return found ? found.label : slot;
        })
        .join(', ');
}

export default function AvailabilitiesIndex() {
    const { caregivers, timeSlots } = usePage<Props>().props;

    const allAvailabilities = caregivers.data.flatMap((cg) =>
        cg.availabilities.map((av) => ({ ...av, caregiver: cg })),
    );

    const uniqueDates = Array.from(
        new Set(allAvailabilities.map((av) => av.date)),
    ).sort();

    const dateHeaders = uniqueDates.map((d) => {
        const formatted = formatDateHeader(d);
        return {
            isoDate: d,
            day: formatted.day,
            date: formatted.date,
        };
    });

    const getIcon = (slot: string) => {
        switch (slot) {
            case 'morning':
                return (
                    <Sunrise className="h-3 w-3" style={{ color: '#F9C74F' }} />
                );
            case 'afternoon':
                return <Sun className="h-3 w-3" style={{ color: '#84D0D2' }} />;
            case 'evening':
                return (
                    <Moon className="h-3 w-3" style={{ color: '#1B3A5C' }} />
                );
            default:
                return null;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Availability" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Availability
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {caregivers.total} caregivers with availability
                        </p>
                    </div>
                </div>

                <InfiniteScroll
                    data="caregivers"
                    manual
                    previous={({ loading, fetch, hasMore }) =>
                        hasMore && (
                            <div className="py-4">
                                <button
                                    onClick={fetch}
                                    disabled={loading}
                                    className="flex h-10 w-full items-center justify-center gap-2 rounded-[3px] border border-input bg-background px-4 text-sm font-medium text-foreground transition hover:bg-accent disabled:opacity-50"
                                >
                                    {loading ? <Spinner /> : null}
                                    {loading ? 'Loading...' : 'Load Previous'}
                                </button>
                            </div>
                        )
                    }
                    next={({ loading, fetch, hasMore }) =>
                        hasMore && (
                            <div className="py-4">
                                <button
                                    onClick={fetch}
                                    disabled={loading}
                                    className="flex h-10 w-full items-center justify-center gap-2 rounded-[3px] border border-input bg-background px-4 text-sm font-medium text-foreground transition hover:bg-accent disabled:opacity-50"
                                >
                                    {loading ? <Spinner /> : null}
                                    {loading ? 'Loading...' : 'Load More'}
                                </button>
                            </div>
                        )
                    }
                >
                    <div className="overflow-x-auto rounded-[6px] border border-border bg-card">
                        <table className="w-full min-w-[800px]">
                            <thead>
                                <tr className="bg-foreground">
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Name
                                    </th>
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Rating
                                    </th>
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Age
                                    </th>
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Area
                                    </th>
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Expertise
                                    </th>
                                    <th className="px-3 py-3 text-left text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase">
                                        Cert
                                    </th>
                                    {dateHeaders.map((dh) => (
                                        <th
                                            key={dh.isoDate}
                                            className="px-2 py-3 text-center text-[11px] font-semibold tracking-wider whitespace-nowrap text-white uppercase"
                                        >
                                            <div className="flex flex-col">
                                                <span>{dh.day}</span>
                                                <span className="text-[9px] font-normal">
                                                    {dh.date}
                                                </span>
                                            </div>
                                        </th>
                                    ))}
                                </tr>
                            </thead>
                            <tbody>
                                {caregivers.data.length === 0 ? (
                                    <tr>
                                        <td
                                            colSpan={6 + dateHeaders.length}
                                            className="px-4 py-8 text-center text-muted-foreground"
                                        >
                                            No availability records found
                                        </td>
                                    </tr>
                                ) : (
                                    caregivers.data.map((caregiver) => {
                                        const availabilityMap =
                                            caregiver.availabilities.reduce(
                                                (acc, av) => {
                                                    acc[av.date] = av;
                                                    return acc;
                                                },
                                                {} as Record<
                                                    string,
                                                    Availability
                                                >,
                                            );

                                        return (
                                            <tr
                                                key={caregiver.id}
                                                className="border-b border-border transition hover:bg-blush"
                                            >
                                                <td className="px-3 py-3">
                                                    <div className="flex items-center gap-2">
                                                        {caregiver.user
                                                            .profile_photo_path ? (
                                                            <img
                                                                src={
                                                                    caregiver
                                                                        .user
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
                                                                    {
                                                                        caregiver
                                                                            .last_name[0]
                                                                    }
                                                                </span>
                                                            </div>
                                                        )}
                                                        <Link
                                                            href={`/availabilities/${caregiver.id}/show`}
                                                            className="text-sm font-medium whitespace-nowrap text-ring hover:text-foreground hover:underline"
                                                        >
                                                            {
                                                                caregiver.first_name
                                                            }{' '}
                                                            {
                                                                caregiver.last_name
                                                            }
                                                        </Link>
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {caregiver.rating ? (
                                                        <span className="text-sm text-foreground">
                                                            {Number(
                                                                caregiver.rating,
                                                            ).toFixed(1)}
                                                        </span>
                                                    ) : (
                                                        <span className="text-sm text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                <td className="px-3 py-3 text-sm whitespace-nowrap text-foreground">
                                                    {caregiver.date_of_birth
                                                        ? calculateAge(
                                                              caregiver.date_of_birth,
                                                          )
                                                        : '—'}
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex flex-wrap gap-1">
                                                        {caregiver.locations
                                                            .slice(0, 3)
                                                            .map((location) => (
                                                                <span
                                                                    key={
                                                                        location.id
                                                                    }
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
                                                                            {
                                                                                location.name
                                                                            }
                                                                        </span>
                                                                    )}
                                                                </span>
                                                            ))}
                                                        {caregiver.locations
                                                            .length === 0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    <div className="flex flex-wrap gap-1">
                                                        {caregiver.specialty_types
                                                            .slice(0, 2)
                                                            .map(
                                                                (specialty) => (
                                                                    <SpecialtyTag
                                                                        key={
                                                                            specialty.id
                                                                        }
                                                                        name={
                                                                            specialty.name
                                                                        }
                                                                    />
                                                                ),
                                                            )}
                                                        {caregiver
                                                            .specialty_types
                                                            .length === 0 && (
                                                            <span className="text-xs text-muted-foreground">
                                                                —
                                                            </span>
                                                        )}
                                                    </div>
                                                </td>
                                                <td className="px-3 py-3">
                                                    {caregiver.certifications.some(
                                                        (c) => c.id === 4,
                                                    ) ? (
                                                        <span className="inline-flex items-center rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">
                                                            Care.com
                                                        </span>
                                                    ) : (
                                                        <span className="text-xs text-muted-foreground">
                                                            —
                                                        </span>
                                                    )}
                                                </td>
                                                {dateHeaders.map((dh) => {
                                                    const av =
                                                        availabilityMap[
                                                            dh.isoDate
                                                        ];
                                                    return (
                                                        <td
                                                            key={dh.isoDate}
                                                            className="px-2 py-3 text-center"
                                                        >
                                                            {av &&
                                                            av.time_slots
                                                                .length > 0 ? (
                                                                <div
                                                                    className="flex justify-center gap-0.5"
                                                                    title={
                                                                        av.specific_time ||
                                                                        formatTimeSlots(
                                                                            av.time_slots,
                                                                            timeSlots,
                                                                        )
                                                                    }
                                                                >
                                                                    {av.time_slots.map(
                                                                        (
                                                                            slot,
                                                                        ) => (
                                                                            <span
                                                                                key={
                                                                                    slot
                                                                                }
                                                                                className="flex items-center"
                                                                            >
                                                                                {getIcon(
                                                                                    slot,
                                                                                )}
                                                                            </span>
                                                                        ),
                                                                    )}
                                                                </div>
                                                            ) : (
                                                                <span className="text-xs text-muted-foreground">
                                                                    —
                                                                </span>
                                                            )}
                                                        </td>
                                                    );
                                                })}
                                            </tr>
                                        );
                                    })
                                )}
                            </tbody>
                        </table>
                    </div>
                </InfiniteScroll>
            </div>
        </AppLayout>
    );
}
