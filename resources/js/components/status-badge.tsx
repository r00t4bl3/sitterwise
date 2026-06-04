interface BookingStatus {
    value: string;
    label: string;
    colors: {
        bg: string;
        text: string;
        border: string;
    };
}

interface StatusBadgeProps {
    status: string;
    bookingStatuses: BookingStatus[];
    className?: string;
}

export function StatusBadge({
    status,
    bookingStatuses,
    className = '',
}: StatusBadgeProps) {
    const statusKey = status.toLowerCase();
    const statusObj = bookingStatuses.find((s) => s.value === statusKey);
    const colors = statusObj?.colors;

    if (colors) {
        return (
            <div
                className={`inline-flex w-24 items-center justify-center rounded-[3px] border px-2 py-0.5 text-[10px] font-semibold ${colors.bg} ${colors.text} ${colors.border} ${className}`}
            >
                {statusObj.label || status.toUpperCase()}
            </div>
        );
    }

    return (
        <div
            className={`inline-flex w-24 items-center justify-center rounded-[3px] border border-gray-300 bg-gray-100 px-2 py-0.5 text-[10px] font-semibold text-gray-800 dark:border-gray-700/50 dark:bg-gray-800/50 dark:text-gray-400 ${className}`}
        >
            {status.toUpperCase()}
        </div>
    );
}
