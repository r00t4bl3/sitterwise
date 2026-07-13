import { useForm } from '@inertiajs/react';
import {
    Baby,
    Backpack,
    BadgeCheck,
    Blocks,
    Briefcase,
    CalendarCheck,
    Heart,
    HeartHandshake,
    History,
    Languages,
    MapPin,
    MapPinCheckInside,
    Palette,
    X,
} from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
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
    [key: string]: unknown;
}

interface NotifyCaregiversSheetProps {
    open: boolean;
    onOpenChange: (open: boolean) => void;
    bookingId: number;
    initialCaregiverId?: number;
    caregiverSuggestions: CaregiverSuggestion[];
    caregiverAllIds: number[];
    caregiverTotal: number;
    caregiverCurrentPage: number;
    caregiverLastPage: number;
    loadingCaregiverRecommendations: boolean;
    loadingMoreCaregivers: boolean;
    onLoadMoreCaregivers?: (ageFilter?: string) => void;
    onAgeFilterChange?: (filter: string) => void;
    onSearchChange?: (query: string, filter: string) => void;
}

export function NotifyCaregiversSheet({
    open,
    onOpenChange,
    bookingId,
    initialCaregiverId,
    caregiverSuggestions,
    caregiverAllIds,
    caregiverTotal,
    caregiverCurrentPage,
    caregiverLastPage,
    loadingCaregiverRecommendations,
    loadingMoreCaregivers,
    onLoadMoreCaregivers,
    onAgeFilterChange,
    onSearchChange,
}: NotifyCaregiversSheetProps) {
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

    const notifyForm = useForm({
        caregiver_ids: initialCaregiverId
            ? [initialCaregiverId]
            : ([] as number[]),
    });

    const toggleCaregiver = (id: number) => {
        const current = notifyForm.data.caregiver_ids;
        const next = current.includes(id)
            ? current.filter((c) => c !== id)
            : [...current, id];

        notifyForm.setData('caregiver_ids', next);
    };

    const handleNotify = () => {
        if (notifyForm.data.caregiver_ids.length === 0) {
            return;
        }

        notifyForm.post(`/bookings/${bookingId}/notify`, {
            onSuccess: () => {
                onOpenChange(false);
                notifyForm.reset();
            },
        });
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
                            <SheetTitle>Notify Caregivers</SheetTitle>
                            <SheetDescription>
                                Select caregivers to notify about this booking.
                            </SheetDescription>
                        </div>
                    </div>
                </SheetHeader>

                <div className="flex flex-col gap-2 border-b border-border px-4 py-2">
                    <div className="items-center text-xs text-muted-foreground">
                        Showing {caregiverSuggestions.length} of{' '}
                        {caregiverTotal} caregivers
                    </div>
                    <div className="flex gap-2">
                        <button
                            type="button"
                            onClick={() => {
                                setAgeFilter('all');
                                onAgeFilterChange?.('all');
                            }}
                            className={`cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'all'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            All
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setAgeFilter('younger');
                                onAgeFilterChange?.('younger');
                            }}
                            className={`cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'younger'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            Younger (18-34)
                        </button>
                        <button
                            type="button"
                            onClick={() => {
                                setAgeFilter('seasoned');
                                onAgeFilterChange?.('seasoned');
                            }}
                            className={`cursor-pointer rounded-full px-3 py-1 text-xs font-medium transition-colors ${
                                ageFilter === 'seasoned'
                                    ? 'bg-primary text-primary-foreground'
                                    : 'bg-muted text-muted-foreground hover:bg-muted/80'
                            }`}
                        >
                            Seasoned (35+)
                        </button>
                        <div className="ml-auto">
                            <Button
                                variant="link"
                                size="sm"
                                className="cursor-pointer"
                                onClick={() => {
                                    const visibleIds = caregiverSuggestions.map(
                                        (cg) => cg.id,
                                    );
                                    const allSelected = visibleIds.every((id) =>
                                        notifyForm.data.caregiver_ids.includes(
                                            id,
                                        ),
                                    );

                                    notifyForm.setData(
                                        'caregiver_ids',
                                        allSelected ? [] : visibleIds,
                                    );
                                }}
                                disabled={loadingCaregiverRecommendations}
                            >
                                {(() => {
                                    const total = caregiverSuggestions.length;
                                    const allSelected =
                                        caregiverSuggestions.every((cg) =>
                                            notifyForm.data.caregiver_ids.includes(
                                                cg.id,
                                            ),
                                        );

                                    return allSelected
                                        ? `Deselect All (${total})`
                                        : `Select All (${total})`;
                                })()}
                            </Button>
                        </div>
                    </div>

                    <div className="relative">
                        <input
                            type="text"
                            value={searchInput}
                            onChange={handleSearchInput}
                            placeholder="Search by name..."
                            className="w-full rounded-md border border-border px-3 py-1.5 pr-8 text-xs outline-none focus:border-primary"
                        />
                        {searchInput && (
                            <button
                                type="button"
                                aria-label="Clear search"
                                onClick={() => {
                                    setSearchInput('');

                                    if (debounceRef.current) {
                                        clearTimeout(debounceRef.current);
                                    }

                                    onSearchChange?.('', ageFilter);
                                }}
                                className="absolute top-1/2 right-2 -translate-y-1/2 cursor-pointer text-muted-foreground hover:text-foreground"
                            >
                                <X className="h-4 w-4" />
                            </button>
                        )}
                    </div>
                </div>

                {(() => {
                    const visibleIds = caregiverSuggestions.map((cg) => cg.id);
                    const visibleAllSelected =
                        visibleIds.length > 0 &&
                        visibleIds.every((id) =>
                            notifyForm.data.caregiver_ids.includes(id),
                        );

                    const totalAllSelected =
                        caregiverAllIds.length > 0 &&
                        caregiverAllIds.every((id) =>
                            notifyForm.data.caregiver_ids.includes(id),
                        );

                    const showBanner =
                        visibleAllSelected &&
                        !totalAllSelected &&
                        caregiverAllIds.length > visibleIds.length;

                    if (!showBanner) {
                        return null;
                    }

                    return (
                        <div className="mx-4 mt-2 flex items-center justify-between rounded-md border border-blue-200 bg-blue-50 px-3 py-2 text-sm text-blue-800">
                            <span>
                                All {visibleIds.length} on this page selected.{' '}
                                <button
                                    type="button"
                                    className="cursor-pointer font-medium underline hover:text-blue-600"
                                    onClick={() => {
                                        notifyForm.setData(
                                            'caregiver_ids',
                                            caregiverAllIds,
                                        );
                                    }}
                                >
                                    Select all {caregiverAllIds.length} matching
                                    caregivers.
                                </button>
                            </span>
                            <button
                                type="button"
                                className="ml-2 cursor-pointer text-blue-500 hover:text-blue-700"
                                onClick={() => {
                                    /* Dismiss by deselecting one so condition is no longer met */
                                }}
                            >
                                ×
                            </button>
                        </div>
                    );
                })()}

                <div className="flex-1 space-y-2 overflow-y-auto px-4">
                    {loadingCaregiverRecommendations ? (
                        <div className="flex h-96 items-center justify-center">
                            <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                <p className="text-sm">
                                    Loading caregiver recommendations...
                                </p>
                            </div>
                        </div>
                    ) : (
                        <>
                            {(() => {
                                const ICON_MAP: Record<
                                    string,
                                    React.ElementType
                                > = {
                                    favorited: Heart,
                                    previous_work: History,
                                    available: CalendarCheck,
                                    specialty_babies: Baby,
                                    specialty_toddlers: Blocks,
                                    specialty_preschool: Palette,
                                    specialty_school_age: Backpack,
                                    special_needs: HeartHandshake,
                                    location_preferred: MapPinCheckInside,
                                    location_willing: MapPin,
                                    recent_work: Briefcase,
                                };

                                const ICON_TOOLTIPS: Record<string, string> = {
                                    favorited: 'Favorited by client',
                                    previous_work:
                                        'Previously worked with this family',
                                    available: 'Available for booking dates',
                                    specialty_babies: 'Specializes in babies',
                                    specialty_toddlers:
                                        'Specializes in toddlers',
                                    specialty_preschool:
                                        'Specializes in preschoolers',
                                    specialty_school_age:
                                        'Specializes in school-age kids',
                                    special_needs: 'Special-needs experience',
                                    location_preferred: 'Based in booking area',
                                    location_willing:
                                        'Willing to travel to booking area',
                                    recent_work: 'Actively working recently',
                                };

                                return caregiverSuggestions.map((caregiver) => {
                                    const hasBeenNotified = (caregiver as any)
                                        .hasBeenNotified;
                                    const matchIcons = (caregiver as any)
                                        .matchIcons as string[] | undefined;

                                    return (
                                        <Label
                                            key={caregiver.id}
                                            className={`flex items-center justify-between gap-2 rounded-lg border border-border p-3 hover:cursor-pointer hover:bg-blush ${notifyForm.data.caregiver_ids.includes(caregiver.id) && `bg-blush`}`}
                                        >
                                            <div className="flex items-center gap-2">
                                                <Checkbox
                                                    id={`cg-${caregiver.id}`}
                                                    checked={notifyForm.data.caregiver_ids.includes(
                                                        caregiver.id,
                                                    )}
                                                    onCheckedChange={() =>
                                                        toggleCaregiver(
                                                            caregiver.id,
                                                        )
                                                    }
                                                />
                                                <div className="flex flex-row items-center gap-2">
                                                    <Label
                                                        htmlFor={`cg-${caregiver.id}`}
                                                        className="flex text-sm font-medium"
                                                    >
                                                        {caregiver.name}
                                                        {hasBeenNotified && (
                                                            <span
                                                                className="ml-2 text-green-500"
                                                                title="Already notified"
                                                            >
                                                                <BadgeCheck className="h-5 w-5" />
                                                            </span>
                                                        )}
                                                    </Label>
                                                </div>
                                            </div>
                                            <div className="flex items-center gap-2">
                                                {matchIcons &&
                                                    matchIcons.length > 0 && (
                                                        <div className="flex items-center gap-1">
                                                            {matchIcons.map(
                                                                (
                                                                    iconKey: string,
                                                                ) => {
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
                                                {(caregiver as any)
                                                    .speaksSpanish && (
                                                    <Tooltip>
                                                        <TooltipTrigger asChild>
                                                            <span className="flex cursor-default items-center">
                                                                <Languages className="h-4 w-4 text-amber-600" />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Speaks Spanish
                                                        </TooltipContent>
                                                    </Tooltip>
                                                )}
                                                {caregiver.age && (
                                                    <span className="rounded-full bg-teal-100 px-2 py-0.5 text-xs font-medium text-teal-800">
                                                        {caregiver.age}y
                                                    </span>
                                                )}
                                            </div>
                                        </Label>
                                    );
                                });
                            })()}

                            {caregiverCurrentPage < caregiverLastPage && (
                                <div className="flex justify-center py-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() =>
                                            onLoadMoreCaregivers?.(ageFilter)
                                        }
                                        disabled={loadingMoreCaregivers}
                                        className="cursor-pointer"
                                    >
                                        {loadingMoreCaregivers && (
                                            <Spinner className="mr-2 size-4" />
                                        )}
                                        {loadingMoreCaregivers
                                            ? 'Loading more caregivers...'
                                            : 'Load More'}
                                    </Button>
                                </div>
                            )}
                        </>
                    )}
                </div>

                <div className="mt-4 flex shrink-0 gap-2 border-t border-border px-4 py-6">
                    <Button
                        onClick={handleNotify}
                        disabled={notifyForm.processing}
                        className="flex-1"
                    >
                        {notifyForm.processing && (
                            <Spinner className="size-4" />
                        )}
                        Send Notification
                    </Button>
                    <Button
                        variant="outline"
                        onClick={() => onOpenChange(false)}
                    >
                        Cancel
                    </Button>
                </div>
            </SheetContent>
        </Sheet>
    );
}
