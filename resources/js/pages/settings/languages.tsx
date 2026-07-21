import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

interface LanguageOption {
    value: string;
    label: string;
}

interface Props {
    [key: string]: unknown;
    languageOptions: LanguageOption[];
    selectedLanguages: string[];
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    {
        title: 'Languages Spoken',
        href: '/settings/caregiver/languages',
    },
];

export default function CaregiverLanguages() {
    const { languageOptions, selectedLanguages } = usePage<Props>().props;

    const form = useForm<{ languages: string[] }>({
        languages: selectedLanguages,
    });

    const toggleLanguage = (value: string) => {
        form.setData(
            'languages',
            form.data.languages.includes(value)
                ? form.data.languages.filter((x) => x !== value)
                : [...form.data.languages, value],
        );
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/settings/caregiver/languages', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Languages Spoken" />
            <ToasterMessage />
            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <div>
                        <h2 className="text-xl font-bold text-foreground">
                            Languages Spoken
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Select every language you can speak with families.
                            This helps us match you to families who request a
                            specific language.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <Label>Languages spoken</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {languageOptions.map((language) => (
                                <label
                                    key={language.value}
                                    className="flex items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4"
                                        checked={form.data.languages.includes(
                                            language.value,
                                        )}
                                        onChange={() =>
                                            toggleLanguage(language.value)
                                        }
                                    />
                                    {language.label}
                                </label>
                            ))}
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        Save Languages
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
