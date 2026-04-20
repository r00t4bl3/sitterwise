import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetFooter,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
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
    description: string | null;
    svg_icon: string | null;
    is_active: boolean;
}

interface Props {
    [key: string]: unknown;
    locations: Location[];
}

export default function LocationsIndex() {
    const { locations } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<{
        name: string;
        description: string;
        svg_icon: string;
        is_active: boolean;
    }>({
        name: '',
        description: '',
        svg_icon: '',
        is_active: true,
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('description', '');
        form.setData('svg_icon', '');
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (location: Location) => {
        setEditingId(location.id);
        form.setData('name', location.name);
        form.setData('description', location.description || '');
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
        if (confirm('Are you sure you want to delete this location?')) {
            form.delete(`/locations/${id}`);
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
                            Manage available locations
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Location</Button>
                </div>

                <div className="border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Icon
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Description
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
                                    <td className="px-4 py-3">
                                        {location.svg_icon ? (
                                            <div
                                                className="h-6 w-6"
                                                dangerouslySetInnerHTML={{
                                                    __html: location.svg_icon,
                                                }}
                                            />
                                        ) : (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {location.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {location.description || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                location.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
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
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Name
                                </label>
                                <input
                                    type="text"
                                    value={form.data.name}
                                    onChange={(e) =>
                                        form.setData('name', e.target.value)
                                    }
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                    required
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Description
                                </label>
                                <textarea
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring"
                                    rows={3}
                                />
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    SVG Icon
                                </label>
                                <textarea
                                    value={form.data.svg_icon}
                                    onChange={(e) =>
                                        form.setData('svg_icon', e.target.value)
                                    }
                                    className="mt-1 w-full rounded-[3px] border border-input bg-background px-3 py-2 font-mono text-sm outline-none focus:border-ring"
                                    rows={4}
                                    placeholder='<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor"><path d="..."/></svg>'
                                />
                            </div>
                            <div className="flex items-center gap-2">
                                <input
                                    type="checkbox"
                                    id="is_active"
                                    checked={form.data.is_active}
                                    onChange={(e) =>
                                        form.setData(
                                            'is_active',
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-input"
                                />
                                <label
                                    htmlFor="is_active"
                                    className="text-sm text-foreground"
                                >
                                    Active
                                </label>
                            </div>
                        </form>
                        <SheetFooter>
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
                        </SheetFooter>
                    </SheetContent>
                </Sheet>
            </div>
        </AppLayout>
    );
}
