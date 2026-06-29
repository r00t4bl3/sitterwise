import { router } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useState, useEffect, useCallback, useMemo } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogHeader,
    DialogTitle,
    DialogDescription,
    DialogFooter,
} from '@/components/ui/dialog';
import { todayInPT, formatDisplayDateInPT } from '@/lib/datetime';

interface AvailabilityData {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
    booked_slots?: string[];
}

interface AvailabilityWeekGridProps {
    initial: AvailabilityData[];
    saveUrl: string;
    fetchMonthUrl: (year: number, month: number) => string;
}

function formatDate(year: number, month: number, day: number): string {
    return `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;
}

function getWeeks(year: number, month: number): Array<Array<{ day: number; monthOffset: number; date: string }>> {
    const firstWeekday = new Date(year, month, 1).getDay();
    const daysInCurrent = new Date(year, month + 1, 0).getDate();
    const daysInPrev = new Date(year, month, 0).getDate();

    const leading = firstWeekday;
    const totalCells = leading + daysInCurrent;
    const trailing = (7 - (totalCells % 7)) % 7;

    const cells: Array<{ day: number; monthOffset: number; date: string }> = [];

    for (let i = leading - 1; i >= 0; i--) {
        const y = month === 0 ? year - 1 : year;
        const m = month === 0 ? 11 : month - 1;
        cells.push({ day: daysInPrev - i, monthOffset: -1, date: formatDate(y, m, daysInPrev - i) });
    }

    for (let d = 1; d <= daysInCurrent; d++) {
        cells.push({ day: d, monthOffset: 0, date: formatDate(year, month, d) });
    }

    for (let d = 1; d <= trailing; d++) {
        const y = month === 11 ? year + 1 : year;
        const m = month === 11 ? 0 : month + 1;
        cells.push({ day: d, monthOffset: 1, date: formatDate(y, m, d) });
    }

    const weeks: Array<Array<{ day: number; monthOffset: number; date: string }>> = [];

    for (let i = 0; i < cells.length; i += 7) {
        weeks.push(cells.slice(i, i + 7));
    }

    return weeks;
}

function getMonthName(month: number): string {
    return new Date(2000, month, 1).toLocaleDateString('en-US', { month: 'long' });
}

function countDays(draft: Record<string, string[]>): number {
    return Object.values(draft).filter((slots) => slots.length > 0).length;
}

const SLOT_COLORS: Record<string, string> = {
    morning: '#F2C14E',
    afternoon: '#84D0D2',
    evening: '#1B3A5C',
};

const SLOT_LABELS: Record<string, string> = {
    morning: 'Morning',
    afternoon: 'Afternoon',
    evening: 'Evening',
};

export default function AvailabilityWeekGrid({ initial, saveUrl, fetchMonthUrl }: AvailabilityWeekGridProps) {
    const todayStr = todayInPT();
    const [currentDate, setCurrentDate] = useState(new Date());
    const [savedByDate, setSavedByDate] = useState<Record<string, AvailabilityData>>({});
    const [openWeekIdx, setOpenWeekIdx] = useState<number | null>(null);
    const [draft, setDraft] = useState<Record<string, string[]>>({});
    const [saving, setSaving] = useState(false);
    const [loadingMonth, setLoadingMonth] = useState(false);

    const year = currentDate.getFullYear();
    const month = currentDate.getMonth();
    const weeks = useMemo(() => getWeeks(year, month), [year, month]);

    useEffect(() => {
        const map: Record<string, AvailabilityData> = {};
        initial.forEach((av) => {
            map[av.date] = av;
        });
        setSavedByDate(map);
    }, [initial]);

    const fetchMonth = useCallback(async (y: number, m: number) => {
        setLoadingMonth(true);

        try {
            const res = await fetch(fetchMonthUrl(y, m + 1));

            if (!res.ok) {
return;
}

            const data = await res.json();
            const map: Record<string, AvailabilityData> = {};
            data.availabilities.forEach((av: AvailabilityData) => {
                map[av.date] = av;
            });
            setSavedByDate((prev) => ({ ...prev, ...map }));
        } finally {
            setLoadingMonth(false);
        }
    }, [fetchMonthUrl]);

    const prevMonth = () => {
        const d = new Date(year, month - 1, 1);
        setCurrentDate(d);
        fetchMonth(d.getFullYear(), d.getMonth());
    };

    const nextMonth = () => {
        const d = new Date(year, month + 1, 1);
        setCurrentDate(d);
        fetchMonth(d.getFullYear(), d.getMonth());
    };

    const openModal = (weekIdx: number) => {
        const d: Record<string, string[]> = {};
        weeks[weekIdx].forEach(({ date }) => {
            const existing = savedByDate[date];
            d[date] = existing ? [...existing.time_slots] : [];
        });
        setDraft(d);
        setOpenWeekIdx(weekIdx);
    };

    const closeModal = () => {
        setOpenWeekIdx(null);
        setDraft({});
    };

    const toggleSlot = (date: string, slot: string) => {
        setDraft((prev) => {
            const cur = prev[date] ? [...prev[date]] : [];
            const idx = cur.indexOf(slot);

            if (idx >= 0) {
                cur.splice(idx, 1);
            } else {
                cur.push(slot);
            }

            return { ...prev, [date]: cur };
        });
    };

    const saveWeek = () => {
        if (saving) {
return;
}

        setSaving(true);

        const days = Object.entries(draft).map(([date, time_slots]) => ({
            date,
            time_slots,
        }));

        router.post(
            saveUrl,
            { days },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setSaving(false);
                    setSavedByDate((prev) => {
                        const next = { ...prev };
                        Object.entries(draft).forEach(([date, time_slots]) => {
                            const existing = next[date];

                            if (time_slots.length > 0) {
                                next[date] = {
                                    ...(existing || { id: 0, date, specific_time: null, booked_slots: [] }),
                                    time_slots,
                                };
                            } else {
                                delete next[date];
                            }
                        });

                        return next;
                    });
                    closeModal();
                },
                onError: () => {
                    setSaving(false);
                },
            },
        );
    };

    const ordinalLabels = ['First', 'Second', 'Third', 'Fourth', 'Fifth', 'Sixth'];

    const isLocked = (dateStr: string) => dateStr <= todayStr;

    const isToday = (dateStr: string) => dateStr === todayStr;

    const openWeek = openWeekIdx !== null ? weeks[openWeekIdx] : null;

    const allChecked = openWeek
        ? openWeek.every(({ date }) => {
              if (isLocked(date)) {
return true;
}

              const bookedSlots = savedByDate[date]?.booked_slots ?? [];
              const selected = draft[date] ?? [];
              const editable = (['morning', 'afternoon', 'evening'] as const).filter((s) => !bookedSlots.includes(s));

              return editable.length > 0 && editable.every((s) => selected.includes(s));
          })
        : false;

    const toggleAll = () => {
        if (!openWeek) {
return;
}

        setDraft((prev) => {
            const next = { ...prev };
            openWeek.forEach(({ date }) => {
                if (isLocked(date)) {
return;
}

                const bookedSlots = savedByDate[date]?.booked_slots ?? [];
                const allSlots = (['morning', 'afternoon', 'evening'] as const);
                const editableSlots = allSlots.filter((s) => !bookedSlots.includes(s));

                if (allChecked) {
                    next[date] = [];
                } else {
                    next[date] = editableSlots;
                }
            });

            return next;
        });
    };

    return (
        <>
            <div className="flex items-center justify-between mb-4">
                    <button
                        type="button"
                        onClick={prevMonth}
                        disabled={loadingMonth}
                        className="flex h-8 w-8 items-center justify-center rounded-none border border-input bg-background cursor-pointer hover:bg-accent disabled:opacity-40"
                    >
                        <ChevronLeft className="h-4 w-4" />
                    </button>
                    <span className="font-semibold text-foreground text-sm">
                        {loadingMonth ? 'Loading...' : `${getMonthName(month)} ${year}`}
                    </span>
                    <button
                        type="button"
                        onClick={nextMonth}
                        disabled={loadingMonth}
                        className="flex h-8 w-8 items-center justify-center rounded-none border border-input bg-background cursor-pointer hover:bg-accent disabled:opacity-40"
                    >
                        <ChevronRight className="h-4 w-4" />
                    </button>
                </div>

                <div className="grid grid-cols-7 gap-px mb-px">
                    {['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'].map((d) => (
                        <div key={d} className="py-1 text-center text-[10px] font-semibold text-muted-foreground uppercase tracking-wider">
                            {d[0]}
                        </div>
                    ))}
                </div>

                {weeks.map((week, wi) => {
                    const allPast = week.every(({ date }) => isLocked(date));

                    return (
                        <div
                            key={wi}
                            className={`grid grid-cols-7 gap-2 mb-2 ${allPast ? '' : 'group cursor-pointer'}`}
                            onClick={() => !allPast && openModal(wi)}
                        >
                            {week.map(({ day, monthOffset, date }) => {
                                const av = savedByDate[date];
                                const slots = av?.time_slots ?? [];
                                const bookedSlots = av?.booked_slots ?? [];
                                const currentMonth = monthOffset === 0;
                                const today = isToday(date);

                                const slotDots = (['morning', 'afternoon', 'evening'] as const)
                                    .filter((s) => slots.includes(s) && !bookedSlots.includes(s));

                                const showBookedTag = bookedSlots.length > 0 && (slots.length === 0 || bookedSlots.length === slots.length);

                                return (
                                    <div
                                        key={date}
                                        className={`flex min-h-16 flex-col border p-2 transition-colors ${
                                            currentMonth
                                                ? 'border-border bg-background group-hover:bg-primary/20'
                                                : 'border-dashed border-border bg-card opacity-60'
                                        } ${today ? 'bg-blush' : ''}`}
                                    >
                                        <span className={`text-sm ${
                                            today
                                                ? 'font-bold text-foreground'
                                                : currentMonth
                                                  ? 'font-medium text-foreground'
                                                  : 'font-medium text-muted-foreground'
                                        }`}>
                                            {day}
                                        </span>
                                        <div className="mt-auto flex flex-col items-start gap-0.5">
                                            {slotDots.length > 0 && (
                                                <div className="flex items-end gap-1">
                                                    {slotDots.map((s) => (
                                                        <span
                                                            key={s}
                                                            className="h-2 w-2 rounded-full"
                                                            style={{ backgroundColor: SLOT_COLORS[s] }}
                                                        />
                                                    ))}
                                                </div>
                                            )}
                                            {showBookedTag && (
                                                <span className="text-[8px] font-semibold text-muted-foreground/60 tracking-wider">
                                                    Booked
                                                </span>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </div>
                    );
                })}

                <div className="flex flex-wrap items-center gap-3 mt-4">
                    <span className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: '#F2C14E' }} />
                        Morning
                    </span>
                    <span className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: '#84D0D2' }} />
                        Afternoon
                    </span>
                    <span className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: '#1B3A5C' }} />
                        Evening
                    </span>
                    <span className="flex items-center gap-1.5 text-[10px] text-muted-foreground">
                        <span className="h-2 w-2 rounded-full" style={{ backgroundColor: '#9aa6ad' }} />
                        Booked
                    </span>
                </div>

            <Dialog open={openWeekIdx !== null} onOpenChange={(open) => {
 if (!open) {
closeModal();
} 
}}>
                <DialogContent className="sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>
                            {openWeekIdx !== null ? ordinalLabels[openWeekIdx] : ''} week of {getMonthName(month)}
                        </DialogTitle>
                        {openWeek && (
                            <DialogDescription>
                                {openWeek.filter((c) => c.monthOffset === 0).length > 0
                                    ? `${getMonthName(month)} ${openWeek.find((c) => c.monthOffset === 0)?.day} – ${[...openWeek].filter((c) => c.monthOffset === 0).pop()?.day}, ${year}`
                                    : `${getMonthName(month)} ${year}`
                                }
                            </DialogDescription>
                        )}
                    </DialogHeader>

                    <div className="flex items-center justify-between px-1 -mt-2">
                        <p className="text-xs text-muted-foreground">
                            {openWeek && (openWeek.filter(({ date }) => !isLocked(date)).length)} editable days
                        </p>
                        <button
                            type="button"
                            onClick={toggleAll}
                            className="text-xs font-medium text-primary hover:underline cursor-pointer"
                        >
                            {allChecked ? 'Uncheck all' : 'Check all'}
                        </button>
                    </div>

                    <div className="-mx-6 px-6 overflow-y-auto max-h-[55vh] border-y border-border">
                        {openWeek && openWeek.map(({ date }) => {
                            const bookedSlots = savedByDate[date]?.booked_slots ?? [];
                            const selectedSlots = draft[date] ?? [];
                            const locked = isLocked(date);

                            return (
                                <div key={date} className={`py-3 border-b border-border last:border-b-0 ${locked ? 'opacity-50' : ''}`}>
                                    <p className="font-medium text-sm text-foreground mb-2">
                                        {formatDisplayDateInPT(date)}
                                    </p>
                                    <div className="flex flex-wrap items-center gap-2">
                                        {(['morning', 'afternoon', 'evening'] as const).map((slot) => {
                                            const isBookedSlot = bookedSlots.includes(slot);
                                            const isSelected = selectedSlots.includes(slot);

                                            return (
                                                <button
                                                    key={slot}
                                                    type="button"
                                                    disabled={locked || isBookedSlot}
                                                    onClick={() => toggleSlot(date, slot)}
                                                    className={`inline-flex items-center justify-center px-3.5 py-1.5 text-xs font-medium rounded-full border transition-all duration-100 cursor-pointer
                                                        ${isBookedSlot
                                                            ? 'bg-muted text-muted-foreground border-border cursor-not-allowed'
                                                            : isSelected
                                                                ? 'bg-primary text-primary-foreground border-primary shadow-sm'
                                                                : 'bg-background text-muted-foreground border-border hover:border-accent hover:text-foreground'
                                                        }
                                                        ${locked && !isBookedSlot ? 'cursor-not-allowed opacity-60' : ''}
                                                    `}
                                                    style={{ width: 'auto', flex: '0 0 auto' }}
                                                >
                                                    {SLOT_LABELS[slot]}
                                                </button>
                                            );
                                        })}
                                        {bookedSlots.length > 0 && (
                                            <span className="text-[10px] font-medium text-muted-foreground/60 ml-1">
                                                {bookedSlots.length} slot{bookedSlots.length > 1 ? 's' : ''} booked
                                            </span>
                                        )}
                                    </div>
                                </div>
                            );
                        })}
                    </div>

                    <DialogFooter className="sm:justify-between">
                        <p className="text-xs text-muted-foreground self-center">
                            Set <strong className="text-foreground">{countDays(draft)}</strong> day{countDays(draft) !== 1 ? 's' : ''} this week
                        </p>
                        <div className="flex gap-2">
                            <Button type="button" variant="outline" onClick={closeModal}>
                                Cancel
                            </Button>
                            <Button type="button" onClick={saveWeek} disabled={saving}>
                                {saving ? 'Saving...' : 'Save week'}
                            </Button>
                        </div>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
