import { Head, usePage } from '@inertiajs/react';
import { Phone } from 'lucide-react';
import { UserAvatar } from '@/components/user-avatar';

interface User {
    profile_photo_url: string | null;
    profile_photo_path: string | null;
}

interface Caregiver {
    id: number;
    first_name: string;
    last_name: string;
    phone: string | null;
    biography: string | null;
    user: User;
}

export default function CaregiverBio() {
    const { caregiver } = usePage<{ caregiver: Caregiver }>().props;

    const fullName = `${caregiver.first_name} ${caregiver.last_name}`;

    return (
        <>
            <Head title={`${fullName} - Bio`} />
            <div className="flex min-h-screen flex-col items-center bg-[#FDFDFC] p-6 text-[#1b1b18] lg:justify-center lg:p-8 dark:bg-[#0a0a0a] dark:text-[#EDEDEC]">
                <div className="mb-6">
                    <img
                        src="/sitterwise.png"
                        alt="Sitterwise"
                        className="h-12 w-auto"
                    />
                </div>
                <div className="w-full max-w-md rounded-lg bg-white p-8 shadow-[inset_0px_0px_0px_1px_rgba(26,26,0,0.16)] dark:bg-[#161615] dark:shadow-[inset_0px_0px_0px_1px_#fffaed2d]">
                    <div className="flex flex-col items-center text-center">
                        <div className="mb-4">
                            <UserAvatar
                                profile_photo_url={
                                    caregiver.user.profile_photo_url
                                }
                                profile_photo_path={
                                    caregiver.user.profile_photo_path
                                }
                                name={fullName}
                                size="lg"
                                className="h-24 w-24"
                            />
                        </div>

                        <h1 className="mb-2 text-2xl font-semibold">
                            {fullName}
                        </h1>

                        {caregiver.phone && (
                            <div className="mb-4 flex items-center gap-2 text-sm text-[#706f6c] dark:text-[#A1A09A]">
                                <Phone className="h-4 w-4" />
                                <a
                                    href={`tel:${caregiver.phone}`}
                                    className="hover:text-[#1b1b18] dark:hover:text-[#EDEDEC]"
                                >
                                    {caregiver.phone}
                                </a>
                            </div>
                        )}

                        {caregiver.biography && (
                            <div className="mt-4 w-full text-left">
                                <h2 className="mb-2 text-sm font-medium text-[#706f6c] dark:text-[#A1A09A]">
                                    About
                                </h2>
                                <p className="text-sm leading-relaxed whitespace-pre-wrap">
                                    {caregiver.biography}
                                </p>
                            </div>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}
