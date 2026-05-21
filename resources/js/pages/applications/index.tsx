import { Head, Link, usePage } from '@inertiajs/react';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Application {
    id: number;
    caregiver_id: number;
    applicant_name: string;
    applicant_email: string;
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
    const { applications } = usePage<Props>().props;

    return (
        <>
            <Head title="Caregiver Applications" />
            <ToasterMessage />

            <div className="mb-6 flex items-center justify-between">
                <div>
                    <h1 className="text-2xl font-bold text-gray-900">
                        Caregiver Applications
                    </h1>
                    <p className="mt-1 text-sm text-gray-500">
                        {applications.total} total application
                        {applications.total !== 1 ? 's' : ''}
                    </p>
                </div>
            </div>

            <div className="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm">
                <table className="w-full text-sm">
                    <thead>
                        <tr className="bg-gray-50 text-left text-xs font-semibold tracking-wider text-gray-500 uppercase">
                            <th className="px-6 py-3">Applicant</th>
                            <th className="px-6 py-3">Email</th>
                            <th className="px-6 py-3">Submitted</th>
                            <th className="px-6 py-3">References</th>
                            <th className="px-6 py-3" />
                        </tr>
                    </thead>
                    <tbody className="divide-y divide-gray-200">
                        {applications.data.map((app) => {
                            const allComplete =
                                app.completed_count === app.reference_count &&
                                app.reference_count > 0;

                            return (
                                <tr key={app.id} className="hover:bg-gray-50">
                                    <td className="px-6 py-4 font-medium text-gray-900">
                                        {app.applicant_name}
                                    </td>
                                    <td className="px-6 py-4 text-gray-500">
                                        {app.applicant_email}
                                    </td>
                                    <td className="px-6 py-4 text-gray-500">
                                        {app.submitted_at
                                            ? new Date(
                                                  app.submitted_at,
                                              ).toLocaleDateString()
                                            : '-'}
                                    </td>
                                    <td className="px-6 py-4">
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
                                            <span className="text-xs whitespace-nowrap text-gray-500">
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
                                    <td className="px-6 py-4 text-right">
                                        <Link href={`/applications/${app.id}`}>
                                            <Button variant="outline" size="sm">
                                                View
                                            </Button>
                                        </Link>
                                    </td>
                                </tr>
                            );
                        })}
                        {applications.data.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="px-6 py-12 text-center text-sm text-gray-500"
                                >
                                    No applications yet.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>

                {applications.last_page > 1 && (
                    <div className="flex items-center justify-between border-t border-gray-200 px-6 py-3">
                        <span className="text-sm text-gray-500">
                            Page {applications.current_page} of{' '}
                            {applications.last_page}
                        </span>
                        <div className="flex gap-2">
                            {applications.links.map((link, i) => {
                                if (!link.url) {
                                    return null;
                                }

                                return (
                                    <Link key={i} href={link.url}>
                                        <Button
                                            variant={
                                                link.active
                                                    ? 'default'
                                                    : 'outline'
                                            }
                                            size="sm"
                                            dangerouslySetInnerHTML={{
                                                __html: link.label,
                                            }}
                                        />
                                    </Link>
                                );
                            })}
                        </div>
                    </div>
                )}
            </div>
        </>
    );
}

ApplicationsIndex.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
