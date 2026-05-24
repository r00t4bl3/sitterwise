import { useState } from 'react';
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

const ratingCategories = [
    { key: 'rating_reliability', label: 'Reliability & Dependability' },
    { key: 'rating_trustworthiness', label: 'Trustworthiness' },
    { key: 'rating_maturity', label: 'Maturity' },
    { key: 'rating_communication', label: 'Communication' },
    { key: 'rating_warmth', label: 'Warmth & Compassion' },
    { key: 'rating_overall_recommendation', label: 'Overall Recommendation' },
] as const;

interface SubmitProps {
    token: string;
    referenceName: string;
    applicantName: string;
    defaults: {
        relationship: string | null;
        years_known: string | null;
        rating_reliability: number | null;
        rating_trustworthiness: number | null;
        rating_maturity: number | null;
        rating_communication: number | null;
        rating_warmth: number | null;
        rating_overall_recommendation: number | null;
        strengths: string | null;
        concerns: string | null;
        additional_comments: string | null;
    };
}

function StarRating({
    value,
    onChange,
}: {
    value: string;
    onChange: (val: string) => void;
}) {
    const [hovered, setHovered] = useState(0);

    return (
        <div className="flex gap-1">
            {[1, 2, 3, 4, 5].map((star) => (
                <button
                    key={star}
                    type="button"
                    onClick={() => onChange(String(star))}
                    onMouseEnter={() => setHovered(star)}
                    onMouseLeave={() => setHovered(0)}
                    className="cursor-pointer"
                >
                    <svg
                        className={`h-7 w-7 transition-colors ${
                            (hovered || parseInt(value)) >= star
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
    );
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
        rating_reliability: defaults.rating_reliability?.toString() ?? '',
        rating_trustworthiness: defaults.rating_trustworthiness?.toString() ?? '',
        rating_maturity: defaults.rating_maturity?.toString() ?? '',
        rating_communication: defaults.rating_communication?.toString() ?? '',
        rating_warmth: defaults.rating_warmth?.toString() ?? '',
        rating_overall_recommendation: defaults.rating_overall_recommendation?.toString() ?? '',
        strengths: defaults.strengths ?? '',
        concerns: defaults.concerns ?? '',
        additional_comments: defaults.additional_comments ?? '',
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

                        <div className="space-y-4">
                            <div>
                                <Label className="text-base">
                                    Ratings
                                </Label>
                                <p className="text-xs text-gray-400">
                                    Rate {applicantName} in each area
                                </p>
                            </div>
                            {ratingCategories.map(({ key, label }) => (
                                <div key={key} className="space-y-1">
                                    <Label
                                        htmlFor={key}
                                        className="text-sm font-normal"
                                    >
                                        {label}
                                    </Label>
                                    <StarRating
                                        value={form.data[key]}
                                        onChange={(val) =>
                                            form.setData(key, val)
                                        }
                                    />
                                    {form.errors[key] && (
                                        <p className="text-sm text-red-500">
                                            {form.errors[key]}
                                        </p>
                                    )}
                                </div>
                            ))}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="strengths">
                                What are {applicantName}&apos;s greatest
                                strengths?
                            </Label>
                            <Textarea
                                id="strengths"
                                rows={4}
                                value={form.data.strengths}
                                onChange={(e) =>
                                    form.setData('strengths', e.target.value)
                                }
                                placeholder="Describe the applicant's strengths, skills, and qualities that make them a great caregiver..."
                            />
                            {form.errors.strengths && (
                                <p className="text-sm text-red-500">
                                    {form.errors.strengths}
                                </p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="concerns">
                                Are there any areas of concern?
                                <span className="ml-1 text-xs text-gray-400">
                                    (optional)
                                </span>
                            </Label>
                            <Textarea
                                id="concerns"
                                rows={3}
                                value={form.data.concerns}
                                onChange={(e) =>
                                    form.setData('concerns', e.target.value)
                                }
                                placeholder="Any concerns about the applicant working as a caregiver..."
                            />
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="additional_comments">
                                Anything else you&apos;d like to share?
                                <span className="ml-1 text-xs text-gray-400">
                                    (optional)
                                </span>
                            </Label>
                            <Textarea
                                id="additional_comments"
                                rows={3}
                                value={form.data.additional_comments}
                                onChange={(e) =>
                                    form.setData(
                                        'additional_comments',
                                        e.target.value,
                                    )
                                }
                                placeholder="Any additional feedback..."
                            />
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
