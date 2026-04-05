import { FormEvent } from 'react';

interface Props {
    form: any;
}

export function BookingAddressFields({ form }: Props) {
    return (
        <div className="space-y-3">
            <div>
                <label className="text-sm font-medium text-foreground">
                    Address Line 1 <span className="text-red-500">*</span>
                </label>
                <input
                    type="text"
                    value={form.data.address_line1}
                    onChange={(e) =>
                        form.setData('address_line1', e.target.value)
                    }
                    placeholder="Street address"
                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                    required
                />
            </div>
            <div>
                <label className="text-sm font-medium text-foreground">
                    Address Line 2
                </label>
                <input
                    type="text"
                    value={form.data.address_line2}
                    onChange={(e) =>
                        form.setData('address_line2', e.target.value)
                    }
                    placeholder="Apt, suite, etc. (optional)"
                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                />
            </div>
            <div className="grid grid-cols-3 gap-3">
                <div>
                    <label className="text-sm font-medium text-foreground">
                        City <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.address_city}
                        onChange={(e) =>
                            form.setData('address_city', e.target.value)
                        }
                        placeholder="City"
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        required
                    />
                </div>
                <div>
                    <label className="text-sm font-medium text-foreground">
                        State <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.address_state}
                        onChange={(e) =>
                            form.setData('address_state', e.target.value)
                        }
                        placeholder="State"
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        required
                    />
                </div>
                <div>
                    <label className="text-sm font-medium text-foreground">
                        Zip <span className="text-red-500">*</span>
                    </label>
                    <input
                        type="text"
                        value={form.data.address_zip}
                        onChange={(e) =>
                            form.setData('address_zip', e.target.value)
                        }
                        placeholder="Zip"
                        className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm"
                        required
                    />
                </div>
            </div>
        </div>
    );
}
