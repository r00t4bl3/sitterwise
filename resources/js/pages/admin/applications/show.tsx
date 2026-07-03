import { Head, Link, usePage, router, useForm } from '@inertiajs/react';
import { format } from 'date-fns';
import { ArrowLeft } from 'lucide-react';
import { useState } from 'react';
import { RatingInput } from '@/components/rating-input';
import { ToasterMessage } from '@/components/toaster-message';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
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
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import AppLayout from '@/layouts/app-layout';
import { calculateAgeFromDate } from '@/lib/age';
import { formatPhoneDisplay } from '@/lib/phone';
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
        address_zip?: string | null;
    };
    sponsor?: {
        first_name: string;
        last_name: string;
        email: string;
        phone?: string;
        relationship?: string;
    };
    position?: {
        babysitting?: boolean;
        petsitting?: boolean;
        group_events?: boolean;
    };
    availability?: {
        weekday_mornings?: boolean;
        weekday_afternoons?: boolean;
        weekday_evenings?: boolean;
        weekends?: boolean;
        overnights?: boolean;
        notes?: string;
    };
    education?: {
        level: string;
        college?: string;
        graduation_year?: string;
        degree?: string;
        high_school_name?: string;
        high_school_graduation_year?: string;
    };
    employment_status?: string;
    current_employer?: string;
    experiences?: Array<{
        start_date: string;
        end_date: string;
        present: boolean;
        role: string;
        organization: string;
        description: string;
        ages_served: string[];
    }>;
    smokes?: string;
    alcohol?: string;
    substance_abuse?: string;
    limitations?: string;
    allergic_to_pets?: string;
    allergic_to_what?: string;
    visible_tattoos?: string;
    tattoo_description?: string;
    authorized_to_work?: string;
    reliable_vehicle?: string;
    cpr_certified?: string;
    cpr_expiration?: string;
    trustline_certified?: string;
    languages?: string[];
    has_children?: string;
    children_ages?: string;
    qualifications?: Record<string, boolean>;
    things_i_bring?: string;
    bio?: string;
    interests?: string;
    location?: {
        north_county?: boolean;
        south_east_county?: boolean;
        flexible?: boolean;
    };
    age_groups?: Record<string, boolean>;
    verification?: {
        signature?: string;
        agree?: boolean;
    };
    agreement?: {
        signature?: string;
        agree?: boolean;
    };
}

interface ApplicationInfo {
    id: number;
    submitted_at: string;
    data: ApplicationData;
    caregiver: CaregiverInfo;
    photo_url?: string;
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
    rating_appearance: number | null;
    rating_punctuality: number | null;
    strengths: string | null;
    concerns: string | null;
    additional_comments: string | null;
    background_drug_alcohol: string | null;
    background_tobacco: string | null;
    trust_own_child: string | null;
    reason_not_care: string | null;
    reason_not_care_explanation: string | null;
    submitted_at: string | null;
    created_at: string;
}

interface CertificationInfo {
    id: number;
    name: string;
    expires_required: boolean;
    expiration_date: string | null;
    verified_at: string | null;
    file_path: string | null;
    file_url: string | null;
    notes: string | null;
}

interface ChecklistItemInfo {
    id: number;
    item_key: string;
    label: string;
    description: string | null;
    completed_at: string | null;
}

interface InterviewInfo {
    id: number;
    status: string;
    composite: number | null;
    evaluated_at: string | null;
    evaluator_name: string | null;
}

interface Props {
    application: ApplicationInfo;
    references: ReferenceInfo[];
    certifications: CertificationInfo[];
    checklistItems: ChecklistItemInfo[];
    caregiverStatuses: Array<{
        value: string;
        label: string;
        color: string;
        is_terminal: boolean;
    }>;
    interview: InterviewInfo | null;
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

function getActions(
    status: string,
    caregiverStatuses: Array<{
        value: string;
        label: string;
        color: string;
        is_terminal: boolean;
    }>,
): Action[] {
    const actions: Action[] = [];
    const current = caregiverStatuses.find((s) => s.value === status);

    switch (status) {
        case 'applicant':
            actions.push({
                label: 'Approve',
                route: 'approve',
                color: 'default',
                confirm: 'Move this application to Under Review?',
            });
            break;
        case 'under_review':
            actions.push({
                label: 'Schedule Interview',
                route: 'schedule-interview',
                color: 'default',
                confirm: 'Mark interview as scheduled?',
            });
            break;
        case 'interview_scheduled':
            actions.push({
                label: 'Start Background Check',
                route: 'background-check',
                color: 'default',
                confirm: 'Start background check process?',
            });
            break;
        case 'background_check':
            actions.push({
                label: 'Hire',
                route: 'hire',
                color: 'default',
                confirm: 'Hire this caregiver?',
            });
            break;
        case 'hired_onboarding':
            actions.push({
                label: 'Complete Onboarding',
                route: 'complete-onboarding',
                color: 'default',
                confirm: 'Complete onboarding and activate this caregiver?',
            });
            break;
    }

    if (!current?.is_terminal) {
        actions.push({
            label: 'Decline',
            route: 'decline',
            color: 'destructive',
            confirm:
                'Decline this application? The applicant will be notified.',
        });
    }

    return actions;
}

function CollapsibleSection({
    title,
    defaultOpen = true,
    children,
}: {
    title: string;
    defaultOpen?: boolean;
    children: React.ReactNode;
}) {
    const [open, setOpen] = useState(defaultOpen);

    return (
        <div className="border border-border bg-card p-6">
            <button
                type="button"
                onClick={() => setOpen(!open)}
                className="flex w-full cursor-pointer items-center justify-between"
            >
                <h2 className="font-serif text-lg font-semibold text-foreground">
                    {title}
                </h2>
                <svg
                    className={`h-5 w-5 text-muted-foreground transition-transform ${
                        open ? '' : '-rotate-90'
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
            {open && <div className="mt-4">{children}</div>}
        </div>
    );
}

export default function ApplicationShow() {
    const {
        application,
        references,
        certifications,
        checklistItems,
        caregiverStatuses,
        interview,
    } = usePage<Props>().props;
    const [resending, setResending] = useState<number | null>(null);
    const [actionLoading, setActionLoading] = useState<string | null>(null);
    const [togglingItem, setTogglingItem] = useState<number | null>(null);
    const [declineNote, setDeclineNote] = useState('');
    const [showDeclineNote, setShowDeclineNote] = useState(false);
    const [expandedRefs, setExpandedRefs] = useState<Set<number>>(new Set());
    const [confirmDialog, setConfirmDialog] = useState<{
        open: boolean;
        title: string;
        message: string;
        onConfirm: () => void;
    }>({
        open: false,
        title: '',
        message: '',
        onConfirm: () => {},
    });

    const toggleRef = (id: number) => {
        setExpandedRefs((prev) => {
            const next = new Set(prev);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });
    };

    const data = application.data;
    const personal = data.personal ?? {
        first_name: '',
        last_name: '',
        phone: '',
        dob: '',
    };
    const sponsor = data.sponsor;
    const completedRefs = references.filter((r) => r.submitted_at);
    const status = application.caregiver.status;
    const actions = getActions(status, caregiverStatuses);

    function handleResend(refId: number) {
        setConfirmDialog({
            open: true,
            title: 'Resend Reference Request',
            message: 'Resend this reference request email?',
            onConfirm: () => {
                setConfirmDialog((prev) => ({ ...prev, open: false }));
                setResending(refId);
                router.post(
                    `/applications/${application.id}/references/${refId}/resend`,
                    {},
                    {
                        preserveScroll: true,
                        onFinish: () => setResending(null),
                    },
                );
            },
        });
    }

    const editForm = useForm({
        reference_name: '',
        reference_email: '',
        relationship: '',
        years_known: '',
        is_sponsor: false,
        rating_reliability: null as number | null,
        rating_trustworthiness: null as number | null,
        rating_maturity: null as number | null,
        rating_communication: null as number | null,
        rating_warmth: null as number | null,
        rating_overall_recommendation: null as number | null,
        rating_appearance: null as number | null,
        rating_punctuality: null as number | null,
        strengths: '',
        concerns: '',
        additional_comments: '',
        background_drug_alcohol: '',
        background_tobacco: '',
        trust_own_child: '',
        reason_not_care: '',
        reason_not_care_explanation: '',
    });

    const [editingRefId, setEditingRefId] = useState<number | null>(null);

    function openEditDialog(ref: ReferenceInfo) {
        editForm.setData('reference_name', ref.reference_name);
        editForm.setData('reference_email', ref.reference_email);
        editForm.setData('relationship', ref.relationship ?? '');
        editForm.setData('years_known', ref.years_known ?? '');
        editForm.setData('is_sponsor', ref.is_sponsor);
        editForm.setData('rating_reliability', ref.rating_reliability);
        editForm.setData('rating_trustworthiness', ref.rating_trustworthiness);
        editForm.setData('rating_maturity', ref.rating_maturity);
        editForm.setData('rating_communication', ref.rating_communication);
        editForm.setData('rating_warmth', ref.rating_warmth);
        editForm.setData(
            'rating_overall_recommendation',
            ref.rating_overall_recommendation,
        );
        editForm.setData('rating_appearance', ref.rating_appearance);
        editForm.setData('rating_punctuality', ref.rating_punctuality);
        editForm.setData('strengths', ref.strengths ?? '');
        editForm.setData('concerns', ref.concerns ?? '');
        editForm.setData('additional_comments', ref.additional_comments ?? '');
        editForm.setData(
            'background_drug_alcohol',
            ref.background_drug_alcohol ?? '',
        );
        editForm.setData('background_tobacco', ref.background_tobacco ?? '');
        editForm.setData('trust_own_child', ref.trust_own_child ?? '');
        editForm.setData('reason_not_care', ref.reason_not_care ?? '');
        editForm.setData(
            'reason_not_care_explanation',
            ref.reason_not_care_explanation ?? '',
        );
        setEditingRefId(ref.id);
    }

    function handleSaveEdit() {
        if (!editingRefId) {
            return;
        }

        editForm.patch(
            `/applications/${application.id}/references/${editingRefId}`,
            { preserveScroll: true, onSuccess: () => setEditingRefId(null) },
        );
    }

    function handleToggleChecklistItem(itemId: number) {
        setTogglingItem(itemId);
        router.post(
            `/applications/${application.id}/checklist/${itemId}/toggle`,
            {},
            {
                preserveScroll: true,
                onFinish: () => setTogglingItem(null),
            },
        );
    }

    function handleAction(action: Action) {
        if (action.confirm) {
            setConfirmDialog({
                open: true,
                title: 'Confirm Action',
                message: action.confirm,
                onConfirm: () => {
                    setConfirmDialog((prev) => ({ ...prev, open: false }));
                    executeAction(action);
                },
            });

            return;
        }

        executeAction(action);
    }

    function executeAction(action: Action) {
        if (action.route === 'decline' && showDeclineNote) {
            setActionLoading(action.route);
            router.post(
                `/applications/${application.id}/${action.route}`,
                { note: declineNote },
                {
                    preserveScroll: true,
                    onFinish: () => setActionLoading(null),
                },
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
        <AppLayout
            breadcrumbs={[
                ...breadcrumbs,
                {
                    title: `${personal.first_name} ${personal.last_name}`,
                    href: `/applications/${application.id}`,
                },
            ]}
        >
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
                        <CollapsibleSection title="Applicant Information">
                            {application.photo_url && (
                                <div className="mb-4">
                                    <img
                                        src={application.photo_url}
                                        alt={`${personal.first_name} ${personal.last_name}`}
                                        className="h-48 w-48 rounded-lg border border-border object-cover"
                                    />
                                </div>
                            )}
                            <div className="grid gap-4 sm:grid-cols-2">
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Name
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.first_name}{' '}
                                        {personal.last_name}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Phone
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {formatPhoneDisplay(personal.phone) ||
                                            '-'}
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
                                            personal.address_zip,
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
                                        {personal.dob
                                            ? format(
                                                  new Date(personal.dob),
                                                  'MMMM d, yyyy',
                                              )
                                            : '—'}
                                    </p>
                                </div>
                                <div>
                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                        Age
                                    </p>
                                    <p className="text-sm font-medium text-foreground">
                                        {personal.dob
                                            ? `${calculateAgeFromDate(personal.dob)} years old`
                                            : '—'}
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
                                                {sponsor.first_name}{' '}
                                                {sponsor.last_name}
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
                        </CollapsibleSection>

                        {/* Education */}
                        {data.education && (
                            <CollapsibleSection title="Education">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Highest Level
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {(
                                                {
                                                    high_school: 'High School',
                                                    associate:
                                                        'Associate Degree',
                                                    bachelor:
                                                        "Bachelor's Degree",
                                                    master: "Master's Degree",
                                                    phd: 'PhD',
                                                } as Record<string, string>
                                            )[data.education.level] ??
                                                data.education.level}
                                        </p>
                                    </div>
                                    {data.education.level !== 'high_school' && (
                                        <>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Degree / Major
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.education.degree ||
                                                        '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    College
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.education.college ||
                                                        '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Graduation Year
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.education
                                                        .graduation_year || '-'}
                                                </p>
                                            </div>
                                        </>
                                    )}
                                    {data.education.level === 'high_school' && (
                                        <>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    High School
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.education
                                                        .high_school_name ||
                                                        '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Grad Year
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.education
                                                        .high_school_graduation_year ||
                                                        '-'}
                                                </p>
                                            </div>
                                        </>
                                    )}
                                </div>
                            </CollapsibleSection>
                        )}

                        {/* Experience */}
                        {data.experiences && data.experiences.length > 0 && (
                            <CollapsibleSection
                                title={`Experience (${data.experiences.length})`}
                            >
                                {data.experiences.map((exp, index) => (
                                    <div
                                        key={index}
                                        className={
                                            index > 0
                                                ? 'mt-4 border-t border-border pt-4'
                                                : ''
                                        }
                                    >
                                        <div className="grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Role
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {exp.role || '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Organization
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {exp.organization || '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Duration
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {exp.start_date} —{' '}
                                                    {exp.present
                                                        ? 'Present'
                                                        : exp.end_date || '-'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Ages Served
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {exp.ages_served?.join(
                                                        ', ',
                                                    ) || '-'}
                                                </p>
                                            </div>
                                            {exp.description && (
                                                <div className="sm:col-span-2">
                                                    <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                        Description
                                                    </p>
                                                    <p className="text-sm text-foreground">
                                                        {exp.description}
                                                    </p>
                                                </div>
                                            )}
                                        </div>
                                    </div>
                                ))}
                            </CollapsibleSection>
                        )}

                        {/* Screening Questions */}
                        {data.authorized_to_work && (
                            <CollapsibleSection title="Screening Questions">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Employment Status
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {((
                                                {
                                                    full_time: 'Full-Time',
                                                    part_time: 'Part-Time',
                                                    no: 'Not Employed',
                                                    student: 'Student',
                                                } as Record<string, string>
                                            )[data.employment_status ?? ''] ??
                                                data.employment_status) ||
                                                '-'}
                                        </p>
                                    </div>
                                    {data.employment_status &&
                                        data.employment_status !== 'no' &&
                                        data.employment_status !==
                                            'student' && (
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Current Employer
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.current_employer ||
                                                        '-'}
                                                </p>
                                            </div>
                                        )}
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Authorized to Work in U.S.
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.authorized_to_work === 'yes'
                                                ? 'Yes'
                                                : data.authorized_to_work ===
                                                    'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Smokes
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.smokes === 'yes'
                                                ? 'Yes'
                                                : data.smokes === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Alcohol
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {((
                                                {
                                                    no: 'No',
                                                    socially:
                                                        'Socially/Occasionally',
                                                    regularly: 'Regularly',
                                                } as Record<string, string>
                                            )[data.alcohol ?? ''] ??
                                                data.alcohol) ||
                                                '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Allergic to Dogs or Cats
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.allergic_to_pets === 'yes'
                                                ? `Yes — ${({ dogs: 'Dogs', cats: 'Cats', both: 'Both' } as Record<string, string>)[data.allergic_to_what ?? ''] ?? data.allergic_to_what}`
                                                : data.allergic_to_pets === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Visible Tattoos
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.visible_tattoos === 'yes'
                                                ? 'Yes'
                                                : data.visible_tattoos === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Reliable Vehicle
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.reliable_vehicle === 'yes'
                                                ? 'Yes'
                                                : data.reliable_vehicle === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            CPR & First Aid Certified
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.cpr_certified === 'yes'
                                                ? 'Yes'
                                                : data.cpr_certified === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    {data.cpr_certified === 'yes' &&
                                        data.cpr_expiration && (
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    CPR & First Aid Expiration
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.cpr_expiration}
                                                </p>
                                            </div>
                                        )}
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Trustline Certified
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.trustline_certified === 'yes'
                                                ? 'Yes'
                                                : data.trustline_certified ===
                                                    'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Languages
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.languages?.length
                                                ? data.languages.map((lang) => (
                                                      <span
                                                          key={lang}
                                                          className="mr-1 inline-block rounded bg-secondary px-2 py-0.5 text-xs"
                                                      >
                                                          {lang
                                                              .replace(
                                                                  /_/g,
                                                                  ' ',
                                                              )
                                                              .replace(
                                                                  /\b\w/g,
                                                                  (l) =>
                                                                      l.toUpperCase(),
                                                              )}
                                                      </span>
                                                  ))
                                                : '-'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Has Children
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.has_children === 'yes'
                                                ? `Yes — ${data.children_ages || 'ages not specified'}`
                                                : data.has_children === 'no'
                                                  ? 'No'
                                                  : '-'}
                                        </p>
                                    </div>
                                    {data.tattoo_description && (
                                        <div className="sm:col-span-2">
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Tattoo Description
                                            </p>
                                            <p className="text-sm text-foreground">
                                                {data.tattoo_description}
                                            </p>
                                        </div>
                                    )}
                                    {data.substance_abuse && (
                                        <div className="sm:col-span-2">
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Substance Abuse History
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap text-foreground">
                                                {data.substance_abuse}
                                            </p>
                                        </div>
                                    )}
                                    {data.limitations && (
                                        <div className="sm:col-span-2">
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Physical/Psychological
                                                Limitations
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap text-foreground">
                                                {data.limitations}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CollapsibleSection>
                        )}

                        {/* Position & Availability */}
                        {data.position && (
                            <CollapsibleSection title="Position & Availability">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            On-Call Babysitting
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.position.babysitting
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            On-Call Petsitting
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.position.petsitting
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Group Events
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.position.group_events
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                </div>
                                {data.availability && (
                                    <>
                                        <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Weekday Mornings
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.availability
                                                        .weekday_mornings
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Weekday Afternoons
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.availability
                                                        .weekday_afternoons
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Weekday Evenings
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.availability
                                                        .weekday_evenings
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Weekends
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.availability.weekends
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                            </div>
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Overnights
                                                </p>
                                                <p className="text-sm font-medium text-foreground">
                                                    {data.availability
                                                        .overnights
                                                        ? 'Yes'
                                                        : 'No'}
                                                </p>
                                            </div>
                                        </div>
                                        {data.availability.notes && (
                                            <div className="mt-4 sm:col-span-2">
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Availability Notes
                                                </p>
                                                <p className="text-sm whitespace-pre-wrap text-foreground">
                                                    {data.availability.notes}
                                                </p>
                                            </div>
                                        )}
                                    </>
                                )}
                            </CollapsibleSection>
                        )}

                        {/* Qualifications & Activities */}
                        {data.qualifications && (
                            <CollapsibleSection title="Qualifications & Activities">
                                <div className="space-y-3">
                                    {(() => {
                                        const labels: Record<string, string> = {
                                            special_needs: 'Special Needs Care',
                                            companion_care: 'Companion Care',
                                            sick_care: 'Sick Care',
                                            work_from_home:
                                                'Work-From-Home Parents',
                                            driving: 'Driving',
                                            dogsitting: 'Dogsitting',
                                            swimming: 'Swimming',
                                            overnight_care: 'Overnight Care',
                                        };
                                        const selected = Object.entries(
                                            data.qualifications,
                                        )
                                            .filter(([, v]) => v)
                                            .map(([k]) => labels[k] ?? k);

                                        return selected.length > 0 ? (
                                            <div>
                                                <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                    Selected Qualifications
                                                </p>
                                                <div className="mt-1 flex flex-wrap gap-1.5">
                                                    {selected.map((label) => (
                                                        <span
                                                            key={label}
                                                            className="inline-block rounded bg-primary/10 px-2 py-0.5 text-xs font-medium text-primary"
                                                        >
                                                            {label}
                                                        </span>
                                                    ))}
                                                </div>
                                            </div>
                                        ) : (
                                            <p className="text-sm text-muted-foreground italic">
                                                None selected
                                            </p>
                                        );
                                    })()}
                                    {data.things_i_bring && (
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Things I Bring to a Job
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap text-foreground">
                                                {data.things_i_bring}
                                            </p>
                                        </div>
                                    )}
                                    {data.interests && (
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Interests & Hobbies
                                            </p>
                                            <p className="text-sm whitespace-pre-wrap text-foreground">
                                                {data.interests}
                                            </p>
                                        </div>
                                    )}
                                </div>
                            </CollapsibleSection>
                        )}

                        {/* Location & Age Groups */}
                        {data.location && (
                            <CollapsibleSection title="Location & Age Groups">
                                <div className="grid gap-4 sm:grid-cols-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            North County
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.location.north_county
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            South / East County
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.location.south_east_county
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                            Flexible
                                        </p>
                                        <p className="text-sm font-medium text-foreground">
                                            {data.location.flexible
                                                ? 'Yes'
                                                : 'No'}
                                        </p>
                                    </div>
                                </div>
                                {data.age_groups && (
                                    <div className="mt-4 grid gap-4 sm:grid-cols-2">
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Babies
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {data.age_groups.babies
                                                    ? 'Yes'
                                                    : 'No'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Toddlers
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {data.age_groups.toddlers
                                                    ? 'Yes'
                                                    : 'No'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                Preschool
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {data.age_groups.preschool
                                                    ? 'Yes'
                                                    : 'No'}
                                            </p>
                                        </div>
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">
                                                School Age
                                            </p>
                                            <p className="text-sm font-medium text-foreground">
                                                {data.age_groups.school_age
                                                    ? 'Yes'
                                                    : 'No'}
                                            </p>
                                        </div>
                                    </div>
                                )}
                            </CollapsibleSection>
                        )}

                        {/* References */}
                        <CollapsibleSection
                            title={`References (${completedRefs.length}/${references.length} completed)`}
                        >
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
                                        'rating_appearance',
                                        'rating_punctuality',
                                    ] as const;
                                    const ratings = ratingKeys
                                        .map(
                                            (k) =>
                                                ref[k as keyof ReferenceInfo],
                                        )
                                        .filter((r): r is number => r != null);
                                    const avg =
                                        ratings.length > 0
                                            ? (
                                                  ratings.reduce(
                                                      (a, b) => a + b,
                                                      0,
                                                  ) / ratings.length
                                              ).toFixed(1)
                                            : null;
                                    const expanded = expandedRefs.has(ref.id);

                                    return (
                                        <div
                                            key={ref.id}
                                            className={`rounded-lg border p-4 ${
                                                isCompleted
                                                    ? 'border-l-4 border-border border-l-green-500 bg-card'
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
                                                            <span className="shrink-0 rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700 dark:bg-purple-900/50 dark:text-purple-300">
                                                                Sponsor
                                                            </span>
                                                        )}
                                                        {isCompleted ? (
                                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                                                Completed
                                                            </span>
                                                        ) : (
                                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
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
                                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                                                {avg}/5
                                                            </span>
                                                            <button
                                                                type="button"
                                                                onClick={() =>
                                                                    toggleRef(
                                                                        ref.id,
                                                                    )
                                                                }
                                                                className="inline-flex cursor-pointer items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground"
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
                                                                        strokeWidth={
                                                                            2
                                                                        }
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
                                                                        (
                                                                            key,
                                                                        ) => {
                                                                            const labels: Record<
                                                                                string,
                                                                                string
                                                                            > =
                                                                                {
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
                                                                                    rating_appearance:
                                                                                        'Appearance',
                                                                                    rating_punctuality:
                                                                                        'Punctuality',
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
                                                                                        {val ??
                                                                                            '—'}
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
                                                                <div className="border-t border-border pt-2">
                                                                    <p className="text-xs font-semibold text-foreground">
                                                                        Background
                                                                    </p>
                                                                    <div className="mt-1 space-y-0.5 text-xs text-muted-foreground">
                                                                        <p>
                                                                            <span className="font-medium text-foreground">
                                                                                Drug/Alcohol:
                                                                            </span>{' '}
                                                                            {ref.background_drug_alcohol ??
                                                                                '—'}
                                                                        </p>
                                                                        <p>
                                                                            <span className="font-medium text-foreground">
                                                                                Tobacco:
                                                                            </span>{' '}
                                                                            {ref.background_tobacco ??
                                                                                '—'}
                                                                        </p>
                                                                        <p>
                                                                            <span className="font-medium text-foreground">
                                                                                Trust
                                                                                6+
                                                                                hrs:
                                                                            </span>{' '}
                                                                            {ref.trust_own_child ??
                                                                                '—'}
                                                                        </p>
                                                                        <p>
                                                                            <span className="font-medium text-foreground">
                                                                                Reason
                                                                                not
                                                                                care:
                                                                            </span>{' '}
                                                                            {ref.reason_not_care ??
                                                                                '—'}
                                                                            {ref.reason_not_care ===
                                                                                'Yes' &&
                                                                                ref.reason_not_care_explanation &&
                                                                                ` — ${ref.reason_not_care_explanation}`}
                                                                        </p>
                                                                    </div>
                                                                </div>
                                                            </div>
                                                        )}
                                                </div>
                                                <div className="ml-4 flex shrink-0 gap-1">
                                                    <Button
                                                        variant="outline"
                                                        size="sm"
                                                        onClick={() =>
                                                            openEditDialog(ref)
                                                        }
                                                    >
                                                        Edit
                                                    </Button>
                                                    {!isCompleted && (
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            onClick={() =>
                                                                handleResend(
                                                                    ref.id,
                                                                )
                                                            }
                                                            disabled={
                                                                resending ===
                                                                ref.id
                                                            }
                                                        >
                                                            {resending ===
                                                            ref.id
                                                                ? 'Resending...'
                                                                : 'Resend'}
                                                        </Button>
                                                    )}
                                                </div>
                                            </div>
                                        </div>
                                    );
                                })}
                            </div>
                        </CollapsibleSection>
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        <div className="border border-border bg-card p-6">
                            <div className="mb-4 flex items-center justify-between">
                                <h2 className="font-serif text-lg font-semibold text-foreground">
                                    Status
                                </h2>
                                <Badge
                                    className="px-3 py-1 text-sm"
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

                        {interview && (
                            <div className="border border-border bg-card p-6">
                                <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                    Interview
                                </h2>
                                <div className="space-y-3">
                                    <div className="flex items-center justify-between text-sm">
                                        <span className="text-muted-foreground">
                                            Status
                                        </span>
                                        <span className="font-medium capitalize">
                                            {interview.status}
                                        </span>
                                    </div>
                                    {interview.composite !== null && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Score
                                            </span>
                                            <span className="font-medium">
                                                {interview.composite}/36
                                            </span>
                                        </div>
                                    )}
                                    {interview.evaluator_name && (
                                        <div className="flex items-center justify-between text-sm">
                                            <span className="text-muted-foreground">
                                                Evaluator
                                            </span>
                                            <span className="font-medium">
                                                {interview.evaluator_name}
                                            </span>
                                        </div>
                                    )}
                                    <Link
                                        href={`/applications/${application.id}/interview`}
                                        className="flex w-full items-center justify-center rounded-md bg-primary px-4 py-2 text-sm font-medium text-primary-foreground hover:bg-primary/90"
                                    >
                                        View Interview
                                    </Link>
                                </div>
                            </div>
                        )}

                        {status === 'hired_onboarding' &&
                            checklistItems.length > 0 && (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                        Onboarding Checklist
                                    </h2>
                                    <div className="space-y-3">
                                        {checklistItems.map((item) => (
                                            <div
                                                key={item.id}
                                                role="button"
                                                tabIndex={0}
                                                onClick={() =>
                                                    handleToggleChecklistItem(
                                                        item.id,
                                                    )
                                                }
                                                onKeyDown={(e) => {
                                                    if (
                                                        e.key === 'Enter' ||
                                                        e.key === ' '
                                                    ) {
                                                        handleToggleChecklistItem(
                                                            item.id,
                                                        );
                                                    }
                                                }}
                                                className={`flex cursor-pointer items-start gap-3 rounded-lg p-3 transition-colors hover:bg-accent/50 ${
                                                    togglingItem === item.id
                                                        ? 'pointer-events-none opacity-60'
                                                        : ''
                                                }`}
                                            >
                                                <div
                                                    className={`mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded border-2 transition-colors ${
                                                        item.completed_at
                                                            ? 'border-green-500 bg-green-500 text-white'
                                                            : 'border-border'
                                                    }`}
                                                >
                                                    {item.completed_at && (
                                                        <svg
                                                            className="h-3 w-3"
                                                            fill="none"
                                                            viewBox="0 0 24 24"
                                                            stroke="currentColor"
                                                        >
                                                            <path
                                                                strokeLinecap="round"
                                                                strokeLinejoin="round"
                                                                strokeWidth={3}
                                                                d="M5 13l4 4L19 7"
                                                            />
                                                        </svg>
                                                    )}
                                                </div>
                                                <div className="min-w-0 flex-1">
                                                    <div className="flex items-center gap-1.5">
                                                        <p
                                                            className={`text-sm font-medium ${item.completed_at ? 'text-green-600 line-through' : 'text-foreground'}`}
                                                        >
                                                            {item.label}
                                                        </p>
                                                        {item.description && (
                                                            <Tooltip>
                                                                <TooltipTrigger
                                                                    asChild
                                                                >
                                                                    <button
                                                                        type="button"
                                                                        onClick={(
                                                                            e,
                                                                        ) =>
                                                                            e.stopPropagation()
                                                                        }
                                                                        className="inline-flex h-4 w-4 cursor-pointer items-center justify-center rounded-full bg-muted text-[10px] font-bold text-muted-foreground hover:bg-table-header hover:text-background"
                                                                    >
                                                                        ?
                                                                    </button>
                                                                </TooltipTrigger>
                                                                <TooltipContent>
                                                                    {
                                                                        item.description
                                                                    }
                                                                </TooltipContent>
                                                            </Tooltip>
                                                        )}
                                                    </div>
                                                    {item.completed_at && (
                                                        <Badge className="mt-1 bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                                            Done ·{' '}
                                                            {format(
                                                                new Date(
                                                                    item.completed_at,
                                                                ),
                                                                'MMM d',
                                                            )}
                                                        </Badge>
                                                    )}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                </div>
                            )}

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
                                                    Expires:{' '}
                                                    {format(
                                                        new Date(
                                                            cert.expiration_date,
                                                        ),
                                                        'MMMM d, yyyy',
                                                    )}
                                                </p>
                                            )}
                                            {cert.file_url && (
                                                <a
                                                    href={cert.file_url}
                                                    target="_blank"
                                                    rel="noopener noreferrer"
                                                    className="mt-0.5 inline-flex items-center gap-1 text-xs text-blue-600 hover:text-blue-800 hover:underline"
                                                >
                                                    View Attachment
                                                </a>
                                            )}
                                        </div>
                                        {cert.verified_at ? (
                                            <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700 dark:bg-green-900/50 dark:text-green-300">
                                                Verified
                                            </span>
                                        ) : (
                                            <span className="inline-flex items-center gap-1 rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700 dark:bg-amber-900/50 dark:text-amber-300">
                                                Unverified
                                            </span>
                                        )}
                                        <button
                                            type="button"
                                            onClick={() =>
                                                setConfirmDialog({
                                                    open: true,
                                                    title: cert.verified_at
                                                        ? `Unverify ${cert.name}?`
                                                        : `Verify ${cert.name}?`,
                                                    message: cert.verified_at
                                                        ? `Mark "${cert.name}" as unverified for this caregiver?`
                                                        : `Mark "${cert.name}" as verified for this caregiver?`,
                                                    onConfirm: () => {
                                                        setConfirmDialog(
                                                            (prev) => ({
                                                                ...prev,
                                                                open: false,
                                                            }),
                                                        );
                                                        router.post(
                                                            `/applications/${application.id}/certifications/${cert.id}/verify`,
                                                            {},
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        );
                                                    },
                                                })
                                            }
                                            className={`ml-2 inline-flex items-center gap-1 rounded px-1.5 py-0.5 text-[10px] font-medium transition-colors ${
                                                cert.verified_at
                                                    ? 'text-muted-foreground hover:bg-red-100 hover:text-red-700 dark:hover:bg-red-900/50 dark:hover:text-red-300'
                                                    : 'text-muted-foreground hover:bg-green-100 hover:text-green-700 dark:hover:bg-green-900/50 dark:hover:text-green-300'
                                            }`}
                                        >
                                            {cert.verified_at
                                                ? 'Unverify'
                                                : 'Verify'}
                                        </button>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <Dialog
                open={confirmDialog.open}
                onOpenChange={(open) =>
                    setConfirmDialog((prev) => ({ ...prev, open }))
                }
            >
                <DialogContent>
                    <DialogHeader>
                        <DialogTitle>{confirmDialog.title}</DialogTitle>
                        <DialogDescription>
                            {confirmDialog.message}
                        </DialogDescription>
                    </DialogHeader>
                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() =>
                                setConfirmDialog((prev) => ({
                                    ...prev,
                                    open: false,
                                }))
                            }
                        >
                            Cancel
                        </Button>
                        <Button onClick={confirmDialog.onConfirm}>
                            Confirm
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>

            <Dialog
                open={editingRefId !== null}
                onOpenChange={(open) => {
                    if (!open) {
                        setEditingRefId(null);
                    }
                }}
            >
                <DialogContent className="max-h-[90vh] overflow-y-auto sm:max-w-lg">
                    <DialogHeader>
                        <DialogTitle>Edit Reference</DialogTitle>
                        <DialogDescription>
                            Update reference details. Ratings and feedback can
                            be entered on behalf of the reference if they
                            submitted via a different channel.
                        </DialogDescription>
                    </DialogHeader>

                    <div className="space-y-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="ref-name">Name</Label>
                                <Input
                                    id="ref-name"
                                    value={editForm.data.reference_name}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'reference_name',
                                            e.target.value,
                                        )
                                    }
                                />
                                {editForm.errors.reference_name && (
                                    <p className="text-sm text-destructive">
                                        {editForm.errors.reference_name}
                                    </p>
                                )}
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="ref-email">Email</Label>
                                <Input
                                    id="ref-email"
                                    value={editForm.data.reference_email}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'reference_email',
                                            e.target.value,
                                        )
                                    }
                                />
                                {editForm.errors.reference_email && (
                                    <p className="text-sm text-destructive">
                                        {editForm.errors.reference_email}
                                    </p>
                                )}
                            </div>
                        </div>

                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="ref-relationship">
                                    Relationship
                                </Label>
                                <Input
                                    id="ref-relationship"
                                    value={editForm.data.relationship}
                                    onChange={(e) =>
                                        editForm.setData(
                                            'relationship',
                                            e.target.value,
                                        )
                                    }
                                />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="ref-years-known">
                                    Years Known
                                </Label>
                                <Select
                                    value={
                                        editForm.data.years_known || undefined
                                    }
                                    onValueChange={(value) =>
                                        editForm.setData('years_known', value)
                                    }
                                >
                                    <SelectTrigger id="ref-years-known">
                                        <SelectValue placeholder="Select years known" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        {[
                                            {
                                                value: '<1',
                                                label: 'Less than 1 year',
                                            },
                                            {
                                                value: '1-3',
                                                label: '1-3 years',
                                            },
                                            {
                                                value: '3-5',
                                                label: '3-5 years',
                                            },
                                            {
                                                value: '5-10',
                                                label: '5-10 years',
                                            },
                                            {
                                                value: '10+',
                                                label: '10+ years',
                                            },
                                        ].map((option) => (
                                            <SelectItem
                                                key={option.value}
                                                value={option.value}
                                            >
                                                {option.label}
                                            </SelectItem>
                                        ))}
                                    </SelectContent>
                                </Select>
                            </div>
                        </div>

                        <label className="flex items-center gap-2">
                            <Checkbox
                                checked={editForm.data.is_sponsor}
                                onCheckedChange={(checked) =>
                                    editForm.setData(
                                        'is_sponsor',
                                        checked === true,
                                    )
                                }
                            />
                            <span className="text-sm">This is the sponsor</span>
                        </label>

                        <div>
                            <h4 className="mb-2 text-sm font-medium">
                                Ratings
                            </h4>
                            <div className="space-y-2">
                                {(
                                    [
                                        [
                                            'rating_reliability',
                                            'Reliability & Dependability',
                                        ],
                                        [
                                            'rating_trustworthiness',
                                            'Trustworthiness',
                                        ],
                                        ['rating_maturity', 'Maturity'],
                                        [
                                            'rating_communication',
                                            'Communication',
                                        ],
                                        [
                                            'rating_warmth',
                                            'Warmth & Compassion',
                                        ],
                                        [
                                            'rating_overall_recommendation',
                                            'Overall Recommendation',
                                        ],
                                        [
                                            'rating_appearance',
                                            'Appearance & Presentation',
                                        ],
                                        ['rating_punctuality', 'Punctuality'],
                                    ] as const
                                ).map(([key, label]) => (
                                    <div key={key}>
                                        <Label className="text-xs font-normal">
                                            {label}
                                        </Label>
                                        <RatingInput
                                            value={
                                                editForm.data[
                                                    key as keyof typeof editForm.data
                                                ] as number | null
                                            }
                                            onChange={(val) =>
                                                editForm.setData(
                                                    key as keyof typeof editForm.data,
                                                    val,
                                                )
                                            }
                                            wholeStarsOnly
                                            allowClear
                                            size="sm"
                                            showScore={false}
                                        />
                                        {editForm.errors[key] && (
                                            <p className="text-sm text-destructive">
                                                {editForm.errors[key]}
                                            </p>
                                        )}
                                    </div>
                                ))}
                            </div>
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ref-strengths">Strengths</Label>
                            <Textarea
                                id="ref-strengths"
                                rows={2}
                                value={editForm.data.strengths}
                                onChange={(e) =>
                                    editForm.setData(
                                        'strengths',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ref-concerns">Concerns</Label>
                            <Textarea
                                id="ref-concerns"
                                rows={2}
                                value={editForm.data.concerns}
                                onChange={(e) =>
                                    editForm.setData('concerns', e.target.value)
                                }
                            />
                        </div>

                        <div className="space-y-1">
                            <Label htmlFor="ref-additional-comments">
                                Additional Comments
                            </Label>
                            <Textarea
                                id="ref-additional-comments"
                                rows={2}
                                value={editForm.data.additional_comments}
                                onChange={(e) =>
                                    editForm.setData(
                                        'additional_comments',
                                        e.target.value,
                                    )
                                }
                            />
                        </div>

                        <div className="space-y-2">
                            <h4 className="text-sm font-medium">Background</h4>
                            <div className="space-y-3">
                                <div className="space-y-1">
                                    <Label className="text-xs">
                                        Drug/Alcohol problem?
                                    </Label>
                                    <RadioGroup
                                        value={
                                            editForm.data
                                                .background_drug_alcohol ||
                                            undefined
                                        }
                                        onValueChange={(value) =>
                                            editForm.setData(
                                                'background_drug_alcohol',
                                                value,
                                            )
                                        }
                                    >
                                        <div className="flex gap-4">
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="Yes" />
                                                <span className="text-sm">
                                                    Yes
                                                </span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="No" />
                                                <span className="text-sm">
                                                    No
                                                </span>
                                            </label>
                                        </div>
                                    </RadioGroup>
                                    {editForm.errors
                                        .background_drug_alcohol && (
                                        <p className="text-sm text-destructive">
                                            {
                                                editForm.errors
                                                    .background_drug_alcohol
                                            }
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">
                                        Tobacco use?
                                    </Label>
                                    <RadioGroup
                                        value={
                                            editForm.data.background_tobacco ||
                                            undefined
                                        }
                                        onValueChange={(value) =>
                                            editForm.setData(
                                                'background_tobacco',
                                                value,
                                            )
                                        }
                                    >
                                        <div className="flex gap-4">
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="Yes" />
                                                <span className="text-sm">
                                                    Yes
                                                </span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="No" />
                                                <span className="text-sm">
                                                    No
                                                </span>
                                            </label>
                                        </div>
                                    </RadioGroup>
                                    {editForm.errors.background_tobacco && (
                                        <p className="text-sm text-destructive">
                                            {editForm.errors.background_tobacco}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">
                                        Trust 6+ hrs
                                    </Label>
                                    <RadioGroup
                                        value={
                                            editForm.data.trust_own_child ||
                                            undefined
                                        }
                                        onValueChange={(value) =>
                                            editForm.setData(
                                                'trust_own_child',
                                                value,
                                            )
                                        }
                                    >
                                        <div className="flex gap-4">
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="Yes" />
                                                <span className="text-sm">
                                                    Yes
                                                </span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="No" />
                                                <span className="text-sm">
                                                    No
                                                </span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="Unsure" />
                                                <span className="text-sm">
                                                    Unsure
                                                </span>
                                            </label>
                                        </div>
                                    </RadioGroup>
                                    {editForm.errors.trust_own_child && (
                                        <p className="text-sm text-destructive">
                                            {editForm.errors.trust_own_child}
                                        </p>
                                    )}
                                </div>
                                <div className="space-y-1">
                                    <Label className="text-xs">
                                        Reason not to care for children?
                                    </Label>
                                    <RadioGroup
                                        value={
                                            editForm.data.reason_not_care ||
                                            undefined
                                        }
                                        onValueChange={(value) =>
                                            editForm.setData(
                                                'reason_not_care',
                                                value,
                                            )
                                        }
                                    >
                                        <div className="flex gap-4">
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="Yes" />
                                                <span className="text-sm">
                                                    Yes
                                                </span>
                                            </label>
                                            <label className="flex items-center gap-2">
                                                <RadioGroupItem value="No" />
                                                <span className="text-sm">
                                                    No
                                                </span>
                                            </label>
                                        </div>
                                    </RadioGroup>
                                    {editForm.errors.reason_not_care && (
                                        <p className="text-sm text-destructive">
                                            {editForm.errors.reason_not_care}
                                        </p>
                                    )}
                                </div>
                                {editForm.data.reason_not_care === 'Yes' && (
                                    <div className="space-y-1">
                                        <Label
                                            htmlFor="ref-reason_explanation"
                                            className="text-xs"
                                        >
                                            Explanation
                                        </Label>
                                        <Textarea
                                            id="ref-reason_explanation"
                                            rows={2}
                                            value={
                                                editForm.data
                                                    .reason_not_care_explanation
                                            }
                                            onChange={(e) =>
                                                editForm.setData(
                                                    'reason_not_care_explanation',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                        {editForm.errors
                                            .reason_not_care_explanation && (
                                            <p className="text-sm text-destructive">
                                                {
                                                    editForm.errors
                                                        .reason_not_care_explanation
                                                }
                                            </p>
                                        )}
                                    </div>
                                )}
                            </div>
                        </div>
                    </div>

                    <DialogFooter>
                        <Button
                            variant="outline"
                            onClick={() => setEditingRefId(null)}
                        >
                            Cancel
                        </Button>
                        <Button
                            onClick={handleSaveEdit}
                            disabled={editForm.processing}
                        >
                            {editForm.processing ? 'Saving...' : 'Save'}
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </AppLayout>
    );
}
