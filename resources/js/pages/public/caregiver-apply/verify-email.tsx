import { useForm } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

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
                            <Input
                                id="email"
                                type="email"
                                required
                                className="w-full"
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

                        <Button
                            type="submit"
                            disabled={emailForm.processing}
                            className="w-full"
                        >
                            {emailForm.processing
                                ? 'Sending...'
                                : 'Send Verification Code'}
                        </Button>

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
                            <Input
                                id="otp"
                                type="text"
                                required
                                maxLength={6}
                                className="w-full text-center tracking-widest text-lg"
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

                        <Button
                            type="submit"
                            disabled={otpForm.processing}
                            className="w-full"
                        >
                            {otpForm.processing
                                ? 'Verifying...'
                                : 'Verify & Continue'}
                        </Button>

                        <Button
                            type="button"
                            onClick={() => setStep('email')}
                            variant="ghost"
                            className="w-full"
                        >
                            ← Back to email
                        </Button>
                    </form>
                )}
            </div>
        </div>
    );
}
