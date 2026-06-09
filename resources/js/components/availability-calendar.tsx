import { ChevronLeft, ChevronRight, Sunrise, Sun, Moon } from 'lucide-react';
import { useState, useMemo } from 'react';

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
    booked_slots?: string[];
}

interface AvailabilityCalendarProps {
    availabilities: Availability[];
    onDateClick: (date: string) => void;
    timeSlots: Array<{ value: string; label: string }>;
}

function getDaysInMonth(
    year: number,
    month: number,
): Array<{ day: number; monthOffset: number }> {
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInCurrent = new Date(year, month + 1, 0).getDate();
    const daysInPrev = new Date(year, month, 0).getDate();

    const leading = firstWeekday;
    const trailing = (7 - ((firstWeekday + daysInCurrent) % 7)) % 7;

    const cells: Array<{ day: number; monthOffset: number }> = [];

    for (let i = leading - 1; i >= 0; i--) {
        cells.push({ day: daysInPrev - i, monthOffset: -1 });
    }

    for (let d = 1; d <= daysInCurrent; d++) {
        cells.push({ day: d, monthOffset: 0 });
    }

    for (let d = 1; d <= trailing; d++) {
        cells.push({ day: d, monthOffset: 1 });
    }

    return cells;
}

function getMonthName(month: number): string {
    const date = new Date(2000, month, 1);

    return date.toLocaleDateString('en-US', { month: 'long' });
}

function getIcon(slot: string) {
    switch (slot) {
        case 'morning':
            return <Sunrise className="h-5 w-5" style={{ color: '#F9C74F' }} />;
        case 'afternoon':
            return <Sun className="h-5 w-5" style={{ color: '#84D0D2' }} />;
        case 'evening':
            return <Moon className="h-5 w-5" style={{ color: '#1B3A5C' }} />;
        default:
            return null;
    }
}

export function AvailabilityCalendar({
    availabilities,
    onDateClick,
    timeSlots,
}: AvailabilityCalendarProps) {
    const [currentDate, setCurrentDate] = useState(new Date());

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const days = getDaysInMonth(year, month);
    const today = new Date();
    today.setHours(0, 0, 0, 0);

    const getSortedTimeSlots = (slots: string[]) => {
        const canonicalOrder = timeSlots.map((slot) => slot.value);

        return [...slots].sort(
            (a, b) => canonicalOrder.indexOf(a) - canonicalOrder.indexOf(b),
        );
    };

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

    return (
        <div className="border border-border bg-card p-4">
            <div className="mb-4 flex items-center justify-between">
                <button
                    onClick={prevMonth}
                    className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                >
                    <ChevronLeft className="h-4 w-4" />
                </button>
                <h2 className="text-lg font-semibold text-foreground">
                    {getMonthName(month)} {year}
                </h2>
                <button
                    onClick={nextMonth}
                    className="flex h-8 w-8 items-center justify-center rounded-[3px] border border-input hover:bg-accent"
                >
                    <ChevronRight className="h-4 w-4" />
                </button>
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

                {days.map(({ day, monthOffset }) => {
                    let cellMonth = month + monthOffset;
                    let cellYear = year;

                    if (cellMonth < 0) {
                        cellMonth = 12;
                        cellYear--;
                    } else if (cellMonth > 11) {
                        cellMonth = 0;
                        cellYear++;
                    }

                    const dateStr = `${cellYear}-${String(cellMonth + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const dateObj = new Date(cellYear, cellMonth, day);
                    const dateOnly = new Date(
                        dateObj.getFullYear(),
                        dateObj.getMonth(),
                        dateObj.getDate(),
                    );
                    const isToday = dateOnly.getTime() === today.getTime();
                    const isPast = dateOnly < today;
                    const availability = availabilityMap[dateStr];
                    const hasAvailability =
                        availability && availability.time_slots.length > 0;

                    const bookedSlots = availability?.booked_slots ?? [];
                    const availableSlots = hasAvailability
                        ? availability.time_slots.filter(
                              (s) => !bookedSlots.includes(s),
                          )
                        : [];
                    const isFullyBooked =
                        hasAvailability && availableSlots.length === 0;

                    const isCurrentMonth = monthOffset === 0;

                    return (
                        <div
                            key={`${monthOffset}-${day}`}
                            className={`flex h-24 flex-col gap-1 border p-2 ${
                                isCurrentMonth
                                    ? 'border-border bg-background'
                                    : 'border-dashed border-gray-300 bg-white'
                            } ${isToday ? 'bg-blush' : ''} ${!isPast && !isToday && !isFullyBooked ? 'group relative cursor-pointer' : ''} ${isFullyBooked ? 'opacity-60' : ''}`}
                        >
                            <span
                                className={`text-sm ${
                                    isToday
                                        ? 'font-bold text-foreground'
                                        : isCurrentMonth
                                          ? 'font-medium text-foreground'
                                          : 'text-gray-300'
                                }`}
                            >
                                {day}
                            </span>
                            {!isPast &&
                                !isToday &&
                                (hasAvailability ? (
                                    <div className="flex flex-1 flex-col items-center justify-center gap-1">
                                        <div className="flex items-center gap-0.5">
                                            {getSortedTimeSlots(
                                                availability.time_slots,
                                            ).map((slot) => {
                                                const isBooked =
                                                    bookedSlots.includes(slot);

                                                return (
                                                    <span
                                                        key={slot}
                                                        className={`flex items-center ${isBooked ? 'opacity-30 grayscale' : ''}`}
                                                    >
                                                        {getIcon(slot)}
                                                    </span>
                                                );
                                            })}
                                        </div>
                                        {availability.specific_time && (
                                            <span className="truncate text-xs text-muted-foreground">
                                                {availability.specific_time}
                                            </span>
                                        )}
                                    </div>
                                ) : null)}
                            {!isPast && !isToday && !isFullyBooked && (
                                <button
                                    onClick={() => onDateClick(dateStr)}
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
    );
}
