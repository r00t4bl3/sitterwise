import { useEffect, useState } from 'react';
import {
    formatPhoneDisplay,
    isInternational,
    stripPhone,
    toE164,
    validatePhone,
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
    const [displayValue, setDisplayValue] = useState(() =>
        value ? formatPhoneDisplay(value) : '',
    );

    useEffect(() => {
        if (value) {
            setInternational(isInternational(value));
            setDisplayValue(formatPhoneDisplay(value));
        } else {
            setDisplayValue('');
            setInternational(false);
        }
    }, [value]);

    const handleChange = (raw: string) => {
        if (international) {
            const digits = raw.startsWith('+') ? '+' + stripPhone(raw.slice(1)) : stripPhone(raw);
            const cleaned = digits === '+' ? '' : digits;

            if (cleaned.startsWith('+')) {
                setDisplayValue(formatPhoneDisplay(cleaned));
                if (cleaned.length >= 3) {
                    onChange(toE164(cleaned));
                } else {
                    onChange('');
                }
            } else if (cleaned.length > 0) {
                const withPlus = '+' + cleaned;
                setDisplayValue(formatPhoneDisplay(withPlus));
                if (withPlus.length >= 3) {
                    onChange(toE164(withPlus));
                } else {
                    onChange('');
                }
            } else {
                setDisplayValue('');
                onChange('');
            }
        } else {
            const digits = stripPhone(raw).slice(0, 10);

            setDisplayValue(formatPhoneDisplay('+1' + digits));

            if (digits.length === 10) {
                onChange('+1' + digits);
            } else {
                onChange('');
            }
        }
    };

    const toggleInternational = () => {
        setInternational((prev) => !prev);
        setDisplayValue('');
        onChange('');
    };

    return (
        <div>
            <div className="flex items-center gap-2">
                <Label>
                    {label}
                    {required && (
                        <span className="text-coral" aria-hidden="true">
                            {' '}*
                        </span>
                    )}
                </Label>
                <button
                    type="button"
                    onClick={toggleInternational}
                    className={cn(
                        'text-xs underline-offset-2 transition-colors',
                        international
                            ? 'text-ring hover:text-foreground'
                            : 'text-muted-foreground hover:text-ring',
                    )}
                >
                    {international ? 'US number?' : 'International?'}
                </button>
            </div>
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
