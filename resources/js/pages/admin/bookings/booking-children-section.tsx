import { Plus, Trash2 } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { getChildBirthYearOptions } from '@/lib/age';

const monthFormatter = new Intl.DateTimeFormat('en-US', { month: 'short' });
const MONTH_ABBR = [
    '',
    ...Array.from({ length: 12 }, (_, i) =>
        monthFormatter.format(new Date(2000, i)),
    ),
];

interface Child {
    tempId: string;
    name: string;
    gender: string;
    birth_month: string;
    birth_year: string;
}

interface BookingChildrenSectionProps {
    children: Child[];
    onAdd: () => void;
    onRemove: (tempId: string) => void;
    onUpdate: (tempId: string, field: string, value: string | boolean) => void;
    calculateAge: (
        birthYear: number | null,
        birthMonth: number | null,
    ) => string;
    serviceType?: string;
}

export function BookingChildrenSection({
    children,
    onAdd,
    onRemove,
    onUpdate,
    calculateAge,
    serviceType,
}: BookingChildrenSectionProps) {
    return (
        <div>
            <div className="flex items-center justify-between">
                <Label className="text-sm font-medium text-foreground">
                    Children
                </Label>
                <Button type="button" onClick={onAdd} size="xs">
                    <Plus className="h-3 w-3" />
                    Add Child
                </Button>
            </div>
            <div className="mt-1 grid gap-4">
                {children.map((child) => (
                    <div
                        key={child.tempId}
                        className="rounded-lg border bg-card p-4"
                    >
                        <div className="mb-3 flex items-start justify-between">
                            <p className="text-sm font-medium text-foreground">
                                {child.name || 'Add New Child'}
                            </p>
                            <Button
                                type="button"
                                onClick={() => onRemove(child.tempId)}
                                size="sm"
                            >
                                <Trash2 className="h-4 w-4" />
                            </Button>
                        </div>
                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="sm:col-span-1 md:col-auto">
                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                    Name
                                </Label>
                                <Input
                                    value={child.name}
                                    onChange={(e) =>
                                        onUpdate(
                                            child.tempId,
                                            'name',
                                            e.target.value,
                                        )
                                    }
                                    placeholder="Name"
                                />
                            </div>
                            <div>
                                <Label className="text-xs font-medium text-muted-foreground uppercase">
                                    Gender
                                </Label>
                                <Select
                                    value={child.gender || ''}
                                    onValueChange={(value) =>
                                        onUpdate(child.tempId, 'gender', value)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select gender" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="male">
                                            Male
                                        </SelectItem>
                                        <SelectItem value="female">
                                            Female
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="flex flex-row gap-4 sm:col-span-2">
                                <div className="grow">
                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                        Month
                                    </Label>
                                    <Select
                                        value={child.birth_month || ''}
                                        onValueChange={(value) =>
                                            onUpdate(
                                                child.tempId,
                                                'birth_month',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Month" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {MONTH_ABBR.map(
                                                (
                                                    monthAbbr: string,
                                                    index: number,
                                                ) => {
                                                    if (index === 0) {
                                                        return null;
                                                    }

                                                    return (
                                                        <SelectItem
                                                            key={`month-${index}`}
                                                            value={String(
                                                                index,
                                                            )}
                                                        >
                                                            {monthAbbr}
                                                        </SelectItem>
                                                    );
                                                },
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grow">
                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                        Year
                                    </Label>
                                    <Select
                                        value={child.birth_year || ''}
                                        onValueChange={(value) =>
                                            onUpdate(
                                                child.tempId,
                                                'birth_year',
                                                value,
                                            )
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Year" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {getChildBirthYearOptions().map(
                                                (year) => (
                                                    <SelectItem
                                                        key={year}
                                                        value={String(year)}
                                                    >
                                                        {year}
                                                    </SelectItem>
                                                ),
                                            )}
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div className="grow">
                                    <Label className="text-xs font-medium text-muted-foreground uppercase">
                                        Age
                                    </Label>
                                    <p className="flex h-11 items-center text-sm text-foreground">
                                        {child.birth_year
                                            ? calculateAge(
                                                  parseInt(child.birth_year) ||
                                                      null,
                                                  parseInt(child.birth_month) ||
                                                      null,
                                              )
                                            : '-'}
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                ))}
                {children.length === 0 && (
                    <div className="rounded-lg border border-dashed bg-card/50 p-8 text-center">
                        <p className="text-sm text-muted-foreground">
                            No children added
                        </p>
                    </div>
                )}
            </div>
            {children.length === 0 &&
                serviceType !== 'group_childcare_invoiced' && (
                    <p className="text-sm text-destructive">
                        At least one child is required.
                    </p>
                )}
        </div>
    );
}
