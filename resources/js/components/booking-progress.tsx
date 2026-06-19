import { cn } from '@/lib/utils';

const steps = [
    { step: 1, label: 'Booking Details' },
    { step: 2, label: 'Payment' },
];

interface BookingProgressProps {
    currentStep: 1 | 2;
}

export default function BookingProgress({ currentStep }: BookingProgressProps) {
    return (
        <div className="mx-auto mb-8 max-w-xl">
            <div className="flex items-center justify-between">
                {steps.map((s, i) => (
                    <div key={s.step} className="flex items-center">
                        <div className="flex flex-col items-center gap-1.5">
                            <div
                                className={cn(
                                    'flex h-8 w-8 items-center justify-center rounded-full text-sm font-semibold transition-colors',
                                    s.step === currentStep &&
                                        'bg-table-header text-white ring-2 ring-primary',
                                    s.step < currentStep &&
                                        'bg-logo-teal text-white',
                                    s.step > currentStep &&
                                        'border border-border bg-card text-muted-foreground',
                                )}
                            >
                                {s.step < currentStep ? (
                                    <svg
                                        className="h-4 w-4"
                                        fill="none"
                                        viewBox="0 0 24 24"
                                        stroke="currentColor"
                                        strokeWidth={3}
                                    >
                                        <path
                                            strokeLinecap="round"
                                            strokeLinejoin="round"
                                            d="M5 13l4 4L19 7"
                                        />
                                    </svg>
                                ) : (
                                    s.step
                                )}
                            </div>
                            <span
                                className={cn(
                                    'text-xs whitespace-nowrap',
                                    s.step <= currentStep
                                        ? 'font-medium text-foreground'
                                        : 'text-muted-foreground',
                                )}
                            >
                                {s.label}
                            </span>
                        </div>
                        {i < steps.length - 1 && (
                            <div
                                className={cn(
                                    'mx-4 mt-[-1.25rem] h-px w-16 sm:w-24',
                                    s.step < currentStep
                                        ? 'bg-logo-teal'
                                        : 'bg-border',
                                )}
                            />
                        )}
                    </div>
                ))}
            </div>
        </div>
    );
}
