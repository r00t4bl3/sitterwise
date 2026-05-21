import { Head, usePage, router } from '@inertiajs/react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface CaregiverInfo {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
}

interface ApplicationData {
    personal?: {
        first_name: string;
        last_name: string;
        phone: string;
        dob: string;
        address_line1?: string;
        address_city?: string;
        address_state?: string;
    };
    sponsor?: {
        first_name: string;
        last_name: string;
        email: string;
        phone?: string;
        relationship?: string;
    };
}

interface ApplicationInfo {
    id: number;
    submitted_at: string;
    data: ApplicationData;
    caregiver: CaregiverInfo;
}

interface ReferenceInfo {
    id: number;
    token: string;
    reference_name: string;
    reference_email: string;
    relationship: string | null;
    years_known: string | null;
    is_sponsor: boolean;
    rating: number | null;
    feedback: string | null;
    submitted_at: string | null;
    created_at: string;
}

interface Props {
    application: ApplicationInfo;
    references: ReferenceInfo[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caregiver Applications', href: '/applications' },
];

export default function ApplicationShow() {
    const { application, references } = usePage<Props>().props;
    const [resending, setResending] = useState<number | null>(null);

    const data = application.data;
    const personal = data.personal || {};
    const sponsor = data.sponsor;
    const completedRefs = references.filter((r) => r.submitted_at);
    const pendingRefs = references.filter((r) => !r.submitted_at);

    function handleResend(refId: number) {
        if (!confirm('Resend this reference request email?')) {
            return;
        }

        setResending(refId);
        router.post(
            `/applications/${application.id}/references/${refId}/resend`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setResending(null),
            },
        );
    }

    return (
        <>
            <Head
                title={`Application - ${personal.first_name} ${personal.last_name}`}
            />
            <ToasterMessage />

            <div className="mb-6">
                <h1 className="text-2xl font-bold text-gray-900">
                    {personal.first_name} {personal.last_name}
                </h1>
                <p className="mt-1 text-sm text-gray-500">
                    Applied{' '}
                    {application.submitted_at
                        ? new Date(
                              application.submitted_at,
                          ).toLocaleDateString()
                        : '-'}
                </p>
            </div>

            <div className="grid gap-6 lg:grid-cols-3">
                {/* Application details */}
                <div className="space-y-6 lg:col-span-2">
                    {/* Applicant Info */}
                    <Card>
                        <CardHeader>
                            <CardTitle>Applicant Information</CardTitle>
                        </CardHeader>
                        <CardContent className="grid grid-cols-2 gap-4 text-sm">
                            <div>
                                <span className="font-medium text-gray-500">
                                    Name
                                </span>
                                <p className="text-gray-900">
                                    {personal.first_name} {personal.last_name}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Phone
                                </span>
                                <p className="text-gray-900">
                                    {personal.phone || '-'}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Email
                                </span>
                                <p className="text-gray-900">
                                    {application.caregiver.email}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Date of Birth
                                </span>
                                <p className="text-gray-900">
                                    {personal.dob || '-'}
                                </p>
                            </div>
                            <div className="col-span-2">
                                <span className="font-medium text-gray-500">
                                    Address
                                </span>
                                <p className="text-gray-900">
                                    {[
                                        personal.address_line1,
                                        personal.address_city,
                                        personal.address_state,
                                    ]
                                        .filter(Boolean)
                                        .join(', ') || '-'}
                                </p>
                            </div>
                        </CardContent>
                    </Card>

                    {/* Sponsor Info */}
                    {sponsor && (
                        <Card>
                            <CardHeader>
                                <CardTitle>Sponsor</CardTitle>
                            </CardHeader>
                            <CardContent className="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span className="font-medium text-gray-500">
                                        Name
                                    </span>
                                    <p className="text-gray-900">
                                        {sponsor.first_name} {sponsor.last_name}
                                    </p>
                                </div>
                                <div>
                                    <span className="font-medium text-gray-500">
                                        Email
                                    </span>
                                    <p className="text-gray-900">
                                        {sponsor.email}
                                    </p>
                                </div>
                                {sponsor.relationship && (
                                    <div>
                                        <span className="font-medium text-gray-500">
                                            Relationship
                                        </span>
                                        <p className="text-gray-900">
                                            {sponsor.relationship}
                                        </p>
                                    </div>
                                )}
                            </CardContent>
                        </Card>
                    )}

                    {/* References */}
                    <Card>
                        <CardHeader>
                            <CardTitle>
                                References
                                <span className="ml-2 text-sm font-normal text-gray-500">
                                    ({completedRefs.length}/{references.length}{' '}
                                    completed)
                                </span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            {references.length === 0 && (
                                <p className="text-sm text-gray-500">
                                    No references found.
                                </p>
                            )}
                            {references.map((ref) => {
                                const isCompleted = !!ref.submitted_at;

                                return (
                                    <div
                                        key={ref.id}
                                        className={`rounded-lg border p-4 ${
                                            isCompleted
                                                ? 'border-green-200 bg-green-50'
                                                : 'border-gray-200 bg-white'
                                        }`}
                                    >
                                        <div className="flex items-start justify-between">
                                            <div className="flex-1">
                                                <div className="flex items-center gap-2">
                                                    <span className="font-medium text-gray-900">
                                                        {ref.reference_name}
                                                    </span>
                                                    {ref.is_sponsor && (
                                                        <Badge
                                                            variant="outline"
                                                            className="text-xs"
                                                        >
                                                            Sponsor
                                                        </Badge>
                                                    )}
                                                    <Badge
                                                        variant={
                                                            isCompleted
                                                                ? 'default'
                                                                : 'secondary'
                                                        }
                                                        className={
                                                            isCompleted
                                                                ? 'bg-green-100 text-green-700'
                                                                : 'bg-gray-100 text-gray-600'
                                                        }
                                                    >
                                                        {isCompleted
                                                            ? 'Completed'
                                                            : 'Pending'}
                                                    </Badge>
                                                </div>
                                                <p className="mt-1 text-sm text-gray-500">
                                                    {ref.reference_email}
                                                </p>
                                                {ref.relationship && (
                                                    <p className="mt-1 text-sm text-gray-500">
                                                        {ref.relationship}
                                                        {ref.years_known &&
                                                            ` · ${ref.years_known} years known`}
                                                    </p>
                                                )}
                                                {isCompleted && ref.rating && (
                                                    <div className="mt-2 flex items-center gap-1">
                                                        {[1, 2, 3, 4, 5].map(
                                                            (star) => (
                                                                <svg
                                                                    key={star}
                                                                    className={`h-4 w-4 ${
                                                                        star <=
                                                                        ref.rating!
                                                                            ? 'fill-amber-400 text-amber-400'
                                                                            : 'fill-gray-200 text-gray-200'
                                                                    }`}
                                                                    viewBox="0 0 24 24"
                                                                >
                                                                    <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                                                </svg>
                                                            ),
                                                        )}
                                                    </div>
                                                )}
                                                {isCompleted &&
                                                    ref.feedback && (
                                                        <p className="mt-2 text-sm text-gray-600 italic">
                                                            &ldquo;
                                                            {ref.feedback}
                                                            &rdquo;
                                                        </p>
                                                    )}
                                            </div>
                                            {!isCompleted && (
                                                <Button
                                                    variant="outline"
                                                    size="sm"
                                                    onClick={() =>
                                                        handleResend(ref.id)
                                                    }
                                                    disabled={
                                                        resending === ref.id
                                                    }
                                                    className="ml-4 shrink-0"
                                                >
                                                    {resending === ref.id
                                                        ? 'Resending...'
                                                        : 'Resend'}
                                                </Button>
                                            )}
                                        </div>
                                    </div>
                                );
                            })}
                        </CardContent>
                    </Card>
                </div>

                {/* Sidebar */}
                <div className="space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle>Summary</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div className="flex justify-between">
                                <span className="text-gray-500">
                                    References
                                </span>
                                <span className="font-medium">
                                    {completedRefs.length}/{references.length}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500">Sponsor</span>
                                <span className="font-medium">
                                    {sponsor
                                        ? `${sponsor.first_name} ${sponsor.last_name}`
                                        : 'None'}
                                </span>
                            </div>
                            <div className="flex justify-between">
                                <span className="text-gray-500">Status</span>
                                <Badge
                                    variant={
                                        completedRefs.length ===
                                            references.length &&
                                        references.length > 0
                                            ? 'default'
                                            : 'secondary'
                                    }
                                    className={
                                        completedRefs.length ===
                                            references.length &&
                                        references.length > 0
                                            ? 'bg-green-100 text-green-700'
                                            : 'bg-amber-100 text-amber-700'
                                    }
                                >
                                    {completedRefs.length ===
                                        references.length &&
                                    references.length > 0
                                        ? 'All Complete'
                                        : `${pendingRefs.length} Pending`}
                                </Badge>
                            </div>
                        </CardContent>
                    </Card>

                    <Card>
                        <CardHeader>
                            <CardTitle>Caregiver</CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-3 text-sm">
                            <div>
                                <span className="font-medium text-gray-500">
                                    Name
                                </span>
                                <p className="text-gray-900">
                                    {application.caregiver.first_name}{' '}
                                    {application.caregiver.last_name}
                                </p>
                            </div>
                            <div>
                                <span className="font-medium text-gray-500">
                                    Profile
                                </span>
                                <p>
                                    <a
                                        href={`/caregivers/${application.caregiver.id}`}
                                        className="text-coral hover:underline"
                                    >
                                        View caregiver profile →
                                    </a>
                                </p>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </>
    );
}

ApplicationShow.layout = (page: React.ReactNode) => (
    <AppLayout breadcrumbs={breadcrumbs}>{page}</AppLayout>
);
