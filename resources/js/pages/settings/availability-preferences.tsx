import { Head, useForm, usePage } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { ToasterMessage } from '@/components/toaster-message';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import type { BreadcrumbItem } from '@/types';

interface Option {
    id: number;
    name: string;
}

interface LanguageOption {
    value: string;
    label: string;
}

interface Props {
    [key: string]: unknown;
    specialtyTypes: Option[];
    locations: Option[];
    languageOptions: LanguageOption[];
    selectedSpecialtyIds: number[];
    selectedLocationIds: number[];
    selectedLanguages: string[];
    preferredLocationId: number | null;
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Settings', href: '/settings' },
    {
        title: 'Availability Preferences',
        href: '/settings/caregiver/availability',
    },
];

export default function AvailabilityPreferences() {
    const {
        specialtyTypes,
        locations,
        languageOptions,
        selectedSpecialtyIds,
        selectedLocationIds,
        selectedLanguages,
        preferredLocationId,
    } = usePage<Props>().props;

    const form = useForm<{
        specialty_type_ids: number[];
        location_ids: number[];
        languages: string[];
        preferred_location_id: number | null;
    }>({
        specialty_type_ids: selectedSpecialtyIds,
        location_ids: selectedLocationIds,
        languages: selectedLanguages,
        preferred_location_id: preferredLocationId,
    });

    const toggleSpecialty = (id: number) => {
        form.setData(
            'specialty_type_ids',
            form.data.specialty_type_ids.includes(id)
                ? form.data.specialty_type_ids.filter((x) => x !== id)
                : [...form.data.specialty_type_ids, id],
        );
    };

    const toggleLanguage = (value: string) => {
        form.setData(
            'languages',
            form.data.languages.includes(value)
                ? form.data.languages.filter((x) => x !== value)
                : [...form.data.languages, value],
        );
    };

    const toggleLocation = (id: number) => {
        const has = form.data.location_ids.includes(id);
        form.setData(
            'location_ids',
            has
                ? form.data.location_ids.filter((x) => x !== id)
                : [...form.data.location_ids, id],
        );

        if (has && form.data.preferred_location_id === id) {
            form.setData('preferred_location_id', null);
        }
    };

    const submit = (e: FormEvent) => {
        e.preventDefault();
        form.put('/settings/caregiver/availability', { preserveScroll: true });
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Availability Preferences" />
            <ToasterMessage />
            <SettingsLayout>
                <form onSubmit={submit} className="space-y-8">
                    <div>
                        <h2 className="text-xl font-bold text-foreground">
                            Availability Preferences
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Choose the age groups you're comfortable with and
                            the areas you'd like to work. These help us match
                            you to the right jobs.
                        </p>
                    </div>

                    <div className="space-y-3">
                        <Label>Preferred age groups</Label>
                        <div className="grid gap-2 sm:grid-cols-2">
                            {specialtyTypes.map((s) => (
                                <label
                                    key={s.id}
                                    className="flex items-center gap-2 text-sm text-foreground"
                                >
                                    <input
                                        type="checkbox"
                                        className="h-4 w-4"
                                        checked={form.data.specialty_type_ids.includes(
                                            s.id,
                                        )}
                                        onChange={() => toggleSpecialty(s.id)}
                                    />
                                    {s.name}
                                </label>
                            ))}
                        </div>
                    </div>

                    <div className="space-y-3">
                        <Label>Languages spoken</Label>
                        <p className="text-sm text-muted-foreground">
                            Select every language you can speak with families.
                        </p>
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

                    <div className="space-y-3">
                        <Label>Service area</Label>
                        <div className="space-y-2">
                            {locations.map((l) => {
                                const checked = form.data.location_ids.includes(
                                    l.id,
                                );

                                return (
                                    <div
                                        key={l.id}
                                        className="flex items-center justify-between rounded-md border border-border p-3"
                                    >
                                        <label className="flex items-center gap-2 text-sm text-foreground">
                                            <input
                                                type="checkbox"
                                                className="h-4 w-4"
                                                checked={checked}
                                                onChange={() =>
                                                    toggleLocation(l.id)
                                                }
                                            />
                                            {l.name}
                                        </label>
                                        {checked && (
                                            <label className="flex items-center gap-2 text-xs text-muted-foreground">
                                                <input
                                                    type="radio"
                                                    name="preferred_location"
                                                    checked={
                                                        form.data
                                                            .preferred_location_id ===
                                                        l.id
                                                    }
                                                    onChange={() =>
                                                        form.setData(
                                                            'preferred_location_id',
                                                            l.id,
                                                        )
                                                    }
                                                />
                                                Preferred
                                            </label>
                                        )}
                                    </div>
                                );
                            })}
                        </div>
                    </div>

                    <Button type="submit" disabled={form.processing}>
                        Save Preferences
                    </Button>
                </form>
            </SettingsLayout>
        </AppLayout>
    );
}
