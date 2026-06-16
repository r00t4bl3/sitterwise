const TIMEZONE_PT = 'America/Los_Angeles';

/**
 * Converts a Date with local = PT components back to a UTC wall-clock string.
 * Output example: "2026-05-31T22:15"
 */
export const formatUtcStringFromPt = (ptDate: Date): string => {
    const y = ptDate.getFullYear();
    const m = ptDate.getMonth();
    const d = ptDate.getDate();
    const h = ptDate.getHours();
    const min = ptDate.getMinutes();

    let epoch = Date.UTC(y, m, d, h, min);

    for (let i = 0; i < 2; i++) {
        const candidate = new Date(epoch);

        const parts = new Intl.DateTimeFormat('en-US', {
            timeZone: TIMEZONE_PT,
            year: 'numeric',
            month: '2-digit',
            day: '2-digit',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        }).formatToParts(candidate);

        const get = (type: string) =>
            parseInt(parts.find((p) => p.type === type)!.value, 10);

        const ptEpoch = Date.UTC(
            get('year'),
            get('month') - 1,
            get('day'),
            get('hour'),
            get('minute'),
        );

        epoch += Date.UTC(y, m, d, h, min) - ptEpoch;
    }

    const utc = new Date(epoch);
    const yr = utc.getUTCFullYear();
    const mo = String(utc.getUTCMonth() + 1).padStart(2, '0');
    const da = String(utc.getUTCDate()).padStart(2, '0');
    const hr = String(utc.getUTCHours()).padStart(2, '0');
    const mi = String(utc.getUTCMinutes()).padStart(2, '0');

    return `${yr}-${mo}-${da}T${hr}:${mi}Z`;
};

/**
 * Formats a Date object to a datetime-local input value (YYYY-MM-DDTHH:mm).
 * Uses the browser's local timezone getters — the caller is responsible for
 * constructing the Date with the desired timezone context.
 */
export const formatDateTimeLocal = (date: Date): string => {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const hours = String(date.getHours()).padStart(2, '0');
    const minutes = String(date.getMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

/**
 * Formats a UTC ISO datetime string to a date in America/Los_Angeles.
 * Output example: "Monday, October 27, 2023"
 */
export const formatDisplayDateInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString('en-US', {
        timeZone: TIMEZONE_PT,
        weekday: 'long',
        year: 'numeric',
        month: 'long',
        day: 'numeric',
    });
};

/**
 * Formats a UTC ISO datetime string to a short date in America/Los_Angeles.
 * Output example: "Oct 27, 2023"
 */
export const formatDisplayDateShortInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleDateString('en-US', {
        timeZone: TIMEZONE_PT,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
    });
};

/**
 * Formats a UTC ISO datetime string to a time in America/Los_Angeles.
 * Output example: "9:15 AM"
 */
export const formatDisplayTimeInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleTimeString('en-US', {
        timeZone: TIMEZONE_PT,
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};

/**
 * Formats a UTC ISO datetime string to a short date/time in America/Los_Angeles.
 * Output example: "Oct 27, 2023, 9:15 AM"
 */
export const formatDisplayDateTimeInPT = (
    dateStr: string | null | undefined,
): string => {
    if (!dateStr) {
        return '';
    }

    const date = new Date(dateStr);

    if (isNaN(date.getTime())) {
        return '';
    }

    return date.toLocaleString('en-US', {
        timeZone: TIMEZONE_PT,
        month: 'short',
        day: 'numeric',
        year: 'numeric',
        hour: 'numeric',
        minute: '2-digit',
        hour12: true,
    });
};

/**
 * Formats a UTC start/end datetime pair as a date range in America/Los_Angeles.
 * If both fall on the same calendar date, shows "Date Time - Time".
 * Otherwise shows "StartDate StartTime - EndDate EndTime".
 * Examples: "May 31, 2026 3:15 PM - 7:15 PM"
 *           "May 31, 2026 3:15 PM - Jun 1, 2026 1:15 AM"
 */
export const formatDisplayDateTimeRangeInPT = (
    startStr: string | null | undefined,
    endStr: string | null | undefined,
): string => {
    if (!startStr || !endStr) {
        return '';
    }

    const startShort = formatDisplayDateShortInPT(startStr);
    const endShort = formatDisplayDateShortInPT(endStr);
    const startTime = formatDisplayTimeInPT(startStr);
    const endTime = formatDisplayTimeInPT(endStr);

    if (startShort === endShort) {
        return `${startShort} ${startTime} - ${endTime}`;
    }

    return `${startShort} ${startTime} - ${endShort} ${endTime}`;
};

/**
 * Auto-set end datetime to minimum 4 hours after start
 */
export const autoSetEndDateTime = (startDatetime: string): string => {
    const d = new Date(
        startDatetime.endsWith('Z') ? startDatetime : startDatetime + 'Z',
    );

    if (isNaN(d.getTime())) {
        return '';
    }

    const end = new Date(d.getTime() + 4 * 60 * 60 * 1000);

    const year = end.getUTCFullYear();
    const month = String(end.getUTCMonth() + 1).padStart(2, '0');
    const day = String(end.getUTCDate()).padStart(2, '0');
    const hours = String(end.getUTCHours()).padStart(2, '0');
    const minutes = String(end.getUTCMinutes()).padStart(2, '0');

    return `${year}-${month}-${day}T${hours}:${minutes}`;
};

/**
 * Validate minimum 4-hour duration
 */
export const validateMinimumDuration = (
    startDatetime: string,
    endDatetime: string,
): string | null => {
    if (!startDatetime || !endDatetime) {
        return null;
    }

    const startDate = new Date(
        startDatetime.endsWith('Z') ? startDatetime : startDatetime + 'Z',
    );
    const endDate = new Date(
        endDatetime.endsWith('Z') ? endDatetime : endDatetime + 'Z',
    );

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
