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
    clientChildren,
    clientPets,
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
    newChildren,
    newPets,
    saveChildrenPetsToProfile,
    setSaveChildrenPetsToProfile,
    client_type_options,
    booking_attributes,
    sitter_preference_options,
    service_types,
    location_types,
    booking_statuses,
    payment_statuses,
    special_consideration_options,
    hotels,
    hotelSuggestions,
    caregiverSuggestions,
    handleClientSearch,
    handleHotelSearch,
    handleCaregiverSearch,
    handleClientChange,
    handleSpecialConsiderationChange,
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
                                <p className="text-sm">Loading booking details...</p>
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
                            clientChildren={clientChildren}
                            clientPets={clientPets}
                            newChildren={newChildren}
                            newPets={newPets}
                            onAddChild={handleAddChild}
                            onRemoveChild={handleRemoveChild}
                            onUpdateChild={handleUpdateChild}
                            onAddPet={handleAddPet}
                            onRemovePet={handleRemovePet}
                            onUpdatePet={handleUpdatePet}
                            saveChildrenPetsToProfile={saveChildrenPetsToProfile}
                            onSaveChildrenPetsToProfileChange={setSaveChildrenPetsToProfile}
                            loadingSuggestions={loadingSuggestions}
                            selectedClientName={selectedClientName}
                            handleClientSearch={handleClientSearch}
                            handleClientChange={handleClientChange}
                            selectedClientType={selectedClientType}
                            location_types={location_types}
                            sitter_preference_options={
                                sitter_preference_options
                            }
                            client_type_options={
                                client_type_options ?? [
                                    {
                                        value: 'resident',
                                        label: 'San Diego Resident',
                                    },
                                    {
                                        value: 'vacationer',
                                        label: 'Vacationer',
                                    },
                                    {
                                        value: 'invoiced',
                                        label: 'Invoiced',
                                    },
                                ]
                            }
                            booking_attributes={booking_attributes}
                            hotels={hotels}
                            hotelSuggestions={hotelSuggestions}
                            selectedHotelName={selectedHotelName}
                            handleHotelSearch={handleHotelSearch}
                            calculateAge={() => '-'}
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
                            onOpenNotifySheet={
                                populateCaregiverSuggestions
                            }
                        />

                        <BookingDetailsSection
                            form={form}
                            editingBooking={editingBooking}
                            service_types={service_types}
                            special_consideration_options={
                                special_consideration_options
                            }
                            booking_statuses={booking_statuses}
                            payment_statuses={payment_statuses}
                            caregiverSuggestions={caregiverSuggestions}
                            selectedCaregiverName={selectedCaregiverName}
                            handleCaregiverSearch={handleCaregiverSearch}
                            handleSpecialConsiderationChange={
                                handleSpecialConsiderationChange
                            }
                            handleSubmit={handleSubmit}
                            handleDelete={handleDelete}
                            setIsSheetOpen={setIsSheetOpen}
                        />
                    </div>
                    )}
                </SheetContent>
            </Sheet>

            <Dialog
                open={showDeleteDialog}
                onOpenChange={setShowDeleteDialog}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Delete</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this booking?
                            This action cannot be undone.
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={handleCancelDelete}
                        >
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