import { Link, useForm } from '@inertiajs/react';
import { Calendar, Split } from 'lucide-react';
import { useState } from 'react';
import { ErrorBoundary } from '@/components/error-boundary';
import { StatusBadge } from '@/components/status-badge';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { calculateAge } from '@/lib/age';
import { formatDisplayDateInPT, formatDisplayTimeInPT } from '@/lib/datetime';
import { BookingDetailsSection } from './booking-details-section';
import { PersonalInfoSection } from './personal-info-section';
import type { UseBookingSheetReturn } from './use-booking-sheet';

type BookingSheetProps = UseBookingSheetReturn;

export function BookingSheet({
    isSheetOpen,
    setIsSheetOpen,
    isLoading,
    editingBooking,
    sheetMode,
    showDeleteDialog,
    setShowDeleteDialog,
    showPastBookingDialog,
    handleConfirmPastBooking,
    handleCancelPastBooking,
    form,
    clientSuggestions,
    clientAddresses,
    bookingChildren,
    bookingPets,
    clientMode,
    setClientMode,
    selectedClientType,
    loadingSuggestions,
    selectedClientName,
    selectedHotelName,
    selectedCaregiverName,
    isAddressLocked,
    setIsAddressLocked,
    showManualAddressInput,
    setShowManualAddressInput,
    addressValue,
    setAddressValue,
    saveChildrenPetsToProfile,
    setSaveChildrenPetsToProfile,
    client_types,
    discovery_sources,
    sitter_preferences,
    service_types,
    location_types,
    pet_types,
    booking_statuses,
    payment_statuses,
    hotels,
    hotelSuggestions,
    caregiverSuggestions,
    handleClientSearch,
    handleHotelSearch,
    handleCaregiverSearch,
    handleClientChange,
    handleAddChild,
    handleRemoveChild,
    handleUpdateChild,
    handleAddPet,
    handleRemovePet,
    handleUpdatePet,
    handleSubmit,
    handleDelete,
    handleConfirmDelete,
    handleCancelDelete,
    populateCaregiverSuggestions,
    loadMoreCaregivers,
    caregiverAllIds,
    caregiverTotal,
    caregiverCurrentPage,
    caregiverLastPage,
    loadingCaregiverRecommendations,
    loadingMoreCaregivers,
    onAgeFilterChange,
    onSearchChange,
}: BookingSheetProps) {
    const [splitDialogOpen, setSplitDialogOpen] = useState(false);

    const group = editingBooking?.booking_group;
    const currentSiblingIds = group
        ? [
              editingBooking!.id,
              ...(group.sibling_bookings ?? []).map((s) => s.id),
          ]
        : [];

    const splitForm = useForm<{ booking_ids: number[] }>({
        booking_ids: [],
    });

    const toggleSplitBooking = (id: number) => {
        const current = splitForm.data.booking_ids;

        if (current.includes(id)) {
            splitForm.setData(
                'booking_ids',
                current.filter((i) => i !== id),
            );
        } else {
            splitForm.setData('booking_ids', [...current, id]);
        }
    };

    const submitSplit = () => {
        splitForm.post(`/bookings/groups/${group?.id}/split`, {
            preserveScroll: true,
        });
    };

    return (
        <>
            <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                <SheetContent
                    side="right"
                    className="w-full overflow-y-auto sm:max-w-2xl"
                >
                    <SheetHeader>
                        <SheetTitle>
                            {sheetMode === 'edit' && 'Edit Booking'}
                            {sheetMode === 'duplicate' && 'Duplicate Booking'}
                            {sheetMode === 'create' && 'Create Booking'}
                        </SheetTitle>
                        <SheetDescription>
                            {sheetMode === 'edit' &&
                                'Update booking details below.'}
                            {sheetMode === 'duplicate' &&
                                'Create a copy of this booking.'}
                            {sheetMode === 'create' &&
                                'Fill in the details to create a new booking.'}
                        </SheetDescription>
                    </SheetHeader>

                    {isLoading ? (
                        <div className="flex h-96 items-center justify-center">
                            <div className="flex flex-col items-center gap-2 text-muted-foreground">
                                <div className="h-8 w-8 animate-spin rounded-full border-4 border-primary border-t-transparent" />
                                <p className="text-sm">
                                    Loading booking details...
                                </p>
                            </div>
                        </div>
                    ) : (
                        <ErrorBoundary>
                            <div className="space-y-4 px-4">
                                {Object.keys(form.errors).length > 0 && (
                                    <div className="rounded-[3px] border border-destructive bg-destructive/10 p-3">
                                        <p className="mb-1 text-sm font-medium text-destructive">
                                            Please fix the following errors:
                                        </p>
                                        <ul className="list-inside list-disc space-y-0.5 text-sm text-destructive">
                                            {Object.values(form.errors).map(
                                                (error, index) => (
                                                    <li key={index}>{error}</li>
                                                ),
                                            )}
                                        </ul>
                                    </div>
                                )}
                                {editingBooking?.booking_group &&
                                    (editingBooking.booking_group
                                        .bookings_count ?? 0) > 1 && (
                                        <div className="rounded-lg border border-border bg-card p-4">
                                            <div className="mb-3 flex items-center justify-between">
                                                <div className="flex items-center gap-2">
                                                    <Calendar className="h-4 w-4 text-muted-foreground" />
                                                    <Badge
                                                        variant="outline"
                                                        className="text-xs"
                                                    >
                                                        Multi-Day (
                                                        {
                                                            editingBooking
                                                                .booking_group
                                                                .bookings_count
                                                        }
                                                        )
                                                    </Badge>
                                                </div>
                                            </div>
                                            <div className="ml-4 space-y-1.5 border-l-2 border-border pl-3">
                                                {[
                                                    {
                                                        ...editingBooking,
                                                        caregiver_name: null,
                                                    } as typeof editingBooking & {
                                                        caregiver_name:
                                                            | string
                                                            | null;
                                                    },
                                                    ...(editingBooking
                                                        .booking_group
                                                        .sibling_bookings ??
                                                        []),
                                                ].map((b) => (
                                                    <Link
                                                        key={b.id}
                                                        href={`/bookings/${b.ulid}`}
                                                        className="flex items-center justify-between rounded px-2 py-1 text-xs transition-colors hover:bg-accent"
                                                    >
                                                        <span className="text-muted-foreground">
                                                            {formatDisplayDateInPT(
                                                                b.start_datetime,
                                                            )}{' '}
                                                            {formatDisplayTimeInPT(
                                                                b.start_datetime,
                                                            )}{' '}
                                                            -{' '}
                                                            {formatDisplayTimeInPT(
                                                                b.end_datetime,
                                                            )}
                                                        </span>
                                                        <div className="flex items-center gap-2">
                                                            {b.caregiver_name && (
                                                                <span className="text-muted-foreground">
                                                                    {
                                                                        b.caregiver_name
                                                                    }
                                                                </span>
                                                            )}
                                                            <StatusBadge
                                                                status={
                                                                    b.status
                                                                }
                                                                bookingStatuses={
                                                                    booking_statuses
                                                                }
                                                            />
                                                        </div>
                                                    </Link>
                                                ))}
                                                <Dialog
                                                    open={splitDialogOpen}
                                                    onOpenChange={
                                                        setSplitDialogOpen
                                                    }
                                                >
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        className="mt-2 text-xs"
                                                        onClick={() => {
                                                            splitForm.setData(
                                                                'booking_ids',
                                                                [
                                                                    editingBooking!
                                                                        .id,
                                                                ],
                                                            );
                                                            setSplitDialogOpen(
                                                                true,
                                                            );
                                                        }}
                                                    >
                                                        <Split className="mr-1 h-3 w-3" />
                                                        Split Group
                                                    </Button>
                                                    <DialogContent>
                                                        <DialogHeader>
                                                            <DialogTitle>
                                                                Split Group
                                                            </DialogTitle>
                                                            <DialogDescription>
                                                                Select which
                                                                dates to move to
                                                                a new group. The
                                                                current booking
                                                                cannot be
                                                                unchecked.
                                                                Extracted
                                                                bookings will
                                                                reset to
                                                                "received"
                                                                status and clear
                                                                caregiver
                                                                assignment.
                                                            </DialogDescription>
                                                        </DialogHeader>
                                                        <div className="space-y-3 py-4">
                                                            {currentSiblingIds.map(
                                                                (id) => {
                                                                    const isCurrent =
                                                                        id ===
                                                                        editingBooking!
                                                                            .id;
                                                                    const sib =
                                                                        isCurrent
                                                                            ? null
                                                                            : (
                                                                                  group?.sibling_bookings ??
                                                                                  []
                                                                              ).find(
                                                                                  (
                                                                                      s,
                                                                                  ) =>
                                                                                      s.id ===
                                                                                      id,
                                                                              );

                                                                    return (
                                                                        <div
                                                                            key={
                                                                                id
                                                                            }
                                                                            className="flex items-center gap-3"
                                                                        >
                                                                            <Checkbox
                                                                                id={`sheet-split-${id}`}
                                                                                checked={splitForm.data.booking_ids.includes(
                                                                                    id,
                                                                                )}
                                                                                disabled={
                                                                                    isCurrent
                                                                                }
                                                                                onCheckedChange={() =>
                                                                                    toggleSplitBooking(
                                                                                        id,
                                                                                    )
                                                                                }
                                                                            />
                                                                            <Label
                                                                                htmlFor={`sheet-split-${id}`}
                                                                                className={`text-sm ${isCurrent ? 'font-medium' : 'text-muted-foreground'}`}
                                                                            >
                                                                                {isCurrent ? (
                                                                                    <span>
                                                                                        {formatDisplayDateInPT(
                                                                                            editingBooking!
                                                                                                .start_datetime,
                                                                                        )}{' '}
                                                                                        {formatDisplayTimeInPT(
                                                                                            editingBooking!
                                                                                                .start_datetime,
                                                                                        )}{' '}
                                                                                        -{' '}
                                                                                        {formatDisplayTimeInPT(
                                                                                            editingBooking!
                                                                                                .end_datetime,
                                                                                        )}
                                                                                        <span className="ml-2 text-xs text-muted-foreground">
                                                                                            (this
                                                                                            booking)
                                                                                        </span>
                                                                                    </span>
                                                                                ) : sib ? (
                                                                                    <span>
                                                                                        {formatDisplayDateInPT(
                                                                                            sib.start_datetime,
                                                                                        )}{' '}
                                                                                        {formatDisplayTimeInPT(
                                                                                            sib.start_datetime,
                                                                                        )}{' '}
                                                                                        -{' '}
                                                                                        {formatDisplayTimeInPT(
                                                                                            sib.end_datetime,
                                                                                        )}
                                                                                        {sib.caregiver_name && (
                                                                                            <span className="ml-2 text-xs text-muted-foreground">
                                                                                                (
                                                                                                {
                                                                                                    sib.caregiver_name
                                                                                                }

                                                                                                )
                                                                                            </span>
                                                                                        )}
                                                                                    </span>
                                                                                ) : null}
                                                                            </Label>
                                                                        </div>
                                                                    );
                                                                },
                                                            )}
                                                        </div>
                                                        <DialogFooter>
                                                            <Button
                                                                variant="outline"
                                                                onClick={() =>
                                                                    setSplitDialogOpen(
                                                                        false,
                                                                    )
                                                                }
                                                            >
                                                                Cancel
                                                            </Button>
                                                            <Button
                                                                onClick={
                                                                    submitSplit
                                                                }
                                                                disabled={
                                                                    splitForm.processing ||
                                                                    splitForm
                                                                        .data
                                                                        .booking_ids
                                                                        .length ===
                                                                        0
                                                                }
                                                            >
                                                                {splitForm.processing
                                                                    ? 'Splitting...'
                                                                    : 'Split Group'}
                                                            </Button>
                                                        </DialogFooter>
                                                    </DialogContent>
                                                </Dialog>
                                            </div>
                                        </div>
                                    )}

                                <PersonalInfoSection
                                    form={form}
                                    editingBooking={editingBooking}
                                    clientMode={clientMode}
                                    setClientMode={setClientMode}
                                    clientSuggestions={clientSuggestions}
                                    clientAddresses={clientAddresses}
                                    bookingChildren={bookingChildren}
                                    bookingPets={bookingPets}
                                    onAddChild={handleAddChild}
                                    onRemoveChild={handleRemoveChild}
                                    onUpdateChild={handleUpdateChild}
                                    onAddPet={handleAddPet}
                                    onRemovePet={handleRemovePet}
                                    onUpdatePet={handleUpdatePet}
                                    saveChildrenPetsToProfile={
                                        saveChildrenPetsToProfile
                                    }
                                    onSaveChildrenPetsToProfileChange={
                                        setSaveChildrenPetsToProfile
                                    }
                                    loadingSuggestions={loadingSuggestions}
                                    selectedClientName={selectedClientName}
                                    handleClientSearch={handleClientSearch}
                                    handleClientChange={handleClientChange}
                                    selectedClientType={selectedClientType}
                                    location_types={location_types}
                                    sitter_preferences={sitter_preferences}
                                    client_types={client_types}
                                    discovery_sources={discovery_sources}
                                    hotels={hotels}
                                    hotelSuggestions={hotelSuggestions}
                                    selectedHotelName={selectedHotelName}
                                    handleHotelSearch={handleHotelSearch}
                                    calculateAge={calculateAge}
                                    pet_types={pet_types}
                                    isAddressLocked={isAddressLocked}
                                    setIsAddressLocked={setIsAddressLocked}
                                    showManualAddressInput={
                                        showManualAddressInput
                                    }
                                    setShowManualAddressInput={
                                        setShowManualAddressInput
                                    }
                                    addressValue={addressValue}
                                    setAddressValue={setAddressValue}
                                    caregiverSuggestions={caregiverSuggestions}
                                    caregiverAllIds={caregiverAllIds}
                                    caregiverTotal={caregiverTotal}
                                    caregiverCurrentPage={caregiverCurrentPage}
                                    caregiverLastPage={caregiverLastPage}
                                    loadingCaregiverRecommendations={
                                        loadingCaregiverRecommendations
                                    }
                                    loadingMoreCaregivers={
                                        loadingMoreCaregivers
                                    }
                                    onOpenNotifySheet={
                                        populateCaregiverSuggestions
                                    }
                                    onLoadMoreCaregivers={loadMoreCaregivers}
                                    onAgeFilterChange={onAgeFilterChange}
                                    onSearchChange={onSearchChange}
                                    sheetMode={sheetMode}
                                />

                                <BookingDetailsSection
                                    sheetMode={sheetMode}
                                    form={form}
                                    editingBooking={editingBooking}
                                    service_types={service_types}
                                    booking_statuses={booking_statuses}
                                    payment_statuses={payment_statuses}
                                    caregiverSuggestions={caregiverSuggestions}
                                    selectedCaregiverName={
                                        selectedCaregiverName
                                    }
                                    handleCaregiverSearch={
                                        handleCaregiverSearch
                                    }
                                    handleSubmit={handleSubmit}
                                    handleDelete={handleDelete}
                                    setIsSheetOpen={setIsSheetOpen}
                                />
                            </div>
                        </ErrorBoundary>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog open={showDeleteDialog} onOpenChange={setShowDeleteDialog}>
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Delete</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this booking? This
                            action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button variant="outline" onClick={handleCancelDelete}>
                            Cancel
                        </Button>
                        <Button
                            onClick={handleConfirmDelete}
                            disabled={form.processing}
                        >
                            {form.processing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={showPastBookingDialog}
                onOpenChange={handleCancelPastBooking}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Past Booking</DialogTitle>
                        <DialogDescription>
                            This booking has already ended. Financial fields are
                            locked and will not be saved. Only non-financial
                            changes will be applied. Continue?
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={handleCancelPastBooking}
                        >
                            Cancel
                        </Button>
                        <Button onClick={handleConfirmPastBooking}>
                            Continue Editing
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}
