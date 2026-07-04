/**
 * Service types that do not require a child on a booking (pet-only, companion
 * care, and group childcare). Mirrors ServiceType::requiresChild() on the
 * backend — keep the two in sync.
 */
export const CHILD_EXEMPT_SERVICE_TYPES = [
    'petsitter',
    'companion_care',
    'group_childcare_invoiced',
] as const;

export function serviceRequiresChild(serviceType?: string | null): boolean {
    return !CHILD_EXEMPT_SERVICE_TYPES.includes(
        serviceType as (typeof CHILD_EXEMPT_SERVICE_TYPES)[number],
    );
}
