import { Link } from '@inertiajs/react';

export default function ThankYou() {
    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
            <div className="w-full max-w-md space-y-8 text-center">
                <div>
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
                    <h2 className="text-3xl font-extrabold text-gray-900">
                        Application Submitted!
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Thank you for applying to become a Sitterwise caregiver.
                        We'll review your application and contact you soon.
                    </p>
                </div>

                <div className="rounded-lg bg-white p-6 shadow">
                    <h3 className="mb-4 text-lg font-medium text-gray-900">
                        What's Next?
                    </h3>
                    <ul className="space-y-3 text-left text-sm text-gray-600">
                        <li className="flex items-start">
                            <span className="mr-2 h-5 w-5 flex-shrink-0 text-coral">
                                ✓
                            </span>
                            We'll verify your email and review your application
                        </li>
                        <li className="flex items-start">
                            <span className="mr-2 h-5 w-5 flex-shrink-0 text-coral">
                                ✓
                            </span>
                            Our team will contact your references
                        </li>
                        <li className="flex items-start">
                            <span className="mr-2 h-5 w-5 flex-shrink-0 text-coral">
                                ✓
                            </span>
                            You'll hear from us within 3-5 business days
                        </li>
                    </ul>
                </div>

                <Link
                    href="/"
                    className="hover:bg-coral-dark inline-flex items-center rounded-md border border-transparent bg-coral px-4 py-2 text-sm font-medium text-white"
                >
                    Return to Home
                </Link>
            </div>
        </div>
    );
}
