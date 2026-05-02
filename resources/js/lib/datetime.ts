import { format } from 'date-fns';

/**
 * Parses a date string as local time, ignoring any timezone indicators.
 * This is used for "wall clock" time where we want to preserve the digits
 * regardless of the user's current timezone.
 */
export const parseAsLocal = (
    dateStr: string | null | undefined,
): Date | null => {
    if (!dateStr) {
        return null;
    }

    // Remove 'Z' if present to treat as local time
    // ISO strings like "2023-10-27T09:15:00.000000Z" -> "2023-10-27T09:15:00.000000"
    const cleanStr = dateStr.endsWith('Z') ? dateStr.slice(0, -1) : dateStr;

    // For simple YYYY-MM-DD, append T00:00:00 to ensure local interpretation
    // Browsers treat "2023-10-27" as UTC 00:00:00, but "2023-10-27T00:00:00" as local 00:00:00
    const finalStr = cleanStr.length === 10 ? `${cleanStr}T00:00:00` : cleanStr;

    return new Date(finalStr);
};

/**
 * Formats a date for display (Wall Clock).
 * Output example: "Monday, October 27, 2023"
 */
export const formatDisplayDate = (
    dateStr: string | null | undefined,
): string => {
    const date = parseAsLocal(dateStr);

    if (!date) {
        return '';
    }

    return format(date, 'EEEE, MMMM d, yyyy');
};

/**
 * Formats a time for display (Wall Clock).
 * Output example: "9:15 AM"
 */
export const formatDisplayTime = (
    dateStr: string | null | undefined,
): string => {
    const date = parseAsLocal(dateStr);

    if (!date) {
        return '';
    }

    return format(date, 'h:mm aa');
};

/**
 * Formats a datetime for display (Wall Clock).
 * Output example: "Oct 27, 2023, 9:15 AM"
 */
export const formatDisplayDateTime = (
    dateStr: string | null | undefined,
): string => {
    const date = parseAsLocal(dateStr);

    if (!date) {
        return '';
    }

    return format(date, 'MMM d, yyyy, h:mm aa');
};

/**
 * Formats a date string that represents a specific point in time (like created_at).
 * This respects the user's timezone by NOT stripping the 'Z' indicator.
 * Output example: "Oct 27, 2023, 5:00 PM" (if local time is 7 hours ahead of UTC)
 */
export const formatPointInTime = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) {
        return '—';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return '—';
    }

    return format(date, 'MMM d, yyyy, h:mm aa');
};

/**
 * Get time options with disabled states based on minimum duration rule
 * Disables times that would result in booking < 4 hours from start time
 */
export const getTimeOptionsWithDisabled = (
    startTime: string | undefined,
    selectedTime?: string,
): Array<{ value: string; label: string; disabled: boolean }> => {
    const options = [];
    const startDate = startTime ? new Date(startTime) : null;

    for (let i = 0; i < 96; i++) {
        const totalMins = i * 15;
        const hours24 = Math.floor(totalMins / 60);
        const minutes = totalMins % 60;
        const hours12 =
            hours24 === 0 ? 12 : hours24 > 12 ? hours24 - 12 : hours24;
        const ampm = hours24 < 12 ? 'AM' : 'PM';

        const timeValue = `${String(hours24).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`;
        const label = `${hours12}:${String(minutes).padStart(2, '0')} ${ampm}`;

        let disabled = false;
        if (startDate) {
            const optionDate = new Date(startDate);
            optionDate.setHours(hours24, minutes, 0, 0);
            const diffHours =
                (optionDate.getTime() - startDate.getTime()) / (1000 * 60 * 60);
            disabled = diffHours < 4;
        }

        options.push({
            value: timeValue,
            label: disabled ? `${label} (min 4h)` : label,
            disabled,
        });
    }

    return options;
};

/**
 * Auto-set end datetime to minimum 4 hours after start
 */
export const autoSetEndDateTime = (startDatetime: string): string => {
    const startDate = new Date(startDatetime);
    const endDate = new Date(startDate.getTime() + 4 * 60 * 60 * 1000);

    const year = endDate.getFullYear();
    const month = String(endDate.getMonth() + 1).padStart(2, '0');
    const day = String(endDate.getDate()).padStart(2, '0');
    const hours = String(endDate.getHours()).padStart(2, '0');
    const minutes = String(endDate.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

/**
 * Validate minimum 4-hour duration
 * Mirrors the backend MinimumBookingDuration rule
 */
export const validateMinimumDuration = (
    startDatetime: string,
    endDatetime: string,
): string | null => {
    if (!startDatetime || !endDatetime) {
        return null;
    }

    const startDate = new Date(startDatetime);
    const endDate = new Date(endDatetime);

    if (isNaN(startDate.getTime()) || isNaN(endDate.getTime())) {
        return 'Invalid date/time.';
    }

    const diffMs = endDate.getTime() - startDate.getTime();
    const diffHours = diffMs / (1000 * 60 * 60);

    if (diffHours < 4) {
        return 'The booking must be at least 4 hours long.';
    }

    return null;
};
