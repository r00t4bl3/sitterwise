import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Trash2 } from 'lucide-react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
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
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Locations',
        href: '#',
    },
];

interface Location {
    id: number;
    name: string;
    svg_icon: string | null;
    is_active: boolean;
}

interface ZipCode {
    id: number;
    zip_code: string;
    area: string | null;
    location_id: number | null;
    location_name: string | null;
}

interface Props {
    [key: string]: unknown;
    locations: Location[];
    zipCodes: ZipCode[];
}

const UNASSIGNED = 'unassigned';

export default function LocationsIndex() {
    const { locations, zipCodes } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);
    const [isZipSheetOpen, setIsZipSheetOpen] = useState(false);
    const [deletingZip, setDeletingZip] = useState<ZipCode | null>(null);
    const [zipSearch, setZipSearch] = useState('');

    const form = useForm<{
        name: string;
        svg_icon: string;
        is_active: boolean;
    }>({
        name: '',
        svg_icon: '',
        is_active: true,
    });

    const zipForm = useForm<{
        zip_code: string;
        area: string;
        location_id: string;
    }>({
        zip_code: '',
        area: '',
        location_id: '',
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('svg_icon', '');
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (location: Location) => {
        setEditingId(location.id);
        form.setData('name', location.name);
        form.setData('svg_icon', location.svg_icon || '');
        form.setData('is_active', location.is_active);
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/locations/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/locations', {
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
            form.delete(`/locations/${deletingId}`);
            setIsDialogOpen(false);
            setDeletingId(null);
        }
    };

    const handleCancelDelete = () => {
        setIsDialogOpen(false);
        setDeletingId(null);
    };

    const openZipSheet = () => {
        zipForm.reset();
        zipForm.clearErrors();
        setIsZipSheetOpen(true);
    };

    const handleAddZip = (e: React.FormEvent) => {
        e.preventDefault();
        zipForm.post('/zip-codes', {
            preserveScroll: true,
            onSuccess: () => {
                zipForm.reset();
                setIsZipSheetOpen(false);
            },
        });
    };

    const handleReassignZip = (zipId: number, locationId: string) => {
        router.patch(
            `/zip-codes/${zipId}`,
            { location_id: locationId === UNASSIGNED ? null : locationId },
            { preserveScroll: true },
        );
    };

    const handleConfirmDeleteZip = () => {
        if (deletingZip) {
            router.delete(`/zip-codes/${deletingZip.id}`, {
                preserveScroll: true,
                onSuccess: () => setDeletingZip(null),
            });
        }
    };

    const filteredZips = zipCodes.filter((zip) => {
        const q = zipSearch.trim().toLowerCase();

        if (!q) {
            return true;
        }

        return (
            zip.zip_code.toLowerCase().includes(q) ||
            (zip.area?.toLowerCase().includes(q) ?? false) ||
            (zip.location_name?.toLowerCase().includes(q) ?? false)
        );
    });

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locations" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-8 p-4">
                <div>
                    <div className="flex items-center justify-between">
                        <div>
                            <h1 className="font-serif text-2xl font-bold text-foreground">
                                Service Areas
                            </h1>
                            <p className="text-sm text-muted-foreground">
                                Manage the regions caregivers are matched to.
                            </p>
                        </div>
                        <Button onClick={openCreateSheet}>Add Area</Button>
                    </div>

                    <div className="mt-4 overflow-x-auto border border-border bg-card">
                        <table className="w-full min-w-[500px]">
                            <thead>
                                <tr className="bg-table-header">
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                        Icon
                                    </th>
                                    <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                        Name
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
                                {locations.map((location) => (
                                    <tr
                                        key={location.id}
                                        className="border-b border-border transition hover:bg-blush"
                                    >
                                        <td className="flex items-center px-4 py-3">
                                            {location.svg_icon ? (
                                                <div
                                                    className="h-4 w-4"
                                                    dangerouslySetInnerHTML={{
                                                        __html: location.svg_icon,
                                                    }}
                                                />
                                            ) : (
                                                <span className="h-6 w-6 text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </td>
                                        <td className="px-4 py-3 text-sm font-medium text-foreground">
                                            {location.name}
                                        </td>
                                        <td className="px-4 py-3">
                                            <span
                                                className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${location.is_active ? 'bg-green-100 text-green-800' : 'bg-gray-100 text-gray-800'}`}
                                            >
                                                {location.is_active
                                                    ? 'Active'
                                                    : 'Inactive'}
                                            </span>
                                        </td>
                                        <td className="flex justify-end gap-x-2 px-4 py-3">
                                            <Button
                                                onClick={() =>
                                                    openEditSheet(location)
                                                }
                                                className="h-8"
                                            >
                                                Edit
                                            </Button>
                                            <Button
                                                variant="secondary"
                                                onClick={() =>
                                                    handleDelete(location.id)
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
                </div>

                {/* Zip code -> region management */}
                <div>
                    <div className="flex items-center justify-between">
                        <div>
                            <h2 className="font-serif text-xl font-bold text-foreground">
                                Zip Codes
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                Assign each zip to a region. A booking's area of
                                town is derived from its zip. Re-assign a zip by
                                changing its region below.
                            </p>
                        </div>
                        <Button onClick={openZipSheet}>Add Zip</Button>
                    </div>

                    <div className="mt-4">
                        <Input
                            type="text"
                            placeholder="Search zip, area, or region..."
                            className="mb-2 max-w-sm"
                            value={zipSearch}
                            onChange={(e) => setZipSearch(e.target.value)}
                        />
                        <div className="overflow-x-auto border border-border bg-card">
                            <table className="w-full min-w-[600px]">
                                <thead>
                                    <tr className="bg-table-header">
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Zip
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Area
                                        </th>
                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Region
                                        </th>
                                        <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                            Actions
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {filteredZips.map((zip) => (
                                        <tr
                                            key={zip.id}
                                            className="border-b border-border transition hover:bg-blush"
                                        >
                                            <td className="px-4 py-3 text-sm font-medium text-foreground">
                                                {zip.zip_code}
                                            </td>
                                            <td className="px-4 py-3 text-sm text-muted-foreground">
                                                {zip.area || '—'}
                                            </td>
                                            <td className="px-4 py-3">
                                                <Select
                                                    value={
                                                        zip.location_id
                                                            ? String(
                                                                  zip.location_id,
                                                              )
                                                            : UNASSIGNED
                                                    }
                                                    onValueChange={(v) =>
                                                        handleReassignZip(
                                                            zip.id,
                                                            v,
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger className="w-44">
                                                        <SelectValue />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        <SelectItem
                                                            value={UNASSIGNED}
                                                        >
                                                            Unassigned
                                                        </SelectItem>
                                                        {locations.map((l) => (
                                                            <SelectItem
                                                                key={l.id}
                                                                value={String(
                                                                    l.id,
                                                                )}
                                                            >
                                                                {l.name}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </td>
                                            <td className="flex justify-end px-4 py-3">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    className="h-8 w-8 text-muted-foreground hover:text-destructive"
                                                    onClick={() =>
                                                        setDeletingZip(zip)
                                                    }
                                                    aria-label="Remove zip"
                                                >
                                                    <Trash2 className="h-4 w-4" />
                                                </Button>
                                            </td>
                                        </tr>
                                    ))}
                                    {filteredZips.length === 0 && (
                                        <tr>
                                            <td
                                                colSpan={4}
                                                className="px-4 py-6 text-center text-sm text-muted-foreground"
                                            >
                                                No zip codes found.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                {/* Add zip sheet */}
                <Sheet open={isZipSheetOpen} onOpenChange={setIsZipSheetOpen}>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>Add Zip Code</SheetTitle>
                            <SheetDescription>
                                Assign a zip code to a region.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleAddZip}
                            className="space-y-4 px-4"
                        >
                            <div className="space-y-2">
                                <Label>Zip code</Label>
                                <Input
                                    type="text"
                                    inputMode="numeric"
                                    placeholder="92101"
                                    value={zipForm.data.zip_code}
                                    onChange={(e) =>
                                        zipForm.setData(
                                            'zip_code',
                                            e.target.value,
                                        )
                                    }
                                />
                                {zipForm.errors.zip_code && (
                                    <p className="text-sm text-destructive">
                                        {zipForm.errors.zip_code}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>Area (neighborhood)</Label>
                                <Input
                                    type="text"
                                    placeholder="Core-Columbia"
                                    value={zipForm.data.area}
                                    onChange={(e) =>
                                        zipForm.setData('area', e.target.value)
                                    }
                                />
                            </div>
                            <div className="space-y-2">
                                <Label>Region</Label>
                                <Select
                                    value={zipForm.data.location_id}
                                    onValueChange={(v) =>
                                        zipForm.setData('location_id', v)
                                    }
                                >
                                    <SelectTrigger>
                                        <SelectValue placeholder="Select region" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {locations.map((l) => (
                                            <SelectItem
                                                key={l.id}
                                                value={String(l.id)}
                                            >
                                                {l.name}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>

                            <div className="w-full space-y-2">
                                <Button
                                    type="submit"
                                    className="w-full"
                                    disabled={zipForm.processing}
                                >
                                    {zipForm.processing ? 'Saving...' : 'Save'}
                                </Button>
                                <Button
                                    variant="secondary"
                                    type="button"
                                    onClick={() => setIsZipSheetOpen(false)}
                                    className="mt-2 w-full"
                                >
                                    Cancel
                                </Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                {/* Location CRUD sheet */}
                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>
                                {editingId ? 'Edit Area' : 'Add Area'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a service region.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-4 px-4"
                        >
                            <div className="space-y-2">
                                <Label>Name</Label>
                                <Input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    required
                                />
                                {form.errors.name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <Label>SVG Icon</Label>
                                <Textarea
                                    value={form.data.svg_icon}
                                    onChange={(e) =>
                                        form.setData('svg_icon', e.target.value)
                                    }
                                    className="font-mono"
                                    rows={4}
                                    placeholder='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="..."/></svg>'
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_active',
                                            checked as boolean,
                                        )
                                    }
                                />
                                <Label
                                    htmlFor="is_active"
                                    className="text-sm font-normal text-foreground"
                                >
                                    Active
                                </Label>
                            </div>

                            <div className="w-full space-y-2">
                                <Button type="submit" className="w-full">
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

                {/* Zip delete confirmation dialog */}
                <Dialog
                    open={deletingZip !== null}
                    onOpenChange={(open) => !open && setDeletingZip(null)}
                >
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Remove Zip Code</DialogTitle>
                            <DialogDescription>
                                Remove zip {deletingZip?.zip_code}
                                {deletingZip?.location_name
                                    ? ` from ${deletingZip.location_name}`
                                    : ''}
                                ? Bookings with this zip will no longer show an
                                area badge.
                            </DialogDescription>
                        </DialogHeader>
                        <DialogFooter>
                            <Button
                                variant="outline"
                                onClick={() => setDeletingZip(null)}
                            >
                                Cancel
                            </Button>
                            <Button onClick={handleConfirmDeleteZip}>
                                Remove
                            </Button>
                        </DialogFooter>
                    </DialogContent>
                </Dialog>

                {/* Delete confirmation dialog */}
                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Confirm Delete</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this area? This
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
