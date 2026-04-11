import { Head, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft } from 'lucide-react';
import { useState, useMemo } from 'react';
import { AvailabilityCalendar } from '@/components/availability-calendar';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

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
}

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    user: {
        profile_photo_path: string | null;
    };
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    availabilities: Availability[];
    timeSlots: Array<{ value: string; label: string }>;
}

function formatDate(dateString: string): string {
    const date = new Date(dateString);

    return date.toLocaleDateString('en-US', {
        weekday: 'short',
        month: 'short',
        day: 'numeric',
    });
}

export default function ManageAvailability() {
    const {
        caregiver,
        availabilities: initialAvailabilities,
        timeSlots,
    } = usePage<Props>().props;

    const [availabilities, setAvailabilities] = useState(initialAvailabilities);
    const [selectedDate, setSelectedDate] = useState<string | null>(null);
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [processing, setProcessing] = useState(false);

    const {
        data,
        setData,
        patch,
        delete: deleteForm,
    } = useForm({
        date: '',
        time_slots: [] as string[],
        specific_time: '',
    });

    const availabilityMap = useMemo(() => {
        return availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;

                return acc;
            },
            {} as Record<string, Availability>,
        );
    }, [availabilities]);

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
        patch(`/availabilities/${caregiver.id}`, {
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
        if (!selectedDate) {
            return;
        }

        setProcessing(true);
        const existing = availabilityMap[selectedDate];

        if (!existing) {
            setProcessing(false);

            return;
        }

        deleteForm(`/caregivers/${caregiver.id}/availability/${existing.id}`, {
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

    const breadcrumbs: BreadcrumbItem[] = [
        {
            title: 'Dashboard',
            href: '/dashboard',
        },
        {
            title: 'Availability',
            href: '/availabilities',
        },
        {
            title: `${caregiver.first_name} ${caregiver.last_name}`,
            href: `/caregivers/${caregiver.id}`,
        },
    ];

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head
                title={`${caregiver.first_name} ${caregiver.last_name} - Availability`}
            />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <a
                            href="/availabilities"
                            className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                        >
                            <ChevronLeft className="h-5 w-5" />
                        </a>
                        <div>
                            <h1 className="font-serif text-2xl font-bold text-foreground">
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Manage Availability
                            </p>
                        </div>
                    </div>
                </div>

                <AvailabilityCalendar
                    availabilities={availabilities}
                    onDateClick={openSheet}
                />

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent side="right" className="w-full sm:max-w-md">
                        <SheetHeader>
                            <SheetTitle>
                                {selectedDate
                                    ? formatDate(selectedDate)
                                    : 'Availability'}
                            </SheetTitle>
                            <SheetDescription>
                                Manage availability for{' '}
                                {selectedDate
                                    ? formatDate(selectedDate)
                                    : 'the selected date'}
                            </SheetDescription>
                        </SheetHeader>

                        <div className="space-y-4 px-4">
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
                                <Button
                                    onClick={handleSave}
                                    disabled={
                                        processing ||
                                        data.time_slots.length === 0
                                    }
                                    className="flex-1"
                                >
                                    {processing && (
                                        <Spinner className="size-4" />
                                    )}
                                    {processing ? 'Saving...' : 'Save'}
                                </Button>
                                {availabilityMap[selectedDate || ''] && (
                                    <Button
                                        onClick={handleDelete}
                                        disabled={processing}
                                        variant="secondary"
                                        className="w-1/4"
                                    >
                                        Delete
                                    </Button>
                                )}
                            </div>

                            <Button
                                onClick={() => setIsSheetOpen(false)}
                                variant="outline"
                                className="mt-2 w-full"
                            >
                                Cancel
                            </Button>
                        </div>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
