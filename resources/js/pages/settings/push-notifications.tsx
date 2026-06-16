import { Head } from '@inertiajs/react';
import PushTestCard from '@/components/push-test-card';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/push-notifications';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Push notifications',
        href: edit(),
    },
];

export default function PushNotifications() {
    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Push notifications" />

            <h1 className="sr-only">Push notifications</h1>

            <SettingsLayout>
                <PushTestCard />
            </SettingsLayout>
        </AppLayout>
    );
}
