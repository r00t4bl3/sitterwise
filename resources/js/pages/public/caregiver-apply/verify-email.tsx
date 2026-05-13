import { useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function VerifyEmail() {
    const [step, setStep] = useState<'email' | 'otp'>('email');
    const [email, setEmail] = useState('');

    const emailForm = useForm({ email: '' });
    const otpForm = useForm({ email: '', otp: '' });

    const sendOtp = (e: React.FormEvent) => {
        e.preventDefault();
        emailForm.post('/caregiver/apply/send-otp', {
            onSuccess: () => {
                setEmail(emailForm.data.email);
                otpForm.setData('email', emailForm.data.email);
                setStep('otp');
            },
        });
    };

    const verifyOtp = (e: React.FormEvent) => {
        e.preventDefault();
        otpForm.post('/caregiver/apply/verify-otp');
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
            <div className="w-full max-w-md space-y-8">
                <div>
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        {step === 'email'
                            ? 'Verify Your Email'
                            : 'Enter Verification Code'}
                    </h2>
                    <p className="mt-2 text-center text-sm text-gray-600">
                        {step === 'email'
                            ? 'Enter your email to start the caregiver application'
                            : `We sent a code to ${email}`}
                    </p>
                </div>

                {step === 'email' ? (
                    <form onSubmit={sendOtp} className="mt-8 space-y-6">
                        <div>
                            <label
                                htmlFor="email"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                required
                                className="relative mt-1 block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-gray-900 placeholder-gray-500 focus:border-coral focus:ring-coral focus:outline-none sm:text-sm"
                                placeholder="you@example.com"
                                value={emailForm.data.email}
                                onChange={(e) =>
                                    emailForm.setData('email', e.target.value)
                                }
                            />
                            {emailForm.errors.email && (
                                <p className="mt-2 text-sm text-red-600">
                                    {emailForm.errors.email}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={emailForm.processing}
                            className="group hover:bg-coral-dark relative flex w-full justify-center rounded-md border border-transparent bg-coral px-4 py-2 text-sm font-medium text-white focus:ring-2 focus:ring-coral focus:ring-offset-2 focus:outline-none"
                        >
                            {emailForm.processing
                                ? 'Sending...'
                                : 'Send Verification Code'}
                        </button>

                        {emailForm.recentlySuccessful && (
                            <p className="text-center text-sm text-green-600">
                                Verification code sent!
                            </p>
                        )}
                    </form>
                ) : (
                    <form onSubmit={verifyOtp} className="mt-8 space-y-6">
                        <div>
                            <label
                                htmlFor="otp"
                                className="block text-sm font-medium text-gray-700"
                            >
                                Verification Code
                            </label>
                            <input
                                id="otp"
                                type="text"
                                required
                                maxLength={6}
                                className="relative mt-1 block w-full appearance-none rounded-md border border-gray-300 px-3 py-2 text-center text-2xl tracking-widest text-gray-900 placeholder-gray-500 focus:border-coral focus:ring-coral focus:outline-none sm:text-sm"
                                placeholder="000000"
                                value={otpForm.data.otp}
                                onChange={(e) =>
                                    otpForm.setData('otp', e.target.value)
                                }
                            />
                            {otpForm.errors.otp && (
                                <p className="mt-2 text-sm text-red-600">
                                    {otpForm.errors.otp}
                                </p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={otpForm.processing}
                            className="group hover:bg-coral-dark relative flex w-full justify-center rounded-md border border-transparent bg-coral px-4 py-2 text-sm font-medium text-white focus:ring-2 focus:ring-coral focus:ring-offset-2 focus:outline-none"
                        >
                            {otpForm.processing
                                ? 'Verifying...'
                                : 'Verify & Continue'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setStep('email')}
                            className="hover:text-coral-dark w-full text-sm text-coral"
                        >
                            ← Back to email
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
