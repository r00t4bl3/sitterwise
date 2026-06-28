import { Gift } from "lucide-react";

interface TrustlineCardProps {
    certified: boolean;
    clearedAt?: string;
    firstName?: string;
}

export default function TrustlineCard({ certified, clearedAt, firstName }: TrustlineCardProps) {
    if (certified) {
        return (
            <div className="relative overflow-hidden rounded-none border border-green-200 bg-linear-135 from-[#1B3A5C] via-[#27557F] via-[58%] to-[#2E6E8F] p-4 shadow-sm">
                <div className="relative z-10 flex items-center gap-3">
                    <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full bg-primary">
                        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="#ffffff" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                            <path d="M12 2.5l7.5 3v5.2c0 4.8-3.2 8.4-7.5 10.3C7.7 19.1 4.5 15.5 4.5 10.7V5.5L12 2.5z" />
                            <path d="M8.6 12.2l2.3 2.3 4.5-4.8" />
                        </svg>
                    </div>
                    <div className="flex-1">
                        <p className="text-xs font-medium tracking-wider uppercase text-[#84D0D2]">
                            TrustLine Registered
                        </p>
                        <h3 className="mt-2 text-lg font-semibold text-white">
                            You&apos;re cleared, {firstName}.
                        </h3>
                        <p className="mt-2 text-sm leading-relaxed text-white">
                            You&apos;ve completed California&apos;s TrustLine Registry — the same background
                            standard every Sitterwise caregiver holds.
                        </p>
                        <div className="mt-3 flex items-center gap-3">
                            <span className="inline-flex items-center gap-1.5 rounded-full bg-green-100 px-2.5 py-1 text-xs font-medium text-green-800">
                                <span className="h-2 w-2 rounded-full bg-green-500" />
                                Active Clearance
                            </span>
                            {clearedAt && (
                                <span className="inline-flex items-center gap-1.5 rounded-full bg-[#2E6E8F] px-2.5 py-1 text-xs font-medium">
                                    <span className="text-xs text-white">
                                        Cleared {clearedAt}
                                    </span>
                                </span>
                            )}
                        </div>
                    </div>
                </div>
            </div>
        );
    }

    return (
        <div className="rounded-none border border-border p-4 shadow-sm bg-linear-135 from-blush to-teal-bg">
            <div className="flex items-center gap-3">
                <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border-dashed border border-teal-400/40 bg-white">
                    <svg viewBox="0 0 24 24" width="30" height="30" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                        <path d="M12 2.5l7.5 3v5.2c0 4.8-3.2 8.4-7.5 10.3C7.7 19.1 4.5 15.5 4.5 10.7V5.5L12 2.5z" />
                        <path d="M12 8.6v5.2M9.4 11.2h5.2" />
                    </svg>
                </div>
                <div className="flex-1">
                    <p className="text-xs font-medium tracking-wider uppercase text-primary">
                        Action Needed
                    </p>
                    <h3 className="mt-2 text-lg font-semibold text-foreground">
                        Complete your TrustLine registration
                    </h3>
                    <p className="mt-2 text-sm leading-relaxed text-muted-foreground">
                        Every Sitterwise caregiver is required to be TrustLine registered &mdash; it's California's background check standard for in-home care. Let's get you cleared.
                    </p>
                    <div className="mt-2 text-sm text-muted-foreground flex border border-2 bg-teal-100/40 gap-3 p-4">
                        <span><Gift /></span>
                        <p><strong>We'll pay you back.</strong> Cover the Trustline fee upfront, complete 10 jobs within 6 months, and Sitterwise reimburses you in full.</p>
                    </div>
                </div>
            </div>
        </div>
    );
}
