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
        <div className="min-h-screen flex items-center justify-center bg-gray-50 py-12 px-4 sm:px-6 lg:px-8">
            <div className="max-w-md w-full space-y-8">
                <div>
                    <h2 className="mt-6 text-center text-3xl font-extrabold text-gray-900">
                        {step === 'email' ? 'Verify Your Email' : 'Enter Verification Code'}
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
                            <label htmlFor="email" className="block text-sm font-medium text-gray-700">
                                Email address
                            </label>
                            <input
                                id="email"
                                type="email"
                                required
                                className="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-coral focus:border-coral sm:text-sm"
                                placeholder="you@example.com"
                                value={emailForm.data.email}
                                onChange={(e) => emailForm.setData('email', e.target.value)}
                            />
                            {emailForm.errors.email && (
                                <p className="mt-2 text-sm text-red-600">{emailForm.errors.email}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={emailForm.processing}
                            className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-coral hover:bg-coral-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-coral"
                        >
                            {emailForm.processing ? 'Sending...' : 'Send Verification Code'}
                        </button>

                        {emailForm.recentlySuccessful && (
                            <p className="text-sm text-green-600 text-center">Verification code sent!</p>
                        )}
                    </form>
                ) : (
                    <form onSubmit={verifyOtp} className="mt-8 space-y-6">
                        <div>
                            <label htmlFor="otp" className="block text-sm font-medium text-gray-700">
                                Verification Code
                            </label>
                            <input
                                id="otp"
                                type="text"
                                required
                                maxLength={6}
                                className="mt-1 appearance-none rounded-md relative block w-full px-3 py-2 border border-gray-300 placeholder-gray-500 text-gray-900 focus:outline-none focus:ring-coral focus:border-coral sm:text-sm text-center text-2xl tracking-widest"
                                placeholder="000000"
                                value={otpForm.data.otp}
                                onChange={(e) => otpForm.setData('otp', e.target.value)}
                            />
                            {otpForm.errors.otp && (
                                <p className="mt-2 text-sm text-red-600">{otpForm.errors.otp}</p>
                            )}
                        </div>

                        <button
                            type="submit"
                            disabled={otpForm.processing}
                            className="group relative w-full flex justify-center py-2 px-4 border border-transparent text-sm font-medium rounded-md text-white bg-coral hover:bg-coral-dark focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-coral"
                        >
                            {otpForm.processing ? 'Verifying...' : 'Verify & Continue'}
                        </button>

                        <button
                            type="button"
                            onClick={() => setStep('email')}
                            className="w-full text-sm text-coral hover:text-coral-dark"
                        >
                            ← Back to email
                        </button>
                    </form>
                )}
            </div>
        </div>
    );
}
