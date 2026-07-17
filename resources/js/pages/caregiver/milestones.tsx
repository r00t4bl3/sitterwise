import { Head } from '@inertiajs/react';
import { Briefcase, Star, TrendingUp, Zap, Gift } from 'lucide-react';
import Medallion from '@/components/medallion';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface Badge {
    slug: string;
    name: string;
    group: string;
    tier: 'teal' | 'coral' | 'navy';
    variant: string;
    earned: boolean;
    earned_date: string | null;
    criteria: string;
    progress?: string | null;
}

interface MilestonesProps {
    badges: Badge[];
    milestones: {
        completedJobs: number;
        sinceJoined: string;
        rating: number | null;
        ratingCount: number;
        reliabilityPercent: number | null;
        teamAvgPercent: number | null;
        jobStreak: number;
        trustlineCertified: boolean;
        trustlineProgress: number;
        trustlineThreshold: number;
        trustlineReward: number;
    };
    engagement: {
        jobsOffered: number;
        jobsAccepted: number;
        acceptanceRate: number;
        avgResponseTimeHours: number | null;
        declined: number;
        declinedPercent: number;
        backOutRate: number;
        jobsThisMonth: number;
        jobsThisQuarter: number;
        lastJobDate: string | null;
        memberSince: string;
    };
}

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Milestones',
        href: '/milestones',
    },
];

function StarDisplay({
    rating,
    size = 'md',
}: {
    rating: number | null;
    size?: 'sm' | 'md' | 'lg';
}) {
    if (rating === null) {
        return <span className="text-muted-foreground">--</span>;
    }

    const sizeClass =
        size === 'sm' ? 'h-3.5 w-3.5' : size === 'lg' ? 'h-5 w-5' : 'h-4 w-4';

    return (
        <span className="inline-flex items-center gap-0.5">
            {[1, 2, 3, 4, 5].map((star) => {
                const fill =
                    rating >= star
                        ? 'fill-amber-400 text-amber-400'
                        : rating >= star - 0.5
                          ? 'fill-amber-300 text-amber-300'
                          : 'text-muted-foreground/30';

                return <Star key={star} className={`${sizeClass} ${fill}`} />;
            })}
        </span>
    );
}

interface StatCardProps {
    icon: React.ReactNode;
    label: string;
    value: React.ReactNode;
    subtext?: string | null;
    accent?: string;
}

function StatCard({
    icon,
    label,
    value,
    subtext,
    accent = 'text-primary',
}: StatCardProps) {
    return (
        <div className="flex flex-col gap-2 rounded-xl border border-border bg-card p-5 shadow-sm">
            <div className="flex items-center gap-2 text-muted-foreground">
                <span className={accent}>{icon}</span>
                <span className="text-xs font-medium tracking-wider uppercase">
                    {label}
                </span>
            </div>
            <div className="text-3xl font-bold text-foreground">{value}</div>
            {subtext && (
                <p className="text-xs text-muted-foreground">{subtext}</p>
            )}
        </div>
    );
}

function ProgressBar({
    current,
    max,
    label,
}: {
    current: number;
    max: number;
    label: string;
}) {
    const pct = Math.min(100, Math.round((current / max) * 100));

    return (
        <div className="space-y-1.5">
            <div className="flex items-center justify-between text-xs text-muted-foreground">
                <span>{label}</span>
                <span>
                    {current} / {max}
                </span>
            </div>
            <div className="h-2.5 w-full overflow-hidden rounded-full bg-muted">
                <div
                    className="h-full rounded-full bg-gradient-to-r from-emerald-400 to-emerald-500 transition-all"
                    style={{ width: `${pct}%` }}
                />
            </div>
        </div>
    );
}

export default function Milestones({
    milestones,
    engagement,
    badges,
}: MilestonesProps) {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Milestones" />

            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <div>
                    <h1 className="text-2xl font-bold text-foreground">
                        Your Milestones
                    </h1>
                    <p className="text-muted-foreground">
                        Since joining in {milestones.sinceJoined}
                    </p>
                </div>

                <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5">
                    <StatCard
                        icon={<Briefcase className="h-4 w-4" />}
                        label="Jobs Completed"
                        value={milestones.completedJobs}
                        subtext={
                            milestones.completedJobs === 1
                                ? '1 job completed'
                                : `${milestones.completedJobs} jobs completed`
                        }
                        accent="text-blue-500"
                    />

                    <StatCard
                        icon={<Star className="h-4 w-4" />}
                        label="Client Rating"
                        value={
                            <span className="inline-flex items-center gap-2">
                                <span className="text-3xl font-bold">
                                    {milestones.rating ?? '--'}
                                </span>
                                <StarDisplay
                                    rating={milestones.rating}
                                    size="md"
                                />
                            </span>
                        }
                        subtext={
                            milestones.ratingCount > 0
                                ? `From ${milestones.ratingCount} review${milestones.ratingCount === 1 ? '' : 's'}`
                                : 'No reviews yet'
                        }
                        accent="text-amber-500"
                    />

                    <StatCard
                        icon={<TrendingUp className="h-4 w-4" />}
                        label="Reliability"
                        value={
                            milestones.reliabilityPercent !== null
                                ? `${milestones.reliabilityPercent}%`
                                : '--'
                        }
                        subtext={
                            milestones.teamAvgPercent !== null
                                ? `Team avg: ${milestones.teamAvgPercent}%`
                                : null
                        }
                        accent="text-emerald-500"
                    />

                    <StatCard
                        icon={<Zap className="h-4 w-4" />}
                        label="Job Streak"
                        value={milestones.jobStreak}
                        subtext={
                            milestones.jobStreak === 1
                                ? '1 consecutive'
                                : milestones.jobStreak > 0
                                  ? `${milestones.jobStreak} consecutive`
                                  : 'Start your streak!'
                        }
                        accent="text-orange-500"
                    />

                    <StatCard
                        icon={<Gift className="h-4 w-4" />}
                        label="Trustline"
                        value={
                            milestones.trustlineCertified
                                ? `${milestones.trustlineProgress} / ${milestones.trustlineThreshold}`
                                : 'Not certified'
                        }
                        subtext={
                            milestones.trustlineCertified &&
                            milestones.trustlineProgress >=
                                milestones.trustlineThreshold
                                ? `$${milestones.trustlineReward} reward earned!`
                                : milestones.trustlineCertified
                                  ? `${milestones.trustlineThreshold - milestones.trustlineProgress} more to earn $${milestones.trustlineReward}`
                                  : null
                        }
                        accent="text-purple-500"
                    />
                </div>

                {milestones.trustlineCertified && (
                    <div className="rounded-xl border border-border bg-card p-5 shadow-sm">
                        <h2 className="mb-3 text-sm leading-none font-semibold tracking-tight">
                            Trustline Progress
                        </h2>
                        <ProgressBar
                            current={Math.min(
                                milestones.trustlineProgress,
                                milestones.trustlineThreshold,
                            )}
                            max={milestones.trustlineThreshold}
                            label="Jobs toward reward"
                        />
                        {milestones.trustlineProgress >=
                            milestones.trustlineThreshold && (
                            <p className="mt-2 text-sm font-medium text-emerald-600">
                                You've earned the ${milestones.trustlineReward}{' '}
                                Trustline reward!
                            </p>
                        )}
                    </div>
                )}

                <div className="rounded-xl border border-border bg-card shadow-sm">
                    <div className="border-b border-border px-6 py-4">
                        <h2 className="text-lg font-semibold">
                            Activity Detail
                        </h2>
                    </div>
                    <div className="divide-y divide-border">
                        <div className="grid gap-4 p-6 sm:grid-cols-2 lg:grid-cols-3">
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Jobs Offered
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.jobsOffered}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Jobs Accepted
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.jobsAccepted}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Acceptance Rate
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.acceptanceRate}%
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Avg Response Time
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.avgResponseTimeHours !== null
                                        ? `${engagement.avgResponseTimeHours}h`
                                        : '--'}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Declined / Ignored
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.declined} (
                                    {engagement.declinedPercent}%)
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Back-Out Rate
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.backOutRate}%
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Jobs This Month
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.jobsThisMonth}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Jobs This Quarter
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.jobsThisQuarter}
                                </p>
                            </div>
                            <div>
                                <span className="text-xs font-medium tracking-wider text-muted-foreground uppercase">
                                    Last Job
                                </span>
                                <p className="mt-1 text-xl font-semibold text-foreground">
                                    {engagement.lastJobDate || '--'}
                                </p>
                            </div>
                        </div>
                        <div className="px-6 py-3 text-xs text-muted-foreground">
                            Member since {engagement.memberSince}
                        </div>
                    </div>
                </div>

                {badges.length > 0 && (
                    <div className="rounded-xl border border-border bg-card shadow-sm">
                        <div className="flex items-center justify-between border-b border-border px-6 py-4">
                            <h2 className="text-lg font-semibold">
                                Trophy Case
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {badges.filter((b) => b.earned).length} of{' '}
                                {badges.length} earned
                            </span>
                        </div>
                        <div className="space-y-6 p-6">
                            {Array.from(new Set(badges.map((b) => b.group))).map(
                                (group) => (
                                    <div key={group}>
                                        <h3 className="mb-3 text-xs font-semibold tracking-wider text-muted-foreground uppercase">
                                            {group}
                                        </h3>
                                        <div className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
                                            {badges
                                                .filter(
                                                    (b) => b.group === group,
                                                )
                                                .map((badge) => (
                                                    <div
                                                        key={badge.slug}
                                                        title={badge.criteria}
                                                        className={`flex flex-col items-center gap-2 rounded-lg border p-3 text-center ${
                                                            badge.earned
                                                                ? 'border-border bg-background'
                                                                : 'border-dashed border-border'
                                                        }`}
                                                    >
                                                        <Medallion
                                                            tier={badge.tier}
                                                            variant={
                                                                badge.variant
                                                            }
                                                            earned={
                                                                badge.earned
                                                            }
                                                            size="md"
                                                        />
                                                        <p
                                                            className={`text-xs font-medium ${
                                                                badge.earned
                                                                    ? 'text-foreground'
                                                                    : 'text-muted-foreground'
                                                            }`}
                                                        >
                                                            {badge.name}
                                                        </p>
                                                        {badge.earned &&
                                                        badge.earned_date ? (
                                                            <p className="text-[10px] text-primary">
                                                                {
                                                                    badge.earned_date
                                                                }
                                                            </p>
                                                        ) : !badge.earned ? (
                                                            <p className="text-[10px] text-muted-foreground">
                                                                {badge.progress ||
                                                                    badge.criteria}
                                                            </p>
                                                        ) : null}
                                                    </div>
                                                ))}
                                        </div>
                                    </div>
                                ),
                            )}
                        </div>
                    </div>
                )}
            </div>
        </AppLayout>
    );
}
