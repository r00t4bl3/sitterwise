import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';
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
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Hotels',
        href: '#',
    },
];

interface Hotel {
    id: number;
    name: string;
    line1: string | null;
    line2: string | null;
    city: string | null;
    state: string | null;
    zip: string | null;
    parking_instructions: string | null;
    hourly_rate: number | null;
    resort_fee: number | null;
    contact_name: string | null;
    contact_phone: string | null;
    admin_notes: string | null;
    is_active: boolean;
}

interface Props {
    [key: string]: unknown;
    hotels: Hotel[];
}

export default function HotelsIndex() {
    const { hotels } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const form = useForm<{
        name: string;
        line1: string;
        line2: string;
        city: string;
        state: string;
        zip: string;
        parking_instructions: string;
        hourly_rate: string;
        resort_fee: string;
        contact_name: string;
        contact_phone: string;
        admin_notes: string;
        is_active: boolean;
    }>({
        name: '',
        line1: '',
        line2: '',
        city: '',
        state: '',
        zip: '',
        parking_instructions: '',
        hourly_rate: '',
        resort_fee: '',
        contact_name: '',
        contact_phone: '',
        admin_notes: '',
        is_active: true,
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('line1', '');
        form.setData('line2', '');
        form.setData('city', '');
        form.setData('state', '');
        form.setData('zip', '');
        form.setData('parking_instructions', '');
        form.setData('hourly_rate', '');
        form.setData('resort_fee', '');
        form.setData('contact_name', '');
        form.setData('contact_phone', '');
        form.setData('admin_notes', '');
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (hotel: Hotel) => {
        setEditingId(hotel.id);
        form.setData('name', hotel.name);
        form.setData('line1', hotel.line1 || '');
        form.setData('line2', hotel.line2 || '');
        form.setData('city', hotel.city || '');
        form.setData('state', hotel.state || '');
        form.setData('zip', hotel.zip || '');
        form.setData('parking_instructions', hotel.parking_instructions || '');
        form.setData('hourly_rate', hotel.hourly_rate?.toString() || '');
        form.setData('resort_fee', hotel.resort_fee?.toString() || '');
        form.setData('contact_name', hotel.contact_name || '');
        form.setData('contact_phone', hotel.contact_phone || '');
        form.setData('admin_notes', hotel.admin_notes || '');
        form.setData('is_active', hotel.is_active);
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/hotels/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/hotels', {
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        setIsDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deletingId) {
            form.delete(`/hotels/${deletingId}`);
            setIsDialogOpen(false);
            setDeletingId(null);
        }
    };

    const handleCancelDelete = () => {
        setIsDialogOpen(false);
        setDeletingId(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Hotels" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Hotels
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage hotels and their properties
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Hotel</Button>
                </div>

                <div className="border border-border bg-card overflow-x-auto">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Location
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Contact
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Active
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {hotels.map((hotel) => (
                                <tr
                                    key={hotel.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {hotel.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {hotel.city && hotel.state
                                            ? `${hotel.city}, ${hotel.state}`
                                            : hotel.line1 || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {hotel.contact_name ||
                                            hotel.contact_phone ||
                                            '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                hotel.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {hotel.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(hotel)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(hotel.id)
                                            }
                                            className="h-8"
                                        >
                                            Delete
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent
                        side="right"
                        className="flex w-full flex-col sm:max-w-lg"
                    >
                        <SheetHeader>
                            <SheetTitle>
                                {editingId ? 'Edit Hotel' : 'Add Hotel'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a hotel partner.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="flex-grow space-y-4 overflow-y-auto px-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="name">Name</Label>
                                <Input
                                    id="name"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                            </div>
                            <AddressAutocomplete form={form} prefix="" />
                            <div className="grid gap-2">
                                <Label htmlFor="parking_instructions">
                                    Parking Instructions
                                </Label>
                                <Textarea
                                    id="parking_instructions"
                                    value={form.data.parking_instructions}
                                    onChange={(e) =>
                                        form.setData(
                                            'parking_instructions',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                    required
                                />
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="hourly_rate">
                                        Hourly Rate ($)
                                    </Label>
                                    <Input
                                        id="hourly_rate"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.data.hourly_rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'hourly_rate',
                                                e.target.value,
                                            )
                                        }
                                        required
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="resort_fee">
                                        Resort Fee ($)
                                    </Label>
                                    <Input
                                        id="resort_fee"
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        value={form.data.resort_fee}
                                        onChange={(e) =>
                                            form.setData(
                                                'resort_fee',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid grid-cols-2 gap-4">
                                <div className="grid gap-2">
                                    <Label htmlFor="contact_name">
                                        Contact Name
                                    </Label>
                                    <Input
                                        id="contact_name"
                                        value={form.data.contact_name}
                                        onChange={(e) =>
                                            form.setData(
                                                'contact_name',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="contact_phone">
                                        Contact Phone
                                    </Label>
                                    <Input
                                        id="contact_phone"
                                        value={form.data.contact_phone}
                                        onChange={(e) =>
                                            form.setData(
                                                'contact_phone',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="admin_notes">Admin Notes</Label>
                                <Textarea
                                    id="admin_notes"
                                    value={form.data.admin_notes}
                                    onChange={(e) =>
                                        form.setData(
                                            'admin_notes',
                                            e.target.value,
                                        )
                                    }
                                    rows={2}
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_active">Active</Label>
                            </div>
                            <div className="mt-10 w-full space-y-2">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={form.processing}
                                >
                                    {form.processing ? 'Saving...' : 'Save'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    onClick={() => setIsSheetOpen(false)}
                                    className="mt-2 w-full"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Confirm Delete</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this hotel? This
                                action cannot be undone.
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
            </div>
        </AppLayout>
    );
}
