import { Star, StarHalf } from 'lucide-react';
import * as React from 'react';
import { cn } from '@/lib/utils';

interface RatingInputProps {
    value: number | null;
    onChange: (value: number | null) => void;
    max?: number;
    allowClear?: boolean;
    wholeStarsOnly?: boolean;
    size?: 'sm' | 'md' | 'lg';
    showScore?: boolean;
    label?: string;
    error?: string;
    className?: string;
}

const sizeClasses = {
    sm: 'h-4 w-4',
    md: 'h-5 w-5',
    lg: 'h-8 w-8',
};

const textSizeClasses = {
    sm: 'text-xs',
    md: 'text-sm',
    lg: 'text-lg',
};

export function RatingInput({
    value,
    onChange,
    max = 5,
    allowClear = false,
    wholeStarsOnly = false,
    size = 'lg',
    showScore = true,
    label,
    error,
    className,
}: RatingInputProps) {
    const [hoverValue, setHoverValue] = React.useState<number | null>(null);

    const displayValue = hoverValue !== null ? hoverValue : (value ?? 0);

    const handleMouseMove = (
        e: React.MouseEvent<HTMLDivElement>,
        index: number,
    ) => {
        if (wholeStarsOnly) {
            setHoverValue(index + 1);
        } else {
            const rect = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const isHalf = x < rect.width / 2;
            setHoverValue(index + (isHalf ? 0.5 : 1));
        }
    };

    const handleMouseLeave = () => {
        setHoverValue(null);
    };

    const handleClick = (
        e: React.MouseEvent<HTMLDivElement>,
        index: number,
    ) => {
        let newValue: number;

        if (wholeStarsOnly) {
            newValue = index + 1;
        } else {
            const rect = e.currentTarget.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const isHalf = x < rect.width / 2;
            newValue = index + (isHalf ? 0.5 : 1);
        }

        if (allowClear && newValue === value) {
            onChange(null);
        } else {
            onChange(newValue);
        }
    };

    return (
        <div className={cn('space-y-2', className)}>
            {label && (
                <label className="block text-sm font-medium text-foreground">
                    {label}
                </label>
            )}
            <div
                className="flex items-center gap-1"
                onMouseLeave={handleMouseLeave}
            >
                {Array.from({ length: max }).map((_, index) => {
                    const isFull = displayValue >= index + 1;
                    const isHalf =
                        !wholeStarsOnly &&
                        displayValue >= index + 0.5 &&
                        !isFull;

                    return (
                        <div
                            key={index}
                            className="relative cursor-pointer transition-transform hover:scale-110"
                            onMouseMove={(e) => handleMouseMove(e, index)}
                            onClick={(e) => handleClick(e, index)}
                        >
                            {isHalf ? (
                                <StarHalf
                                    className={cn(
                                        'fill-amber-400 text-amber-400',
                                        sizeClasses[size],
                                    )}
                                />
                            ) : (
                                <Star
                                    className={cn(
                                        sizeClasses[size],
                                        isFull
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'fill-muted text-muted',
                                    )}
                                />
                            )}
                        </div>
                    );
                })}
                {showScore && (
                    <span
                        className={cn(
                            'ml-2 font-semibold text-foreground',
                            textSizeClasses[size],
                        )}
                    >
                        {displayValue > 0 ? displayValue.toFixed(1) : ''}
                    </span>
                )}
            </div>
            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
