import { Head, useForm } from '@inertiajs/react';
import { ChevronLeft, ChevronRight, Sunrise, Sun, Moon } from 'lucide-react';
import { useState, useMemo } from 'react';
import AppLayout from '@/layouts/app-layout';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface CaregiverDashboardProps {
    caregiver: {
        id: number;
        first_name: string;
        last_name: string;
        rating: number | null;
        status: string;
        availabilities: Availability[];
    };
}

const timeSlots = [
    { value: 'morning', label: 'Morning (6am - 12pm)' },
    { value: 'afternoon', label: 'Afternoon (12pm - 5pm)' },
    { value: 'evening', label: 'Evening (5pm - 10pm)' },
];

function getDaysInMonth(year: number, month: number): (number | null)[] {
    const firstDay = new Date(year, month, 1).getDay();
    const daysInMonth = new Date(year, month + 1, 0).getDate();

    const days: (number | null)[] = [];

    for (let i = 0; i < firstDay; i++) {
        days.push(null);
    }

    for (let i = 1; i <= daysInMonth; i++) {
        days.push(i);
    }

    return days;
}

function getMonthName(month: number): string {
    const date = new Date(2000, month, 1);
    return date.toLocaleDateString('en-US', { month: 'long' });
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export default function CaregiverDashboard({
    caregiver,
}: CaregiverDashboardProps) {
    const [currentDate, setCurrentDate] = useState(() => {
        const tomorrow = new Date();
        tomorrow.setDate(tomorrow.getDate() + 1);
        return tomorrow;
    });
    const [availabilities, setAvailabilities] = useState(
        caregiver.availabilities,
    );
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const {
        data,
        setData,
        put,
        delete: deleteForm,
    } = useForm({
        date: '',
        time_slots: [] as string[],
        specific_time: '',
    });

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const days = getDaysInMonth(year, month);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const availabilityMap = useMemo(() => {
        return availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;
                return acc;
            },
            {} as Record<string, Availability>,
        );
    }, [availabilities]);

    const prevMonth = () => {
        setCurrentDate(new Date(year, month - 1, 1));
    };

    const nextMonth = () => {
        setCurrentDate(new Date(year, month + 1, 1));
    };

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

    const openSheet = (date: string) => {
        const existing = availabilityMap[date];
        setSelectedDate(date);
        setData({
            date: date,
            time_slots: existing?.time_slots || [],
            specific_time: existing?.specific_time || '',
        });
        setIsSheetOpen(true);
    };

    const handleSave = () => {
        setProcessing(true);
        put('/my-availability/availability', {
            method: 'put',
            onSuccess: () => {
                const updated = [...availabilities];
                const existingIndex = updated.findIndex(
                    (a) => a.date === data.date,
                );

                const newAvailability = {
                    id:
                        existingIndex >= 0
                            ? updated[existingIndex].id
                            : Date.now(),
                    date: data.date,
                    time_slots: data.time_slots,
                    specific_time: data.specific_time || null,
                };

                if (existingIndex >= 0) {
                    updated[existingIndex] = newAvailability;
                } else {
                    updated.push(newAvailability);
                }

                setAvailabilities(updated);
                setIsSheetOpen(false);
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const handleDelete = () => {
        if (!selectedDate) return;

        setProcessing(true);
        const existing = availabilityMap[selectedDate];
        if (!existing || existing.id === undefined) {
            setAvailabilities((prev) =>
                prev.filter((a) => a.date !== selectedDate),
            );
            setIsSheetOpen(false);
            setProcessing(false);
            return;
        }

        deleteForm(`/my-availability/availability/${existing.id}`, {
            preserveScroll: true,
            onSuccess: () => {
                setAvailabilities((prev) =>
                    prev.filter((a) => a.date !== selectedDate),
                );
                setIsSheetOpen(false);
                setProcessing(false);
            },
            onError: () => {
                setProcessing(false);
            },
        });
    };

    const handleTimeSlotChange = (slot: string, checked: boolean) => {
        if (checked) {
            if (!data.time_slots.includes(slot)) {
                setData('time_slots', [...data.time_slots, slot]);
            }
        } else {
            setData(
                'time_slots',
                data.time_slots.filter((s) => s !== slot),
            );
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregiver Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-x-auto rounded-xl p-4">
                <div className="mb-4">
                    <h1 className="text-2xl font-bold text-foreground">
                        Welcome back, {caregiver.first_name}!
                    </h1>
                    <p className="text-muted-foreground">
                        Manage your availability and appointments
                    </p>
                </div>

                <div className="grid auto-rows-min gap-4 md:grid-cols-3">
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">Rating</p>
                        <p className="text-2xl font-bold text-foreground">
                            {caregiver.rating
                                ? `${caregiver.rating} ★`
                                : 'No rating'}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">Status</p>
                        <p className="text-2xl font-bold text-foreground">
                            {caregiver.status}
                        </p>
                    </div>
                    <div className="rounded-xl border border-border bg-card p-6">
                        <p className="text-sm text-muted-foreground">
                            Upcoming Bookings
                        </p>
                        <p className="text-2xl font-bold text-foreground">0</p>
                    </div>
                </div>

                <div className="flex-1 overflow-hidden rounded-xl border border-border bg-card p-4">
                    <div className="mb-4 flex items-center justify-between">
                        <h2 className="text-lg font-semibold text-foreground">
                            My Availability
                        </h2>
                        <div className="flex items-center gap-2">
                            <button
                                onClick={prevMonth}
                                className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                            >
                                <ChevronLeft className="h-4 w-4" />
                            </button>
                            <span className="text-sm font-medium text-foreground">
                                {getMonthName(month)} {year}
                            </span>
                            <button
                                onClick={nextMonth}
                                className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                            >
                                <ChevronRight className="h-4 w-4" />
                            </button>
                        </div>
                    </div>

                    <div className="grid grid-cols-7 gap-1">
                        {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map(
                            (day) => (
                                <div
                                    key={day}
                                    className="py-2 text-center text-xs font-semibold text-muted-foreground uppercase"
                                >
                                    {day}
                                </div>
                            ),
                        )}

                        {days.map((day, index) => {
                            if (day === null) {
                                return (
                                    <div
                                        key={`empty-${index}`}
                                        className="h-24"
                                    />
                                );
                            }

                            const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                            const dateObj = new Date(year, month, day);
                            const dateOnly = new Date(
                                dateObj.getFullYear(),
                                dateObj.getMonth(),
                                dateObj.getDate(),
                            );
                            const isToday =
                                dateOnly.getTime() === today.getTime();
                            const isPast = dateOnly < today;
                            const availability = availabilityMap[dateStr];
                            const hasAvailability =
                                availability &&
                                availability.time_slots.length > 0;

                            return (
                                <div
                                    key={day}
                                    className={`flex h-24 flex-col items-center justify-center gap-1 border border-border p-1 ${isToday ? 'bg-blush' : 'bg-background'} ${!isPast && !isToday ? 'group relative cursor-pointer' : ''}`}
                                >
                                    <span
                                        className={`text-sm ${isPast ? 'text-muted-foreground' : 'text-foreground'}`}
                                    >
                                        {day}
                                    </span>
                                    {!isPast &&
                                        !isToday &&
                                        (hasAvailability ? (
                                            <>
                                                <div className="flex items-center gap-0.5">
                                                    {availability.time_slots.map(
                                                        (slot) => (
                                                            <span
                                                                key={slot}
                                                                className="flex items-center"
                                                            >
                                                                {getIcon(slot)}
                                                            </span>
                                                        ),
                                                    )}
                                                </div>
                                                {availability.specific_time && (
                                                    <span className="truncate text-[8px] text-muted-foreground">
                                                        {
                                                            availability.specific_time
                                                        }
                                                    </span>
                                                )}
                                            </>
                                        ) : null)}
                                    {!isPast && !isToday && (
                                        <button
                                            onClick={() => openSheet(dateStr)}
                                            className={`absolute inset-0 flex items-center justify-center rounded-[3px] text-xs font-medium transition ${
                                                hasAvailability
                                                    ? 'bg-primary/80 text-primary-foreground opacity-0 group-hover:opacity-100 hover:bg-primary'
                                                    : 'bg-muted/80 text-muted-foreground opacity-0 group-hover:opacity-100 hover:bg-muted'
                                            }`}
                                        >
                                            {hasAvailability ? 'Edit' : 'Add'}
                                        </button>
                                    )}
                                </div>
                            );
                        })}
                    </div>
                </div>
            </div>

            <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                <SheetContent side="right" className="w-full sm:max-w-md">
                    <SheetHeader>
                        <SheetTitle>
                            {selectedDate
                                ? formatDate(selectedDate)
                                : 'Availability'}
                        </SheetTitle>
                    </SheetHeader>

                    <div className="mt-4 space-y-4 px-4">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Time Slots
                            </label>
                            <div className="mt-2 space-y-2">
                                {timeSlots.map((slot) => (
                                    <label
                                        key={slot.value}
                                        className="flex items-center gap-2"
                                    >
                                        <input
                                            type="checkbox"
                                            checked={data.time_slots.includes(
                                                slot.value,
                                            )}
                                            onChange={(e) =>
                                                handleTimeSlotChange(
                                                    slot.value,
                                                    e.target.checked,
                                                )
                                            }
                                            className="h-4 w-4 rounded border-input text-primary"
                                        />
                                        <span className="text-sm text-foreground">
                                            {slot.label}
                                        </span>
                                    </label>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Specific Time
                            </label>
                            <input
                                type="text"
                                value={data.specific_time}
                                onChange={(e) =>
                                    setData('specific_time', e.target.value)
                                }
                                placeholder="e.g., after 9am"
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm text-foreground"
                            />
                        </div>

                        <div className="flex gap-2 pt-4">
                            <button
                                onClick={handleSave}
                                disabled={
                                    processing || data.time_slots.length === 0
                                }
                                className="btn-primary flex-1"
                            >
                                {processing && <Spinner className="size-4" />}
                                {processing ? 'Saving...' : 'Save'}
                            </button>
                            {availabilityMap[selectedDate || ''] && (
                                <button
                                    onClick={handleDelete}
                                    disabled={processing}
                                    className="btn-secondary w-1/4"
                                >
                                    Delete
                                </button>
                            )}
                        </div>

                        <button
                            onClick={() => setIsSheetOpen(false)}
                            className="btn-secondary mt-2 w-full"
                        >
                            Cancel
                        </button>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
