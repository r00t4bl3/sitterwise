import { Link } from '@inertiajs/react';
import AppLogoIcon from '@/components/app-logo-icon';
import type { BreadcrumbItem } from '@/types';

interface GuestLayoutProps {
    children: React.ReactNode;
    breadcrumbs?: BreadcrumbItem[];
}

export default function GuestLayout({
    children,
    breadcrumbs = [],
}: GuestLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col">
            <header className="border-b bg-background">
                <div className="container mx-auto flex h-16 items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2">
                        <AppLogoIcon className="h-8 w-8 fill-current text-foreground" />
                        <span className="text-lg font-semibold">Sitterwise</span>
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link
                            href="/login"
                            className="text-sm text-muted-foreground hover:text-foreground"
                        >
                            Sign In
                        </Link>
                    </div>
                </div>
            </header>
            <main className="flex-1">
                <div className="container mx-auto py-6">{children}</div>
            </main>
        </div>
    );
}