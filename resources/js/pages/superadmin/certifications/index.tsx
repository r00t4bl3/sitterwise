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
        title: 'Certifications',
        href: '#',
    },
];

interface Certification {
    id: number;
    name: string;
    description: string | null;
    expires_required: boolean;
    is_active: boolean;
}

interface Props {
    [key: string]: unknown;
    certifications: Certification[];
}

export default function CertificationsIndex() {
    const { certifications } = usePage<Props>().props;
    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);

    const form = useForm<{
        name: string;
        description: string;
        expires_required: boolean;
        is_active: boolean;
    }>({
        name: '',
        description: '',
        expires_required: true,
        is_active: true,
    });

    const openCreateSheet = () => {
        setEditingId(null);
        form.setData('name', '');
        form.setData('description', '');
        form.setData('expires_required', true);
        form.setData('is_active', true);
        setIsSheetOpen(true);
    };

    const openEditSheet = (cert: Certification) => {
        setEditingId(cert.id);
        form.setData('name', cert.name);
        form.setData('description', cert.description || '');
        form.setData('expires_required', cert.expires_required);
        form.setData('is_active', cert.is_active);
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/certifications/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/certifications', {
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const handleDelete = (id: number) => {
        if (confirm('Are you sure you want to delete this certification?')) {
            form.delete(`/certifications/${id}`);
        }
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Certifications" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Certifications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Manage certifications visible to caregivers
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>Add Certification</Button>
                </div>

                <div className="rounded-[6px] border border-border bg-card">
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
                                    Expires Required
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
                            {certifications.map((cert) => (
                                <tr
                                    key={cert.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {cert.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {cert.description || '—'}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {cert.expires_required ? 'Yes' : 'No'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <span
                                            className={`inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium ${
                                                cert.is_active
                                                    ? 'bg-green-100 text-green-800'
                                                    : 'bg-gray-100 text-gray-800'
                                            }`}
                                        >
                                            {cert.is_active
                                                ? 'Active'
                                                : 'Inactive'}
                                        </span>
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(cert)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(cert.id)
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
                                {editingId
                                    ? 'Edit Certification'
                                    : 'Add Certification'}
                            </SheetTitle>
                            <SheetDescription>
                                Add or edit a certification type for caregivers.
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
                                    id="expires_required"
                                    checked={form.data.expires_required}
                                    onChange={(e) =>
                                        form.setData(
                                            'expires_required',
                                            e.target.checked,
                                        )
                                    }
                                    className="h-4 w-4 rounded border-input"
                                />
                                <label
                                    htmlFor="expires_required"
                                    className="text-sm text-foreground"
                                >
                                    Expiration Required
                                </label>
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
