import { Head, Link, useForm } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import type { SubmitEventHandler } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Clients',
        href: '/clients',
    },
    {
        title: 'Add Client',
        href: '#',
    },
];

export default function ClientCreate() {
    const form = useForm({
        first_name: '',
        last_name: '',
        email: '',
        phone: '',
        client_type: 'vacationer',
        password: '',
        password_confirmation: '',
        how_did_you_hear: '',
    });

    const submit: SubmitEventHandler = (e) => {
        e.preventDefault();
        form.post('/clients', {
            onSuccess: () => {},
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Add Client" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center gap-4">
                    <Link
                        href="/clients"
                        className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                    >
                        <ArrowLeft className="h-5 w-5" />
                    </Link>
                    <h1 className="font-serif text-2xl font-bold text-foreground">
                        Add New Client
                    </h1>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="rounded-[6px] border border-border bg-card p-6">
                        <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                            Account Information
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
                                    Phone{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={form.data.phone}
                                    onChange={(e) =>
                                        form.setData('phone', e.target.value)
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                />
                                {form.errors.phone && (
                                    <p className="text-sm text-destructive">
                                        {form.errors.phone}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    Client Type{' '}
                                    <span className="text-red-500">*</span>
                                </label>
                                <select
                                    value={form.data.client_type}
                                    onChange={(e) =>
                                        form.setData(
                                            'client_type',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                    required
                                >
                                    <option value="vacationer">
                                        Vacationer
                                    </option>
                                    <option value="sd_resident">
                                        SD Resident
                                    </option>
                                    <option value="invoiced">Invoiced</option>
                                </select>
                            </div>
                            <div className="space-y-2">
                                <label className="text-sm font-medium text-foreground">
                                    How did you hear about us?
                                </label>
                                <select
                                    value={form.data.how_did_you_hear}
                                    onChange={(e) =>
                                        form.setData(
                                            'how_did_you_hear',
                                            e.target.value,
                                        )
                                    }
                                    className="h-10 w-full rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                                >
                                    <option value="">Select an option</option>
                                    <option value="concierge">Concierge</option>
                                    <option value="friend_family">
                                        Friend/Family
                                    </option>
                                    <option value="google">Google</option>
                                    <option value="returning_client">
                                        Returning Client
                                    </option>
                                    <option value="care_com">Care.com</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
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
                            </div>
                        </div>
                    </div>

                    <div className="flex justify-end gap-2">
                        <Button
                            variant="secondary"
                            onClick={() => (window.location.href = '/clients')}
                        >
                            Cancel
                        </Button>
                        <Button type="submit">Create Client</Button>
                    </div>
                </form>
            </div>
        </AppLayout>
    );
}
