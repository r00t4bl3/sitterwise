export function stripPhone(value: string): string {
    return value.replace(/[^0-9]/g, '');
}

export function toE164(value: string): string {
    const digits = stripPhone(value);

    if (digits.length === 0) {
        return '';
    }

    if (digits.length === 10) {
        return '+1' + digits;
    }

    if (digits.length === 11 && digits.startsWith('1')) {
        return '+' + digits;
    }

    return '+' + digits;
}

export function formatPhoneDisplay(e164: string): string {
    if (!e164 || !e164.startsWith('+')) {
        return e164 || '';
    }

    const digits = e164.slice(1);

    if (digits.startsWith('1') && digits.length === 11) {
        const area = digits.slice(1, 4);
        const prefix = digits.slice(4, 7);
        const line = digits.slice(7, 11);

        return `(${area}) ${prefix}-${line}`;
    }

    const parts: string[] = [];
    let i = 0;

    if (digits.length > 0) {
        const ccLen = digits.startsWith('1') ? 1 : digits.length <= 3 ? 0 : 2;

        if (ccLen > 0) {
            parts.push(digits.slice(0, ccLen));
            i = ccLen;
        }
    }

    while (i < digits.length) {
        const chunk = Math.min(3, digits.length - i);
        parts.push(digits.slice(i, i + chunk));
        i += chunk;
    }

    return '+' + parts.join(' ');
}

export function isInternational(e164: string): boolean {
    if (!e164.startsWith('+')) {
        return false;
    }

    const digits = e164.slice(1);

    return !(digits.startsWith('1') && digits.length === 11);
}

export function validatePhone(e164: string): string | null {
    if (!e164) {
        return 'Phone is required.';
    }

    if (!e164.startsWith('+')) {
        return 'Invalid phone number format.';
    }

    const digits = e164.slice(1);

    if (digits.startsWith('1') && digits.length === 11) {
        return null;
    }

    if (digits.length >= 7 && digits.length <= 15) {
        return null;
    }

    return 'Please enter a valid phone number.';
}
