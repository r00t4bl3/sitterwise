import { useForm } from '@inertiajs/react';
import {
    Baby,
    BadgeCheck,
    Briefcase,
    CalendarCheck,
    ChevronDown,
    Heart,
    History,
    MapPin,
    MapPinCheckInside,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';

interface CaregiverSuggestion {
    id: number;
    name: string;
    age?: number | null;
    matchIcons?: string[];
    hasBeenNotified?: boolean;
}

interface ReplaceCaregiverSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    bookingId: number;
    currentCaregiverName?: string | null;
    caregiverSuggestions: CaregiverSuggestion[];
    caregiverTotal?: number;
    caregiverCurrentPage?: number;
    caregiverLastPage?: number;
    loadingCaregiverRecommendations?: boolean;
    loadingMoreCaregivers?: boolean;
    onLoadMoreCaregivers?: (ageFilter?: string) => void;
    onAgeFilterChange?: (filter: string) => void;
    onSearchChange?: (query: string, filter: string) => void;
}

export function ReplaceCaregiverSheet({
    open,
    onOpenChange,
    bookingId,
    currentCaregiverName,
    caregiverSuggestions,
    caregiverTotal,
    caregiverCurrentPage = 1,
    caregiverLastPage = 1,
    loadingCaregiverRecommendations,
    loadingMoreCaregivers,
    onLoadMoreCaregivers,
    onAgeFilterChange,
    onSearchChange,
}: ReplaceCaregiverSheetProps) {
    const replaceForm = useForm({ caregiver_id: 0 });
    const [ageFilter, setAgeFilter] = useState<'all' | 'younger' | 'seasoned'>(
        'all',
    );
    const [searchInput, setSearchInput] = useState('');
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    const handleSearchInput = (e: React.ChangeEvent<HTMLInputElement>) => {
        const value = e.target.value;
        setSearchInput(value);

        if (debounceRef.current) {
            clearTimeout(debounceRef.current);
        }

        debounceRef.current = setTimeout(() => {
            onSearchChange?.(value, ageFilter);
        }, 300);
    };

    useEffect(() => {
        return () => {
            if (debounceRef.current) {
                clearTimeout(debounceRef.current);
            }
        };
    }, []);

    const handleReplace = () => {
        if (!replaceForm.data.caregiver_id) {
            return;
        }

        replaceForm.post(`/bookings/${bookingId}/replace-caregiver`, {
            onSuccess: () => {
                onOpenChange(false);
                replaceForm.reset();
            },
        });
    };

    const selected = replaceForm.data.caregiver_id;

    const ICON_MAP: Record<string, React.ElementType> = {
        favorited: Heart,
        previous_work: History,
        available: CalendarCheck,
        specialty: Baby,
        location_preferred: MapPinCheckInside,
        location_willing: MapPin,
        recent_work: Briefcase,
    };

    const ICON_TOOLTIPS: Record<string, string> = {
        favorited: 'Favorited by client',
        previous_work: 'Previously worked with this family',
        available: 'Available for booking dates',
        specialty: 'Specializes in this age group',
        location_preferred: 'Based in booking area',
        location_willing: 'Willing to travel to booking area',
        recent_work: 'Actively working recently',
    };

    return (
        <Sheet open={open} onOpenChange={onOpenChange}>
            <SheetContent
                side="right"
                className="flex w-full flex-col sm:max-w-lg"
            >
                <SheetHeader className="shrink-0 pb-0">
                    <div className="flex items-center justify-between">
                        <div className="space-y-1">
                            <SheetTitle>
                                {currentCaregiverName
                                    ? 'Replace Caregiver'
                                    : 'Assign Caregiver'}
                            </SheetTitle>
                            <SheetDescription>
                                {currentCaregiverName ? (
                                    <>
                                        Select a new caregiver to replace{' '}
                                        <strong>{currentCaregiverName}</strong>.
                                    </>
                                ) : (
                                    <>
                                        Select a caregiver to assign to this
                                        booking.
                                    </>
                                )}
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                {replaceForm.errors.caregiver_id && (
                    <div className="mx-4 mt-2 rounded-md border border-red-200 bg-red-50 px-3 py-2 text-sm text-red-800">
                        {replaceForm.errors.caregiver_id}
                    </div>
                )}

                {caregiverTotal !== undefined && (
                    <div className="flex items-center justify-between px-4 pt-3 pb-1">
                        <span className="text-xs text-muted-foreground">
                            Showing {caregiverSuggestions.length} of{' '}
                            {caregiverTotal} caregivers
                        </span>
                        <div className="flex gap-0.5">
                            {(['all', 'younger', 'seasoned'] as const).map(
                                (f) => (
                                    <button
                                        key={f}
                                        type="button"
                                        onClick={() => {
                                            setAgeFilter(f);
                                            onAgeFilterChange?.(f);
                                        }}
                                        className={`rounded-[3px] px-2 py-1 text-xs font-medium transition-colors ${
                                            ageFilter === f
                                                ? 'bg-primary text-primary-foreground'
                                                : 'bg-accent text-muted-foreground hover:bg-accent/80'
                                        }`}
                                    >
                                        {f === 'all'
                                            ? 'All'
                                            : f === 'younger'
                                              ? 'Younger (18-34)'
                                              : 'Seasoned (35+)'}
                                    </button>
                                ),
                            )}
                        </div>
                    </div>
                )}

                <div className="px-4 pt-2">
                    <input
                        type="text"
                        value={searchInput}
                        onChange={handleSearchInput}
                        placeholder="Search by name..."
                        className="w-full rounded-md border border-border px-3 py-1.5 text-xs outline-none focus:border-primary"
                    />
                </div>

                <div className="flex-1 space-y-2 overflow-y-auto px-4 pt-4">
                    {loadingCaregiverRecommendations ? (
                        <div className="flex h-48 items-center justify-center">
                            <Spinner className="size-6" />
                        </div>
                    ) : caregiverSuggestions.length === 0 ? (
                        <div className="flex h-48 items-center justify-center text-sm text-muted-foreground">
                            No available caregivers found.
                        </div>
                    ) : (
                        caregiverSuggestions.map((caregiver) => {
                            const isSelected = selected === caregiver.id;
                            const matchIcons = caregiver.matchIcons;

                            return (
                                <button
                                    key={caregiver.id}
                                    type="button"
                                    onClick={() =>
                                        replaceForm.setData(
                                            'caregiver_id',
                                            caregiver.id,
                                        )
                                    }
                                    className={`flex w-full items-center justify-between gap-2 rounded-lg border p-3 text-left transition-colors ${
                                        isSelected
                                            ? 'border-primary bg-primary/5 ring-1 ring-primary'
                                            : 'border-border hover:bg-accent'
                                    }`}
                                >
                                    <div className="flex items-center gap-3">
                                        <div
                                            className={`flex h-5 w-5 items-center justify-center rounded-full border-2 ${
                                                isSelected
                                                    ? 'border-primary bg-primary'
                                                    : 'border-muted-foreground'
                                            }`}
                                        >
                                            {isSelected && (
                                                <div className="h-2 w-2 rounded-full bg-white" />
                                            )}
                                        </div>
                                        <div className="flex flex-col">
                                            <span className="text-sm font-medium">
                                                {caregiver.name}
                                                {caregiver.hasBeenNotified && (
                                                    <span
                                                        className="ml-2 text-green-500"
                                                        title="Already notified"
                                                    >
                                                        <BadgeCheck className="inline h-4 w-4" />
                                                    </span>
                                                )}
                                            </span>
                                        </div>
                                    </div>
                                    <div className="flex items-center gap-2">
                                        {matchIcons &&
                                            matchIcons.length > 0 && (
                                                <div className="flex items-center gap-1">
                                                    {matchIcons.map(
                                                        (iconKey) => {
                                                            const IconComponent =
                                                                ICON_MAP[
                                                                    iconKey
                                                                ];
                                                            const tooltip =
                                                                ICON_TOOLTIPS[
                                                                    iconKey
                                                                ];

                                                            if (
                                                                !IconComponent
                                                            ) {
                                                                return null;
                                                            }

                                                            return (
                                                                <Tooltip
                                                                    key={
                                                                        iconKey
                                                                    }
                                                                >
                                                                    <TooltipTrigger
                                                                        asChild
                                                                    >
                                                                        <span className="flex cursor-default items-center">
                                                                            <IconComponent className="h-4 w-4 text-muted-foreground" />
                                                                        </span>
                                                                    </TooltipTrigger>
                                                                    <TooltipContent>
                                                                        {
                                                                            tooltip
                                                                        }
                                                                    </TooltipContent>
                                                                </Tooltip>
                                                            );
                                                        },
                                                    )}
                                                </div>
                                            )}
                                        {caregiver.age && (
                                            <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-800">
                                                {caregiver.age}y
                                            </span>
                                        )}
                                    </div>
                                </button>
                            );
                        })
                    )}
                    {caregiverCurrentPage < caregiverLastPage &&
                        onLoadMoreCaregivers && (
                            <div className="flex justify-center pt-2 pb-4">
                                <Button
                                    variant="outline"
                                    size="sm"
                                    onClick={() =>
                                        onLoadMoreCaregivers(ageFilter)
                                    }
                                    disabled={loadingMoreCaregivers}
                                    className="w-full"
                                >
                                    {loadingMoreCaregivers ? (
                                        <>
                                            <Spinner className="mr-2 size-4" />
                                            Loading more caregivers...
                                        </>
                                    ) : (
                                        <>
                                            <ChevronDown className="mr-2 h-4 w-4" />
                                            Load More
                                        </>
                                    )}
                                </Button>
                            </div>
                        )}
                </div>

                <SheetFooter className="mt-4 flex shrink-0 gap-2 border-t border-border px-4 py-6">
                    <Button
                        onClick={handleReplace}
                        disabled={!selected || replaceForm.processing}
                        className="flex-1"
                    >
                        {replaceForm.processing && (
                            <Spinner className="size-4" />
                        )}
                        {currentCaregiverName
                            ? 'Replace Caregiver'
                            : 'Assign Caregiver'}
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                </SheetFooter>
            </SheetContent>
        </Sheet>
    );
}
