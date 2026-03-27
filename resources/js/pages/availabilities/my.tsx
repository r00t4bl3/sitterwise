import { Head, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'My Availability',
        href: '/my-availability',
    },
];

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface Props {
    [key: string]: unknown;
    availabilities: {
        data: Availability[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
    };
    timeSlots: Array<{ value: string; label: string }>;
}

interface DayAvailability {
    date: string;
    time_slots: string[];
    specific_time: string;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

function getDateString(date: Date): string {
    return date.toISOString().split('T')[0];
}

export default function MyAvailability() {
    const { availabilities, timeSlots } = usePage<Props>().props;
    const { data, setData, post, processing, errors } = useForm({
        availabilities: [] as DayAvailability[],
    });

    const [displayDays, setDisplayDays] = useState(7);
    const [existingAvailabilities, setExistingAvailabilities] = useState<
        Record<string, Availability>
    >({});

    useEffect(() => {
        const mapped: Record<string, Availability> = {};
        availabilities.data.forEach((av) => {
            mapped[av.date] = av;
        });
        setExistingAvailabilities(mapped);
    }, [availabilities]);

    useEffect(() => {
        const days: DayAvailability[] = [];
        const startDate = new Date();
        startDate.setDate(startDate.getDate() + 1);

        for (let i = 0; i < displayDays; i++) {
            const date = new Date(startDate);
            date.setDate(date.getDate() + i);
            const dateStr = getDateString(date);
            const existing = existingAvailabilities[dateStr];

            days.push({
                date: dateStr,
                time_slots: existing?.time_slots || [],
                specific_time: existing?.specific_time || '',
            });
        }

        setData('availabilities', days);
    }, [displayDays, existingAvailabilities, setData]);

    const handleTimeSlotChange = (
        index: number,
        slot: string,
        checked: boolean,
    ) => {
        const updated = [...data.availabilities];
        if (checked) {
            if (!updated[index].time_slots.includes(slot)) {
                updated[index].time_slots.push(slot);
            }
        } else {
            updated[index].time_slots = updated[index].time_slots.filter(
                (s) => s !== slot,
            );
        }
        setData('availabilities', updated);
    };

    const handleSpecificTimeChange = (index: number, value: string) => {
        const updated = [...data.availabilities];
        updated[index].specific_time = value;
        setData('availabilities', updated);
    };

    const handleSubmit = () => {
        post('/my-availability', {
            preserveScroll: true,
        });
    };

    const loadMore = () => {
        setDisplayDays((prev) => prev + 7);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="My Availability" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            My Availability
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Set your availability for the next {displayDays}{' '}
                            days
                        </p>
                    </div>
                </div>

                <div className="rounded-[6px] border border-border bg-card p-4">
                    <div className="space-y-4">
                        {data.availabilities.map((day, index) => (
                            <div
                                key={day.date}
                                className="flex flex-col gap-3 rounded-[6px] border border-border p-3"
                            >
                                <div className="flex items-center justify-between">
                                    <span className="text-sm font-medium text-foreground">
                                        {formatDate(day.date)}
                                    </span>
                                    {existingAvailabilities[day.date] && (
                                        <span className="text-xs text-muted-foreground">
                                            Saved
                                        </span>
                                    )}
                                </div>

                                <div className="flex flex-wrap gap-4">
                                    {timeSlots.map((slot) => (
                                        <label
                                            key={slot.value}
                                            className="flex items-center gap-2"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={day.time_slots.includes(
                                                    slot.value,
                                                )}
                                                onChange={(e) =>
                                                    handleTimeSlotChange(
                                                        index,
                                                        slot.value,
                                                        e.target.checked,
                                                    )
                                                }
                                                className="h-4 w-4 rounded border-input text-primary focus:ring-ring"
                                            />
                                            <span className="text-sm text-foreground">
                                                {slot.label}
                                            </span>
                                        </label>
                                    ))}
                                </div>

                                <input
                                    type="text"
                                    placeholder="Specific time (e.g., after 9am)"
                                    value={day.specific_time}
                                    onChange={(e) =>
                                        handleSpecificTimeChange(
                                            index,
                                            e.target.value,
                                        )
                                    }
                                    className="h-9 rounded-[3px] border border-input bg-background px-3 text-sm text-foreground outline-none placeholder:text-muted-foreground focus:border-ring"
                                />
                            </div>
                        ))}
                    </div>

                    <div className="mt-4 flex justify-between">
                        <button
                            type="button"
                            onClick={loadMore}
                            className="inline-flex h-10 items-center justify-center rounded-[3px] border border-input bg-background px-4 text-sm font-medium text-foreground transition hover:bg-accent"
                        >
                            Load More Days
                        </button>

                        <button
                            type="button"
                            onClick={handleSubmit}
                            disabled={processing}
                            className="inline-flex h-10 items-center justify-center rounded-[3px] bg-primary px-4 text-sm font-medium text-primary-foreground transition hover:bg-primary/90 disabled:opacity-50"
                        >
                            {processing ? 'Saving...' : 'Save Availability'}
                        </button>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
