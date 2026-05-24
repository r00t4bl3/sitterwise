import { Head, Link, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { ChevronLeft, ChevronRight } from 'lucide-react';
import { useRef, useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Application {
    id: number;
    caregiver_id: number;
    applicant_name: string;
    applicant_email: string;
    status: string;
    status_label: string;
    submitted_at: string;
    reference_count: number;
    completed_count: number;
}

interface PaginatedData<T> {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
}

interface Props {
    applications: PaginatedData<Application>;
    filters: { status?: string; search?: string };
    caregiverStatuses: Array<{ value: string; label: string; color: string; is_terminal: boolean }>;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caregiver Applications', href: '/applications' },
];

function referenceProgressClass(completed: number, total: number): string {
    if (total === 0) {
        return 'bg-gray-200';
    }

    const ratio = completed / total;

    if (ratio === 1) {
        return 'bg-green-500';
    }

    if (ratio >= 0.5) {
        return 'bg-amber-400';
    }

    return 'bg-coral';
}

export default function ApplicationsIndex() {
    const { applications, filters, caregiverStatuses } = usePage<Props>().props;

    const [searchQuery, setSearchQuery] = useState(filters.search || '');
    const [statusFilter, setStatusFilter] = useState<string | null>(
        filters.status && filters.status !== 'all' ? filters.status : null,
    );
    const debounceTimer = useRef<ReturnType<typeof setTimeout> | undefined>(
        undefined,
    );

    const applyFilters = (search: string, status: string | null) => {
        const params: Record<string, string> = {};

        if (search.trim()) {
            params.search = search.trim();
        }

        if (status) {
            params.status = status;
        }

        router.get('/applications', params, {
            preserveState: true,
            replace: true,
        });
    };

    const handleSearchChange = (value: string) => {
        setSearchQuery(value);
        clearTimeout(debounceTimer.current);
        debounceTimer.current = setTimeout(() => {
            applyFilters(value, statusFilter);
        }, 300);
    };

    const handleStatusChange = (status: string | null) => {
        setStatusFilter(status);
        applyFilters(searchQuery, status);
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregiver Applications" />
            <ToasterMessage />

            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div>
                        <h1 className="font-serif text-2xl font-bold text-foreground">
                            Caregiver Applications
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {applications.total} total application
                            {applications.total !== 1 ? 's' : ''}
                            {statusFilter && (
                                <span className="ml-1">
                                    (
                                    {caregiverStatuses.find(
                                        (s) => s.value === statusFilter,
                                    )?.label || statusFilter}
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
                </div>

                <div className="flex flex-wrap items-center gap-2">
                    <div className="relative">
                        <Input
                            type="text"
                            placeholder="Search by name..."
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
                        variant={!statusFilter ? 'default' : 'outline'}
                        size="sm"
                        onClick={() => handleStatusChange(null)}
                    >
                        All
                    </Button>
                    {caregiverStatuses.map((s) => (
                        <Button
                            key={s.value}
                            variant={
                                statusFilter === s.value ? 'default' : 'outline'
                            }
                            size="sm"
                            onClick={() =>
                                handleStatusChange(
                                    statusFilter === s.value
                                        ? null
                                        : s.value,
                                )
                            }
                        >
                            {s.label}
                        </Button>
                    ))}
                </div>

                <div className="overflow-x-auto border border-border bg-card">
                    <table className="w-full min-w-[800px]">
                        <thead>
                            <tr className="bg-foreground">
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    ID
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Applicant
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Email
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Status
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Submitted
                                </th>
                                <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">
                                    References
                                </th>
                                <th className="px-4 py-3 text-right text-[11px] font-semibold tracking-wider text-white uppercase">
                                    Actions
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {applications.data.map((app) => {
                                const allComplete =
                                    app.completed_count ===
                                        app.reference_count &&
                                    app.reference_count > 0;

                                return (
                                <tr
                                    key={app.id}
                                    className="border-b border-border transition hover:bg-blush"
                                >
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {app.id}
                                    </td>
                                    <td className="px-4 py-3 text-sm font-medium text-foreground">
                                        {app.applicant_name}
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {app.applicant_email}
                                    </td>
                                    <td className="px-4 py-3">
                                        <Badge
                                            style={{
                                                backgroundColor:
                                                    (caregiverStatuses.find(
                                                        (s) =>
                                                            s.value ===
                                                            app.status,
                                                    )?.color ?? '#6B7280') +
                                                    '20',
                                                color:
                                                    caregiverStatuses.find(
                                                        (s) =>
                                                            s.value ===
                                                            app.status,
                                                    )?.color ?? '#6B7280',
                                            }}
                                        >
                                            {app.status_label}
                                        </Badge>
                                    </td>
                                    <td className="px-4 py-3 text-sm text-muted-foreground">
                                        {app.submitted_at
                                            ? format(new Date(app.submitted_at), 'MMMM d, yyyy')
                                            : '-'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <div className="flex items-center gap-3">
                                            <div className="flex h-2 w-24 overflow-hidden rounded-full bg-gray-200">
                                                <div
                                                    className={`h-full rounded-full transition-all ${referenceProgressClass(app.completed_count, app.reference_count)}`}
                                                    style={{
                                                        width:
                                                            app.reference_count >
                                                            0
                                                                ? `${(app.completed_count / app.reference_count) * 100}%`
                                                                : '0%',
                                                    }}
                                                />
                                            </div>
                                            <span className="text-xs whitespace-nowrap text-muted-foreground">
                                                {app.completed_count}/
                                                {app.reference_count}
                                            </span>
                                            {allComplete && (
                                                <Badge
                                                    variant="default"
                                                    className="bg-green-100 text-green-700 hover:bg-green-200"
                                                >
                                                    Complete
                                                </Badge>
                                            )}
                                        </div>
                                    </td>
                                    <td className="px-4 py-3 text-right">
                                        <Button asChild className="h-8">
                                            <Link
                                                href={`/applications/${app.id}`}
                                            >
                                                View
                                            </Link>
                                        </Button>
                                    </td>
                                </tr>
                            );
                        })}
                        {applications.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={7}
                                    className="px-4 py-12 text-center text-sm text-muted-foreground"
                                >
                                    No applications yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>

            {applications.last_page > 1 && (
                <div className="flex items-center justify-between">
                    <p className="text-sm text-muted-foreground">
                        Page {applications.current_page} of{' '}
                        {applications.last_page}
                    </p>
                    <div className="flex gap-1">
                        {applications.links.map((link, index) => {
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
                                            ? 'bg-foreground text-white'
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
