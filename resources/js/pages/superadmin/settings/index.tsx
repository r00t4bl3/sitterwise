import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import type { BreadcrumbItem } from '@/types';

interface SettingItem {
    key: string;
    value: string | number | boolean | null;
    type: string;
    group: string;
    label: string;
    description: string | null;
}

interface Props {
    settingGroups: Record<string, SettingItem[]>;
}

const GROUP_LABELS: Record<string, string> = {
    lifesaver: 'Lifesaver',
    trustline: 'Trustline',
    caregiver: 'Caregiver',
    references: 'References',
    applications: 'Applications',
    bookings: 'Bookings',
    billing: 'Billing',
    general: 'General',
};

export default function AppSettings({ settingGroups }: Props) {
    const initial: Record<string, string | number | boolean> = {};
    Object.values(settingGroups).forEach((items) =>
        items.forEach((s) => {
            initial[s.key] = s.value ?? '';
        }),
    );

    const { data, setData, put, processing, errors } = useForm<{
        settings: Record<string, string | number | boolean>;
    }>({ settings: initial });

    const { flash } = usePage<{ flash: { success?: string } }>().props;
    const fieldErrors = errors as Record<string, string>;

    function update(key: string, value: string | number | boolean) {
        setData('settings', { ...data.settings, [key]: value });
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        put('/app-settings', { preserveScroll: true });
    }

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Settings" />
            <ToasterMessage />

            <form
                onSubmit={submit}
                className="flex h-full flex-1 flex-col gap-6 p-4"
            >
                <div>
                    <h1 className="font-serif text-2xl font-bold text-foreground">
                        Settings
                    </h1>
                    <p className="text-sm text-muted-foreground">
                        Application configuration.
                    </p>
                </div>

                {flash?.success && (
                    <div className="rounded-lg border border-green-500/30 bg-green-50 p-4 text-sm text-green-800 dark:bg-green-950 dark:text-green-200">
                        {flash.success}
                    </div>
                )}

                {Object.entries(settingGroups).map(([group, items]) => (
                    <div key={group} className="rounded-lg border bg-card p-4">
                        <h2 className="mb-4 text-lg font-semibold text-foreground">
                            {GROUP_LABELS[group] ?? group}
                        </h2>
                        <div className="space-y-4">
                            {items.map((s) => (
                                <div
                                    key={s.key}
                                    className="grid max-w-md gap-1"
                                >
                                    <Label htmlFor={s.key}>{s.label}</Label>
                                    {s.type === 'bool' ? (
                                        <input
                                            id={s.key}
                                            type="checkbox"
                                            checked={Boolean(
                                                data.settings[s.key],
                                            )}
                                            onChange={(e) =>
                                                update(s.key, e.target.checked)
                                            }
                                            className="h-4 w-4"
                                        />
                                    ) : (
                                        <Input
                                            id={s.key}
                                            type={
                                                s.type === 'int' ||
                                                s.type === 'float'
                                                    ? 'number'
                                                    : 'text'
                                            }
                                            step={
                                                s.type === 'float'
                                                    ? '0.01'
                                                    : s.type === 'int'
                                                      ? '1'
                                                      : undefined
                                            }
                                            value={String(
                                                data.settings[s.key] ?? '',
                                            )}
                                            onChange={(e) =>
                                                update(s.key, e.target.value)
                                            }
                                        />
                                    )}
                                    {s.description && (
                                        <p className="text-xs text-muted-foreground">
                                            {s.description}
                                        </p>
                                    )}
                                    {fieldErrors[`settings.${s.key}`] && (
                                        <p className="text-xs text-red-600">
                                            {fieldErrors[`settings.${s.key}`]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>
                    </div>
                ))}

                <div>
                    <Button type="submit" disabled={processing}>
                        Save Settings
                    </Button>
                </div>
            </form>
        </AppLayout>
    );
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Dashboard', href: '/dashboard' },
    { title: 'Settings', href: '/app-settings' },
];
