import { Head } from '@inertiajs/react';

interface SubmittedProps {
    referenceName: string;
    applicantName: string;
}

export default function Submitted({
    referenceName,
    applicantName,
}: SubmittedProps) {
    return (
        <>
            <Head title="Reference Submitted - Sitterwise" />
            <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
                <div className="w-full max-w-md text-center">
                    <div className="mb-8">
                        <img
                            src="/sitterwise.png"
                            alt="Sitterwise"
                            className="mx-auto mb-6 h-10 w-auto"
                        />
                    </div>
                    <div className="rounded-lg bg-white p-8 shadow">
                        <div className="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                            <svg
                                className="h-8 w-8 text-green-600"
                                fill="none"
                                viewBox="0 0 24 24"
                                stroke="currentColor"
                            >
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth={2}
                                    d="M5 13l4 4L19 7"
                                />
                            </svg>
                        </div>
                        <h1 className="text-2xl font-bold text-gray-900">
                            Reference Submitted
                        </h1>
                        <p className="mt-2 text-sm text-gray-600">
                            Thank you, {referenceName}. Your reference for{' '}
                            <strong>{applicantName}</strong> has been received.
                        </p>
                        <p className="mt-4 text-sm text-gray-500">
                            Your feedback helps us maintain the highest quality
                            of caregivers at Sitterwise.
                        </p>
                    </div>
                </div>
            </div>
        </>
    );
}
