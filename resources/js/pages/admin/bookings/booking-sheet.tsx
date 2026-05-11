import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { BookingDetailsSection } from './booking-details-section';
import { PersonalInfoSection } from './personal-info-section';
import type { UseBookingSheetReturn } from './use-booking-sheet';

type BookingSheetProps = UseBookingSheetReturn;

function calculateAge(
    birthYear: number | null,
    birthMonth: number | null,
): string {
    if (!birthYear) {
        return '-';
    }

    const today = new Date();
    const birthDate = new Date(birthYear, (birthMonth || 1) - 1, 1);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
        age--;
    }

    if (age < 1) {
        const months =
            (today.getFullYear() - birthDate.getFullYear()) * 12 +
            today.getMonth() -
            birthDate.getMonth();

        return `${months} months`;
    }

    return `${age} years`;
}

export function BookingSheet({
    isSheetOpen,
    setIsSheetOpen,
    isLoading,
    editingBooking,
    sheetMode,
    showDeleteDialog,
    setShowDeleteDialog,
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
    booking_attributes,
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
}: BookingSheetProps) {
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
                        <div className="space-y-4 px-4">
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
                                booking_attributes={booking_attributes}
                                hotels={hotels}
                                hotelSuggestions={hotelSuggestions}
                                selectedHotelName={selectedHotelName}
                                handleHotelSearch={handleHotelSearch}
                                calculateAge={calculateAge}
                                pet_types={pet_types}
                                isAddressLocked={isAddressLocked}
                                setIsAddressLocked={setIsAddressLocked}
                                showManualAddressInput={showManualAddressInput}
                                setShowManualAddressInput={
                                    setShowManualAddressInput
                                }
                                addressValue={addressValue}
                                setAddressValue={setAddressValue}
                                caregiverSuggestions={caregiverSuggestions}
                                onOpenNotifySheet={populateCaregiverSuggestions}
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
                                selectedCaregiverName={selectedCaregiverName}
                                handleCaregiverSearch={handleCaregiverSearch}
                                handleSubmit={handleSubmit}
                                handleDelete={handleDelete}
                                setIsSheetOpen={setIsSheetOpen}
                            />
                        </div>
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
        </>
    );
}
