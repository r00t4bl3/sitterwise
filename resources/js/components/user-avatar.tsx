import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';
import { useInitials } from '@/hooks/use-initials';

interface UserAvatarProps {
    profile_photo_url: string | null;
    profile_photo_path: string | null;
    name: string;
    className?: string;
    size?: 'sm' | 'md' | 'lg';
}

const sizeClasses = {
    sm: 'h-8 w-8',
    md: 'h-10 w-10',
    lg: 'h-16 w-16',
};

function getAvatarUrl(
    profile_photo_url: string | null,
    profile_photo_path: string | null,
): string | undefined {
    if (profile_photo_url) {
        return profile_photo_url;
    }

    if (!profile_photo_path) {
        return undefined;
    }

    if (profile_photo_path === 'avatar.jpg') {
        return '/avatar.jpg';
    }

    return `/storage/${profile_photo_path}`;
}

export function UserAvatar({
    profile_photo_url,
    profile_photo_path,
    name,
    className = '',
    size = 'md',
}: UserAvatarProps) {
    const getInitials = useInitials();
    const sizeClass = sizeClasses[size];

    return (
        <Avatar
            className={`${sizeClass} overflow-hidden rounded-full ${className}`}
        >
            <AvatarImage
                src={getAvatarUrl(profile_photo_url, profile_photo_path)}
                alt={name}
            />
            <AvatarFallback className="rounded-lg bg-amber-100 text-amber-600 dark:bg-amber-900 dark:text-amber-200">
                {getInitials(name)}
            </AvatarFallback>
        </Avatar>
    );
}
