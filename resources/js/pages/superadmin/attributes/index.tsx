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
        title: 'Attributes',
        href: '#',
    },
];

interface Attribute {
    id: number;
    name: string;
    slug: string;
    type: string;
    entity_type: string;
    is_active: boolean;
    sort_order: number;
}

interface Props {
    [key: string]: unknown;
    attributes: Attribute[];
}

export default function AttributesIndex() {
    const { attributes } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<{
        name: string;
        type: string;
        entity_type: string;
        is_active: boolean;
    }>({
        name: '',
        type: 'boolean',
        entity_type: 'caregiver',
        is_active: true,
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('type', 'boolean');
        form.setData('entity_type', 'caregiver');
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (attr: Attribute) => {
        setEditingId(attr.id);
        form.setData('name', attr.name);
        form.setData('type', attr.type);
        form.setData('entity_type', attr.entity_type);
        form.setData('is_active', attr.is_active);
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/attributes/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/attributes', {
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this attribute?')) {
            form.delete(`/attributes/${id}`);
        }
    };

    const entityTypeLabel = (type: string) => {
        switch (type) {
            case 'caregiver':
                return 'Caregiver';
            case 'client':
                return 'Client';
            case 'both':
                return 'Both';
            default:
                return type;
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Attributes" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Attributes
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage caregiver and client attributes
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Attribute</Button>
                </div>

                <div className="rounded-[6px] border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Type
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Entity
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
                            {attributes.map((attr) => (
                                <tr
                                    key={attr.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {attr.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground capitalize">
                                        {attr.type}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                attr.entity_type === 'caregiver'
                                                    ? 'bg-blue-100 text-blue-800'
                                                    : attr.entity_type ===
                                                        'client'
                                                      ? 'bg-purple-100 text-purple-800'
                                                      : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {entityTypeLabel(attr.entity_type)}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                attr.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {attr.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(attr)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(attr.id)
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
                                {editingId ? 'Edit Attribute' : 'Add Attribute'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a caregiver attribute.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="mt-4 space-y-4 px-4"
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
                                    Type
                                </label>
                                <select
                                    value={form.data.type}
                                    onChange={(e) =>
                                        form.setData('type', e.target.value)
                                    }
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                >
                                    <option value="boolean">Boolean</option>
                                    <option value="date">Date</option>
                                    <option value="text">Text</option>
                                    <option value="number">Number</option>
                                    <option value="select">Select</option>
                                </select>
                            </div>
                            <div>
                                <label className="text-sm font-medium text-foreground">
                                    Entity Type
                                </label>
                                <select
                                    value={form.data.entity_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'entity_type',
                                            e.target.value,
                                        )
                                    }
                                    className="mt-1 h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                >
                                    <option value="caregiver">Caregiver</option>
                                    <option value="client">Client</option>
                                    <option value="both">Both</option>
                                </select>
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
