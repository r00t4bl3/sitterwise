import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
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
        title: 'Users',
        href: '/users',
    },
];

interface User {
    id: number;
    name: string;
    email: string;
    role: string;
    last_login_at: string | null;
}

interface Role {
    value: string;
    label: string;
}

interface Props {
    [key: string]: unknown;
    users: {
        data: User[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        links: Array<{
            url: string | null;
            label: string;
            active: boolean;
        }>;
    };
    roles: Role[];
    filters: {
        search: string | null;
        sort: string;
        direction: 'asc' | 'desc';
    };
}

const roleColors: Record<string, { bg: string; text: string }> = {
    super_admin: { bg: '#F3E8FF', text: '#6B21A8' },
    admin: { bg: '#E0E7FF', text: '#3730A3' },
    caregiver: { bg: '#D1FAE5', text: '#065F46' },
    client: { bg: '#DBEAFE', text: '#1E40AF' },
};

function RoleBadge({ role }: { role: string }) {
    const colors = roleColors[role] || { bg: '#F3F4F6', text: '#374151' };
    const labels: Record<string, string> = {
        super_admin: 'Super Admin',
        admin: 'Admin',
        caregiver: 'Caregiver',
        client: 'Client',
    };

    return (
        <span
            className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{ backgroundColor: colors.bg, color: colors.text }}
        >
            {labels[role] || role}
        </span>
    );
}

function formatLastLogin(date: string | null): string {
    if (!date) return 'Never';

    const d = new Date(date.replace(/\.\d+Z$/, 'Z'));

    if (isNaN(d.getTime())) return 'Never';

    return d.toLocaleDateString('en-US', {
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
    });
}

export default function UsersIndex() {
    const { users, roles, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );
    const sortFieldRef = useRef(filters.sort || 'id');
    const sortDirRef = useRef<'asc' | 'desc'>(
        (filters.direction as 'asc' | 'desc') || 'desc',
    );
    const sortField = filters.sort || 'id';
    const sortDir = (filters.direction as 'asc' | 'desc') || 'desc';

    const [isSheetOpen, setIsSheetOpen] = useState(false);
    const [editingId, setEditingId] = useState<number | null>(null);
    const [deletingId, setDeletingId] = useState<number | null>(null);
    const [isDeleteDialogOpen, setIsDeleteDialogOpen] = useState(false);
    const [resettingId, setResettingId] = useState<number | null>(null);
    const [isResetDialogOpen, setIsResetDialogOpen] = useState(false);

    const form = useForm<{
        first_name: string;
        last_name: string;
        email: string;
        role: string;
        password: string;
    }>({
        first_name: '',
        last_name: '',
        email: '',
        role: '',
        password: '',
    });

    const resetForm = useForm<{
        password: string;
    }>({
        password: '',
    });

    const applyFilters = (search: string) => {
        const params: Record<string, string> = {};

        if (search.trim()) {
            params.search = search.trim();
        }

        if (sortFieldRef.current !== 'id' || sortDirRef.current !== 'desc') {
            params.sort = sortFieldRef.current;
            params.direction = sortDirRef.current;
        }

        router.get('/users', params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSort = (field: string) => {
        const newDir = field === sortField && sortDir === 'asc' ? 'desc' : 'asc';
        sortFieldRef.current = field;
        sortDirRef.current = newDir;
        applyFilters(searchQuery);
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters(value);
        }, 300);
    };

    useEffect(() => {
        return () => clearTimeout(debounceTimer.current);
    }, []);

    useEffect(() => {
        sortFieldRef.current = filters.sort || 'id';
        sortDirRef.current =
            (filters.direction as 'asc' | 'desc') || 'desc';
    }, [filters.sort, filters.direction]);

    const openCreateSheet = () => {
        setEditingId(null);
        form.reset();
        form.setData('role', roles[0]?.value || '');
        setIsSheetOpen(true);
    };

    const openEditSheet = (user: User) => {
        setEditingId(user.id);
        const spaceIndex = user.name.indexOf(' ');
        form.setData('first_name', spaceIndex === -1 ? user.name : user.name.slice(0, spaceIndex));
        form.setData('last_name', spaceIndex === -1 ? '' : user.name.slice(spaceIndex + 1));
        form.setData('email', user.email);
        form.setData('role', user.role);
        form.setData('password', '');
        setIsSheetOpen(true);
    };

    const handleSubmit = (e: React.FormEvent) => {
        e.preventDefault();

        if (editingId) {
            form.patch(`/users/${editingId}`, {
                onSuccess: () => setIsSheetOpen(false),
            });
        } else {
            form.post('/users', {
                onSuccess: () => setIsSheetOpen(false),
            });
        }
    };

    const openResetDialog = (user: User) => {
        setResettingId(user.id);
        resetForm.reset();
        setIsResetDialogOpen(true);
    };

    const handleResetPassword = (e: React.FormEvent) => {
        e.preventDefault();

        if (resettingId) {
            resetForm.post(`/users/${resettingId}/reset-password`, {
                onSuccess: () => {
                    setIsResetDialogOpen(false);
                    setResettingId(null);
                },
            });
        }
    };

    const handleDelete = (id: number) => {
        setDeletingId(id);
        setIsDeleteDialogOpen(true);
    };

    const handleConfirmDelete = () => {
        if (deletingId) {
            form.delete(`/users/${deletingId}`, {
                onSuccess: () => {
                    setIsDeleteDialogOpen(false);
                    setDeletingId(null);
                },
            });
        }
    };

    const handleCancelDelete = () => {
        setIsDeleteDialogOpen(false);
        setDeletingId(null);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Users" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Users
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {users.total} users
                            {searchQuery && (
                                <span className="ml-1">
                                    (search: &quot;{searchQuery}&quot;)
                                </span>
                            )}
                        </p>
                    </div>
                    <Button onClick={openCreateSheet}>
                        Add User
                    </Button>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by name or email..."
                            value={searchQuery}
                            onChange={(e) =>
                                handleSearchChange(e.target.value)
                            }
                            className="h-8"
                        />
                        {searchQuery && (
                            <Button
                                size="sm"
                                variant="ghost"
                                onClick={() => handleSearchChange('')}
                                className="absolute top-1/2 right-2 -translate-y-1/2 text-muted-foreground hover:text-foreground"
                                type="button"
                            >
                                ×
                            </Button>
                        )}
                    </div>
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[700px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('id')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        ID
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'id' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'id' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('name')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Name
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'name' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'name' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('email')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Email
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'email' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'email' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() => handleSort('role')}
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Role
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField === 'role' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField === 'role' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    <button
                                        onClick={() =>
                                            handleSort('last_login_at')
                                        }
                                        className="flex cursor-pointer items-center gap-1 uppercase hover:text-primary"
                                    >
                                        Last Login
                                        <span className="text-[9px] leading-none">
                                            <span
                                                className={
                                                    sortField ===
                                                        'last_login_at' &&
                                                    sortDir === 'asc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▲
                                            </span>
                                            <span
                                                className={
                                                    sortField ===
                                                        'last_login_at' &&
                                                    sortDir === 'desc'
                                                        ? ''
                                                        : 'opacity-30'
                                                }
                                            >
                                                ▼
                                            </span>
                                        </span>
                                    </button>
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.data.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={6}
                                        className="px-4 py-8 text-center text-sm italic text-muted-foreground"
                                    >
                                        No users found.
                                    </td>
                                </tr>
                            )}
                            {users.data.map((user) => (
                                <tr
                                    key={user.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {user.id}
                                    </td>
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {user.name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {user.email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <RoleBadge role={user.role} />
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {formatLastLogin(user.last_login_at)}
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button
                                            onClick={() => openEditSheet(user)}
                                            className="h-8"
                                        >
                                            Edit
                                        </Button>
                                        <Button
                                            onClick={() =>
                                                openResetDialog(user)
                                            }
                                            className="h-8"
                                        >
                                            Reset
                                        </Button>
                                        <Button
                                            variant="secondary"
                                            onClick={() =>
                                                handleDelete(user.id)
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

                {users.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {users.current_page} of {users.last_page}
                        </p>
                        <div className="flex gap-1">
                            {users.links.map((link, index) => {
                                if (link.label === '...') {
                                    return null;
                                }

                                const isPrev =
                                    link.label.includes('Previous') ||
                                    link.label.includes('&laquo;');
                                const isNext =
                                    link.label.includes('Next') ||
                                    link.label.includes('&raquo;');

                                return (
                                    <Link
                                        key={index}
                                        href={link.url || '#'}
                                        className={`flex h-8 w-8 items-center justify-center rounded text-sm ${
                                            link.active
                                                ? 'bg-table-header text-white'
                                                : 'border border-border text-muted-foreground hover:bg-accent'
                                        } ${!link.url ? 'pointer-events-none opacity-50' : ''}`}
                                    >
                                        {isPrev ? (
                                            <ChevronLeft className="h-4 w-4" />
                                        ) : isNext ? (
                                            <ChevronRight className="h-4 w-4" />
                                        ) : (
                                            link.label
                                        )}
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>

            <Sheet open={isSheetOpen} onOpenChange={setIsSheetOpen}>
                <SheetContent side="right">
                    <SheetHeader>
                        <SheetTitle>
                            {editingId ? 'Edit User' : 'Add User'}
                        </SheetTitle>
                        <SheetDescription>
                            {editingId
                                ? 'Update the user account details.'
                                : 'Create a new user account.'}
                        </SheetDescription>
                    </SheetHeader>
                    <form onSubmit={handleSubmit} className="space-y-4 px-4">
                        <div className="grid grid-cols-2 gap-3">
                            <div className="grid gap-2">
                                <Label htmlFor="first_name">First Name</Label>
                                <Input
                                    id="first_name"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {form.errors.first_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.first_name}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="last_name">Last Name</Label>
                                <Input
                                    id="last_name"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {form.errors.last_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.last_name}
                                    </p>
                                )}
                            </div>
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                value={form.data.email}
                                onChange={(e) =>
                                    form.setData('email', e.target.value)
                                }
                                required
                            />
                            {form.errors.email && (
                                <p className="text-sm text-destructive">
                                    {form.errors.email}
                                </p>
                            )}
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="role">Role</Label>
                            <Select
                                value={form.data.role}
                                onValueChange={(value) =>
                                    form.setData('role', value)
                                }
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Select role..." />
                                </SelectTrigger>
                                <SelectContent>
                                    {roles.map((role) => (
                                        <SelectItem
                                            key={role.value}
                                            value={role.value}
                                        >
                                            {role.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.role && (
                                <p className="text-sm text-destructive">
                                    {form.errors.role}
                                </p>
                            )}
                        </div>
                        {!editingId && (
                            <div className="grid gap-2">
                                <Label htmlFor="password">Password</Label>
                                <Input
                                    id="password"
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    required
                                />
                                {form.errors.password && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.password}
                                    </p>
                                )}
                            </div>
                        )}
                        <div className="mt-10 w-full space-y-2">
                            <Button
                                className="w-full"
                                type="submit"
                                disabled={form.processing}
                            >
                                {form.processing
                                    ? 'Saving...'
                                    : editingId
                                      ? 'Update User'
                                      : 'Create User'}
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

            <Dialog
                open={isResetDialogOpen}
                onOpenChange={setIsResetDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Reset Password</DialogTitle>
                        <DialogDescription>
                            Enter a new password for this user.
                        </DialogDescription>
                    </DialogHeader>
                    <form onSubmit={handleResetPassword}>
                        <div className="grid gap-4">
                            <div className="grid gap-2">
                                <Label htmlFor="new-password">
                                    New Password
                                </Label>
                                <Input
                                    id="new-password"
                                    type="password"
                                    value={resetForm.data.password}
                                    onChange={(e) =>
                                        resetForm.setData(
                                            'password',
                                            e.target.value,
                                        )
                                    }
                                    required
                                    minLength={8}
                                />
                                {resetForm.errors.password && (
                                    <p className="text-sm text-destructive">
                                        {resetForm.errors.password}
                                    </p>
                                )}
                            </div>
                            <DialogFooter>
                                <Button
                                    variant="outline"
                                    type="button"
                                    onClick={() => {
                                        setIsResetDialogOpen(false);
                                        setResettingId(null);
                                    }}
                                >
                                    Cancel
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={resetForm.processing}
                                >
                                    {resetForm.processing
                                        ? 'Resetting...'
                                        : 'Reset Password'}
                                </Button>
                            </DialogFooter>
                        </div>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isDeleteDialogOpen}
                onOpenChange={setIsDeleteDialogOpen}
            >
                <DialogContent className="sm:max-w-md">
                    <DialogHeader>
                        <DialogTitle>Confirm Delete</DialogTitle>
                        <DialogDescription>
                            Are you sure you want to delete this user? The
                            account will be soft-deleted and can be restored if
                            needed.
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
                            variant="destructive"
                        >
                            {form.processing ? 'Deleting...' : 'Delete'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
