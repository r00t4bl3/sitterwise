import { Head, Link, useForm, usePage } from '@inertiajs/react';
import {
    ArrowLeft,
    Check,
    CheckCircle,
    ChevronDown,
    MinusCircle,
    MoreVertical,
    Shield,
    Users,
    Eye,
    EyeOff,
    Briefcase,
    Star,
    FileText,
    ClipboardCheck,
    MessageSquare,
    Sun,
} from 'lucide-react';
import type { SubmitEventHandler } from 'react';
import { useState } from 'react';
import { RatingInput } from '@/components/rating-input';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Rating } from '@/components/ui/rating';
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
import { Spinner } from '@/components/ui/spinner';
import { Textarea } from '@/components/ui/textarea';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import { calculateAgeFromDate } from '@/lib/age';
import { formatDisplayDateTimeInPT, formatDisplayDateShortInPT } from '@/lib/datetime';
import { formatPhoneDisplay } from '@/lib/phone';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Caregivers', href: '/caregivers' },
    { title: 'Caregiver Details', href: '#' },
];

interface CertificationType {
    id: number;
    name: string;
}

interface Certification {
    id: number;
    certification_type: CertificationType;
    notes: string;
    expiration_date: string;
    verified_at: string;
}

interface Status {
    value: string;
    label: string;
    color: string;
}

interface SpecialtyType {
    id: number;
    name: string;
    color_bg: string | null;
    color_text: string | null;
}

interface AttributeDefinition {
    id: number;
    name: string;
    slug: string;
}

interface Attribute {
    id: number;
    attribute_definition: AttributeDefinition;
    value: string | boolean;
}

interface Education {
    id: number;
    education_type: string;
    school_name: string;
    graduation_year: number | null;
    degree: string | null;
}

interface Location {
    id: number;
    name: string;
    svg_icon: string | null;
    is_preferred: boolean;
}

interface CaregiverApplication {
    id: number;
    submitted_at: string;
    data: {
        personal?: {
            first_name: string;
            last_name: string;
            address: string;
        };
        sponsor?: {
            first_name: string;
            last_name: string;
            email: string;
        };
    };
}

interface Agreement {
    id: number;
    pdf_path: string;
    type: string;
}

interface ReferenceRequest {
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

interface Review {
    id: number;
    rating: number;
    comment: string | null;
    rater_name: string | null;
    booking_service: string | null;
    client_name: string | null;
    created_at: string;
}

interface JobAssignment {
    id: number;
    job_number: string;
    date: string;
    client_id: number | null;
    client_name: string;
    client_description: string;
    resolution: string | null;
    resolution_label: string;
    resolution_color: string;
    resolution_note: string | null;
    late_arrival: boolean;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    slug: string;
    email: string;
    phone: string;
    address_line1: string | null;
    address_line2: string | null;
    address_city: string | null;
    address_state: string | null;
    address_zip: string | null;
    date_of_birth: string;
    date_of_birth_raw: string | null;
    user: { profile_photo_path: string | null; profile_photo_url: string | null };
    rating: number | null;
    admin_rating: number | null;
    biography: string | null;
    notes: string | null;
    stripe_account_id: string | null;
    stripe_charges_enabled: boolean | null;
    status: Status;
    specialty_types: SpecialtyType[];
    locations: Location[];
    certifications: Certification[];
    attributes: Attribute[];
    educations: Education[];
    application: CaregiverApplication | null;
    agreements: Agreement[];
    reference_requests: ReferenceRequest[];
    internal_rating: InternalRating | null;
}

interface InternalRating {
    communication_score: number | null;
    communication_notes: string | null;
    reliability_score: number | null;
    reliability_override: number | null;
    reliability_cached_at: string | null;
    composite_score: number | null;
}

interface ActivePause {
    paused_at: string;
    resume_by: string | null;
    pause_reason: string | null;
}

interface Props {
    [key: string]: unknown;
    caregiver: Caregiver;
    statuses: Status[];
    activePause?: ActivePause | null;
    reviews?: Review[];
    jobHistory?: JobAssignment[];
}

function StatusBadge({ status }: { status: Status }) {
    return (
        <span
            className="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
            style={{ backgroundColor: status.color + '20', color: status.color }}
        >
            {status.label}
        </span>
    );
}

function SpecialtyTag({ name, color_bg, color_text }: { name: string; color_bg?: string | null; color_text?: string | null }) {
    return (
        <span
            className="inline-block rounded-[10px] px-2 py-0.5 text-[10px] font-medium"
            style={{ backgroundColor: color_bg || '#E8F5F5', color: color_text || '#1B3A5C' }}
        >
            {name}
        </span>
    );
}

function AttributeBadge({ name, value }: { name: string; value: string | boolean }) {
    const isTrue = value === 'true' || value === '1' || value === true;

    return (
        <div className="flex items-center gap-2">
            {isTrue && <Check className="h-4 w-4 text-green-600" />}
            <span className={`text-sm ${isTrue ? 'text-foreground' : 'text-muted-foreground'}`}>{name}</span>
        </div>
    );
}

function StripeBadge({ isConnected }: { isConnected: boolean | null }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium ${
                isConnected
                    ? 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400'
                    : 'bg-gray-100 text-gray-600 dark:bg-gray-800 dark:text-gray-400'
            }`}
        >
            {isConnected ? <CheckCircle className="h-3.5 w-3.5" /> : <MinusCircle className="h-3.5 w-3.5" />}
            Stripe {isConnected ? 'Connected' : 'Not Connected'}
        </span>
    );
}

function EducationTypeLabel({ type }: { type: string }) {
    const labels: Record<string, string> = {
        high_school: 'High School',
        associate: 'Associate Degree',
        bachelor: "Bachelor's Degree",
        master: "Master's Degree",
        phd: 'PhD / Doctorate',
    };

    return <>{labels[type] || type}</>;
}

const TABS = [
    { key: 'summary', label: 'Summary', icon: Sun },
    { key: 'application', label: 'Application', icon: FileText },
    { key: 'references', label: 'References', icon: Users },
    { key: 'reviews', label: 'Reviews', icon: Star },
    { key: 'internal_rating', label: 'Internal Rating', icon: ClipboardCheck },
    { key: 'job_history', label: 'Job History', icon: Briefcase },
    { key: 'compliance', label: 'Compliance', icon: Shield },
    { key: 'notes', label: 'Notes', icon: MessageSquare },
] as const;

export default function CaregiverShow() {
    const { caregiver, statuses, activePause, reviews, jobHistory } = usePage<Props>().props;
    const [activeTab, setActiveTab] = useState<string>('summary');
    const [isStatusUpdating, setIsStatusUpdating] = useState(false);
    const [isPasswordSheetOpen, setIsPasswordSheetOpen] = useState(false);
    const [showPassword, setShowPassword] = useState(false);
    const [expandedRefs, setExpandedRefs] = useState<Set<number>>(new Set());
    const [isResuming, setIsResuming] = useState(false);

    const adminRatingForm = useForm({
        admin_rating: caregiver.admin_rating || 0,
        communication_notes: caregiver.internal_rating?.communication_notes || '',
    });
    const reliabilityOverrideForm = useForm({
        reliability_override: caregiver.internal_rating?.reliability_override || null as number | null,
    });
    const statusForm = useForm<{ status: string }>({ status: caregiver.status.value });
    const passwordForm = useForm<{ new_password: string; new_password_confirmation: string }>({
        new_password: '',
        new_password_confirmation: '',
    });

    const handleResume = () => {
        setIsResuming(true);
        statusForm.post(`/caregivers/${caregiver.id}/resume`, {
            onFinish: () => setIsResuming(false),
        });
    };

    const handleAdminRatingUpdate = () => {
        adminRatingForm.put(`/caregivers/${caregiver.id}/admin-rating`, { preserveScroll: true });
    };

    const handleReliabilityOverrideUpdate = () => {
        reliabilityOverrideForm.put(`/caregivers/${caregiver.id}/reliability-override`, { preserveScroll: true });
    };

    const handleClearReliabilityOverride = () => {
        reliabilityOverrideForm.setData('reliability_override', null);
        reliabilityOverrideForm.put(`/caregivers/${caregiver.id}/reliability-override`, { preserveScroll: true });
    };

    const handleStatusUpdate = () => {
        setIsStatusUpdating(true);
        statusForm.patch(`/caregivers/${caregiver.id}`, {
            onSuccess: () => setIsStatusUpdating(false),
            onError: () => setIsStatusUpdating(false),
        });
    };

    const handlePasswordReset: SubmitEventHandler = (e) => {
        e.preventDefault();
        passwordForm.post(`/caregivers/${caregiver.id}/password`, {
            onSuccess: () => {
 setIsPasswordSheetOpen(false); passwordForm.reset(); 
},
        });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title={`${caregiver.first_name} ${caregiver.last_name}`} />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-4 p-4">
                {/* Header */}
                <div className="flex items-center justify-between">
                    <div className="flex items-center gap-4">
                        <Link href="/caregivers" className="flex h-10 w-10 items-center justify-center rounded border border-border text-muted-foreground hover:bg-accent">
                            <ArrowLeft className="h-5 w-5" />
                        </Link>
                        <UserAvatar
                            profile_photo_url={caregiver.user.profile_photo_url}
                            profile_photo_path={caregiver.user.profile_photo_path}
                            name={`${caregiver.first_name} ${caregiver.last_name}`}
                            size="md"
                            className="size-10 md:size-16"
                        />
                        <div>
                            <h1 className="text-xl font-bold text-foreground md:text-2xl">
                                {caregiver.first_name} {caregiver.last_name}
                            </h1>
                            <p className="hidden text-muted-foreground md:block">Caregiver Profile</p>
                        </div>
                    </div>
                    <div className="hidden gap-2 xl:flex">
                        <Link href={`/caregivers/${caregiver.id}/jobs`} className="btn-secondary">View Jobs</Link>
                        <Link href={`/availabilities/${caregiver.id}`} className="btn-secondary">View Availability</Link>
                        <Link href={`/bio/${caregiver.slug}`} target="_blank" rel="noopener noreferrer" className="btn-secondary">View Public Profile</Link>
                        <Button onClick={() => setIsPasswordSheetOpen(true)} variant="secondary">Reset Password</Button>
                        <Link href={`/caregivers/${caregiver.id}/edit`} className="btn-primary">Edit</Link>
                    </div>
                    <div className="xl:hidden">
                        <DropdownMenu>
                            <DropdownMenuTrigger asChild>
                                <Button variant="outline" size="icon"><MoreVertical className="h-5 w-5" /></Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent align="end">
                                <DropdownMenuItem asChild><Link href={`/caregivers/${caregiver.id}/jobs`}>View Jobs</Link></DropdownMenuItem>
                                <DropdownMenuItem asChild><Link href={`/availabilities/${caregiver.id}`}>View Availability</Link></DropdownMenuItem>
                                <DropdownMenuItem asChild><Link href={`/bio/${caregiver.slug}`} target="_blank" rel="noopener noreferrer">View Public Profile</Link></DropdownMenuItem>
                                <DropdownMenuItem onClick={() => setIsPasswordSheetOpen(true)}>Reset Password</DropdownMenuItem>
                                <DropdownMenuItem asChild><Link href={`/caregivers/${caregiver.id}/edit`}>Edit</Link></DropdownMenuItem>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                </div>

                <Sheet open={isPasswordSheetOpen} onOpenChange={setIsPasswordSheetOpen}>
                    <SheetContent side="right">
                        <SheetHeader>
                            <SheetTitle>Reset Password</SheetTitle>
                            <SheetDescription>Enter and confirm a new password for this caregiver.</SheetDescription>
                        </SheetHeader>
                        <form onSubmit={handlePasswordReset} className="space-y-4 px-4">
                            <div className="grid gap-2">
                                <Label htmlFor="new_password">New Password</Label>
                                <div className="relative">
                                    <Input id="new_password" type={showPassword ? 'text' : 'password'}
                                        value={passwordForm.data.new_password}
                                        onChange={(e) => passwordForm.setData('new_password', e.target.value)}
                                        className="pr-10" required />
                                    <button type="button" onClick={() => setShowPassword(!showPassword)}
                                        className="absolute top-1/2 right-3 -translate-y-1/2 text-muted-foreground">
                                        {showPassword ? <EyeOff className="h-4 w-4" /> : <Eye className="h-4 w-4" />}
                                    </button>
                                </div>
                                {passwordForm.errors.new_password && <p className="text-sm text-destructive">{passwordForm.errors.new_password}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label htmlFor="confirm_password">Confirm Password</Label>
                                <Input id="confirm_password" type={showPassword ? 'text' : 'password'}
                                    value={passwordForm.data.new_password_confirmation}
                                    onChange={(e) => passwordForm.setData('new_password_confirmation', e.target.value)} required />
                                {passwordForm.errors.new_password_confirmation && <p className="text-sm text-destructive">{passwordForm.errors.new_password_confirmation}</p>}
                            </div>
                            <div className="mt-10 w-full space-y-2">
                                <Button type="submit" disabled={passwordForm.processing} className="w-full">
                                    {passwordForm.processing ? 'Resetting...' : 'Reset Password'}
                                </Button>
                                <Button type="button" onClick={() => setIsPasswordSheetOpen(false)} variant="outline" className="mt-2 w-full">Cancel</Button>
                            </div>
                        </form>
                    </SheetContent>
                </Sheet>

                {/* Tab bar */}
                <div className="flex overflow-x-auto border-b border-border">
                    {TABS.map((tab) => {
                        const Icon = tab.icon;

                        return (
                            <button
                                key={tab.key}
                                onClick={() => setActiveTab(tab.key)}
                                className={`flex cursor-pointer items-center gap-2 whitespace-nowrap border-b-2 px-4 py-3 text-sm font-medium transition-colors ${
                                    activeTab === tab.key
                                        ? 'border-primary text-primary'
                                        : 'border-transparent text-muted-foreground hover:text-foreground'
                                }`}
                            >
                                <Icon className="h-4 w-4" />
                                {tab.label}
                            </button>
                        );
                    })}
                </div>

                {/* Main content with sidebar */}
                <div className="grid gap-6 lg:grid-cols-3">
                    {/* Tab content */}
                    <div className="lg:col-span-2">
                        {/* Summary tab */}
                        {activeTab === 'summary' && (
                            <div className="border border-border bg-card p-6">
                                <div className="grid grid-cols-2 gap-x-6 gap-y-2">
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Email</p>
                                        <p className="text-sm font-medium text-foreground">
                                            {caregiver.email ? <a href={`mailto:${caregiver.email}`} className="text-primary hover:underline">{caregiver.email}</a> : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Phone</p>
                                        <p className="text-sm font-medium text-foreground">
                                            {caregiver.phone ? <a href={`tel:${caregiver.phone}`} className="text-primary hover:underline">{formatPhoneDisplay(caregiver.phone)}</a> : '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Date of Birth</p>
                                        <p className="text-sm font-medium text-foreground">{caregiver.date_of_birth || '—'}</p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Age</p>
                                        <p className="text-sm font-medium text-foreground">
                                            {caregiver.date_of_birth_raw ? `${calculateAgeFromDate(caregiver.date_of_birth_raw)} years old` : '—'}
                                        </p>
                                    </div>
                                    <div className="col-span-2">
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Address</p>
                                        <p className="text-sm font-medium text-foreground">
                                            {[caregiver.address_line1, caregiver.address_line2, caregiver.address_city, caregiver.address_state, caregiver.address_zip].filter(Boolean).join(', ') || '—'}
                                        </p>
                                    </div>
                                    <div>
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Status</p>
                                        <div className="mt-0.5"><StatusBadge status={caregiver.status} /></div>
                                    </div>
                                    {caregiver.rating ? (
                                        <div>
                                            <p className="text-xs tracking-wider text-muted-foreground uppercase">Client Rating</p>
                                            <Rating value={caregiver.rating} size="md" />
                                        </div>
                                    ) : null}
                                </div>
                                {caregiver.biography && (
                                    <div className="mt-3 border-t border-border pt-3">
                                        <p className="text-xs tracking-wider text-muted-foreground uppercase">Biography</p>
                                        <p className="text-sm text-foreground">{caregiver.biography}</p>
                                    </div>
                                )}
                                <div className="mt-3 flex flex-wrap gap-4 border-t border-border pt-3">
                                    {caregiver.specialty_types.length > 0 && (
                                        <div>
                                            <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Specialties</p>
                                            <div className="flex flex-wrap gap-1.5">
                                                {caregiver.specialty_types.map((s) => <SpecialtyTag key={s.id} {...s} />)}
                                            </div>
                                        </div>
                                    )}
                                </div>
                                {caregiver.educations.length > 0 && (
                                    <div className="mt-3 border-t border-border pt-3">
                                        <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Education</p>
                                        <div className="space-y-0.5">
                                            {caregiver.educations.map((edu) => (
                                                <p key={edu.id} className="text-sm text-foreground">
                                                    {edu.school_name} — <EducationTypeLabel type={edu.education_type} />
                                                    {edu.degree && ` (${edu.degree})`}
                                                </p>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {caregiver.locations.length > 0 && (
                                    <div className="mt-3 border-t border-border pt-3">
                                        <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Locations</p>
                                        <div className="space-y-1.5">
                                            {caregiver.locations.map((loc) => (
                                                <div key={loc.id} className={`flex items-center gap-2 text-sm ${loc.is_preferred ? 'font-medium text-foreground' : 'text-muted-foreground'}`}>
                                                    <span className={`h-2 w-2 rounded-full ${loc.is_preferred ? 'bg-ring' : 'bg-border'}`} />
                                                    {loc.name} {loc.is_preferred && <span className="text-xs text-ring">(Preferred)</span>}
                                                </div>
                                            ))}
                                        </div>
                                    </div>
                                )}
                                {caregiver.attributes.filter((a) => a.value === 'true' || a.value === '1' || a.value === true).length > 0 && (
                                    <div className="mt-3 border-t border-border pt-3">
                                        <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Attributes</p>
                                        <div className="grid gap-1.5 sm:grid-cols-2">
                                            {caregiver.attributes.filter((a) => a.value === 'true' || a.value === '1' || a.value === true).map((attr) => (
                                                <AttributeBadge key={attr.id} name={attr.attribute_definition.name} value={attr.value} />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        )}

                        {/* Application tab */}
                        {activeTab === 'application' && (
                            caregiver.application ? (
                                <div key={caregiver.application.id} className="border border-border bg-card p-6">
                                    <div className="mb-4 flex items-center justify-between">
                                        <h2 className="font-serif text-lg font-semibold text-foreground">Application</h2>
                                        <Link href={`/applications/${caregiver.application.id}`} className="text-sm text-primary hover:underline">View Application</Link>
                                    </div>
                                    <p className="text-sm text-muted-foreground">Submitted: {caregiver.application.submitted_at}</p>
                                    {caregiver.application.data && (
                                        <div className="mt-4 space-y-4">
                                            {caregiver.application.data.personal && (
                                                <div>
                                                    <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Personal Info</p>
                                                    <p className="text-sm font-medium text-foreground">{caregiver.application.data.personal.first_name} {caregiver.application.data.personal.last_name}</p>
                                                    <p className="text-sm text-muted-foreground">{caregiver.application.data.personal.address}</p>
                                                </div>
                                            )}
                                            {caregiver.application.data.sponsor && (
                                                <div>
                                                    <p className="mb-1 text-xs tracking-wider text-muted-foreground uppercase">Sponsor</p>
                                                    <p className="text-sm font-medium text-foreground">{caregiver.application.data.sponsor.first_name} {caregiver.application.data.sponsor.last_name}</p>
                                                    <p className="text-sm text-muted-foreground">{caregiver.application.data.sponsor.email}</p>
                                                </div>
                                            )}
                                        </div>
                                    )}
                                    {caregiver.agreements.length > 0 && (
                                        <div className="mt-4 border-t border-border pt-4">
                                            <p className="mb-2 text-xs tracking-wider text-muted-foreground uppercase">Agreements</p>
                                            <div className="flex flex-wrap gap-2">
                                                {caregiver.agreements.map((ag) => (
                                                    <a key={ag.id} href={`/storage/${ag.pdf_path}`} target="_blank" rel="noopener noreferrer"
                                                        className="rounded border border-border px-3 py-1.5 text-xs font-medium text-foreground hover:bg-accent">
                                                        Download {ag.type === 'verification' ? 'Verification' : 'Agreement'} PDF
                                                    </a>
                                                ))}
                                            </div>
                                        </div>
                                    )}
                                </div>
                            ) : (
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">Application</h2>
                                    <p className="text-sm text-muted-foreground">No application submitted.</p>
                                </div>
                            )
                        )}

                        {/* References tab */}
                        {activeTab === 'references' && (
                            <div className="border border-border bg-card p-6">
                                <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">
                                    References ({caregiver.reference_requests.length})
                                </h2>
                                {caregiver.reference_requests.length > 0 ? (
                                    <div className="space-y-3">
                                        {caregiver.reference_requests.map((ref) => {
                                            const ratingKeys = ['rating_reliability', 'rating_trustworthiness', 'rating_maturity', 'rating_communication', 'rating_warmth', 'rating_overall_recommendation'] as const;
                                            const ratings = ratingKeys.map((k) => ref[k as keyof ReferenceRequest]).filter((r): r is number => r !== null);
                                            const avg = ratings.length > 0 ? (ratings.reduce((a, b) => a + b, 0) / ratings.length).toFixed(1) : null;
                                            const expanded = expandedRefs.has(ref.id);
                                            const toggleExpanded = () => {
                                                setExpandedRefs((prev) => {
                                                    const next = new Set(prev);

                                                    if (next.has(ref.id)) {
next.delete(ref.id);
} else {
next.add(ref.id);
}

                                                    return next;
                                                });
                                            };

                                            return (
                                                <div key={ref.id} className="rounded-lg border border-border p-3">
                                                    <div className="flex items-start justify-between gap-2">
                                                        <div className="min-w-0 flex-1">
                                                            <p className="truncate text-sm font-medium text-foreground">{ref.reference_name}</p>
                                                            <p className="truncate text-xs text-muted-foreground">{ref.reference_email}</p>
                                                        </div>
                                                        {ref.is_sponsor && <span className="shrink-0 rounded bg-purple-100 px-1.5 py-0.5 text-[10px] font-medium text-purple-700">Sponsor</span>}
                                                    </div>
                                                    <div className="mt-2 flex items-center gap-2">
                                                        {ref.submitted_at ? (
                                                            <>
                                                                <span className="inline-flex items-center gap-1 rounded bg-green-100 px-1.5 py-0.5 text-[10px] font-medium text-green-700">
                                                                    <Check className="h-3 w-3" />{avg}/5
                                                                </span>
                                                                <button type="button" onClick={toggleExpanded}
                                                                    className="inline-flex items-center gap-1 text-[10px] text-muted-foreground hover:text-foreground">
                                                                    Details <ChevronDown className={`h-3 w-3 transition-transform ${expanded ? 'rotate-180' : ''}`} />
                                                                </button>
                                                            </>
                                                        ) : (
                                                            <span className="rounded bg-amber-100 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">Pending</span>
                                                        )}
                                                        {ref.relationship && <span className="text-[10px] text-muted-foreground">{ref.relationship}</span>}
                                                    </div>
                                                    {expanded && ref.submitted_at && (
                                                        <div className="mt-3 space-y-2 border-t border-border pt-3">
                                                            <div className="grid grid-cols-2 gap-x-4 gap-y-1">
                                                                {ratingKeys.map((key) => {
                                                                    const labels: Record<string, string> = {
                                                                        rating_reliability: 'Reliability',
                                                                        rating_trustworthiness: 'Trustworthiness',
                                                                        rating_maturity: 'Maturity',
                                                                        rating_communication: 'Communication',
                                                                        rating_warmth: 'Warmth',
                                                                        rating_overall_recommendation: 'Overall',
                                                                    };
                                                                    const val = ref[key as keyof ReferenceRequest];

                                                                    return (
                                                                        <div key={key} className="flex items-center justify-between text-xs">
                                                                            <span className="text-muted-foreground">{labels[key]}</span>
                                                                            <span className="font-medium text-foreground">{val ?? '—'}/5</span>
                                                                        </div>
                                                                    );
                                                                })}
                                                            </div>
                                                            {ref.strengths && <p className="text-xs text-muted-foreground"><span className="font-medium text-foreground">Strengths:</span> {ref.strengths}</p>}
                                                            {ref.concerns && <p className="text-xs text-muted-foreground"><span className="font-medium text-foreground">Concerns:</span> {ref.concerns}</p>}
                                                            {ref.additional_comments && <p className="text-xs text-muted-foreground"><span className="font-medium text-foreground">Additional Comments:</span> {ref.additional_comments}</p>}
                                                        </div>
                                                    )}
                                                </div>
                                            );
                                        })}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No reference requests</p>
                                )}
                            </div>
                        )}

                        {/* Reviews tab */}
                        {activeTab === 'reviews' && (
                            <div className="border border-border bg-card p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="font-serif text-lg font-semibold text-foreground">Client Reviews</h2>
                                    {reviews !== undefined && caregiver.rating && (
                                        <div className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                            <span className="font-medium text-foreground">{caregiver.rating.toFixed(1)}</span>
                                            <div className="flex">
                                                {[1, 2, 3, 4, 5].map((star) => (
                                                    <Star key={star} className={`h-3.5 w-3.5 ${star <= Math.round(caregiver.rating!) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground/30'}`} />
                                                ))}
                                            </div>
                                            <span>· {reviews.length} {reviews.length === 1 ? 'review' : 'reviews'}</span>
                                        </div>
                                    )}
                                </div>
                                {reviews === undefined ? (
                                    <div className="space-y-3">
                                        {[1, 2, 3].map((i) => (
                                            <div key={i} className="animate-pulse space-y-2 rounded-lg border border-border p-4">
                                                <div className="h-4 w-24 rounded bg-muted" />
                                                <div className="h-3 w-full rounded bg-muted" />
                                                <div className="h-3 w-3/4 rounded bg-muted" />
                                            </div>
                                        ))}
                                    </div>
                                ) : reviews.length > 0 ? (
                                    <div className="space-y-3">
                                        {reviews.map((r) => (
                                            <div key={r.id} className="rounded-lg border border-border p-4">
                                                <div className="flex items-center justify-between">
                                                    <div className="flex items-center gap-2">
                                                        <div className="flex">
                                                            {[1, 2, 3, 4, 5].map((star) => (
                                                                <Star key={star} className={`h-4 w-4 ${star <= Math.round(r.rating) ? 'fill-yellow-400 text-yellow-400' : 'text-muted-foreground/30'}`} />
                                                            ))}
                                                        </div>
                                                        <span className="text-sm font-medium text-foreground">{r.rating.toFixed(1)}/5</span>
                                                    </div>
                                                    <span className="text-xs text-muted-foreground">{formatDisplayDateShortInPT(r.created_at)}</span>
                                                </div>
                                                {r.comment && <p className="mt-2 text-sm text-foreground italic">"{r.comment}"</p>}
                                                <div className="mt-2 flex gap-3 text-xs text-muted-foreground">
                                                    {r.client_name && <span>From: {r.client_name}</span>}
                                                    {r.booking_service && <span>Service: {r.booking_service}</span>}
                                                </div>
                                            </div>
                                        ))}
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No reviews yet.</p>
                                )}
                            </div>
                        )}

                        {/* Internal Rating tab */}
                        {activeTab === 'internal_rating' && (
                            <div className="border border-border bg-card p-6">
                                <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">Internal Rating</h2>
                                <p className="mb-4 text-xs text-muted-foreground">Visible to admins only. Composite weights: interview (20%), communication (30%), reliability (50%). When a component is unavailable, remaining weights are re-proportioned.</p>
                                <div className="space-y-8">
                                    {/* Communication */}
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-foreground">Communication</h3>
                                        <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                                            <div>
                                                <p className="mb-2 text-sm font-medium text-foreground">Score (1&ndash;5)</p>
                                                <RatingInput value={adminRatingForm.data.admin_rating}
                                                    onChange={(val) => adminRatingForm.setData('admin_rating', val)}
                                                    error={adminRatingForm.errors.admin_rating} />
                                            </div>
                                            <div>
                                                <p className="mb-2 text-sm font-medium text-foreground">Notes</p>
                                                <Textarea
                                                    value={adminRatingForm.data.communication_notes}
                                                    onChange={(e) => adminRatingForm.setData('communication_notes', e.target.value)}
                                                    placeholder="Optional notes about communication..."
                                                    className="min-h-[80px] text-sm"
                                                />
                                            </div>
                                            <Button onClick={handleAdminRatingUpdate} disabled={adminRatingForm.processing} variant="outline" size="sm">
                                                {adminRatingForm.processing ? 'Saving...' : 'Save Communication Rating'}
                                            </Button>
                                        </div>
                                    </div>

                                    {/* Reliability */}
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-foreground">Reliability</h3>
                                        <div className="space-y-3 rounded-lg border border-border bg-card p-4">
                                            <div className="flex items-center gap-4">
                                                <div>
                                                    <p className="text-xs text-muted-foreground">Auto-calculated</p>
                                                    <p className="text-lg font-bold text-foreground">
                                                        {caregiver.internal_rating?.reliability_score !== null
                                                            ? caregiver.internal_rating!.reliability_score.toFixed(2)
                                                            : '—'}
                                                    </p>
                                                </div>
                                                <div className="text-xs text-muted-foreground">
                                                    {caregiver.internal_rating?.reliability_cached_at
                                                        ? `Cached: ${caregiver.internal_rating.reliability_cached_at}`
                                                        : 'Not yet calculated'}
                                                </div>
                                            </div>
                                            <div>
                                                <p className="mb-2 text-sm font-medium text-foreground">Override</p>
                                                <RatingInput
                                                    value={reliabilityOverrideForm.data.reliability_override ?? 0}
                                                    onChange={(val) => reliabilityOverrideForm.setData('reliability_override', val)}
                                                />
                                                <p className="mt-1 text-xs text-muted-foreground">When set, overrides the auto-calculated score.</p>
                                            </div>
                                            <div className="flex gap-2">
                                                <Button onClick={handleReliabilityOverrideUpdate} disabled={reliabilityOverrideForm.processing} variant="outline" size="sm">
                                                    {reliabilityOverrideForm.processing ? 'Saving...' : 'Save Override'}
                                                </Button>
                                                {caregiver.internal_rating?.reliability_override !== null && (
                                                    <Button onClick={handleClearReliabilityOverride} disabled={reliabilityOverrideForm.processing} variant="ghost" size="sm" className="text-muted-foreground">
                                                        Clear Override
                                                    </Button>
                                                )}
                                            </div>
                                        </div>
                                    </div>

                                    {/* Composite */}
                                    <div>
                                        <h3 className="mb-3 text-sm font-semibold text-foreground">Composite Score</h3>
                                        <div className="rounded-lg border border-border bg-card p-4">
                                            <div className="flex items-baseline gap-2">
                                                <span className="text-2xl font-bold text-foreground">
                                                    {caregiver.internal_rating?.composite_score !== null
                                                        ? caregiver.internal_rating!.composite_score.toFixed(1)
                                                        : '—'}
                                                </span>
                                                <span className="text-sm text-muted-foreground">/ 100</span>
                                            </div>
                                            <div className="mt-3 space-y-1 text-sm text-muted-foreground">
                                                {caregiver.internal_rating && (
                                                    <>
                                                        <p>Interview (20%) &mdash; from completed interview composite</p>
                                                        <p>Communication (30%) &mdash; from manual admin rating</p>
                                                        <p>Reliability (50%) &mdash; from auto-score or override</p>
                                                    </>
                                                )}
                                                {!caregiver.internal_rating && (
                                                    <p>No rating data yet. Save a communication rating or recalculate reliability to generate a composite.</p>
                                                )}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Job History tab */}
                        {activeTab === 'job_history' && (
                            <div className="border border-border bg-card p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="font-serif text-lg font-semibold text-foreground">Job History</h2>
                                    <Link href={`/caregivers/${caregiver.id}/jobs`} className="text-sm text-primary hover:underline">
                                        View Full Job History
                                    </Link>
                                </div>

                                {jobHistory === undefined ? (
                                    <div className="space-y-3">
                                        {[1, 2, 3, 4, 5].map((i) => (
                                            <div key={i} className="animate-pulse space-y-2 rounded-lg border border-border p-4">
                                                <div className="h-4 w-24 rounded bg-muted" />
                                                <div className="h-3 w-full rounded bg-muted" />
                                            </div>
                                        ))}
                                    </div>
                                ) : jobHistory.length > 0 ? (
                                    <div className="-mx-6 -mb-6 mt-4 border-t border-border">
                                        <div className="overflow-x-auto">
                                            <table className="w-full min-w-[600px]">
                                                <thead>
                                                    <tr className="bg-table-header">
                                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Job ID</th>
                                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Date</th>
                                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Client</th>
                                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Resolution</th>
                                                        <th className="px-4 py-3 text-left text-[11px] font-semibold tracking-wider text-white uppercase">Notes</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    {jobHistory.map((row) => (
                                                        <tr key={row.id} className="border-b border-border transition hover:bg-blush last:border-0">
                                                            <td className="px-4 py-3 text-sm whitespace-nowrap font-mono text-foreground">
                                                                {row.job_number}
                                                            </td>
                                                            <td className="px-4 py-3 text-sm whitespace-nowrap text-foreground">
                                                                {formatDisplayDateTimeInPT(row.date)}
                                                            </td>
                                                            <td className="px-4 py-3 text-sm font-medium text-ring">
                                                                {row.client_id ? (
                                                                    <Link href={`/clients/${row.client_id}`} className="hover:underline">{row.client_name}</Link>
                                                                ) : row.client_name}
                                                            </td>
                                                            <td className="px-4 py-3">
                                                                <span
                                                                    className="inline-block rounded px-2 py-0.5 text-[11px] font-semibold"
                                                                    style={{
                                                                        backgroundColor: row.resolution_color + '20',
                                                                        color: row.resolution_color,
                                                                    }}
                                                                >
                                                                    {row.resolution_label}
                                                                </span>
                                                                {row.late_arrival && (
                                                                    <span className="ml-1 text-[10px] text-amber-600 font-medium">
                                                                        (Late)
                                                                    </span>
                                                                )}
                                                            </td>
                                                            <td className="px-4 py-3 text-xs text-muted-foreground max-w-[200px] truncate">
                                                                {row.resolution_note || '—'}
                                                            </td>
                                                        </tr>
                                                    ))}
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No job history yet.</p>
                                )}
                            </div>
                        )}

                        {/* Compliance tab */}
                        {activeTab === 'compliance' && (
                            <div className="space-y-6">
                                <div className="border border-border bg-card p-6">
                                    <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">Certifications</h2>
                                    <div className="space-y-3">
                                        {caregiver.certifications.length > 0 ? caregiver.certifications.map((cert) => (
                                            <div key={cert.id} className="flex items-center justify-between">
                                                <div>
                                                    <p className="text-sm font-medium text-foreground">{cert.certification_type.name}</p>
                                                    {cert.expiration_date && <p className="text-xs text-muted-foreground">Expires: {cert.expiration_date}</p>}
                                                    {cert.notes && <p className="text-xs text-muted-foreground">Note: {cert.notes}</p>}
                                                </div>
                                                {cert.verified_at ? (
                                                    <span className="flex items-center gap-1 text-xs text-green-600"><Check className="h-3 w-3" /> Verified</span>
                                                ) : (
                                                    <span className="text-xs text-muted-foreground">Unverified</span>
                                                )}
                                            </div>
                                        )) : (
                                            <p className="text-sm text-muted-foreground">No certifications</p>
                                        )}
                                    </div>
                                </div>
                            </div>
                        )}

                        {/* Notes tab */}
                        {activeTab === 'notes' && (
                            <div className="border border-border bg-card p-6">
                                <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">Notes</h2>
                                {caregiver.notes ? (
                                    <p className="whitespace-pre-wrap text-sm text-foreground">{caregiver.notes}</p>
                                ) : (
                                    <p className="text-sm text-muted-foreground">No notes.</p>
                                )}
                                <div className="mt-4 border-t border-border pt-4">
                                    <Link href={`/caregivers/${caregiver.id}/edit`} className="text-sm text-primary hover:underline">
                                        Edit caregiver profile to add or update notes
                                    </Link>
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Sidebar */}
                    <div className="space-y-6">
                        {activePause && (
                            <div className="border border-border bg-card p-6">
                                <h2 className="mb-4 font-serif text-lg font-semibold text-foreground">On Hold</h2>
                                <div className="space-y-2 text-sm">
                                    <div className="flex justify-between">
                                        <span className="text-muted-foreground">Paused since</span>
                                        <span className="font-medium text-foreground">{activePause.paused_at}</span>
                                    </div>
                                    {activePause.resume_by && (
                                        <div className="flex justify-between">
                                            <span className="text-muted-foreground">Resume by</span>
                                            <span className="font-medium text-foreground">{activePause.resume_by}</span>
                                        </div>
                                    )}
                                    {activePause.pause_reason && (
                                        <div>
                                            <span className="text-muted-foreground">Reason</span>
                                            <p className="mt-0.5 text-foreground">{activePause.pause_reason}</p>
                                        </div>
                                    )}
                                </div>
                                <Button onClick={handleResume} disabled={isResuming} className="mt-4 w-full">
                                    {isResuming ? 'Resuming...' : 'Resume'}
                                </Button>
                            </div>
                        )}
                        {caregiver.status.value !== 'applicant' && (
                            <div className="border border-border bg-card p-6">
                                <div className="mb-4 flex items-center justify-between">
                                    <h2 className="font-serif text-lg font-semibold text-foreground">Status</h2>
                                    {/* eslint-disable-next-line no-constant-binary-expression */}
                                    {false && <StripeBadge isConnected={caregiver.stripe_charges_enabled} />}
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor="status">Change Status</Label>
                                    <Select value={statusForm.data.status} onValueChange={(value) => statusForm.setData('status', value)} disabled={statusForm.processing}>
                                        <SelectTrigger id="status">
                                            <SelectValue placeholder="Select status" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            {statuses.map((s) => (
                                                <SelectItem key={s.value} value={s.value}>{s.label}</SelectItem>
                                            ))}
                                        </SelectContent>
                                    </Select>
                                    {statusForm.errors.status && <p className="mt-1 text-xs text-red-500">{statusForm.errors.status}</p>}
                                    <Button onClick={handleStatusUpdate} disabled={statusForm.processing} className="w-full">
                                        {isStatusUpdating ? <Spinner /> : null}
                                        {isStatusUpdating ? 'Updating...' : 'Update Status'}
                                    </Button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
