import { Star, StarHalf } from 'lucide-react';
import * as React from 'react';
import { cn } from '@/lib/utils';

interface RatingInputProps {
    value: number;
    onChange: (value: number) => void;
    max?: number;
    label?: string;
    error?: string;
    className?: string;
}

export function RatingInput({
    value,
    onChange,
    max = 5,
    label,
    error,
    className,
}: RatingInputProps) {
    const [hoverValue, setHoverValue] = React.useState<number | null>(null);

    const displayValue = hoverValue !== null ? hoverValue : value;

    const handleMouseMove = (
        e: React.MouseEvent<HTMLDivElement>,
        index: number,
    ) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const isHalf = x < rect.width / 2;
        setHoverValue(index + (isHalf ? 0.5 : 1));
    };

    const handleMouseLeave = () => {
        setHoverValue(null);
    };

    const handleClick = (
        e: React.MouseEvent<HTMLDivElement>,
        index: number,
    ) => {
        const rect = e.currentTarget.getBoundingClientRect();
        const x = e.clientX - rect.left;
        const isHalf = x < rect.width / 2;
        onChange(index + (isHalf ? 0.5 : 1));
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
                    const isHalf = displayValue >= index + 0.5 && !isFull;

                    return (
                        <div
                            key={index}
                            className="relative cursor-pointer transition-transform hover:scale-110"
                            onMouseMove={(e) => handleMouseMove(e, index)}
                            onClick={(e) => handleClick(e, index)}
                        >
                            {isHalf ? (
                                <StarHalf className="h-8 w-8 fill-amber-400 text-amber-400" />
                            ) : (
                                <Star
                                    className={cn(
                                        'h-8 w-8',
                                        isFull
                                            ? 'fill-amber-400 text-amber-400'
                                            : 'fill-muted text-muted',
                                    )}
                                />
                            )}
                        </div>
                    );
                })}
                <span className="ml-2 text-lg font-semibold text-foreground">
                    {typeof displayValue === 'number' && displayValue > 0
                        ? displayValue.toFixed(1)
                        : ''}
                </span>
            </div>
            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
