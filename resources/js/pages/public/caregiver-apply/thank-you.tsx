import { Link } from '@inertiajs/react';

export default function ThankYou() {
    return (
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-md w-full space-y-8 text-center">
                <div>
                    <div className="mx-auto flex items-center justify-center h-16 w-16 rounded-full bg-green-100 mb-4">
                        <svg className="h-8 w-8 text-green-600" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 className="text-3xl font-extrabold text-gray-900">
                        Application Submitted!
                    </h2>
                    <p className="mt-2 text-sm text-gray-600">
                        Thank you for applying to become a Sitterwise caregiver. We'll review your application and contact you soon.
                    </p>
                </div>

                <div className="bg-white shadow rounded-lg p-6">
                    <h3 className="text-lg font-medium text-gray-900 mb-4">What's Next?</h3>
                    <ul className="text-left space-y-3 text-sm text-gray-600">
                        <li className="flex items-start">
                            <span className="flex-shrink-0 h-5 w-5 text-coral mr-2">✓</span>
                            We'll verify your email and review your application
                        </li>
                        <li className="flex items-start">
                            <span className="flex-shrink-0 h-5 w-5 text-coral mr-2">✓</span>
                            Our team will contact your references
                        </li>
                        <li className="flex items-start">
                            <span className="flex-shrink-0 h-5 w-5 text-coral mr-2">✓</span>
                            You'll hear from us within 3-5 business days
                        </li>
                    </ul>
                </div>

                <Link
                    href="/"
                    className="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md text-white bg-coral hover:bg-coral-dark"
                >
                    Return to Home
                </Link>
            </div>
        </div>
    );
}
