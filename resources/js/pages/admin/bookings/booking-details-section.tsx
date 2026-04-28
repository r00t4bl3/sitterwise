import { ChevronDown, ChevronUp } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Autocomplete } from '@/components/ui/autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
    const [isOpen, setIsOpen] = useState(true);
    const startDatetime = form.data.start_datetime;
    const endDatetime = form.data.end_datetime;
    const datetimeError = validateDatetime(startDatetime, endDatetime);

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
                    <Label htmlFor="service_type">
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
                </div>

                <div className="grid grid-cols-2 gap-4">
                    <div className="grid gap-2">
                        <Label>
                            Start DateTime{' '}
                            <span className="text-red-500">*</span>
                        </Label>
                        <DateTimePicker
                            value={startDatetime}
                            onChange={(datetime) => {
                                form.setData('start_datetime', datetime);
                            }}
                        />
                    </div>
                    <div className="grid gap-2">
                        <Label>
                            End DateTime <span className="text-red-500">*</span>
                        </Label>
                        <DateTimePicker
                            value={endDatetime}
                            onChange={(datetime) => {
                                form.setData('end_datetime', datetime);
                            }}
                        />
                    </div>
                    {datetimeError && (
                        <div className="col-span-2 text-sm text-destructive">
                            {datetimeError}
                        </div>
                    )}
                </div>

                <div className="grid gap-2">
                    <Label>Caregiver</Label>
                    <Autocomplete
                        value={form.data.caregiver_id}
                        onChange={(id) => form.setData('caregiver_id', id)}
                        suggestions={caregiverSuggestions}
                        onSearch={handleCaregiverSearch}
                        placeholder="Search caregiver..."
                        displayValue={selectedCaregiverName}
                        renderItem={(item) => {
                            const badge = (item as any).matchBadge;

                            if (!badge) {
                                return item.name;
                            }

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
                                            colorClasses[badge.color] ||
                                            'bg-gray-100 text-gray-800'
                                        }`}
                                    >
                                        {badge.label}
                                    </span>
                                </div>
                            );
                        }}
                    />
                </div>

                <div className="grid gap-2">
                    <Label>Special Considerations</Label>
                    <div className="mt-2 grid grid-cols-2 gap-4">
                        {special_consideration_options.map((option) => (
                            <div
                                key={option.value}
                                className="flex items-center gap-2"
                            >
                                <Checkbox
                                    id={`sc-${option.value}`}
                                    checked={form.data.special_considerations.includes(
                                        option.value,
                                    )}
                                    onCheckedChange={(checked) =>
                                        handleSpecialConsiderationChange(
                                            option.value,
                                            checked === true,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor={`sc-${option.value}`}
                                    className="text-sm font-normal"
                                >
                                    {option.label}
                                </Label>
                            </div>
                        ))}
                    </div>
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
                            <Label htmlFor="status">
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
                                    {booking_statuses.map((status) => (
                                        <SelectItem
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="payment_status">
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
