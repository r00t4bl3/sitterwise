import { useState } from 'react';
import {
    formatPhoneDisplay,
    isInternational,
    stripPhone,
    toE164,
} from '@/lib/phone';
import { cn } from '@/lib/utils';
import InputError from '@/components/input-error';
import { Input } from './input';
import { Label } from './label';

interface PhoneInputProps {
    value: string;
    onChange: (value: string) => void;
    onBlur?: () => void;
    error?: string;
    label?: string;
    name?: string;
    required?: boolean;
    placeholder?: string;
    disabled?: boolean;
}

export function PhoneInput({
    value,
    onChange,
    onBlur,
    error,
    label = 'Phone',
    name,
    required = false,
    placeholder = '(555) 123-4567',
    disabled = false,
}: PhoneInputProps) {
    const [international, setInternational] = useState(() =>
        value ? isInternational(value) : false,
    );

    const displayValue = value ? formatPhoneDisplay(value) : '';

    const handleChange = (raw: string) => {
        if (international) {
            const digits = raw.startsWith('+') ? '+' + stripPhone(raw.slice(1)) : stripPhone(raw);
            const cleaned = digits === '+' ? '' : digits;

            if (cleaned.startsWith('+')) {
                onChange(cleaned.length >= 3 ? toE164(cleaned) : cleaned);
            } else if (cleaned.length > 0) {
                const withPlus = '+' + cleaned;
                onChange(withPlus.length >= 3 ? toE164(withPlus) : withPlus);
            } else {
                onChange('');
            }
        } else {
            const digits = stripPhone(raw).slice(0, 10);

            onChange(digits.length > 0 ? '+1' + digits : '');
        }
    };

    const toggleInternational = () => {
        setInternational((prev) => !prev);
        onChange('');
    };

    return (
        <div>
            <Label>
                {label}
                {required && (
                    <span className="text-coral" aria-hidden="true">
                        {' '}*
                    </span>
                )}
                {' '}
                <button
                    type="button"
                    onClick={toggleInternational}
                    className={cn(
                        'inline text-xs underline-offset-2 transition-colors',
                        international
                            ? 'text-ring hover:text-foreground'
                            : 'text-muted-foreground hover:text-ring',
                    )}
                >
                    {international ? 'Back to US number' : 'Need a different country code?'}
                </button>
            </Label>
            <Input
                type="tel"
                name={name}
                value={displayValue}
                onChange={(e) => handleChange(e.target.value)}
                onBlur={onBlur}
                placeholder={international ? '+1 (555) 123-4567' : placeholder}
                disabled={disabled}
                aria-required={required}
                aria-invalid={!!error}
            />
            {error && <InputError message={error} />}
        </div>
    );
}
