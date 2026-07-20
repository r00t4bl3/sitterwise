import { Hotel } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';

export interface HotelInfo {
    id: number;
    name: string;
    line1: string | null;
    line2: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    parking_instructions: string | null;
    resort_fee: number | string | null;
    contact_name: string | null;
    contact_phone: string | null;
}

function formatAddress(hotel: HotelInfo): string | null {
    const cityLine = [hotel.city, hotel.state].filter(Boolean).join(', ');
    const parts = [
        hotel.line1,
        hotel.line2,
        [cityLine, hotel.zip].filter(Boolean).join(' '),
    ].filter((part) => part && part.trim().length > 0);

    return parts.length > 0 ? parts.join('\n') : null;
}

function DetailRow({ label, value }: { label: string; value: string | null }) {
    if (!value) {
        return null;
    }

    return (
        <div>
            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                {label}
            </p>
            <p className="text-sm whitespace-pre-line text-foreground">
                {value}
            </p>
        </div>
    );
}

/**
 * Read-only hotel details popup, shared by the booking detail page and the
 * Transactions page. Renders a trigger button that opens a dialog listing the
 * hotel's address, parking instructions, resort fee, and contact.
 */
export function HotelInfoDialog({
    hotel,
    triggerLabel = 'Hotel info',
    triggerClassName,
}: {
    hotel: HotelInfo;
    triggerLabel?: string;
    triggerClassName?: string;
}) {
    const [open, setOpen] = useState(false);

    const resortFee =
        hotel.resort_fee !== null && hotel.resort_fee !== undefined
            ? `$${Number(hotel.resort_fee).toFixed(2)}`
            : null;

    return (
        <>
            <Button
                type="button"
                variant="outline"
                size="sm"
                className={triggerClassName}
                onClick={() => setOpen(true)}
            >
                <Hotel className="h-4 w-4" />
                {triggerLabel}
            </Button>
            <Dialog open={open} onOpenChange={setOpen}>
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{hotel.name}</DialogTitle>
                        <DialogDescription>Hotel details</DialogDescription>
                    </DialogHeader>
                    <div className="space-y-4">
                        <DetailRow
                            label="Address"
                            value={formatAddress(hotel)}
                        />
                        <DetailRow
                            label="Parking"
                            value={hotel.parking_instructions}
                        />
                        <DetailRow label="Resort Fee" value={resortFee} />
                        <DetailRow
                            label="Contact"
                            value={
                                [hotel.contact_name, hotel.contact_phone]
                                    .filter(Boolean)
                                    .join(' · ') || null
                            }
                        />
                    </div>
                </DialogContent>
            </Dialog>
        </>
    );
}
