import { Link } from '@inertiajs/react';

interface GuestLayoutProps {
    children: React.ReactNode;
}

export default function GuestLayout({ children }: GuestLayoutProps) {
    return (
        <div className="flex min-h-svh flex-col">
            <header className="border-b bg-background">
                <div className="container mx-auto flex h-16 items-center justify-between px-4">
                    <Link href="/" className="flex items-center gap-2">
                        <img
                            src="/sitterwise.png"
                            alt="Sitterwise"
                            className="h-10 w-auto object-contain"
                        />
                    </Link>
                    <div className="flex items-center gap-4">
                        <Link href="/login" className="btn-primary">
                            Sign In
                        </Link>
                    </div>
                </div>
            </header>
            <main className="flex-1 bg-blush">
                <div className="container mx-auto py-6">{children}</div>
            </main>
        </div>
    );
}
