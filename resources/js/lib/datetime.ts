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
