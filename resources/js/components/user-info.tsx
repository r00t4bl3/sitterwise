import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';
import type { User } from '@/types';

function getAvatarUrl(user: User | null): string | undefined {
    if (!user || !user.profile_photo_path) {
        console.log('User', user);
        console.log('No profile photo path provided, using default avatar.');
        return undefined;
    }
    if (user.profile_photo_path === 'avatar.jpg') {
        console.log('Profile photo path is avatar.jpg, using default avatar.');
        return '/avatar.jpg';
    }
    console.log(`Profile photo path provided: ${user.profile_photo_path}, constructing URL.`);
    return `/storage/${user.profile_photo_path}`;
}

export function UserInfo({
    user,
    showEmail = false,
}: {
    user: User;
    showEmail?: boolean;
}) {
    const getInitials = useInitials();

    return (
        <>
            <Avatar className="h-8 w-8 overflow-hidden rounded-full">
                <AvatarImage
                    src={getAvatarUrl(user)}
                    alt={user.name}
                />
                <AvatarFallback className="rounded-lg bg-neutral-200 text-black dark:bg-neutral-700 dark:text-white">
                    {getInitials(user.name)}
                </AvatarFallback>
            </Avatar>
            <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                {showEmail && (
                    <span className="truncate text-xs text-muted-foreground">
                        {user.email}
                    </span>
                )}
            </div>
        </>
    );
}
