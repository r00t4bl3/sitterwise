import type { Auth } from '@/types/auth';

interface CaregiverStatusOption {
    value: string;
    label: string;
    color: string;
    is_terminal: boolean;
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            caregiverStatuses: CaregiverStatusOption[];
            [key: string]: unknown;
        };
    }
}
