import { usePage } from '@inertiajs/react';
import CaregiverDashboard from './dashboard/caregiver';
import ClientDashboard from './dashboard/client';
import AdminDashboard from './dashboard/admin';

export default function Dashboard() {
    const { auth } = usePage<{
        auth: {
            user: {
                role: string;
                name: string;
                caregiver?: {
                    first_name: string;
                    last_name: string;
                    rating: number | null;
                    status: { name: string };
                };
            };
        };
    }>().props;

    switch (auth.user.role) {
        case 'caregiver':
            return (
                <CaregiverDashboard
                    caregiver={{
                        first_name:
                            auth.user.caregiver?.first_name || auth.user.name,
                        last_name: auth.user.caregiver?.last_name || '',
                        rating: auth.user.caregiver?.rating || null,
                        status: auth.user.caregiver?.status?.name || 'Unknown',
                    }}
                />
            );

        case 'admin':
            return (
                <AdminDashboard
                    stats={{
                        total_caregivers: 51,
                        active_caregivers: 10,
                        total_clients: 0,
                    }}
                />
            );

        case 'client':
        default:
            return <ClientDashboard user={{ name: auth.user.name }} />;
    }
}
