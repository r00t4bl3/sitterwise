import { ChevronLeft, ChevronRight, Sunrise, Sun, Moon } from 'lucide-react';
import { useState, useMemo } from 'react';

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface AvailabilityCalendarProps {
    availabilities: Availability[];
    onDateClick: (date: string) => void;
}

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

function getIcon(slot: string) {
    switch (slot) {
        case 'morning':
            return <Sunrise className="h-3 w-3" style={{ color: '#F9C74F' }} />;
        case 'afternoon':
            return <Sun className="h-3 w-3" style={{ color: '#84D0D2' }} />;
        case 'evening':
            return <Moon className="h-3 w-3" style={{ color: '#1B3A5C' }} />;
        default:
            return null;
    }
}

export function AvailabilityCalendar({
    availabilities,
    onDateClick,
}: AvailabilityCalendarProps) {
    const [currentDate, setCurrentDate] = useState(new Date());

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

    return (
        <div className="rounded-[6px] border border-border bg-card p-4">
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

                {days.map((day, index) => {
                    if (day === null) {
                        return <div key={`empty-${index}`} className="h-24" />;
                    }

                    const dateStr = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
                    const dateObj = new Date(year, month, day);
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
                                                {availability.specific_time}
                                            </span>
                                        )}
                                    </>
                                ) : null)}
                            {!isPast && !isToday && (
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
