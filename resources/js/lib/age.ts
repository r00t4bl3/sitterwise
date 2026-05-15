export const MAX_CHILD_AGE = 30;

export function getChildBirthYearOptions(): number[] {
    const currentYear = new Date().getFullYear();

    return Array.from({ length: MAX_CHILD_AGE + 1 }, (_, i) => currentYear - i);
}

export function calculateAgeFromDate(dateOfBirth: string): number {
    const today = new Date();
    const birthDate = new Date(dateOfBirth);
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();

    if (
        monthDiff < 0 ||
        (monthDiff === 0 && today.getDate() < birthDate.getDate())
    ) {
        age--;
    }

    return age;
}

export function calculateAge(
    birthYear: number | null,
    birthMonth: number | null,
): string {
    if (!birthYear && !birthMonth) {
        return 'Age unknown';
    }

    const now = new Date();
    const currentYear = now.getFullYear();
    const currentMonth = now.getMonth() + 1;

    const year = birthYear ?? currentYear;
    const month = birthMonth ?? 1;

    let years = currentYear - year;
    let months = currentMonth - month;

    if (months < 0) {
        years--;
        months += 12;
    }

    if (years < 0 || (years === 0 && months < 0)) {
        return 'Age unknown';
    }

    if (years === 0 && months === 0) {
        return 'Newborn';
    }

    if (years >= 18) {
        return `${years} yrs`;
    }

    if (years === 0) {
        return `${months} mo${months !== 1 ? 's' : ''}`;
    }

    if (months === 0) {
        return `${years} yr${years !== 1 ? 's' : ''}`;
    }

    return `${years} yr${years !== 1 ? 's' : ''} ${months} mo${months !== 1 ? 's' : ''}`;
}
