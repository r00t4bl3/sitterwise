import { useState } from 'react';
import { ChevronDown, ChevronUp } from 'lucide-react';
import { Autocomplete } from '@/components/ui/autocomplete';
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
                                value={form.data.start_datetime}
                                onChange={(datetime) =>
                                    form.setData('start_datetime', datetime)
                                }
                            />
                        </div>
                    </div>
                    <div>
                        <label className="text-sm font-medium text-foreground">
                            End DateTime <span className="text-red-500">*</span>
                        </label>
                        <div className="mt-1">
                            <DateTimePicker
                                value={form.data.end_datetime}
                                onChange={(datetime) =>
                                    form.setData('end_datetime', datetime)
                                }
                            />
                        </div>
                    </div>
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
                        />
                    </div>
                </div>

                <div>
                    <label className="text-sm font-medium text-foreground">
                        Special Considerations
                    </label>
                    <div className="mt-2 flex flex-wrap gap-2">
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

                <div className="flex items-center gap-4">
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.comped}
                            onChange={(e) =>
                                form.setData('comped', e.target.checked)
                            }
                            className="h-4 w-4 rounded border-input"
                        />
                        <span className="text-sm text-foreground">Comped</span>
                    </label>
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={form.data.requires_payment}
                            onChange={(e) =>
                                form.setData(
                                    'requires_payment',
                                    e.target.checked,
                                )
                            }
                            className="h-4 w-4 rounded border-input"
                        />
                        <span className="text-sm text-foreground">
                            Requires Payment
                        </span>
                    </label>
                </div>

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
                                <option key={status.value} value={status.value}>
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
                                form.setData('payment_status', e.target.value)
                            }
                            className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        >
                            {payment_statuses.map((status) => (
                                <option key={status.value} value={status.value}>
                                    {status.label}
                                </option>
                            ))}
                        </select>
                    </div>
                </div>

                <div className="flex gap-2 pt-4">
                    <button
                        onClick={handleSubmit}
                        disabled={form.processing}
                        className="btn-primary flex-1"
                    >
                        {form.processing && <Spinner className="size-4" />}
                        {form.processing
                            ? 'Saving...'
                            : editingBooking
                              ? 'Update'
                              : 'Create'}
                    </button>
                    {editingBooking && (
                        <button
                            onClick={handleDelete}
                            disabled={form.processing}
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
        </details>
    );
}
