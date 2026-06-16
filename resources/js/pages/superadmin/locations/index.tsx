import { Head, useForm, usePage } from '@inertiajs/react';
import { Plus, Trash2 } from 'lucide-react';
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
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
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
    cities: string[];
}

interface Props {
    [key: string]: unknown;
    locations: Location[];
    knownCities: string[];
}

export default function LocationsIndex() {
    const { locations, knownCities } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    // City editor state (shared with edit sheet)
    const [cityEditorCities, setCityEditorCities] = useState<string[]>([]);
    const [newCityInput, setNewCityInput] = useState('');

    const form = useForm<{
        name: string;
        svg_icon: string;
        is_active: boolean;
        cities: string[];
    }>({
        name: '',
        svg_icon: '',
        is_active: true,
        cities: [],
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('svg_icon', '');
        form.setData('is_active', true);
        form.setData('cities', []);
        setCityEditorCities([]);
        setNewCityInput('');
        setIsSheetOpen(true);
    };

    const openEditSheet = (location: Location) => {
        setEditingId(location.id);
        form.setData('name', location.name);
        form.setData('svg_icon', location.svg_icon || '');
        form.setData('is_active', location.is_active);
        form.setData('cities', [...location.cities]);
        setCityEditorCities([...location.cities]);
        setNewCityInput('');
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        form.transform((data) => ({
            ...data,
            cities: cityEditorCities.sort((a, b) => a.localeCompare(b)),
        }));

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

    // City editor handlers
    const handleAddCity = () => {
        const trimmed = newCityInput.trim();

        if (!trimmed) {
            return;
        }

        if (
            cityEditorCities
                .map((c) => c.toLowerCase())
                .includes(trimmed.toLowerCase())
        ) {
            return;
        }

        setCityEditorCities((prev) => [...prev, trimmed]);
        setNewCityInput('');
    };

    const handleRemoveCity = (index: number) => {
        setCityEditorCities((prev) => prev.filter((_, i) => i !== index));
    };

    const allAssignedExcludingCurrent = new Set(
        locations
            .filter((l) => l.id !== editingId)
            .flatMap((l) => l.cities)
            .map((c) => c.toLowerCase()),
    );

    const unassignedCities = knownCities.filter(
        (city) => !allAssignedExcludingCurrent.has(city.toLowerCase()),
    );

    const handleAssignUnassigned = (city: string) => {
        if (
            !cityEditorCities
                .map((c) => c.toLowerCase())
                .includes(city.toLowerCase())
        ) {
            setCityEditorCities((prev) => [...prev, city]);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Locations" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Locations
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage service areas and their cities
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Location</Button>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Icon
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Cities
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
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {location.cities.length > 0
                                            ? location.cities.join(', ')
                                            : '—'}
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

                {/* Location CRUD sheet */}
                <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>
                                {editingId ? 'Edit Location' : 'Add Location'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a service location.
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

                            <hr className="border-border" />

                            <div className="space-y-3">
                                <Label>Assigned Cities</Label>
                                <div className="flex flex-wrap gap-2">
                                    {cityEditorCities.map((city, index) => (
                                        <span
                                            key={`${city}-${index}`}
                                            className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-3 py-1 text-sm text-foreground"
                                        >
                                            {city}
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    handleRemoveCity(index)
                                                }
                                                className="ml-1 cursor-pointer text-muted-foreground hover:text-destructive"
                                            >
                                                <Trash2 className="h-3 w-3" />
                                            </button>
                                        </span>
                                    ))}
                                    {cityEditorCities.length === 0 && (
                                        <p className="text-sm text-muted-foreground italic">
                                            No cities assigned
                                        </p>
                                    )}
                                </div>

                                <div className="flex gap-2">
                                    <Input
                                        type="text"
                                        placeholder="Add a city..."
                                        value={newCityInput}
                                        onChange={(e) =>
                                            setNewCityInput(e.target.value)
                                        }
                                        onKeyDown={(e) => {
                                            if (e.key === 'Enter') {
                                                e.preventDefault();
                                                handleAddCity();
                                            }
                                        }}
                                    />
                                    <Button
                                        type="button"
                                        size="icon"
                                        variant="outline"
                                        onClick={handleAddCity}
                                    >
                                        <Plus className="h-4 w-4" />
                                    </Button>
                                </div>

                                {unassignedCities.filter(
                                    (city) =>
                                        !cityEditorCities
                                            .map((c) => c.toLowerCase())
                                            .includes(city.toLowerCase()),
                                ).length > 0 && (
                                    <div>
                                        <Label className="text-sm text-muted-foreground">
                                            Not yet assigned to any area
                                        </Label>
                                        <div className="mt-1 flex flex-wrap gap-1">
                                            {unassignedCities
                                                .filter(
                                                    (city) =>
                                                        !cityEditorCities
                                                            .map((c) =>
                                                                c.toLowerCase(),
                                                            )
                                                            .includes(
                                                                city.toLowerCase(),
                                                            ),
                                                )
                                                .map((city) => (
                                                    <Tooltip key={city}>
                                                        <TooltipTrigger asChild>
                                                            <span
                                                                className="inline-flex cursor-pointer items-center gap-1 rounded-full bg-muted px-2 py-0.5 text-xs text-muted-foreground transition-colors hover:bg-primary/10 hover:text-foreground"
                                                                onClick={() =>
                                                                    handleAssignUnassigned(
                                                                        city,
                                                                    )
                                                                }
                                                            >
                                                                {city}
                                                                <Plus className="h-3 w-3" />
                                                            </span>
                                                        </TooltipTrigger>
                                                        <TooltipContent>
                                                            Assign to{' '}
                                                            {form.data.name ||
                                                                'this location'}
                                                        </TooltipContent>
                                                    </Tooltip>
                                                ))}
                                        </div>
                                    </div>
                                )}
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

                {/* Delete confirmation dialog */}
                <Dialog open={isDialogOpen} onOpenChange={setIsDialogOpen}>
                    <DialogContent className="sm:max-w-md">
                        <DialogHeader>
                            <DialogTitle>Confirm Delete</DialogTitle>
                            <DialogDescription>
                                Are you sure you want to delete this location?
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
            </div>
        </AppLayout>
    );
}
