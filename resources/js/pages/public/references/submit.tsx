import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

const yearsKnownOptions = [
    { value: '<1', label: 'Less than 1 year' },
    { value: '1-3', label: '1–3 years' },
    { value: '3-5', label: '3–5 years' },
    { value: '5-10', label: '5–10 years' },
    { value: '10+', label: '10+ years' },
];

interface SubmitProps {
    token: string;
    referenceName: string;
    applicantName: string;
    defaults: {
        relationship: string | null;
        years_known: string | null;
    };
}

export default function Submit({
    token,
    referenceName,
    applicantName,
    defaults,
}: SubmitProps) {
    const form = useForm({
        relationship: defaults.relationship ?? '',
        years_known: defaults.years_known ?? '',
        rating: '',
        feedback: '',
    });

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        form.post(`/references/${token}`);
    };

    return (
        <div className="flex min-h-screen items-center justify-center bg-gray-50 px-4 py-12 sm:px-6 lg:px-8">
            <div className="w-full max-w-lg">
                <div className="mb-8 text-center">
                    <img
                        src="/sitterwise.png"
                        alt="Sitterwise"
                        className="mx-auto mb-6 h-10 w-auto"
                    />
                    <h1 className="text-2xl font-bold text-gray-900">
                        Reference for {applicantName}
                    </h1>
                    <p className="mt-2 text-sm text-gray-600">
                        Thank you for providing a reference for {applicantName}.
                        Your feedback helps us ensure we&apos;re bringing the
                        best caregivers onto our team.
                    </p>
                </div>

                <div className="rounded-lg bg-white p-8 shadow">
                    <p className="mb-6 text-sm text-gray-500">
                        <strong>Reference:</strong> {referenceName}
                    </p>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        <div className="space-y-2">
                            <Label htmlFor="relationship">
                                Relationship to {applicantName}
                            </Label>
                            <input
                                id="relationship"
                                type="text"
                                className="flex h-11 w-full min-w-0 rounded-[3px] border border-input bg-white px-3 py-[9px] text-sm shadow-xs transition-[color,box-shadow] outline-none selection:bg-primary selection:text-primary-foreground file:inline-flex file:h-7 file:border-0 file:bg-transparent file:text-sm file:font-medium file:text-foreground placeholder:text-muted-foreground focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:pointer-events-none disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20 dark:aria-invalid:ring-destructive/40"
                                value={form.data.relationship}
                                onChange={(e) =>
                                    form.setData('relationship', e.target.value)
                                }
                                placeholder="e.g., Former Employer, Friend, Colleague"
                            />
                            {form.errors.relationship && (
                                <p className="text-sm text-red-500">
                                    {form.errors.relationship}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="years_known">
                                How long have you known {applicantName}?
                            </Label>
                            <Select
                                value={form.data.years_known || undefined}
                                onValueChange={(value) =>
                                    form.setData('years_known', value)
                                }
                            >
                                <SelectTrigger id="years_known">
                                    <SelectValue placeholder="Select years known" />
                                </SelectTrigger>
                                <SelectContent>
                                    {yearsKnownOptions.map((option) => (
                                        <SelectItem
                                            key={option.value}
                                            value={option.value}
                                        >
                                            {option.label}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.years_known && (
                                <p className="text-sm text-red-500">
                                    {form.errors.years_known}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="rating">Rating</Label>
                            <div className="flex gap-1">
                                {[1, 2, 3, 4, 5].map((star) => (
                                    <button
                                        key={star}
                                        type="button"
                                        onClick={() =>
                                            form.setData('rating', String(star))
                                        }
                                        className="cursor-pointer"
                                    >
                                        <svg
                                            className={`h-8 w-8 transition-colors ${
                                                parseInt(form.data.rating) >=
                                                star
                                                    ? 'fill-amber-400 text-amber-400'
                                                    : 'text-gray-300 hover:text-amber-300'
                                            }`}
                                            fill="currentColor"
                                            viewBox="0 0 24 24"
                                        >
                                            <path d="M12 2l3.09 6.26L22 9.27l-5 4.87 1.18 6.88L12 17.77l-6.18 3.25L7 14.14 2 9.27l6.91-1.01L12 2z" />
                                        </svg>
                                    </button>
                                ))}
                            </div>
                            {form.errors.rating && (
                                <p className="text-sm text-red-500">
                                    {form.errors.rating}
                                </p>
                            )}
                            <p className="text-xs text-gray-400">
                                Click a star to rate
                            </p>
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="feedback">Reference Feedback</Label>
                            <Textarea
                                id="feedback"
                                rows={5}
                                value={form.data.feedback}
                                onChange={(e) =>
                                    form.setData('feedback', e.target.value)
                                }
                                placeholder="Please describe your experience working with the applicant, their strengths, and any areas for growth..."
                            />
                            {form.errors.feedback && (
                                <p className="text-sm text-red-500">
                                    {form.errors.feedback}
                                </p>
                            )}
                        </div>

                        <Button
                            type="submit"
                            disabled={form.processing}
                            className="w-full"
                        >
                            {form.processing
                                ? 'Submitting...'
                                : 'Submit Reference'}
                        </Button>
                    </form>
                </div>
            </div>
        </div>
    );
}
