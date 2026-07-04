import {
    Baby,
    Briefcase,
    CalendarCheck,
    ChevronDown,
    ChevronUp,
    History,
    MapPin,
    MapPinCheckInside,
} from 'lucide-react';
import { useEffect, useMemo, useState } from 'react';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import {
    autoSetEndDateTime,
    formatUtcStringFromPt,
    shiftEndPreservingDuration,
    todayInPT,
    validateMinimumDuration,
} from '@/lib/datetime';

interface DateEntry {
    id: string;
    start_datetime: string;
    end_datetime: string;
}

function generateDateId(): string {
    return `date-${Date.now()}-${Math.random().toString(36).slice(2, 7)}`;
}

function findDateOverlaps(dates: DateEntry[]): Record<string, string[]> {
    const overlaps: Record<string, string[]> = {};

    for (let i = 0; i < dates.length; i++) {
        for (let j = i + 1; j < dates.length; j++) {
            const a = new Date(dates[i].start_datetime).getTime();
            const bEnd = new Date(dates[i].end_datetime).getTime();
            const c = new Date(dates[j].start_datetime).getTime();
            const dEnd = new Date(dates[j].end_datetime).getTime();

            if (a < dEnd && c < bEnd) {
                if (!overlaps[dates[i].id]) {
                    overlaps[dates[i].id] = [];
                }

                if (!overlaps[dates[j].id]) {
                    overlaps[dates[j].id] = [];
                }

                overlaps[dates[i].id].push(`Date ${j + 1}`);
                overlaps[dates[j].id].push(`Date ${i + 1}`);
            }
        }
    }

    return overlaps;
}

interface BookingDetailsSectionProps {
    sheetMode: 'create' | 'edit' | 'duplicate';
    form: any;
    editingBooking: { id: number } | null;
    service_types: Array<{ value: string; label: string }>;
    booking_statuses: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    caregiverSuggestions: Array<{
        id: number;
        name: string;
        matchIcons?: string[];
        [key: string]: unknown;
    }>;
    selectedCaregiverName: string;
    handleCaregiverSearch: (query: string) => void;
    handleSubmit: (notifyAfter?: boolean) => void;
    handleDelete: () => void;
    setIsSheetOpen: (open: boolean) => void;
}

export function BookingDetailsSection({
    form,
    editingBooking,
    service_types,
    booking_statuses,
    payment_statuses,
    caregiverSuggestions,
    selectedCaregiverName,
    handleCaregiverSearch,
    handleSubmit,
    handleDelete,
    setIsSheetOpen,
    sheetMode,
}: BookingDetailsSectionProps) {
    const [isOpen, setIsOpen] = useState(true);
    const startDatetime = form.data.start_datetime;
    const endDatetime = form.data.end_datetime;
    const datetimeError = validateMinimumDuration(startDatetime, endDatetime);

    // Build a Date whose local components represent PT wall-clock tomorrow at
    // 9:00 AM. formatUtcStringFromPt reads those local components as PT, so the
    // default matches the DateTimePicker/backend UTC convention regardless of
    // the admin's browser timezone.
    const tomorrow = useMemo(() => {
        const [y, m, d] = todayInPT().split('-').map(Number);

        return new Date(y, m - 1, d + 1, 9, 0, 0, 0);
    }, []);

    const defaultStartStr = useMemo(
        () => formatUtcStringFromPt(tomorrow),
        [tomorrow],
    );
    const defaultEndStr = useMemo(
        () =>
            formatUtcStringFromPt(
                new Date(
                    tomorrow.getFullYear(),
                    tomorrow.getMonth(),
                    tomorrow.getDate(),
                    13,
                    0,
                    0,
                    0,
                ),
            ),
        [tomorrow],
    );

    const [dates, setDates] = useState<DateEntry[]>(() => {
        if (sheetMode !== 'create') {
            return [];
        }

        if (form.data.start_datetime && form.data.end_datetime) {
            return [
                {
                    id: generateDateId(),
                    start_datetime: form.data.start_datetime,
                    end_datetime: form.data.end_datetime,
                },
            ];
        }

        return [
            {
                id: generateDateId(),
                start_datetime: defaultStartStr,
                end_datetime: defaultEndStr,
            },
        ];
    });

    const syncDatesToForm = (allDates: DateEntry[]) => {
        if (allDates.length > 0) {
            form.setData('start_datetime', allDates[0].start_datetime);
            form.setData('end_datetime', allDates[0].end_datetime);
            form.setData(
                'dates',
                allDates.map((d) => ({
                    start_datetime: d.start_datetime,
                    end_datetime: d.end_datetime,
                })),
            );
        }
    };

    const handleAddDate = () => {
        // Advance by whole days on the PT wall-clock (keeps 9:00 AM - 1:00 PM PT
        // for each added date regardless of the admin's browser timezone).
        const nextDate = new Date(
            tomorrow.getFullYear(),
            tomorrow.getMonth(),
            tomorrow.getDate() + dates.length,
            9,
            0,
            0,
            0,
        );
        const endDate = new Date(
            nextDate.getFullYear(),
            nextDate.getMonth(),
            nextDate.getDate(),
            13,
            0,
            0,
            0,
        );
        const newEntry: DateEntry = {
            id: generateDateId(),
            start_datetime: formatUtcStringFromPt(nextDate),
            end_datetime: formatUtcStringFromPt(endDate),
        };
        const updated = [...dates, newEntry];
        setDates(updated);
        syncDatesToForm(updated);
    };

    const handleRemoveDate = (id: string) => {
        const updated = dates.filter((d) => d.id !== id);
        setDates(updated);
        syncDatesToForm(updated);
    };

    const handleUpdateDate = (
        id: string,
        field: 'start_datetime' | 'end_datetime',
        value: string,
    ) => {
        const updated = dates.map((d) => {
            if (d.id !== id) {
                return d;
            }

            const next = { ...d, [field]: value };

            if (field === 'start_datetime') {
                const newStart = new Date(value);
                const currentEnd = new Date(d.end_datetime);
                const minEnd = new Date(
                    newStart.getTime() + 4 * 60 * 60 * 1000,
                );

                if (isNaN(currentEnd.getTime()) || currentEnd <= minEnd) {
                    next.end_datetime = autoSetEndDateTime(value);
                }
            }

            return next;
        });
        setDates(updated);
        syncDatesToForm(updated);
    };

    const dateOverlaps = useMemo(() => findDateOverlaps(dates), [dates]);

    // Reset dates when sheet opens with fresh form data
    useEffect(() => {
        if (sheetMode !== 'create') {
            return;
        }

        const formStart = form.data.start_datetime;
        const currentStart = dates[0]?.start_datetime;

        if (formStart && currentStart !== formStart) {
            const initial = [
                {
                    id: generateDateId(),
                    start_datetime: formStart,
                    end_datetime:
                        form.data.end_datetime || autoSetEndDateTime(formStart),
                },
            ];
            setDates(initial);
            syncDatesToForm(initial);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [sheetMode, form.data.start_datetime]);

    useEffect(() => {
        const serviceType = form.data.service_type;
        const requiresPayment =
            serviceType === 'babysitter' ||
            serviceType === 'petsitter' ||
            serviceType === 'companion_care';

        if (form.data.requires_payment !== requiresPayment) {
            form.setData('requires_payment', requiresPayment);
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.service_type]);

    return (
        <details
            className="mb-4 rounded-[3px] border border-border bg-card"
            open={isOpen}
            onToggle={(e) => setIsOpen(e.currentTarget.open)}
        >
            <summary className="flex cursor-pointer items-center justify-between bg-muted px-4 py-3 font-medium text-foreground">
                <span>Booking Details</span>
                {isOpen ? (
                    <ChevronUp className="h-4 w-4" />
                ) : (
                    <ChevronDown className="h-4 w-4" />
                )}
            </summary>
            <div className="space-y-4 p-4">
                <div className="grid gap-2">
                    <Label
                        htmlFor="service_type"
                        className={
                            form.errors.service_type ? 'text-destructive' : ''
                        }
                    >
                        Service Type <span className="text-red-500">*</span>
                    </Label>
                    <Select
                        value={form.data.service_type}
                        onValueChange={(value) =>
                            form.setData('service_type', value)
                        }
                    >
                        <SelectTrigger id="service_type">
                            <SelectValue placeholder="Select service type" />
                        </SelectTrigger>
                        <SelectContent>
                            {service_types.map((type) => (
                                <SelectItem key={type.value} value={type.value}>
                                    {type.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    {form.errors.service_type && (
                        <p className="text-sm text-destructive">
                            {form.errors.service_type}
                        </p>
                    )}
                </div>

                {form.data.service_type === 'corporate_invoiced' && (
                    <div className="grid gap-2">
                        <Label htmlFor="corporate_id">Corporate ID</Label>
                        <Input
                            id="corporate_id"
                            value={form.data.corporate_id}
                            onChange={(e) =>
                                form.setData('corporate_id', e.target.value)
                            }
                        />
                    </div>
                )}

                {form.data.service_type === 'group_childcare_invoiced' && (
                    <div className="grid gap-2">
                        <Label htmlFor="children_notes">Children</Label>
                        <Textarea
                            id="children_notes"
                            value={form.data.children_notes}
                            onChange={(e) =>
                                form.setData('children_notes', e.target.value)
                            }
                            placeholder="How many children and age range?"
                            rows={2}
                        />
                        <p className="text-xs text-muted-foreground">
                            This will ignore the client's stored children data
                            for this booking.
                        </p>
                    </div>
                )}

                {sheetMode === 'create' ? (
                    <div className="space-y-3">
                        {dates.map((dateEntry, index) => (
                            <div
                                key={dateEntry.id}
                                className="rounded-[4px] border border-border bg-card p-[14px]"
                            >
                                <div className="mb-[10px] flex items-center justify-between">
                                    <span className="text-xs font-semibold tracking-[0.5px] text-foreground uppercase">
                                        Date {index + 1}
                                    </span>
                                    {index > 0 && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                handleRemoveDate(dateEntry.id)
                                            }
                                            className="cursor-pointer border-none bg-none p-0 text-xs text-primary"
                                        >
                                            × Remove
                                        </button>
                                    )}
                                </div>
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div className="grid gap-2">
                                        <Label
                                            className={
                                                form.errors[
                                                    'dates.' +
                                                        index +
                                                        '.start_datetime'
                                                ]
                                                    ? 'text-destructive'
                                                    : ''
                                            }
                                        >
                                            Start Date/Time{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <DateTimePicker
                                            value={dateEntry.start_datetime}
                                            onChange={(datetime) => {
                                                if (datetime) {
                                                    handleUpdateDate(
                                                        dateEntry.id,
                                                        'start_datetime',
                                                        datetime,
                                                    );
                                                }
                                            }}
                                        />
                                    </div>
                                    <div className="grid gap-2">
                                        <Label
                                            className={
                                                form.errors[
                                                    'dates.' +
                                                        index +
                                                        '.end_datetime'
                                                ]
                                                    ? 'text-destructive'
                                                    : ''
                                            }
                                        >
                                            End Date/Time{' '}
                                            <span className="text-red-500">
                                                *
                                            </span>
                                        </Label>
                                        <DateTimePicker
                                            value={dateEntry.end_datetime}
                                            startTime={dateEntry.start_datetime}
                                            onChange={(datetime) => {
                                                if (datetime) {
                                                    handleUpdateDate(
                                                        dateEntry.id,
                                                        'end_datetime',
                                                        datetime,
                                                    );
                                                }
                                            }}
                                        />
                                    </div>
                                </div>
                                {dateOverlaps[dateEntry.id]?.length > 0 && (
                                    <p className="mt-2 text-xs text-amber-700">
                                        ⚠ This overlaps with{' '}
                                        {dateOverlaps[dateEntry.id].join(', ')}.
                                    </p>
                                )}
                            </div>
                        ))}

                        <button
                            type="button"
                            onClick={handleAddDate}
                            className="w-full cursor-pointer rounded-[4px] border border-dashed border-logo-teal bg-card py-3 text-sm font-medium text-foreground transition-[background] duration-150 hover:bg-teal-bg"
                        >
                            + Add another date
                        </button>
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label
                                className={
                                    form.errors.start_datetime
                                        ? 'text-destructive'
                                        : ''
                                }
                            >
                                Start DateTime{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <DateTimePicker
                                value={startDatetime}
                                onChange={(datetime) => {
                                    if (!datetime) {
                                        form.setData(
                                            'start_datetime',
                                            datetime,
                                        );
                                        return;
                                    }

                                    const newEnd = shiftEndPreservingDuration(
                                        startDatetime,
                                        endDatetime,
                                        datetime,
                                    );
                                    form.setData('start_datetime', datetime);
                                    form.setData('end_datetime', newEnd);
                                    form.setData('dates', [
                                        {
                                            start_datetime: datetime,
                                            end_datetime: newEnd,
                                        },
                                    ]);
                                }}
                            />
                            {form.errors.start_datetime && (
                                <p className="text-sm text-destructive">
                                    {form.errors.start_datetime}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                className={
                                    form.errors.end_datetime
                                        ? 'text-destructive'
                                        : ''
                                }
                            >
                                End DateTime{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <DateTimePicker
                                value={endDatetime}
                                startTime={startDatetime}
                                onChange={(datetime) => {
                                    form.setData('end_datetime', datetime);
                                    form.setData('dates', [
                                        {
                                            start_datetime: startDatetime,
                                            end_datetime: datetime,
                                        },
                                    ]);
                                }}
                            />
                            {form.errors.end_datetime && (
                                <p className="text-sm text-destructive">
                                    {form.errors.end_datetime}
                                </p>
                            )}
                        </div>
                        {datetimeError && (
                            <div className="col-span-2 text-sm text-destructive">
                                {datetimeError}
                            </div>
                        )}
                    </div>
                )}

                <div className="grid gap-2">
                    <Label
                        className={
                            form.errors.caregiver_id ? 'text-destructive' : ''
                        }
                    >
                        Caregiver
                    </Label>
                    <Autocomplete
                        value={form.data.caregiver_id}
                        onChange={(id) => form.setData('caregiver_id', id)}
                        suggestions={caregiverSuggestions}
                        onSearch={handleCaregiverSearch}
                        placeholder="Search caregiver..."
                        displayValue={selectedCaregiverName}
                        renderItem={(item) => {
                            const matchIcons = (item as any).matchIcons as
                                | string[]
                                | undefined;

                            const ICON_MAP: Record<string, React.ElementType> =
                                {
                                    previous_work: History,
                                    available: CalendarCheck,
                                    specialty: Baby,
                                    location_preferred: MapPinCheckInside,
                                    location_willing: MapPin,
                                    recent_work: Briefcase,
                                };

                            const ICON_TOOLTIPS: Record<string, string> = {
                                previous_work:
                                    'Previously worked with this family',
                                available: 'Available for booking dates',
                                specialty: 'Specializes in this age group',
                                location_preferred: 'Based in booking area',
                                location_willing:
                                    'Willing to travel to booking area',
                                recent_work: 'Actively working recently',
                            };

                            return (
                                <div className="flex items-center justify-between">
                                    <span>{item.name}</span>
                                    <div className="ml-2 flex items-center gap-1">
                                        {matchIcons &&
                                            matchIcons.length > 0 &&
                                            matchIcons.map(
                                                (iconKey: string) => {
                                                    const IconComponent =
                                                        ICON_MAP[iconKey];
                                                    const tooltip =
                                                        ICON_TOOLTIPS[iconKey];

                                                    if (!IconComponent) {
                                                        return null;
                                                    }

                                                    return (
                                                        <Tooltip key={iconKey}>
                                                            <TooltipTrigger
                                                                asChild
                                                            >
                                                                <span className="flex cursor-default items-center">
                                                                    <IconComponent className="h-3.5 w-3.5 text-muted-foreground" />
                                                                </span>
                                                            </TooltipTrigger>
                                                            <TooltipContent>
                                                                {tooltip}
                                                            </TooltipContent>
                                                        </Tooltip>
                                                    );
                                                },
                                            )}
                                    </div>
                                </div>
                            );
                        }}
                    />
                    {form.errors.caregiver_id && (
                        <p className="text-sm text-destructive">
                            {form.errors.caregiver_id}
                        </p>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="caregiver_notes">Caregiver Notes</Label>
                    <Textarea
                        id="caregiver_notes"
                        value={form.data.caregiver_notes}
                        onChange={(e) =>
                            form.setData('caregiver_notes', e.target.value)
                        }
                        rows={2}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="notes_to_sitterwise">
                        Notes to Sitterwise
                    </Label>
                    <Textarea
                        id="notes_to_sitterwise"
                        value={form.data.notes_to_sitterwise}
                        onChange={(e) =>
                            form.setData('notes_to_sitterwise', e.target.value)
                        }
                        rows={2}
                    />
                </div>

                <div className="grid gap-2">
                    <Label htmlFor="admin_notes">Admin Notes</Label>
                    <Textarea
                        id="admin_notes"
                        value={form.data.admin_notes}
                        onChange={(e) =>
                            form.setData('admin_notes', e.target.value)
                        }
                        rows={2}
                    />
                </div>

                <input
                    type="hidden"
                    name="requires_payment"
                    value={form.data.requires_payment ? '1' : '0'}
                />

                {!editingBooking && (
                    <>
                        <input type="hidden" name="status" value="received" />
                        <input
                            type="hidden"
                            name="payment_status"
                            value="pending"
                        />
                    </>
                )}

                {editingBooking && (
                    <div className="grid grid-cols-2 gap-4">
                        <div className="grid gap-2">
                            <Label
                                htmlFor="status"
                                className={
                                    form.errors.status ? 'text-destructive' : ''
                                }
                            >
                                Status <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={form.data.status}
                                onValueChange={(value) =>
                                    form.setData('status', value)
                                }
                            >
                                <SelectTrigger id="status">
                                    <SelectValue placeholder="Select status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {booking_statuses
                                        .filter((s) => s.value !== 'cancelled')
                                        .map((status) => (
                                            <SelectItem
                                                key={status.value}
                                                value={status.value}
                                            >
                                                {status.label}
                                            </SelectItem>
                                        ))}
                                </SelectContent>
                            </Select>
                            {form.errors.status && (
                                <p className="text-sm text-destructive">
                                    {form.errors.status}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label
                                htmlFor="payment_status"
                                className={
                                    form.errors.payment_status
                                        ? 'text-destructive'
                                        : ''
                                }
                            >
                                Payment Status{' '}
                                <span className="text-red-500">*</span>
                            </Label>
                            <Select
                                value={form.data.payment_status}
                                onValueChange={(value) =>
                                    form.setData('payment_status', value)
                                }
                            >
                                <SelectTrigger id="payment_status">
                                    <SelectValue placeholder="Select payment status" />
                                </SelectTrigger>
                                <SelectContent>
                                    {payment_statuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.payment_status && (
                                <p className="text-sm text-destructive">
                                    {form.errors.payment_status}
                                </p>
                            )}
                        </div>
                    </div>
                )}

                <div className="flex gap-2 pt-4">
                    <Button
                        onClick={() => handleSubmit()}
                        disabled={form.processing}
                        className="flex-1"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        {form.processing
                            ? 'Saving...'
                            : sheetMode === 'edit'
                              ? 'Update'
                              : sheetMode === 'duplicate'
                                ? 'Duplicate'
                                : 'Create'}
                    </Button>
                    {sheetMode === 'edit' && (
                        <Button
                            onClick={handleDelete}
                            disabled={form.processing}
                            variant="destructive"
                            className="w-1/4"
                        >
                            Delete
                        </Button>
                    )}
                </div>

                {sheetMode !== 'edit' && (
                    <Button
                        onClick={() => handleSubmit(true)}
                        disabled={form.processing}
                        variant="secondary"
                        className="mt-2 w-full"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        Create &amp; Notify Caregivers
                    </Button>
                )}

                <Button
                    onClick={() => setIsSheetOpen(false)}
                    variant="outline"
                    className="mt-2 w-full"
                >
                    Cancel
                </Button>
            </div>
        </details>
    );
}
