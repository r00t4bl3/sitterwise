import { Download } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';

type ExportSheetProps = {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    defaultMonth?: number;
    defaultYear?: number;
};

const monthNames = [
    'January',
    'February',
    'March',
    'April',
    'May',
    'June',
    'July',
    'August',
    'September',
    'October',
    'November',
    'December',
];

export function ExportSheet({
    open,
    onOpenChange,
    defaultMonth,
    defaultYear,
}: ExportSheetProps) {
    const currentYear = new Date().getFullYear();
    const [month, setMonth] = useState(
        defaultMonth ?? new Date().getMonth() + 1,
    );
    const [year, setYear] = useState(defaultYear ?? currentYear);

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent side="right">
                <SheetHeader>
                    <SheetTitle>Export Bookings</SheetTitle>
                    <SheetDescription>
                        Select a month and year to download bookings as an XLSX
                        file.
                    </SheetDescription>
                </SheetHeader>
                <div className="flex flex-col gap-4 p-4">
                    <div className="flex flex-col gap-2">
                        <label className="text-sm font-medium text-foreground">
                            Month
                        </label>
                        <Select
                            value={String(month)}
                            onValueChange={(v) => setMonth(Number(v))}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {monthNames.map((name, i) => (
                                    <SelectItem
                                        key={i + 1}
                                        value={String(i + 1)}
                                    >
                                        {name}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="flex flex-col gap-2">
                        <label className="text-sm font-medium text-foreground">
                            Year
                        </label>
                        <Select
                            value={String(year)}
                            onValueChange={(v) => setYear(Number(v))}
                        >
                            <SelectTrigger>
                                <SelectValue />
                            </SelectTrigger>
                            <SelectContent>
                                {Array.from({ length: 8 }, (_, i) => {
                                    return currentYear - 5 + i;
                                }).map((y) => (
                                    <SelectItem key={y} value={String(y)}>
                                        {y}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    </div>
                    <div className="mt-2">
                        <Button
                            className="w-full"
                            onClick={() => {
                                const url = `/bookings/export?month=${month}&year=${year}`;
                                const link = document.createElement('a');
                                link.href = url;
                                link.click();
                            }}
                        >
                            <Download className="h-4 w-4" />
                            Download
                        </Button>
                    </div>
                </div>
            </SheetContent>
        </Sheet>
    );
}
