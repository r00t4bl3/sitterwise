import { Head, Link } from '@inertiajs/react';
import {
    Award,
    Calendar,
    CalendarRange,
    Clock,
    User as UserIcon,
    Briefcase,
    MapPin,
    Users,
    ChevronRight,
    ChevronDown,
    Bell,
    ExternalLink,
    Globe,
    FileText,
    BookOpen,
    Phone,
    Mail,
    HelpCircle,
    Home,
    Link as LucideLink,
    X,
} from 'lucide-react';
import { useCallback, useState } from 'react';
import AttentionStrip from '@/components/attention-strip';
import AvailabilityWeekGrid from '@/components/availability-week-grid';
import Medallion from '@/components/medallion';
import { StatusBadge } from '@/components/status-badge';
import { ToasterMessage } from '@/components/toaster-message';
import TrustlineCard from '@/components/trustline-card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';

import {
    Collapsible,
    CollapsibleTrigger,
    CollapsibleContent,
} from '@/components/ui/collapsible';
import AppLayout from '@/layouts/app-layout';
import { formatDisplayDateTimeInPT } from '@/lib/datetime';
import { dashboard } from '@/routes';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Dashboard',
        href: dashboard(),
    },
];

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
    booked_slots?: string[];
}

interface Booking {
    id: number;
    ulid: string;
    service_type: string;
    start_datetime: string;
    end_datetime: string;
    status: string;
    client?: {
        user: {
            name: string;
        };
    };
    children?: any[];
    address_city?: string;
    address_state?: string;
    hotel?: {
        name: string;
    };
}

interface CaregiverDashboardProps {
    caregiver: {
        id: number;
        firstName: string;
        lastName: string;
        rating: number | null;
        status: string;
        availabilities: Availability[];
        bookingStatuses: Array<{
            value: string;
            label: string;
            colors: { bg: string; text: string; border: string };
        }>;
        nextJob?: Booking | null;
        upcomingJobs?: Booking[];
        newInvites?: Booking[];
        timeSlots: Array<{ value: string; label: string }>;
    };
    stats: {
        totalEarned: number;
        completedJobs: number;
    };
    quickLinks?: Array<{
        id: number;
        title: string;
        url: string;
        description: string | null;
        icon: string | null;
        is_external: boolean;
    }>;
    trustline?: {
        certified: boolean;
        cleared_at?: string;
    };
    attention?: Array<{
        icon: 'AlertTriangle' | 'Calendar';
        title: string;
        description: string;
        actionLabel?: string;
        actionHref?: string;
    }>;
    badges?: Array<{
        slug: string;
        name: string;
        group: string;
        tier: 'teal' | 'coral' | 'navy';
        variant: string;
        earned: boolean;
        earned_date: string | null;
        criteria: string;
        progress: string | null;
    }>;
    newlyEarnedBadges?: Array<{
        slug: string;
        name: string;
        tier: 'teal' | 'coral' | 'navy';
        variant: string;
    }>;
}

export default function CaregiverDashboard({
    caregiver,
    stats,
    quickLinks,
    trustline,
    attention,
    badges,
    newlyEarnedBadges,
}: CaregiverDashboardProps) {
    const fetchMonthUrl = useCallback(
        (y: number, m: number) =>
            `/availabilities/${caregiver.id}?year=${y}&month=${m}`,
        [caregiver.id],
    );

    const [badgeMomentDismissed, setBadgeMomentDismissed] = useState(false);
    const showBadgeMoment =
        !badgeMomentDismissed && (newlyEarnedBadges?.length ?? 0) > 0;

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Caregiver Dashboard" />
            <ToasterMessage />
            <div className="flex h-full flex-1 flex-col gap-6 p-4">
                {/* Header */}
                <div className="flex flex-col gap-2">
                    <h1 className="text-2xl font-bold text-foreground">
                        Welcome back, {caregiver.firstName}!
                    </h1>
                    <div className="flex items-center gap-2">
                        <Badge variant="outline" className="px-2 py-0.5">
                            <span className="mr-1.5 h-2 w-2 rounded-full bg-green-500"></span>
                            {caregiver.status}
                        </Badge>
                        <p className="text-sm text-muted-foreground">
                            You have {caregiver.newInvites?.length || 0} new job
                            invites
                        </p>
                    </div>
                </div>

                {showBadgeMoment && (
                    <div className="relative overflow-hidden rounded-xl border border-primary/30 bg-primary/5 p-5 shadow-sm">
                        <button
                            type="button"
                            aria-label="Dismiss"
                            onClick={() => setBadgeMomentDismissed(true)}
                            className="absolute top-3 right-3 text-muted-foreground hover:text-foreground"
                        >
                            <X className="h-4 w-4" />
                        </button>
                        <div className="flex items-center gap-4">
                            <div className="flex shrink-0 gap-1">
                                {newlyEarnedBadges
                                    ?.slice(0, 3)
                                    .map((badge) => (
                                        <Medallion
                                            key={badge.slug}
                                            tier={badge.tier}
                                            variant={badge.variant}
                                            earned
                                            size="md"
                                        />
                                    ))}
                            </div>
                            <div>
                                <p className="text-sm font-semibold tracking-wider text-primary uppercase">
                                    New badge
                                    {(newlyEarnedBadges?.length ?? 0) > 1
                                        ? 's'
                                        : ''}{' '}
                                    earned!
                                </p>
                                <p className="mt-0.5 text-lg font-semibold text-foreground">
                                    {newlyEarnedBadges
                                        ?.map((b) => b.name)
                                        .join(', ')}
                                </p>
                                <Link
                                    href="/milestones"
                                    className="mt-1 inline-block text-sm text-primary hover:underline"
                                >
                                    View your trophy case →
                                </Link>
                            </div>
                        </div>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-2">
                    {/* Primary Focus: Next Appointment */}
                    <div className="flex flex-col gap-4">
                        {caregiver.nextJob ? (
                            <div className="relative overflow-hidden rounded-none border-2 border-primary/20 bg-card px-6 py-4 shadow-md transition-all hover:border-primary/40">
                                <div className="mb-4">
                                    <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <Calendar className="h-4 w-4 text-primary" />
                                        Your Next Job
                                    </p>
                                </div>
                                <div className="absolute top-0 right-0 p-4">
                                    <StatusBadge
                                        status={caregiver.nextJob.status}
                                        bookingStatuses={
                                            caregiver.bookingStatuses
                                        }
                                    />
                                </div>

                                <div className="flex items-center gap-3 pb-4">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-full bg-primary/10 text-primary">
                                        <Clock className="h-6 w-6" />
                                    </div>
                                    <div>
                                        <h3 className="text-sm font-medium tracking-tight text-muted-foreground uppercase">
                                            {caregiver.nextJob.service_type} Job
                                        </h3>
                                        <p className="text-lg font-bold">
                                            {formatDisplayDateTimeInPT(
                                                caregiver.nextJob
                                                    .start_datetime,
                                            )}
                                        </p>
                                    </div>
                                </div>

                                <div className="mb-6 grid gap-1">
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <UserIcon className="h-4 w-4 text-muted-foreground" />
                                        <span className="font-medium">
                                            Client:{' '}
                                            {caregiver.nextJob.client?.user
                                                .name || 'N/A'}
                                        </span>
                                    </div>
                                    <div className="flex items-center gap-2 text-sm text-foreground">
                                        <MapPin className="h-4 w-4 text-muted-foreground" />
                                        <span>
                                            {caregiver.nextJob.hotel?.name ||
                                                `${caregiver.nextJob.address_city}, ${caregiver.nextJob.address_state}`}
                                        </span>
                                    </div>
                                    {caregiver.nextJob.children && (
                                        <div className="flex items-center gap-2 text-sm text-foreground">
                                            <Users className="h-4 w-4 text-muted-foreground" />
                                            <span>
                                                {caregiver.nextJob.children
                                                    .length || 0}{' '}
                                                Children
                                            </span>
                                        </div>
                                    )}
                                </div>

                                <Button asChild className="w-full">
                                    <Link
                                        href={`/jobs/${caregiver.nextJob.ulid}`}
                                    >
                                        View Job Details
                                        <ChevronRight className="ml-2 h-4 w-4" />
                                    </Link>
                                </Button>
                            </div>
                        ) : (
                            <div className="flex flex-col items-center justify-center rounded-none border border-dashed border-border bg-card px-6 py-4 text-center">
                                <div className="mb-4 self-start">
                                    <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <Calendar className="h-4 w-4 text-primary" />
                                        Your Next Job
                                    </p>
                                </div>
                                <div className="mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-muted">
                                    <Calendar className="h-8 w-8 text-muted-foreground" />
                                </div>
                                <h3 className="mb-2 text-lg font-medium">
                                    No jobs scheduled
                                </h3>
                                <p className="mb-6 text-sm text-muted-foreground">
                                    Your next confirmed job will appear here.
                                    Make sure your availability is up to date!
                                </p>
                            </div>
                        )}

                        {/* More Upcoming Jobs */}
                        {caregiver.upcomingJobs &&
                            caregiver.upcomingJobs.length > 0 && (
                                <div className="rounded-none border border-border bg-card px-6 py-4 shadow-sm">
                                    <div className="mb-4 flex items-center justify-between">
                                        <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            <Briefcase className="h-4 w-4 text-primary" />
                                            More Upcoming Jobs
                                        </p>
                                        <Link
                                            href="/jobs"
                                            className="text-xs font-medium text-primary hover:underline"
                                        >
                                            Full Schedule
                                        </Link>
                                    </div>
                                    <div className="space-y-2">
                                        {caregiver.upcomingJobs.map((job) => (
                                            <Link
                                                key={job.id}
                                                href={`/jobs/${job.ulid}`}
                                                className="flex items-center justify-between rounded-none border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                            >
                                                <div className="flex items-center gap-3">
                                                    <div className="flex h-8 w-8 items-center justify-center rounded bg-primary/5">
                                                        <Briefcase className="h-4 w-4 text-primary" />
                                                    </div>
                                                    <div>
                                                        <p className="text-sm font-medium">
                                                            {formatDisplayDateTimeInPT(
                                                                job.start_datetime,
                                                            )}
                                                        </p>
                                                        <p className="text-xs text-muted-foreground">
                                                            {job.client?.user
                                                                .name || 'N/A'}
                                                        </p>
                                                    </div>
                                                </div>
                                                <ChevronRight className="h-4 w-4 text-muted-foreground" />
                                            </Link>
                                        ))}
                                    </div>
                                </div>
                            )}

                        {/* Job Opportunities / Invites */}
                        <div className="rounded-none border border-border bg-card px-6 py-4 shadow-sm">
                            <div className="mb-4">
                                <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <Bell className="h-4 w-4 text-primary" />
                                    Job Opportunities
                                </p>
                            </div>
                            {caregiver.newInvites &&
                            caregiver.newInvites.length > 0 ? (
                                <div className="space-y-2">
                                    {caregiver.newInvites.map((invite) => (
                                        <Link
                                            key={invite.id}
                                            href={`/bookings/${invite.ulid}`}
                                            className="flex items-center justify-between rounded-none border border-border bg-card p-4 transition-all hover:border-primary/50 hover:bg-accent/30"
                                        >
                                            <div className="flex flex-col gap-1">
                                                <div className="flex items-center gap-2">
                                                    <Badge
                                                        variant="secondary"
                                                        className="text-[10px]"
                                                    >
                                                        NEW
                                                    </Badge>
                                                    <span className="text-sm font-bold text-primary uppercase">
                                                        {invite.service_type}
                                                    </span>
                                                </div>
                                                <p className="text-sm font-medium">
                                                    {formatDisplayDateTimeInPT(
                                                        invite.start_datetime,
                                                    )}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {invite.address_city},{' '}
                                                    {invite.address_state}
                                                </p>
                                            </div>
                                            <ChevronRight className="h-5 w-5 text-muted-foreground" />
                                        </Link>
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-muted-foreground">
                                    No new job invites at the moment.
                                </p>
                            )}
                        </div>

                        {quickLinks && quickLinks.length > 0 && (
                            <div className="rounded-none border border-border bg-card px-6 py-4 shadow-sm">
                                <p className="mb-4 flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <ExternalLink className="h-4 w-4 text-primary" />
                                    Quick Links
                                </p>
                                <div className="grid grid-cols-2 gap-2">
                                    {quickLinks.map((link) => {
                                        const iconMap: Record<
                                            string,
                                            React.ComponentType<{
                                                className?: string;
                                            }>
                                        > = {
                                            ExternalLink,
                                            Globe,
                                            FileText,
                                            BookOpen,
                                            Phone,
                                            Mail,
                                            HelpCircle,
                                            Home,
                                            Calendar,
                                            Users,
                                        };
                                        const Icon =
                                            iconMap[link.icon ?? ''] ??
                                            LucideLink;

                                        return (
                                            <a
                                                key={link.id}
                                                href={link.url}
                                                target={
                                                    link.is_external
                                                        ? '_blank'
                                                        : '_self'
                                                }
                                                rel={
                                                    link.is_external
                                                        ? 'noopener noreferrer'
                                                        : ''
                                                }
                                                className="flex items-center gap-2 rounded-none border border-border bg-card p-3 transition-colors hover:bg-accent/50"
                                            >
                                                <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded bg-blue-100">
                                                    <Icon className="h-4 w-4 text-blue-600" />
                                                </div>
                                                <span className="text-sm leading-tight font-medium">
                                                    {link.title}
                                                </span>
                                            </a>
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>

                    {/* Secondary Side: Schedule & Availability */}
                    <div className="flex flex-col gap-6">
                        <TrustlineCard
                            certified={trustline?.certified ?? false}
                            clearedAt={trustline?.cleared_at}
                            firstName={caregiver.firstName}
                        />

                        <Collapsible
                            defaultOpen
                            className="rounded-none border border-border bg-card shadow-sm"
                        >
                            <CollapsibleTrigger className="group flex w-full cursor-pointer items-center justify-between px-6 py-4">
                                <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                    <CalendarRange className="h-4 w-4 text-primary" />
                                    My Availability
                                </p>
                                <ChevronDown className="h-4 w-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                            </CollapsibleTrigger>
                            <CollapsibleContent className="px-6 pb-4">
                                <AvailabilityWeekGrid
                                    initial={caregiver.availabilities}
                                    saveUrl={`/availabilities/${caregiver.id}`}
                                    fetchMonthUrl={fetchMonthUrl}
                                />
                            </CollapsibleContent>
                        </Collapsible>

                        {badges && badges.length > 0 && (
                            <Collapsible
                                defaultOpen
                                className="rounded-none border border-border bg-card shadow-sm"
                            >
                                <CollapsibleTrigger className="group flex w-full cursor-pointer items-center justify-between px-6 py-4">
                                    <p className="flex items-center gap-2 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                        <Award className="h-4 w-4 text-primary" />
                                        Your Trophy Case
                                    </p>
                                    <ChevronDown className="h-4 w-4 text-muted-foreground transition-transform group-data-[state=open]:rotate-180" />
                                </CollapsibleTrigger>
                                <CollapsibleContent className="px-6 pb-4">
                                    <p className="text-sm text-muted-foreground">
                                        Milestones you&apos;ve earned. They never
                                        expire.
                                    </p>
                                    <div className="mt-3 flex flex-wrap gap-3">
                                        {badges
                                            .filter((b) => b.earned)
                                            .slice(0, 4)
                                            .map((badge) => (
                                                <div
                                                    key={badge.slug}
                                                    className="flex flex-col items-center gap-0.5"
                                                >
                                                    <Medallion
                                                        tier={badge.tier}
                                                        variant={badge.variant}
                                                        earned={true}
                                                    />
                                                    <span className="text-[10px] font-medium text-foreground">
                                                        {badge.name}
                                                    </span>
                                                    {badge.earned_date && (
                                                        <span className="text-[9px] text-muted-foreground">
                                                            {badge.earned_date}
                                                        </span>
                                                    )}
                                                </div>
                                            ))}
                                        {badges.filter((b) => !b.earned).length >
                                            0 && (
                                            <div className="flex flex-col items-center gap-0.5">
                                                <Medallion
                                                    tier="navy"
                                                    variant="star"
                                                    earned={false}
                                                />
                                                <span className="text-[10px] font-medium text-muted-foreground">
                                                    {
                                                        badges.filter(
                                                            (b) => !b.earned,
                                                        ).length
                                                    }{' '}
                                                    locked
                                                </span>
                                            </div>
                                        )}
                                    </div>
                                    <Link
                                        href="/milestones"
                                        className="mt-3 inline-flex text-sm font-medium text-blue-600 hover:text-blue-700 hover:underline"
                                    >
                                        View all milestones →
                                    </Link>
                                </CollapsibleContent>
                            </Collapsible>
                        )}

                        {attention && <AttentionStrip items={attention} />}

                        {/* Mini Stats */}
                        <div className="grid grid-cols-2 gap-3">
                            <div className="rounded-none border border-border bg-card p-3 text-center">
                                <p className="text-lg font-bold text-foreground">
                                    {stats.completedJobs}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Jobs Completed
                                </p>
                            </div>
                            <div className="rounded-none border border-border bg-card p-3 text-center">
                                <p className="text-lg font-bold text-foreground">
                                    ${stats.totalEarned.toLocaleString('en-US')}
                                </p>
                                <p className="text-xs text-muted-foreground">
                                    Earned with Us
                                </p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
