import { Head, Link, usePage } from '@inertiajs/react';
import { Search } from 'lucide-react';
import { useState, useRef, useEffect } from 'react';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

function ClientTypeBadge({ type }: { type: string }) {
    const colors: Record<string, { bg: string; text: string }> = {
        sd_resident: { bg: '#DBEAFE', text: '#1E40AF' },
        vacationer: { bg: '#FEF3C7', text: '#B45309' },
        invoiced: { bg: '#E0E7FF', text: '#3730A3' },
    };

    const style = colors[type] || { bg: '#F3F4F6', text: '#374151' };
    const labels: Record<string, string> = {
        sd_resident: 'SD Resident',
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
    cell_phone: string;
    client_type: string;
    user: {
        profile_photo_path: string | null;
    };
    _count?: {
        children: number;
        pets: number;
    };
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
    filters: {
        search: string | null;
        client_type: string | null;
    };
}

export default function ClientsIndex() {
    const { clients, filters } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [suggestions, setSuggestions] = useState<Client[]>([]);
    const [showSuggestions, setShowSuggestions] = useState(false);
    const [isLoading, setIsLoading] = useState(false);
    const searchRef = useRef<HTMLDivElement>(null);
    const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null);

    useEffect(() => {
        function handleClickOutside(event: MouseEvent) {
            if (
                searchRef.current &&
                !searchRef.current.contains(event.target as Node)
            ) {
                setShowSuggestions(false);
            }
        }
        document.addEventListener('mousedown', handleClickOutside);
        return () =>
            document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        if (debounceRef.current) clearTimeout(debounceRef.current);
        if (!value.trim()) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }
        if (value.trim().length < 2) {
            setSuggestions([]);
            setShowSuggestions(false);
            return;
        }
        setIsLoading(true);
        debounceRef.current = setTimeout(async () => {
            try {
                const params = new URLSearchParams({ q: value });
                const response = await fetch(
                    `/clients/search-suggestions?${params}`,
                );
                const data = await response.json();
                setSuggestions(data);
                setShowSuggestions(true);
            } catch (error) {
                console.error('Search error:', error);
            } finally {
                setIsLoading(false);
            }
        }, 300);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Clients" />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Clients
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {clients.total} clients total
                        </p>
                    </div>
                    <Link href="/clients/create" className="btn-primary">
                        Add Client
                    </Link>
                </div>

                <div className="flex gap-4">
                    <form method="get" className="flex flex-1 gap-2">
                        <div
                            className="relative max-w-md flex-1"
                            ref={searchRef}
                        >
                            <Search className="absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-muted-foreground" />
                            <input
                                type="text"
                                name="search"
                                autoComplete="off"
                                value={searchQuery}
                                onChange={(e) =>
                                    handleSearchChange(e.target.value)
                                }
                                onFocus={() =>
                                    suggestions.length > 0 &&
                                    setShowSuggestions(true)
                                }
                                placeholder="Search by name or email..."
                                className="h-10 w-full rounded-[3px] border border-input bg-background px-10 pr-4 text-sm outline-none focus:border-ring focus:ring-[3px] focus:ring-ring/20"
                            />
                            {showSuggestions && (
                                <div className="absolute top-full right-0 left-0 z-50 mt-1 max-h-60 overflow-auto rounded-[3px] border border-border bg-card shadow-md">
                                    {isLoading ? (
                                        <div className="p-3 text-sm text-muted-foreground">
                                            Loading...
                                        </div>
                                    ) : suggestions.length > 0 ? (
                                        <ul>
                                            {suggestions.map((client) => (
                                                <li key={client.id}>
                                                    <Link
                                                        href={`/clients/${client.id}`}
                                                        className="flex items-center justify-between px-3 py-2 hover:bg-accent"
                                                    >
                                                        <span className="text-sm text-foreground">
                                                            {client.first_name}{' '}
                                                            {client.last_name}
                                                        </span>
                                                        <ClientTypeBadge
                                                            type={
                                                                client.client_type
                                                            }
                                                        />
                                                    </Link>
                                                </li>
                                            ))}
                                        </ul>
                                    ) : (
                                        <div className="p-3 text-sm text-muted-foreground">
                                            No results found
                                        </div>
                                    )}
                                </div>
                            )}
                        </div>
                        <select
                            name="client_type"
                            defaultValue={filters.client_type || ''}
                            className="h-10 rounded-[3px] border border-input bg-background px-3 text-sm outline-none focus:border-ring"
                        >
                            <option value="">All Types</option>
                            <option value="sd_resident">SD Resident</option>
                            <option value="vacationer">Vacationer</option>
                            <option value="invoiced">Invoiced</option>
                        </select>
                        <button type="submit" className="btn-primary">
                            Filter
                        </button>
                    </form>
                </div>

                <div className="rounded-[6px] border border-border bg-card">
                    <table className="w-full">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Name
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Phone
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Type
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Photo
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
                                    <td className="px-4 py-3">
                                        <Link
                                            href={`/clients/${client.id}`}
                                            className="text-sm font-medium text-ring hover:text-foreground hover:underline"
                                        >
                                            {client.first_name}{' '}
                                            {client.last_name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.email}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-foreground">
                                        {client.cell_phone}
                                    </td>
                                    <td className="px-4 py-3">
                                        <ClientTypeBadge
                                            type={client.client_type}
                                        />
                                    </td>
                                    <td className="px-4 py-3">
                                        {client.user.profile_photo_path ? (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
                                                <img
                                                    src={
                                                        client.user
                                                            .profile_photo_path ===
                                                        'avatar.jpg'
                                                            ? '/avatar.jpg'
                                                            : `/storage/${client.user.profile_photo_path}`
                                                    }
                                                    alt={`${client.first_name} ${client.last_name}`}
                                                    className="h-10 w-10 rounded-full object-cover"
                                                />
                                            </div>
                                        ) : (
                                            <div className="flex h-10 w-10 items-center justify-center rounded-full bg-amber-100">
                                                <span className="text-lg font-medium text-amber-600">
                                                    {client.first_name[0]}
                                                    {client.last_name[0]}
                                                </span>
                                            </div>
                                        )}
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Link
                                            href={`/clients/${client.id}`}
                                            className="text-sm font-medium text-ring hover:text-foreground"
                                        >
                                            View
                                        </Link>
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
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
