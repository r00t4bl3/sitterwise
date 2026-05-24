import { Head, Link, usePage, router } from '@inertiajs/react';
import { format } from 'date-fns';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { calculateAgeFromDate } from '@/lib/age';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface CaregiverInfo {
    id: number;
    first_name: string;
    last_name: string;
    email: string;
    status: string;
    status_label: string;
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
    bio?: string;
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
    rating_reliability: number | null;
    rating_trustworthiness: number | null;
    rating_maturity: number | null;
    rating_communication: number | null;
    rating_warmth: number | null;
    rating_overall_recommendation: number | null;
    strengths: string | null;
    concerns: string | null;
    additional_comments: string | null;
    submitted_at: string | null;
    created_at: string;
}

interface CertificationInfo {
    id: number;
    name: string;
    expires_required: boolean;
    expiration_date: string | null;
    verified_at: string | null;
    notes: string | null;
}

interface Props {
    application: ApplicationInfo;
    references: ReferenceInfo[];
    certifications: CertificationInfo[];
    caregiverStatuses: Array<{ value: string; label: string; color: string; is_terminal: boolean }>;
    [key: string]: unknown;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caregiver Applications', href: '/applications' },
];

interface Action {
    label: string;
    route: string;
    color: string;
    confirm?: string;
}

function getActions(status: string, caregiverStatuses: Array<{ value: string; label: string; color: string; is_terminal: boolean }>): Action[] {
    const actions: Action[] = [];
    const current = caregiverStatuses.find((s) => s.value === status);

    switch (status) {
        case 'applicant':
            actions.push({ label: 'Approve', route: 'approve', color: 'default', confirm: 'Move this application to Under Review?' });
            break;
        case 'under_review':
            actions.push({ label: 'Schedule Interview', route: 'schedule-interview', color: 'default', confirm: 'Mark interview as scheduled?' });
            break;
        case 'interview_scheduled':
            actions.push({ label: 'Start Background Check', route: 'background-check', color: 'default', confirm: 'Start background check process?' });
            break;
        case 'background_check':
            actions.push({ label: 'Hire', route: 'hire', color: 'default', confirm: 'Hire this caregiver?' });
            break;
    }

    if (!current?.is_terminal) {
        actions.push({ label: 'Decline', route: 'decline', color: 'destructive', confirm: 'Decline this application? The applicant will be notified.' });
    }

    return actions;
}

export default function ApplicationShow() {
    const { application, references, certifications, caregiverStatuses } = usePage<Props>().props;
    const [resending, setResending] = useState<number | null>(null);
    const [actionLoading, setActionLoading] = useState<string | null>(null);
    const [declineNote, setDeclineNote] = useState('');
    const [showDeclineNote, setShowDeclineNote] = useState(false);

    const data = application.data;
    const personal = data.personal || {};
    const sponsor = data.sponsor;
    const completedRefs = references.filter((r) => r.submitted_at);
    const pendingRefs = references.filter((r) => !r.submitted_at);

    const status = application.caregiver.status;
    const actions = getActions(status, caregiverStatuses);

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

    function handleAction(action: Action) {
        if (action.confirm && !confirm(action.confirm)) {
            return;
        }

        if (action.route === 'decline' && showDeclineNote) {
            setActionLoading(action.route);
            router.post(
                `/applications/${application.id}/${action.route}`,
                { note: declineNote },
                { preserveScroll: true, onFinish: () => setActionLoading(null) },
            );
            return;
        }

        setActionLoading(action.route);
        router.post(
            `/applications/${application.id}/${action.route}`,
            {},
            { preserveScroll: true, onFinish: () => setActionLoading(null) },
        );
    }

    return (
        <AppLayout breadcrumbs={[...breadcrumbs, { title: `${personal.first_name} ${personal.last_name}`, href: `/applications/${application.id}` }]}>
            <Head
                title={`Application - ${personal.first_name} ${personal.last_name}`}
            />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link
                            href="/applications"
                            className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent"
                        >
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <div>
                            <h1 className="text-xl font-bold text-foreground md:text-2xl">
                                {personal.first_name} {personal.last_name}
                            </h1>
                            <p className="hidden text-muted-foreground md:block">
                                Caregiver Application
                            </p>
                        </div>
                    </div>
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Application details */}
                    <div className="space-y-6 lg:col-span-2">
                        {/* Applicant Info */}
                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Applicant Information
                            </h2>
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Name
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.first_name} {personal.last_name}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Phone
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.phone || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Email
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {application.caregiver.email}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Address
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {[
                                            personal.address_line1,
                                            personal.address_city,
                                            personal.address_state,
                                        ]
                                            .filter(Boolean)
                                            .join(', ') || '-'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Date of Birth
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.dob ? format(new Date(personal.dob), 'MMMM d, yyyy') : '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Age
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.dob ? `${calculateAgeFromDate(personal.dob)} years old` : '—'}
                                    </p>
                                </div>
                                {data.bio && (
                                    <div className="sm:col-span-2">
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Biography
                                        </p>
                                        <p className="text-sm text-foreground">
                                            {data.bio}
                                        </p>
                                    </div>
                                )}
                            </div>

                            <div className="mt-6 border-t border-border pt-6">
                                <h3 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                    Sponsor
                                </h3>
                                {sponsor ? (
                                    <div className="grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Name
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {sponsor.first_name} {sponsor.last_name}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Email
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {sponsor.email}
                                            </p>
                                        </div>
                                        {sponsor.relationship && (
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Relationship
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {sponsor.relationship}
                                                </p>
                                            </div>
                                        )}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">
                                        No sponsor
                                    </p>
                                )}
                            </div>
                        </div>

                        {/* References */}
                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                References ({completedRefs.length}/{references.length} completed)
                            </h2>
                            <div className="space-y-3">
                                {references.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No references found.
                                    </p>
                                )}
                                {references.map((ref) => {
                                    const isCompleted = !!ref.submitted_at;
                                    const ratingKeys = [
                                        'rating_reliability',
                                        'rating_trustworthiness',
                                        'rating_maturity',
                                        'rating_communication',
                                        'rating_warmth',
                                        'rating_overall_recommendation',
                                    ] as const;
                                    const ratings = ratingKeys
                                        .map((k) => ref[k as keyof ReferenceInfo])
                                        .filter((r): r is number => r !== null);
                                    const avg =
                                        ratings.length > 0
                                            ? (
                                                  ratings.reduce(
                                                      (a, b) => a + b,
                                                      0,
                                                  ) / ratings.length
                                              ).toFixed(1)
                                            : null;
                                    const [expanded, setExpanded] = useState(false);

                                    return (
                                        <div
                                            key={ref.id}
                                            className={`rounded-lg border p-4 ${
                                                isCompleted
                                                    ? 'border-green-200 bg-green-50'
                                                    : 'border-border bg-card'
                                            }`}
                                        >
                                            <div className="flex items-start justify-between gap-2">
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-2">
                                                        <span className="truncate text-sm font-medium text-foreground">
                                                            {ref.reference_name}
                                                        </span>
                                                        {ref.is_sponsor && (
                                                            <span className="shrink-0 rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700">
                                                                Sponsor
                                                            </span>
                                                        )}
                                                        {isCompleted ? (
                                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">
                                                                Completed
                                                            </span>
                                                        ) : (
                                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                                                                Pending
                                                            </span>
                                                        )}
                                                    </div>
                                                    <p className="mt-1 truncate text-xs text-muted-foreground">
                                                        {ref.reference_email}
                                                    </p>
                                                    {ref.relationship && (
                                                        <p className="text-xs text-muted-foreground">
                                                            {ref.relationship}
                                                            {ref.years_known &&
                                                                ` · ${ref.years_known} years known`}
                                                        </p>
                                                    )}
                                                    {isCompleted && avg && (
                                                        <div className="mt-2 flex items-center gap-2">
                                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">
                                                                {avg}/5
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    setExpanded(
                                                                        !expanded,
                                                                    )
                                                                }
                                                                className="inline-flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground"
                                                            >
                                                                Details
                                                                <svg
                                                                    className={`h-3 w-3 transition-transform ${
                                                                        expanded
                                                                            ? 'rotate-180'
                                                                            : ''
                                                                    }`}
                                                                    fill="none"
                                                                    viewBox="0 0 24 24"
                                                                    stroke="currentColor"
                                                                >
                                                                    <path
                                                                        strokeLinecap="round"
                                                                        strokeLinejoin="round"
                                                                        strokeWidth={2}
                                                                        d="M19 9l-7 7-7-7"
                                                                    />
                                                                </svg>
                                                            </button>
                                                        </div>
                                                    )}
                                                    {expanded &&
                                                        isCompleted && (
                                                            <div className="mt-3 space-y-2 border-t border-border pt-3">
                                                                <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                                                                    {ratingKeys.map(
                                                                        (key) => {
                                                                            const labels: Record<
                                                                                string,
                                                                                string
                                                                            > = {
                                                                                rating_reliability:
                                                                                    'Reliability',
                                                                                rating_trustworthiness:
                                                                                    'Trustworthiness',
                                                                                rating_maturity:
                                                                                    'Maturity',
                                                                                rating_communication:
                                                                                    'Communication',
                                                                                rating_warmth:
                                                                                    'Warmth',
                                                                                rating_overall_recommendation:
                                                                                    'Overall',
                                                                            };
                                                                            const val =
                                                                                ref[
                                                                                    key as keyof ReferenceInfo
                                                                                ];
                                                                            return (
                                                                                <div
                                                                                    key={
                                                                                        key
                                                                                    }
                                                                                    className="flex items-center justify-between text-xs"
                                                                                >
                                                                                    <span className="text-muted-foreground">
                                                                                        {
                                                                                            labels[
                                                                                                key
                                                                                            ]
                                                                                        }
                                                                                    </span>
                                                                                    <span className="font-medium text-foreground">
                                                                                        {val ?? '—'}
                                                                                        /5
                                                                                    </span>
                                                                                </div>
                                                                            );
                                                                        },
                                                                    )}
                                                                </div>
                                                                {ref.strengths && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        <span className="font-medium text-foreground">
                                                                            Strengths:
                                                                        </span>{' '}
                                                                        {
                                                                            ref.strengths
                                                                        }
                                                                    </p>
                                                                )}
                                                                {ref.concerns && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        <span className="font-medium text-foreground">
                                                                            Concerns:
                                                                        </span>{' '}
                                                                        {
                                                                            ref.concerns
                                                                        }
                                                                    </p>
                                                                )}
                                                                {ref.additional_comments && (
                                                                    <p className="text-xs text-muted-foreground">
                                                                        <span className="font-medium text-foreground">
                                                                            Additional
                                                                            Comments:
                                                                        </span>{' '}
                                                                        {
                                                                            ref.additional_comments
                                                                        }
                                                                    </p>
                                                                )}
                                                            </div>
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
                            </div>
                        </div>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="border border-border bg-card p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Status
                                </h2>
                                <Badge
                                    className="text-sm px-3 py-1"
                                    style={{
                                        backgroundColor:
                                            (caregiverStatuses.find(
                                                (s) => s.value === status,
                                            )?.color ?? '#6B7280') + '20',
                                        color:
                                            caregiverStatuses.find(
                                                (s) => s.value === status,
                                            )?.color ?? '#6B7280',
                                    }}
                                >
                                    {application.caregiver.status_label}
                                </Badge>
                            </div>

                            {status === 'interview_scheduled' && (
                                <Link
                                    href={`/applications/${application.id}/interview`}
                                    className="mb-4 flex w-full items-center justify-center rounded-lg border border-border bg-card p-3 text-sm font-medium text-foreground transition-colors hover:bg-accent/50"
                                >
                                    Evaluate Interview
                                </Link>
                            )}

                            {actions.length > 0 && (
                                <div className="space-y-2">
                                    {actions.map((action) => (
                                        <div key={action.route}>
                                            <Button
                                                variant={
                                                    action.color ===
                                                    'destructive'
                                                        ? 'destructive'
                                                        : 'default'
                                                }
                                                className="w-full"
                                                onClick={() =>
                                                    handleAction(action)
                                                }
                                                disabled={
                                                    actionLoading ===
                                                    action.route
                                                }
                                            >
                                                {actionLoading === action.route
                                                    ? 'Processing...'
                                                    : action.label}
                                            </Button>
                                            {action.route === 'decline' && (
                                                <button
                                                    type="button"
                                                    onClick={() =>
                                                        setShowDeclineNote(
                                                            !showDeclineNote,
                                                        )
                                                    }
                                                    className="mt-1 text-xs text-muted-foreground hover:text-foreground"
                                                >
                                                    {showDeclineNote
                                                        ? 'Hide'
                                                        : 'Add reason (optional)'}
                                                </button>
                                            )}
                                            {action.route === 'decline' &&
                                                showDeclineNote && (
                                                    <textarea
                                                        value={declineNote}
                                                        onChange={(e) =>
                                                            setDeclineNote(
                                                                e.target.value,
                                                            )
                                                        }
                                                        placeholder="Reason for declining..."
                                                        className="mt-2 w-full rounded border border-border bg-card p-2 text-sm text-foreground placeholder:text-muted-foreground"
                                                        rows={3}
                                                    />
                                                )}
                                        </div>
                                    ))}
                                </div>
                            )}
                        </div>


                        <div className="border border-border bg-card p-6">
                            <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                Certifications
                            </h2>
                            <div className="space-y-3">
                                {certifications.length === 0 && (
                                    <p className="text-sm text-muted-foreground">
                                        No certifications
                                    </p>
                                )}
                                {certifications.map((cert) => (
                                    <div
                                        key={cert.id}
                                        className="flex items-center justify-between"
                                    >
                                        <div>
                                            <p className="text-sm font-medium text-foreground">
                                                {cert.name}
                                            </p>
                                            {cert.expiration_date && (
                                                <p className="text-xs text-muted-foreground">
                                                    Expires: {format(new Date(cert.expiration_date), 'MMMM d, yyyy')}
                                                </p>
                                            )}
                                        </div>
                                        {cert.verified_at ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">
                                                Verified
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">
                                                Unverified
                                            </span>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
