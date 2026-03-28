import { usePage } from '@inertiajs/react';
import CaregiverDashboard from './dashboard/caregiver';
import ClientDashboard from './dashboard/client';
import AdminDashboard from './dashboard/admin';

interface Availability {
    id: number;
    date: string;
    time_slots: string[];
    specific_time: string | null;
}

interface Props {
    [key: string]: unknown;
    user: {
        role: string;
        name: string;
    };
    stats?: {
        total_caregivers: number;
        active_caregivers: number;
        total_clients: number;
    };
    caregiver?: {
        id: number;
        first_name: string;
        last_name: string;
        rating: number | null;
        status: { name: string };
        availabilities: Availability[];
    };
}

export default function Dashboard() {
    const { user, stats, caregiver } = usePage<Props>().props;

    switch (user.role) {
        case 'caregiver':
            return (
                <CaregiverDashboard
                    caregiver={{
                        id: caregiver?.id || 0,
                        first_name: caregiver?.first_name || user.name,
                        last_name: caregiver?.last_name || '',
                        rating: caregiver?.rating || null,
                        status: caregiver?.status?.name || 'Unknown',
                        availabilities: caregiver?.availabilities || [],
                    }}
                />
            );

        case 'admin':
            return (
                <AdminDashboard
                    stats={{
                        total_caregivers: stats?.total_caregivers || 0,
                        active_caregivers: stats?.active_caregivers || 0,
                        total_clients: stats?.total_clients || 0,
                    }}
                />
            );

        case 'client':
        default:
            return <ClientDashboard user={{ name: user.name }} />;
    }
}
