import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { SubmitEventHandler } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { DatePicker } from '@/components/ui/date-picker';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Caregivers',
        href: '/caregivers',
    },
    {
        title: 'Add Caregiver',
        href: '#',
    },
];

interface Status {
    id: number;
    name: string;
    color: string;
}

interface Props {
    [key: string]: unknown;
    statuses: Status[];
}

export default function CaregiverCreate() {
    const { statuses } = usePage<Props>().props;

    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        address: '',
        date_of_birth: '',
        status_id: '',
        password: '',
        password_confirmation: '',
        biography: '',
        notes: '',
    });

    const submit: SubmitEventHandler = (e) => {
        e.preventDefault();
        form.post('/caregivers', {
            onSuccess: () => {},
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Caregiver" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href="/caregivers"
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="font-serif text-2xl font-bold text-foreground">
                        Add New Caregiver
                    </h1>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Personal Information
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    First Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.first_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'first_name',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.first_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.first_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Last Name{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.last_name}
                                    onChange={(e) =>
                                        form.setData(
                                            'last_name',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.last_name && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.last_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Email{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="email"
                                    value={form.data.email}
                                    onChange={(e) =>
                                        form.setData('email', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.email && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.email}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Phone
                                </label>
                                <input
                                    type="text"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Date of Birth
                                </label>
                                <DatePicker
                                    name="date_of_birth"
                                    value={form.data.date_of_birth}
                                    onChange={(date) =>
                                        form.setData(
                                            'date_of_birth',
                                            date || '',
                                        )
                                    }
                                    placeholder="Select date of birth"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Status{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.data.status_id}
                                    onChange={(e) =>
                                        form.setData(
                                            'status_id',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                                    required
                                >
                                    <option value="">Select status</option>
                                    {statuses.map((status) => (
                                        <option
                                            key={status.id}
                                            value={status.id}
                                        >
                                            {status.name}
                                        </option>
                                    ))}
                                </select>
                                {form.errors.status_id && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.status_id}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2 md:col-span-2">
                                <label className="text-sm font-medium text-foreground">
                                    Address
                                </label>
                                <input
                                    type="text"
                                    value={form.data.address}
                                    onChange={(e) =>
                                        form.setData('address', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Account Credentials
                        </h2>
                        <div className="grid gap-4 md:grid-cols-2">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Password{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="password"
                                    value={form.data.password}
                                    onChange={(e) =>
                                        form.setData('password', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.password && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.password}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Confirm Password{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="password"
                                    value={form.data.password_confirmation}
                                    onChange={(e) =>
                                        form.setData(
                                            'password_confirmation',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.password_confirmation && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.password_confirmation}
                                    </p>
                                )}
                            </div>
                        </div>
                    </div>

                    <div className="border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Additional Information
                        </h2>
                        <div className="space-y-4">
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Biography
                                </label>
                                <textarea
                                    value={form.data.biography}
                                    onChange={(e) =>
                                        form.setData(
                                            'biography',
                                            e.target.value,
                                        )
                                    }
                                    rows={4}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Notes
                                </label>
                                <textarea
                                    value={form.data.notes}
                                    onChange={(e) =>
                                        form.setData('notes', e.target.value)
                                    }
                                    rows={4}
                                    className="w-full rounded-[3px] border border-input bg-background px-3 py-2 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-4">
                        <Button
                            variant="secondary"
                            type="button"
                            onClick={() =>
                                (window.location.href = '/caregivers')
                            }
                        >
                            Cancel
                        </Button>
                        <Button type="submit" disabled={form.processing}>
                            {form.processing
                                ? 'Creating...'
                                : 'Create Caregiver'}
                        </Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
