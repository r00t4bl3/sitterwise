import { Head, Link, router, usePage } from '@inertiajs/react';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { formatPhoneDisplay } from '@/lib/phone';
import type { BreadcrumbItem } from '@/types';

function ClientTypeBadge({ type }: { type: string }) {
    const colors: Record<string, { bg: string; text: string }> = {
        resident: { bg: '#E0F7FA', text: '#006064' },
        vacationer: { bg: '#E8F5E9', text: '#2E7D32' },
        invoiced: { bg: '#FFF3E0', text: '#E65100' },
    };

    const style = colors[type] || { bg: '#F3F4F6', text: '#374151' };
    const labels: Record<string, string> = {
        resident: 'SD Resident',
        vacationer: 'Vacationer',
        invoiced: 'Invoiced',
    };

    return (
        <span
            className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{
                backgroundColor: style.bg,
                color: style.text,
            }}
        >
            {labels[type] || type}
        </span>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: '/dashboard',
    },
    {
        title: 'Clients',
        href: '/clients',
    },
];

interface Client {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    client_type: string;
    user: {
        profile_photo_path: string | null;
        profile_photo_url: string | null;
    };
    children_count?: number;
    pets_count?: number;
}

interface Props {
    [key: string]: unknown;
    clients: {
        data: Client[];
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
    clientTypes: Array<{ value: string; label: string }>;
    filters: {
        search: string | null;
        client_type: string | null;
    };
}

export default function ClientsIndex() {
    const { clients, clientTypes, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [typeFilter, setTypeFilter] = useState<string | null>(
        filters.client_type && filters.client_type !== 'all'
            ? filters.client_type
            : null,
    );
    const debounceTimer = useRef<ReturnType<typeof setTimeout>>(undefined);

    const applyFilters = (search: string, type: string | null) => {
        const params: Record<string, string> = {};

        if (search.trim()) {
            params.search = search.trim();
        }

        if (type) {
            params.client_type = type;
        }

        router.get('/clients', params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters(value, typeFilter);
        }, 300);
    };

    const handleTypeChange = (type: string | null) => {
        setTypeFilter(type);
        applyFilters(searchQuery, type);
    };

    useEffect(() => {
        return () => clearTimeout(debounceTimer.current);
    }, []);

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clients" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Clients
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {clients.total} clients
                            {typeFilter && (
                                <span className="ml-1">
                                    (
                                    {clientTypes.find(
                                        (t) => t.value === typeFilter,
                                    )?.label || typeFilter}
                                    )
                                </span>
                            )}
                            {searchQuery && (
                                <span className="ml-1">
                                    (search: "{searchQuery}")
                                </span>
                            )}
                        </p>
                    </div>
                    <Link href="/clients/create" className="btn-primary">
                        Add Client
                    </Link>
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by name or email..."
                            value={searchQuery}
                            onChange={(e) => handleSearchChange(e.target.value)}
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

                    <Button
                        variant={!typeFilter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleTypeChange(null)}
                    >
                        All
                    </Button>
                    {clientTypes.map((type) => (
                        <Button
                            key={type.value}
                            variant={
                                typeFilter === type.value
                                    ? 'default'
                                    : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                handleTypeChange(
                                    typeFilter === type.value
                                        ? null
                                        : type.value,
                                )
                            }
                        >
                            {type.label}
                        </Button>
                    ))}
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="bg-table-header">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    ID
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Type
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Children
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Pets
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Phone
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {clients.data.map((client) => (
                                <tr
                                    key={client.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.id}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-2">
                                            <UserAvatar
                                                profile_photo_url={
                                                    client.user
                                                        .profile_photo_url
                                                }
                                                profile_photo_path={
                                                    client.user
                                                        .profile_photo_path
                                                }
                                                name={`${client.first_name} ${client.last_name}`}
                                                size="sm"
                                            />
                                            <Link
                                                href={`/clients/${client.id}`}
                                                className="text-sm font-medium text-ring hover:text-foreground hover:underline"
                                            >
                                                {client.first_name}{' '}
                                                {client.last_name}
                                            </Link>
                                        </div>
                                    </td>
                                    <td className="px-4 py-3">
                                        <ClientTypeBadge
                                            type={client.client_type}
                                        />
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.children_count ?? 0}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.pets_count ?? 0}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.email}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {formatPhoneDisplay(client.phone)}
                                    </td>
                                    <td className="flex justify-end gap-x-2 px-4 py-3">
                                        <Button asChild className="h-8">
                                            <Link
                                                href={`/clients/${client.id}`}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>

                {clients.last_page > 1 && (
                    <div className="flex items-center justify-between">
                        <p className="text-sm text-muted-foreground">
                            Page {clients.current_page} of {clients.last_page}
                        </p>
                        <div className="flex gap-1">
                            {clients.links.map((link, index) => {
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
        </AppLayout>
    );
}
