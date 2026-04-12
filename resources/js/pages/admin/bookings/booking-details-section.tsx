import { ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { DateTimePicker } from '@/components/ui/datetime-picker';
import { Spinner } from '@/components/ui/spinner';

interface BookingDetailsSectionProps {
    form: any;
    editingBooking: { id: number } | null;
    service_types: Array<{ value: string; label: string }>;
    special_consideration_options: Array<{ value: string; label: string }>;
    booking_statuses: Array<{ value: string; label: string }>;
    payment_statuses: Array<{ value: string; label: string }>;
    caregiverSuggestions: Array<{
        id: number;
        name: string;
        [key: string]: unknown;
    }>;
    selectedCaregiverName: string;
    handleCaregiverSearch: (query: string) => void;
    handleSpecialConsiderationChange: (
        option: string,
        checked: boolean,
    ) => void;
    handleSubmit: () => void;
    handleDelete: () => void;
    setIsSheetOpen: (open: boolean) => void;
}

function validateDatetime(start: string, end: string): string | null {
    if (!start && !end) {
        return null;
    }

    if (!start) {
        return 'Start date/time is required.';
    }

    if (!end) {
        return 'End date/time is required.';
    }

    const startDate = new Date(start);
    const endDate = new Date(end);
    const now = new Date();

    if (isNaN(startDate.getTime())) {
        return 'Invalid start date/time.';
    }

    if (isNaN(endDate.getTime())) {
        return 'Invalid end date/time.';
    }

    if (startDate < now) {
        return 'Start date/time must be in the future.';
    }

    if (endDate <= startDate) {
        return 'End date/time must be after start date/time.';
    }

    const diffMs = endDate.getTime() - startDate.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffHours < 4) {
        return 'Booking must be at least 4 hours long.';
    }

    return null;
}

export function BookingDetailsSection({
    form,
    editingBooking,
    service_types,
    special_consideration_options,
    booking_statuses,
    payment_statuses,
    caregiverSuggestions,
    selectedCaregiverName,
    handleCaregiverSearch,
    handleSpecialConsiderationChange,
    handleSubmit,
    handleDelete,
    setIsSheetOpen,
}: BookingDetailsSectionProps) {
    const [isOpen, setIsOpen] = useState(false);
    const [datetimeError, setDatetimeError] = useState<string | null>(null);
    const [startDatetime, setStartDatetime] = useState(
        form.data.start_datetime,
    );
    const [endDatetime, setEndDatetime] = useState(form.data.end_datetime);

    useEffect(() => {
        setStartDatetime(form.data.start_datetime);
        setEndDatetime(form.data.end_datetime);
    }, [form.data.start_datetime, form.data.end_datetime]);

    useEffect(() => {
        const error = validateDatetime(startDatetime, endDatetime);
        setDatetimeError(error);
    }, [startDatetime, endDatetime]);

    useEffect(() => {
        const serviceType = form.data.service_type;
        const requiresPayment =
            serviceType === 'babysitter' ||
            serviceType === 'petsitter' ||
            serviceType === 'companion_care';
        form.setData('requires_payment', requiresPayment);
    }, [form.data.service_type]);

    return (
        <details
            className="rounded-[3px] border border-border bg-card"
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
                <div>
                    <label className="text-sm font-medium text-foreground">
                        Service Type <span className="text-red-500">*</span>
                    </label>
                    <select
                        value={form.data.service_type}
                        onChange={(e) =>
                            form.setData('service_type', e.target.value)
                        }
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                    >
                        {service_types.map((type) => (
                            <option key={type.value} value={type.value}>
                                {type.label}
                            </option>
                        ))}
                    </select>
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Start DateTime{' '}
                            <span className="text-red-500">*</span>
                        </label>
                        <div className="mt-1">
                            <DateTimePicker
                                value={startDatetime}
                                onChange={(datetime) => {
                                    setStartDatetime(datetime);
                                    form.setData('start_datetime', datetime);
                                }}
                            />
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            End DateTime <span className="text-red-500">*</span>
                        </label>
                        <div className="mt-1">
                            <DateTimePicker
                                value={endDatetime}
                                onChange={(datetime) => {
                                    setEndDatetime(datetime);
                                    form.setData('end_datetime', datetime);
                                }}
                            />
                        </div>
                    </div>
                    {datetimeError && (
                        <div className="col-span-2 text-sm text-destructive">
                            {datetimeError}
                        </div>
                    )}
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Caregiver
                    </label>
                    <div className="mt-1">
                        <Autocomplete
                            value={form.data.caregiver_id}
                            onChange={(id) => form.setData('caregiver_id', id)}
                            suggestions={caregiverSuggestions}
                            onSearch={handleCaregiverSearch}
                            placeholder="Search caregiver..."
                            displayValue={selectedCaregiverName}
                            renderItem={(item) => {
                                const badge = (item as any).matchBadge;
                                if (!badge) return item.name;

                                const colorClasses: Record<string, string> = {
                                    green: 'bg-green-100 text-green-800',
                                    yellow: 'bg-yellow-100 text-yellow-800',
                                    orange: 'bg-orange-100 text-orange-800',
                                    blue: 'bg-blue-100 text-blue-800',
                                };

                                return (
                                    <div className="flex items-center justify-between">
                                        <span>{item.name}</span>
                                        <span
                                            className={`ml-2 rounded-full px-2 py-0.5 text-xs font-medium ${
                                                colorClasses[badge.color] || 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {badge.label}
                                        </span>
                                    </div>
                                );
                            }}
                        />
                    </div>
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Special Considerations
                    </label>
                    <div className="mt-2 grid grid-cols-2 gap-4">
                        {special_consideration_options.map((option) => (
                            <label
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <input
                                    type="checkbox"
                                    checked={form.data.special_considerations.includes(
                                        option.value,
                                    )}
                                    onChange={(e) =>
                                        handleSpecialConsiderationChange(
                                            option.value,
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-input"
                                />
                                <span className="text-sm text-foreground">
                                    {option.label}
                                </span>
                            </label>
                        ))}
                    </div>
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Caregiver Notes
                    </label>
                    <textarea
                        value={form.data.caregiver_notes}
                        onChange={(e) =>
                            form.setData('caregiver_notes', e.target.value)
                        }
                        rows={2}
                        className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Notes to Sitterwise
                    </label>
                    <textarea
                        value={form.data.notes_to_sitterwise}
                        onChange={(e) =>
                            form.setData('notes_to_sitterwise', e.target.value)
                        }
                        rows={2}
                        className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Admin Notes
                    </label>
                    <textarea
                        value={form.data.admin_notes}
                        onChange={(e) =>
                            form.setData('admin_notes', e.target.value)
                        }
                        rows={2}
                        className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm"
                    />
                </div>

                {form.data.service_type === 'corporate_invoiced' && (
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            Corporate ID
                        </label>
                        <input
                            type="text"
                            value={form.data.corporate_id}
                            onChange={(e) =>
                                form.setData('corporate_id', e.target.value)
                            }
                            className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        />
                    </div>
                )}

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
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Status <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.data.status}
                                onChange={(e) =>
                                    form.setData('status', e.target.value)
                                }
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            >
                                {booking_statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                        <div>
                            <label className="text-sm font-medium text-foreground">
                                Payment Status{' '}
                                <span className="text-red-500">*</span>
                            </label>
                            <select
                                value={form.data.payment_status}
                                onChange={(e) =>
                                    form.setData(
                                        'payment_status',
                                        e.target.value,
                                    )
                                }
                                className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                            >
                                {payment_statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                        </div>
                    </div>
                )}

                <div className="flex gap-2 pt-4">
                    <Button
                        onClick={handleSubmit}
                        disabled={form.processing}
                        className="flex-1"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        {form.processing
                            ? 'Saving...'
                            : editingBooking
                              ? 'Update'
                              : 'Create'}
                    </Button>
                    {editingBooking && (
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
