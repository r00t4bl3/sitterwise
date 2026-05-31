import { Head, useForm, usePage } from '@inertiajs/react';
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
    options: string[] | null;
}

interface Props {
    [key: string]: unknown;
    attributes: Attribute[];
}

export default function AttributesIndex() {
    const { attributes } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const form = useForm<{
        name: string;
        type: string;
        entity_type: string;
        is_active: boolean;
        options: string | string[];
    }>({
        name: '',
        type: 'boolean',
        entity_type: 'caregiver',
        is_active: true,
        options: '',
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('type', 'boolean');
        form.setData('entity_type', 'caregiver');
        form.setData('is_active', true);
        form.setData('options', '');
        setIsSheetOpen(true);
    };

    const openEditSheet = (attr: Attribute) => {
        setEditingId(attr.id);
        form.setData('name', attr.name);
        form.setData('type', attr.type);
        form.setData('entity_type', attr.entity_type);
        form.setData('is_active', attr.is_active);
        form.setData(
            'options',
            Array.isArray(attr.options) ? attr.options.join(', ') : '',
        );
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        const optionsValue = form.data.options;

        if (
            form.data.type === 'select' &&
            typeof optionsValue === 'string' &&
            optionsValue.trim()
        ) {
            form.setData(
                'options',
                optionsValue
                    .split(',')
                    .map((o) => o.trim())
                    .filter(Boolean),
            );
        } else {
            form.setData('options', []);
        }

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
        setDeletingId(id);
        setIsDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deletingId) {
            form.delete(`/attributes/${deletingId}`);
            setIsDialogOpen(false);
            setDeletingId(null);
        }
    };

    const handleCancelDelete = () => {
        setIsDialogOpen(false);
        setDeletingId(null);
    };

    const entityTypeLabel = (type: string) => {
        switch (type) {
            case 'caregiver':
                return 'Caregiver';
            case 'client':
                return 'Client';
            case 'booking':
                return 'Booking';
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

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-table-header">
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
                            className="space-y-4 px-4"
                            id="attribute-form"
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
                            <div className="grid gap-2">
                                <Label htmlFor="type">Type</Label>
                                <Select
                                    value={form.data.type}
                                    onValueChange={(value) =>
                                        form.setData('type', value)
                                    }
                                >
                                    <SelectTrigger id="type">
                                        <SelectValue placeholder="Select type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="boolean">
                                            Boolean
                                        </SelectItem>
                                        <SelectItem value="date">
                                            Date
                                        </SelectItem>
                                        <SelectItem value="text">
                                            Text
                                        </SelectItem>
                                        <SelectItem value="number">
                                            Number
                                        </SelectItem>
                                        <SelectItem value="select">
                                            Select
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="entity_type">Entity Type</Label>
                                <Select
                                    value={form.data.entity_type}
                                    onValueChange={(value) =>
                                        form.setData('entity_type', value)
                                    }
                                >
                                    <SelectTrigger id="entity_type">
                                        <SelectValue placeholder="Select entity type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="caregiver">
                                            Caregiver
                                        </SelectItem>
                                        <SelectItem value="client">
                                            Client
                                        </SelectItem>
                                        <SelectItem value="booking">
                                            Booking
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                            </div>
                            {form.data.type === 'select' && (
                                <div className="grid gap-2">
                                    <Label htmlFor="options">
                                        Options (comma-separated)
                                    </Label>
                                    <Input
                                        id="options"
                                        value={
                                            typeof form.data.options ===
                                            'string'
                                                ? form.data.options
                                                : ''
                                        }
                                        onChange={(e) =>
                                            form.setData(
                                                'options',
                                                e.target.value,
                                            )
                                        }
                                        placeholder="Option 1, Option 2, Option 3"
                                    />
                                </div>
                            )}
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
                                Are you sure you want to delete this attribute?
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
