import { cn } from '@/lib/utils';

interface FeesBreakdownProps {
    charge_to_client?: number | null;
    paid_to_caregiver?: number | null;
    sitterwise_cut?: number | null;
    tip?: number | null;
    reimbursement?: number | null;
    bonus?: number | null;
    lifesaver_bonus?: number | null;
    /** Optional section heading (e.g. "Fees"). Omit when the container already labels it. */
    heading?: string;
    /** Shown when there are no fee values. Omit to render nothing in that case. */
    emptyMessage?: string;
    className?: string;
}

function formatCurrency(amount: number | null | undefined): string {
    return `$${Number(amount ?? 0).toFixed(2)}`;
}

/**
 * Read-only fee/cost breakdown shared by the booking detail page and the
 * Transactions "View breakdown" dialog so both stay in sync.
 */
export function FeesBreakdown({
    charge_to_client,
    paid_to_caregiver,
    sitterwise_cut,
    tip,
    reimbursement,
    bonus,
    lifesaver_bonus,
    heading,
    emptyMessage,
    className,
}: FeesBreakdownProps) {
    const feeItems = [
        { label: 'Charge to Client', value: charge_to_client },
        { label: 'Paid to Caregiver', value: paid_to_caregiver },
        { label: 'Sitterwise Cut', value: sitterwise_cut },
        { label: 'Tip', value: tip },
        { label: 'Reimbursement', value: reimbursement },
        { label: 'Bonus', value: bonus },
        ...((lifesaver_bonus ?? 0) > 0
            ? [{ label: 'Lifesaver Bonus', value: lifesaver_bonus }]
            : []),
    ].filter((f) => f.value !== null && f.value !== undefined);

    if (feeItems.length === 0) {
        return emptyMessage ? (
            <p className="text-sm text-muted-foreground">{emptyMessage}</p>
        ) : null;
    }

    return (
        <div className={className}>
            {heading && (
                <h2 className="text-md mb-2 font-semibold text-foreground">
                    {heading}
                </h2>
            )}
            <div className={cn('space-y-2')}>
                {feeItems.map((item) => (
                    <div
                        key={item.label}
                        className="flex items-center justify-between"
                    >
                        <span className="text-sm text-muted-foreground">
                            {item.label}
                        </span>
                        <span className="text-sm font-medium text-foreground">
                            {formatCurrency(item.value)}
                        </span>
                    </div>
                ))}
            </div>
        </div>
    );
}
