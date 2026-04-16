import { Head, useForm, usePage } from '@inertiajs/react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
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
        title: 'Specialties',
        href: '#',
    },
];

interface Specialty {
    id: number;
    name: string;
    description: string | null;
    is_active: boolean;
    sort_order: number;
}

interface Props {
    [key: string]: unknown;
    specialties: Specialty[];
}

export default function SpecialtiesIndex() {
    const { specialties } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<{
        name: string;
        description: string;
        is_active: boolean;
    }>({
        name: '',
        description: '',
        is_active: true,
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('description', '');
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (specialty: Specialty) => {
        setEditingId(specialty.id);
        form.setData('name', specialty.name);
        form.setData('description', specialty.description || '');
        form.setData('is_active', specialty.is_active);
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/specialties/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/specialties', {
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this specialty?')) {
            form.delete(`/specialties/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Specialties" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Specialties
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage specialties visible to caregivers
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Specialty</Button>
                </div>

                <div className="border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
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
                            {specialties.map((specialty) => (
                                <tr
                                    key={specialty.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {specialty.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {specialty.description || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                specialty.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {specialty.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() =>
                                                openEditSheet(specialty)
                                            }
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(specialty.id)
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
                                {editingId ? 'Edit Specialty' : 'Add Specialty'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a specialty type.
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
                            <div className="gap-2 pt-4">
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
            </div>
        </AppLayout>
    );
}
