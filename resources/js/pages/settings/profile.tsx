import { Transition } from '@headlessui/react';
import { Form, Head, Link, useForm, usePage } from '@inertiajs/react';
import type { ChangeEvent } from 'react';
import { useEffect } from 'react';
import ProfileController from '@/actions/App/Http/Controllers/Settings/ProfileController';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { UserAvatar } from '@/components/user-avatar';
import AppLayout from '@/layouts/app-layout';
import SettingsLayout from '@/layouts/settings/layout';
import { edit } from '@/routes/profile';
import { send } from '@/routes/verification';
import type { BreadcrumbItem } from '@/types';

const breadcrumbs: BreadcrumbItem[] = [
    {
        title: 'Profile settings',
        href: edit(),
    },
];

export default function Profile({
    mustVerifyEmail,
    status,
    firstName,
    lastName,
}: {
    mustVerifyEmail: boolean;
    status?: string;
    firstName: string;
    lastName: string;
}) {
    const { auth } = usePage().props;

    // Self-service profile photo is limited to admin/superadmin for now.
    const canUploadPhoto =
        auth.user.role === 'admin' || auth.user.role === 'super_admin';

    const photoForm = useForm<{ profile_photo: File | null }>({
        profile_photo: null,
    });

    // Upload as soon as a file is picked; the success redirect refreshes the
    // shared auth.user, so the avatar (here and in the header) updates itself.
    useEffect(() => {
        if (!photoForm.data.profile_photo) {
            return;
        }

        photoForm.post('/settings/profile/photo', {
            preserveScroll: true,
            onFinish: () => photoForm.reset('profile_photo'),
        });
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [photoForm.data.profile_photo]);

    const handlePhotoChange = (e: ChangeEvent<HTMLInputElement>) => {
        photoForm.setData('profile_photo', e.target.files?.[0] ?? null);
        e.target.value = '';
    };

    return (
        <AppLayout breadcrumbs={breadcrumbs}>
            <Head title="Profile settings" />

            <h1 className="sr-only">Profile settings</h1>

            <SettingsLayout>
                {canUploadPhoto && (
                    <div className="mb-8 space-y-4">
                        <div>
                            <h2 className="text-xl font-bold text-foreground">
                                Profile photo
                            </h2>
                            <p className="mt-1 text-sm text-muted-foreground">
                                Upload a photo — it appears next to your name
                                across the app
                            </p>
                        </div>
                        <div className="flex items-center gap-4">
                            <div className="group relative">
                                <UserAvatar
                                    name={auth.user.name}
                                    profile_photo_url={
                                        auth.user.profile_photo_url
                                    }
                                    profile_photo_path={
                                        auth.user.profile_photo_path
                                    }
                                    size="lg"
                                />
                                {photoForm.processing && (
                                    <div className="absolute inset-0 flex items-center justify-center rounded-full bg-black/50">
                                        <Spinner className="h-5 w-5 text-white" />
                                    </div>
                                )}
                                <Label className="absolute inset-0 flex cursor-pointer items-center justify-center rounded-full bg-black/50 text-[10px] font-medium text-white opacity-0 transition group-hover:opacity-100">
                                    <Input
                                        type="file"
                                        accept="image/*"
                                        className="hidden"
                                        disabled={photoForm.processing}
                                        onChange={handlePhotoChange}
                                    />
                                    Change
                                </Label>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                JPG, PNG, GIF or WebP. Max 1&nbsp;MB.
                            </p>
                        </div>
                        <InputError message={photoForm.errors.profile_photo} />
                    </div>
                )}

                <div className="space-y-6">
                    <div>
                        <h2 className="text-xl font-bold text-foreground">
                            Profile information
                        </h2>
                        <p className="mt-1 text-sm text-muted-foreground">
                            Update your name and email address
                        </p>
                    </div>

                    <Form
                        {...ProfileController.update.form()}
                        options={{
                            preserveScroll: true,
                        }}
                        className="space-y-6"
                    >
                        {({ processing, recentlySuccessful, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="first_name">
                                        First Name
                                    </Label>

                                    <Input
                                        id="first_name"
                                        className="mt-1 block w-full"
                                        defaultValue={firstName}
                                        name="first_name"
                                        required
                                        autoComplete="given-name"
                                        placeholder="First name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.first_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="last_name">Last Name</Label>

                                    <Input
                                        id="last_name"
                                        className="mt-1 block w-full"
                                        defaultValue={lastName}
                                        name="last_name"
                                        required
                                        autoComplete="family-name"
                                        placeholder="Last name"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.last_name}
                                    />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="email">Email address</Label>

                                    <Input
                                        id="email"
                                        type="email"
                                        className="mt-1 block w-full"
                                        defaultValue={auth.user.email}
                                        name="email"
                                        required
                                        autoComplete="username"
                                        placeholder="Email address"
                                    />

                                    <InputError
                                        className="mt-2"
                                        message={errors.email}
                                    />
                                </div>

                                {mustVerifyEmail &&
                                    auth.user.email_verified_at === null && (
                                        <div>
                                            <p className="-mt-4 text-sm text-muted-foreground">
                                                Your email address is
                                                unverified.{' '}
                                                <Link
                                                    href={send()}
                                                    as="button"
                                                    className="text-foreground underline decoration-neutral-300 underline-offset-4 transition-colors duration-300 ease-out hover:decoration-current! dark:decoration-neutral-500"
                                                >
                                                    Click here to resend the
                                                    verification email.
                                                </Link>
                                            </p>

                                            {status ===
                                                'verification-link-sent' && (
                                                <div className="mt-2 text-sm font-medium text-green-600">
                                                    A new verification link has
                                                    been sent to your email
                                                    address.
                                                </div>
                                            )}
                                        </div>
                                    )}

                                <div className="flex items-center gap-4">
                                    <Button
                                        disabled={processing}
                                        data-test="update-profile-button"
                                    >
                                        Save
                                    </Button>

                                    <Transition
                                        show={recentlySuccessful}
                                        enter="transition ease-in-out"
                                        enterFrom="opacity-0"
                                        leave="transition ease-in-out"
                                        leaveTo="opacity-0"
                                    >
                                        <p className="text-sm text-neutral-600">
                                            Saved
                                        </p>
                                    </Transition>
                                </div>
                            </>
                        )}
                    </Form>
                </div>

                {/* <DeleteUser /> */}
            </SettingsLayout>
        </AppLayout>
    );
}
