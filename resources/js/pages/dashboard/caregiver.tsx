import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { AvailabilityCalendar } from '@/components/availability-calendar';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import AppLayout from '@/layouts/app-layout';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { ToasterMessage } from '@/components/toaster-message';

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
    const [availabilities, setAvailabilities] = useState(
        caregiver.availabilities,
    );
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

    const openSheet = (date: string) => {
        const availabilityMap = availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;

                return acc;
            },
            {} as Record<string, Availability>,
        );
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
        const map = availabilities.reduce(
            (acc, av) => {
                acc[av.date] = av;

                return acc;
            },
            {} as Record<string, Availability>,
        );
        const existing = map[selectedDate];

        if (!existing || existing.id === undefined) {
            setAvailabilities((prev) =>
                prev.filter((a) => a.date !== selectedDate),
            );
            setIsSheetOpen(false);
            setProcessing(false);

            return;
        }

        deleteForm(`/availabilities/${existing.id}`, {
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
            <ToasterMessage />
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
                    </div>

                    <AvailabilityCalendar
                        availabilities={availabilities}
                        onDateClick={openSheet}
                    />
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
                        <SheetDescription>
                            Set availability for the selected date.
                        </SheetDescription>
                    </SheetHeader>

                    <div className="space-y-4 px-4">
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Time Slots
                            </label>
                            <div className="mt-2 space-y-2">
                                {timeSlots.map((slot) => (
                                    <div
                                        key={slot.value}
                                        className="flex items-center gap-2"
                                    >
                                        <Checkbox
                                            id={`slot-${slot.value}`}
                                            checked={data.time_slots.includes(
                                                slot.value,
                                            )}
                                            onCheckedChange={(checked) =>
                                                handleTimeSlotChange(
                                                    slot.value,
                                                    checked === true,
                                                )
                                            }
                                        />
                                        <Label
                                            htmlFor={`slot-${slot.value}`}
                                            className="text-sm font-normal"
                                        >
                                            {slot.label}
                                        </Label>
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Specific Time
                            </label>
                            <Input
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
                                    processing || data.time_slots.length === 0
                                }
                                className="btn-primary flex-1"
                            >
                                {processing && <Spinner className="size-4" />}
                                {processing ? 'Saving...' : 'Save'}
                            </Button>
                            {(() => {
                                const map = availabilities.reduce(
                                    (acc, av) => {
                                        acc[av.date] = av;

                                        return acc;
                                    },
                                    {} as Record<string, Availability>,
                                );

                                return (
                                    map[selectedDate || ''] && (
                                        <button
                                            onClick={handleDelete}
                                            disabled={processing}
                                            className="btn-secondary w-1/4"
                                        >
                                            Delete
                                        </button>
                                    )
                                );
                            })()}
                        </div>

                        <Button
                            onClick={() => setIsSheetOpen(false)}
                            className="btn-secondary mt-2 w-full"
                        >
                            Cancel
                        </Button>
                    </div>
                </SheetContent>
            </Sheet>
        </AppLayout>
    );
}
