import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';

interface ErrorPageProps {
    status: number;
}

const COPY: Record<number, { title: string; description: string }> = {
    503: {
        title: 'Service Unavailable',
        description: "We're doing some maintenance. Please check back soon.",
    },
    500: {
        title: 'Server Error',
        description: 'Whoops, something went wrong on our servers.',
    },
    404: {
        title: 'Page Not Found',
        description: "Sorry, the page you're looking for could not be found.",
    },
    403: {
        title: 'Forbidden',
        description: 'Sorry, you are not authorized to access this page.',
    },
};

export default function ErrorPage({ status }: ErrorPageProps) {
    const { title, description } = COPY[status] ?? {
        title: 'Something went wrong',
        description: 'An unexpected error occurred. Please try again.',
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-background px-4 py-12">
            <Head title={title} />
            <div className="w-full max-w-md space-y-6 text-center">
                <p className="text-6xl font-bold text-muted-foreground">
                    {status}
                </p>
                <h1 className="text-2xl font-bold text-foreground">{title}</h1>
                <p className="text-muted-foreground">{description}</p>
                <Button asChild>
                    <Link href="/">Back to Home</Link>
                </Button>
            </div>
        </div>
    );
}
