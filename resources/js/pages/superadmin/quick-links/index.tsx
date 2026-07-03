import { Head, useForm, usePage } from '@inertiajs/react';
import {
    ExternalLink,
    Link as LinkIcon,
    Globe,
    FileText,
    BookOpen,
    Phone,
    Mail,
    HelpCircle,
    Home,
    Calendar,
    Users,
} from 'lucide-react';
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
        title: 'Quick Links',
        href: '#',
    },
];

interface QuickLink {
    id: number;
    title: string;
    url: string;
    description: string | null;
    icon: string | null;
    sort_order: number;
    is_active: boolean;
    is_external: boolean;
    visible_for_roles: string[];
}

const iconOptions: Record<string, typeof LinkIcon> = {
    ExternalLink,
    Link: LinkIcon,
    Globe,
    FileText,
    BookOpen,
    Phone,
    Mail,
    HelpCircle,
    Home,
    Calendar,
    Users,
};

const roleLabels: Record<string, string> = {
    admin: 'Admin',
    super_admin: 'Super Admin',
    caregiver: 'Caregiver',
    client: 'Client',
};

interface Props {
    [key: string]: unknown;
    quickLinks: QuickLink[];
}

export default function QuickLinksIndex() {
    const { quickLinks } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDialogOpen, setIsDialogOpen] = useState(false);

    const form = useForm({
        title: '',
        url: '',
        description: '',
        icon: 'Link',
        sort_order: 0,
        is_active: true,
        is_external: true,
        visible_for_roles: ['admin', 'super_admin'],
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.reset();
        setIsSheetOpen(true);
    };

    const openEditSheet = (link: QuickLink) => {
        setEditingId(link.id);
        form.setData({
            title: link.title,
            url: link.url,
            description: link.description || '',
            icon: link.icon || 'Link',
            sort_order: link.sort_order,
            is_active: link.is_active,
            is_external: link.is_external,
            visible_for_roles: link.visible_for_roles ?? [
                'admin',
                'super_admin',
            ],
        });
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/quick-links/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/quick-links', {
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
            form.delete(`/quick-links/${deletingId}`, {
                onSuccess: () => {
                    setIsDialogOpen(false);
                    setDeletingId(null);
                },
            });
        }
    };

    const handleCancelDelete = () => {
        setIsDialogOpen(false);
        setDeletingId(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Quick Links" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Quick Links
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage useful links for the dashboard
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Quick Link</Button>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[600px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Title
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Icon
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    URL
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Active
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    External
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Visible For
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {quickLinks.map((link) => (
                                <tr
                                    key={link.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        <div className="flex items-center gap-2">
                                            {(() => {
                                                const IconComp =
                                                    iconOptions[
                                                        link.icon ?? 'Link'
                                                    ] ?? LinkIcon;

                                                return (
                                                    <IconComp className="h-4 w-4" />
                                                );
                                            })()}
                                            {link.title}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {(() => {
                                            const IconComp =
                                                iconOptions[
                                                    link.icon ?? 'Link'
                                                ] ?? LinkIcon;

                                            return (
                                                <div className="flex h-8 w-8 items-center justify-center rounded bg-blue-100">
                                                    <IconComp className="h-4 w-4 text-blue-600" />
                                                </div>
                                            );
                                        })()}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        <a
                                            href={link.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            className="text-blue-600 hover:underline"
                                        >
                                            {link.url}
                                        </a>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                link.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {link.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                link.is_external
                                                    ? 'bg-blue-100 text-blue-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {link.is_external ? 'Yes' : 'No'}
                                        </span>
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex flex-wrap gap-1">
                                            {(link.visible_for_roles ?? []).map(
                                                (role) => (
                                                    <span
                                                        key={role}
                                                        className="inline-flex items-center rounded-full bg-purple-100 px-2 py-0.5 text-xs font-medium text-purple-800"
                                                    >
                                                        {roleLabels[role] ??
                                                            role}
                                                    </span>
                                                ),
                                            )}
                                        </div>
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(link)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(link.id)
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
                    <SheetContent side="right" className="overflow-y-auto">
                        <SheetHeader>
                            <SheetTitle>
                                {editingId
                                    ? 'Edit Quick Link'
                                    : 'Add Quick Link'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a quick link.
                            </SheetDescription>
                        </SheetHeader>
                        <form
                            onSubmit={handleSubmit}
                            className="space-y-4 overflow-y-auto px-4"
                        >
                            <div className="grid gap-2">
                                <Label htmlFor="title">Title</Label>
                                <Input
                                    id="title"
                                    value={form.data.title}
                                    onChange={(e) =>
                                        form.setData('title', e.target.value)
                                    }
                                    required
                                />
                                {form.errors.title && (
                                    <p className="text-xs text-red-500">
                                        {form.errors.title}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="url">URL</Label>
                                <Input
                                    id="url"
                                    value={form.data.url}
                                    onChange={(e) =>
                                        form.setData('url', e.target.value)
                                    }
                                    required
                                />
                                {form.errors.url && (
                                    <p className="text-xs text-red-500">
                                        {form.errors.url}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="description">Description</Label>
                                <Textarea
                                    id="description"
                                    value={form.data.description}
                                    onChange={(e) =>
                                        form.setData(
                                            'description',
                                            e.target.value,
                                        )
                                    }
                                />
                                {form.errors.description && (
                                    <p className="text-xs text-red-500">
                                        {form.errors.description}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="sort_order">Sort Order</Label>
                                <Input
                                    id="sort_order"
                                    type="number"
                                    value={form.data.sort_order}
                                    onChange={(e) =>
                                        form.setData(
                                            'sort_order',
                                            parseInt(e.target.value) || 0,
                                        )
                                    }
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
                            <div className="flex items-center gap-2">
                                <Checkbox
                                    id="is_external"
                                    checked={form.data.is_external}
                                    onCheckedChange={(checked) =>
                                        form.setData(
                                            'is_external',
                                            checked === true,
                                        )
                                    }
                                />
                                <Label htmlFor="is_external">
                                    Open in new tab
                                </Label>
                            </div>
                            <div className="grid gap-2">
                                <Label>Icon</Label>
                                <Select
                                    value={form.data.icon}
                                    onValueChange={(value) =>
                                        form.setData('icon', value)
                                    }
                                >
                                    <SelectTrigger className="h-10 w-full">
                                        <SelectValue placeholder="Select an icon" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {Object.entries(iconOptions).map(
                                            ([name, IconComp]) => (
                                                <SelectItem
                                                    key={name}
                                                    value={name}
                                                >
                                                    <div className="flex items-center gap-2">
                                                        <IconComp className="h-4 w-4" />
                                                        {name}
                                                    </div>
                                                </SelectItem>
                                            ),
                                        )}
                                    </SelectContent>
                                </Select>
                                {form.errors.icon && (
                                    <p className="text-xs text-red-500">
                                        {form.errors.icon}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label>Visible For Roles</Label>
                                <div className="space-y-2">
                                    {Object.entries(roleLabels).map(
                                        ([role, label]) => (
                                            <div
                                                key={role}
                                                className="flex items-center gap-2"
                                            >
                                                <Checkbox
                                                    id={`role-${role}`}
                                                    checked={form.data.visible_for_roles.includes(
                                                        role,
                                                    )}
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const roles = [
                                                            ...form.data
                                                                .visible_for_roles,
                                                        ];

                                                        if (checked) {
                                                            if (
                                                                !roles.includes(
                                                                    role,
                                                                )
                                                            ) {
                                                                roles.push(
                                                                    role,
                                                                );
                                                            }
                                                        } else {
                                                            const idx =
                                                                roles.indexOf(
                                                                    role,
                                                                );

                                                            if (idx !== -1) {
                                                                roles.splice(
                                                                    idx,
                                                                    1,
                                                                );
                                                            }
                                                        }

                                                        form.setData(
                                                            'visible_for_roles',
                                                            roles,
                                                        );
                                                    }}
                                                />
                                                <Label
                                                    htmlFor={`role-${role}`}
                                                    className="text-sm font-normal"
                                                >
                                                    {label}
                                                </Label>
                                            </div>
                                        ),
                                    )}
                                </div>
                                {form.errors.visible_for_roles && (
                                    <p className="text-xs text-red-500">
                                        {form.errors.visible_for_roles}
                                    </p>
                                )}
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
                                Are you sure you want to delete this quick link?
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
