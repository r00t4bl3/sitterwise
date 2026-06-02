import { useForm } from '@inertiajs/react';
import { AlertCircle, Trash2 } from 'lucide-react';
import { useState, useEffect } from 'react';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { DatePicker } from '@/components/ui/date-picker';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import { RadioGroup, RadioGroupItem } from '@/components/ui/radio-group';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';

interface Experience {
    start_date: string;
    end_date: string;
    present: boolean;
    role: string;
    organization: string;
    description: string;
    ages_served: string[];
}

interface Reference {
    first_name: string;
    last_name: string;
    email: string;
    phone: string;
    relationship: string;
    years_known: string;
}

const positionLabels: Record<string, string> = {
    babysitting: 'On-Call Babysitting',
    petsitting: 'On-Call Petsitting',
    group_events: 'Group Events',
};

const locationLabels: Record<string, string> = {
    north_county: 'North County',
    south_east_county: 'South / East County',
    flexible: 'Flexible',
};

const locationDescriptions: Record<string, string> = {
    north_county:
        'Rancho Santa Fe, Del Mar, Carlsbad, Encinitas, Escondido, San Marcos, Vista',
    south_east_county:
        'Coronado, Downtown, La Jolla, Mission Valley, Chula Vista, El Cajon, La Mesa',
    flexible:
        'Would you sometimes be willing to work at the other location group if we were in a pinch?',
};

const ageLabels: Record<string, string> = {
    infant: 'Infant (0-1)',
    toddler: 'Toddler (1-3)',
    preschool: 'Preschool (3-5)',
    school_age: 'School age (5-12)',
    teen: 'Teen (13+)',
};

const ageGroupDescriptions: Record<string, string> = {
    babies: 'I am comfortable changing diapers and fixing bottles. I am familiar with safe sleep practices. I can remain calm when a baby is crying and know how to soothe a fussy baby. I wash my hands upon entering the room.',
    toddlers:
        'I am aware of dangerous situations — stairs, sharp corners. I am patient with typical toddler meltdowns and can gently take control of a situation. I know how to distract a crying child when parents leave.',
    preschool:
        "I love getting on the floor and playing actively with small children. I enjoy reading books and teaching little ones new things. I'm okay with an occasional potty training accident.",
    school_age:
        'I enjoy older kids and bring plenty of crafts and board games! I know to keep conversation light and avoid controversial topics and questionable media. I like to keep big kids active and engaged.',
};

const months = [
    { value: '01', label: 'Jan' },
    { value: '02', label: 'Feb' },
    { value: '03', label: 'Mar' },
    { value: '04', label: 'Apr' },
    { value: '05', label: 'May' },
    { value: '06', label: 'Jun' },
    { value: '07', label: 'Jul' },
    { value: '08', label: 'Aug' },
    { value: '09', label: 'Sep' },
    { value: '10', label: 'Oct' },
    { value: '11', label: 'Nov' },
    { value: '12', label: 'Dec' },
];

const currentYear = new Date().getFullYear();
const years = Array.from({ length: 31 }, (_, i) =>
    String(currentYear - 30 + i),
);

const startYear = 1960;
const graduationYears = Array.from(
    { length: currentYear - startYear + 1 },
    (_, i) => String(startYear + i),
);

export default function Wizard({ verifiedEmail }: { verifiedEmail?: string }) {
    const [currentStep, setCurrentStep] = useState<number>(() => {
        const saved = sessionStorage.getItem('caregiver_application_draft');

        if (saved) {
            const draft = JSON.parse(saved);

            if (draft.step && draft.data) {
                return draft.step;
            }
        }

        return 1;
    });

    const today = new Date().toLocaleDateString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
    });

    const defaultFormData = {
        sponsor: {
            first_name: '',
            last_name: '',
            email: '',
            phone: '',
            relationship: '',
        },
        personal: {
            first_name: '',
            last_name: '',
            address_line1: '',
            address_line2: '',
            address_city: '',
            address_state: '',
            address_zip: '',
            phone: '',
            email: '',
            dob: '',
            photo: null as File | null,
        },
        position: {
            babysitting: false,
            petsitting: false,
            group_events: false,
        },
        availability: {
            weekday_mornings: false,
            weekday_afternoons: false,
            weekday_evenings: false,
            weekends: false,
            overnights: false,
            notes: '',
        },
        education: {
            level: 'bachelor',
            college: '',
            graduation_year: '',
            degree: '',
            high_school_name: '',
            high_school_graduation_year: '',
        },
        employment_status: '',
        current_employer: '',
        experiences: [
            {
                start_date: '',
                end_date: '',
                present: false,
                role: '',
                organization: '',
                description: '',
                ages_served: [],
            },
        ] as Experience[],
        certifications: [],
        smokes: '',
        alcohol: '',
        substance_abuse: '',
        limitations: '',
        allergic_to_pets: '',
        allergic_to_what: '',
        visible_tattoos: '',
        tattoo_description: '',
        authorized_to_work: '',
        reliable_vehicle: '',
        cpr_certified: '',
        cpr_expiration: '',
        cpr_card: null as File | null,
        trustline_certified: '',
        trustline_upload: null as File | null,
        languages: '',
        has_children: '',
        children_ages: '',
        qualifications: {
            special_needs: false,
            companion_care: false,
            sick_care: false,
            work_from_home: false,
            driving: false,
            dogsitting: false,
            swimming: false,
            overnight_care: false,
        },
        things_i_bring: '',
        bio: '',
        interests: '',

        references: [
            {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                relationship: '',
                years_known: '',
            },
            {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                relationship: '',
                years_known: '',
            },
            {
                first_name: '',
                last_name: '',
                email: '',
                phone: '',
                relationship: '',
                years_known: '',
            },
        ] as Reference[],
        location: {
            north_county: false,
            south_east_county: false,
            flexible: false,
        },
        age_groups: {
            babies: false,
            toddlers: false,
            preschool: false,
            school_age: false,
        },
        terms: { agree: false },
        verification: { signature: '', agree: false },
        agreement: { signature: '', agree: false },
    };

    const form = useForm(defaultFormData);

    const expectedSignature =
        `${form.data.personal.first_name} ${form.data.personal.last_name}`.trim();

    // Deep merge draft data with defaults to fill missing fields
    const deepMerge = (
        target: Record<string, unknown>,
        source: Record<string, unknown>,
    ): Record<string, unknown> => {
        const result = { ...target };

        for (const key of Object.keys(result)) {
            if (key in source && source[key] != null) {
                if (
                    typeof result[key] === 'object' &&
                    result[key] !== null &&
                    !Array.isArray(result[key]) &&
                    !(result[key] instanceof File)
                ) {
                    result[key] = deepMerge(
                        result[key] as Record<string, unknown>,
                        source[key] as Record<string, unknown>,
                    );
                } else {
                    result[key] = source[key];
                }
            }
        }

        return result;
    };

    // Load draft form data from sessionStorage
    useEffect(() => {
        const saved = sessionStorage.getItem('caregiver_application_draft');

        if (saved) {
            const draft = JSON.parse(saved);

            if (draft.step && draft.data) {
                form.setData(deepMerge(defaultFormData, draft.data));
            }
        }
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, []);

    // Sync employment status with first experience's "present" checkbox
    useEffect(() => {
        if (verifiedEmail && !form.data.personal.email) {
            form.setData('personal', {
                ...form.data.personal,
                email: verifiedEmail,
            });
        }
    }, [verifiedEmail]);

    useEffect(() => {
        const isEmployed =
            form.data.employment_status === 'full_time' ||
            form.data.employment_status === 'part_time';

        const newExp = [...form.data.experiences];
        newExp[0] = {
            ...newExp[0],
            present: isEmployed,
            end_date: isEmployed ? '' : newExp[0].end_date,
        };
        form.setData('experiences', newExp);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [form.data.employment_status]);

    // Save draft to sessionStorage and server on step change
    const saveDraft = () => {
        sessionStorage.setItem(
            'caregiver_application_draft',
            JSON.stringify({
                step: currentStep,
                data: form.data,
            }),
        );

        fetch('/caregiver/apply/save-progress', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': (window as any).csrfToken || '' },
            body: JSON.stringify({ step: currentStep }),
        }).catch(() => {});
    };

    const validateStep = (step: number): boolean => {
        form.clearErrors();
        const data = form.data;
        let hasError = false;

        if (step === 1) {
            if (!data.sponsor.first_name?.trim()) {
                form.setError('sponsor.first_name', 'Sponsor first name is required.');
                hasError = true;
            }
            if (!data.sponsor.last_name?.trim()) {
                form.setError('sponsor.last_name', 'Sponsor last name is required.');
                hasError = true;
            }
            if (!data.sponsor.email?.trim()) {
                form.setError('sponsor.email', 'Sponsor email is required.');
                hasError = true;
            }
            if (!data.personal.first_name?.trim()) {
                form.setError('personal.first_name', 'First name is required.');
                hasError = true;
            }
            if (!data.personal.last_name?.trim()) {
                form.setError('personal.last_name', 'Last name is required.');
                hasError = true;
            }
            if (!data.personal.phone?.trim()) {
                form.setError('personal.phone', 'Phone number is required.');
                hasError = true;
            }
            if (!data.personal.email?.trim()) {
                form.setError('personal.email', 'Email is required.');
                hasError = true;
            }
            if (!data.personal.dob) {
                form.setError('personal.dob', 'Date of birth is required.');
                hasError = true;
            }
            if (!data.personal.address_line1?.trim()) {
                form.setError('personal.address_line1', 'Address is required.');
                hasError = true;
            }
        }

        if (step === 2) {
            if (!data.position.babysitting && !data.position.petsitting && !data.position.group_events) {
                form.setError('position', 'Please select at least one position.');
                hasError = true;
            }
        }

        if (step === 3) {
            if (!data.employment_status) {
                form.setError('employment_status', 'Employment status is required.');
                hasError = true;
            }
            if ((data.employment_status === 'full_time' || data.employment_status === 'part_time') && !data.current_employer?.trim()) {
                form.setError('current_employer', 'Current employer is required.');
                hasError = true;
            }
            data.experiences.forEach((exp, index) => {
                if (!exp.start_date || exp.start_date.length < 7) {
                    form.setError(`experiences.${index}.start_date`, 'Start date is required.');
                    hasError = true;
                }
                if (!exp.description?.trim()) {
                    form.setError(`experiences.${index}.description`, 'Description is required.');
                    hasError = true;
                }
                if (exp.ages_served.length === 0) {
                    form.setError(`experiences.${index}.ages_served`, 'Please select at least one age group.');
                    hasError = true;
                }
            });
        }

        if (step === 4) {
            if (data.allergic_to_pets === 'yes' && !data.allergic_to_what) {
                form.setError('allergic_to_what', 'Please select which pet you are allergic to.');
                hasError = true;
            }

            if (data.visible_tattoos === 'yes' && !data.tattoo_description) {
                form.setError('tattoo_description', 'Please describe your tattoos and whether they can be covered.');
                hasError = true;
            }

            if (data.has_children === 'yes' && !data.children_ages) {
                form.setError('children_ages', 'Please enter your children\'s ages.');
                hasError = true;
            }

            if (data.cpr_certified === 'yes') {
                if (!data.cpr_expiration) {
                    form.setError('cpr_expiration', 'CPR expiration date is required.');
                    hasError = true;
                }
                if (!data.cpr_card) {
                    form.setError('cpr_card', 'CPR card upload is required.');
                    hasError = true;
                }
            }
        }

        if (step === 5) {
            data.references.forEach((ref, index) => {
                if (!ref.first_name?.trim()) {
                    form.setError(`references.${index}.first_name`, 'Reference first name is required.');
                    hasError = true;
                }
                if (!ref.last_name?.trim()) {
                    form.setError(`references.${index}.last_name`, 'Reference last name is required.');
                    hasError = true;
                }
                if (!ref.email?.trim()) {
                    form.setError(`references.${index}.email`, 'Reference email is required.');
                    hasError = true;
                }
                if (!ref.phone?.trim()) {
                    form.setError(`references.${index}.phone`, 'Reference phone is required.');
                    hasError = true;
                }
                if (!ref.relationship?.trim()) {
                    form.setError(`references.${index}.relationship`, 'Relationship is required.');
                    hasError = true;
                }
                if (!ref.years_known) {
                    form.setError(`references.${index}.years_known`, 'Years known is required.');
                    hasError = true;
                }
            });
        }

        if (step === 6) {
            if (!data.location.north_county && !data.location.south_east_county && !data.location.flexible) {
                form.setError('location', 'Please select at least one location.');
                hasError = true;
            }
        }

        if (step === 7) {
            if (!data.bio?.trim()) {
                form.setError('bio', 'Bio is required.');
                hasError = true;
            }
        }

        if (step === 8) {
            if (!data.verification.signature?.trim()) {
                form.setError('verification.signature', 'Signature is required.');
                hasError = true;
            }
            if (!data.verification.agree) {
                form.setError('verification.agree', 'You must agree to proceed.');
                hasError = true;
            }
            if (!data.agreement.signature?.trim()) {
                form.setError('agreement.signature', 'Signature is required.');
                hasError = true;
            }
            if (!data.agreement.agree) {
                form.setError('agreement.agree', 'You must agree to proceed.');
                hasError = true;
            }
        }

        return !hasError;
    };

    const nextStep = () => {
        if (!validateStep(currentStep)) return;
        saveDraft();
        setCurrentStep((prev) => Math.min(prev + 1, 8));
    };

    const prevStep = () => {
        saveDraft();
        setCurrentStep((prev) => Math.max(prev - 1, 1));
    };

    const goToStep = (step: number) => {
        if (step > currentStep && !validateStep(currentStep)) return;
        saveDraft();
        setCurrentStep(step);
    };

    const handleSubmit = () => {
        sessionStorage.removeItem('caregiver_application_draft');
        form.post('/caregiver/apply/submit');
    };

    const addExperience = () => {
        form.setData('experiences', [
            ...form.data.experiences,
            {
                start_date: '',
                end_date: '',
                present: false,
                role: '',
                organization: '',
                description: '',
                ages_served: [],
            },
        ]);
    };

    const removeExperience = (index: number) => {
        form.setData(
            'experiences',
            form.data.experiences.filter((_, i) => i !== index),
        );
    };

    return (
        <div className="min-h-screen bg-background py-12">
            <div className="mx-auto max-w-3xl px-4">
                {/* Header */}
                <div className="mb-8 text-center">
                    <h1 className="text-3xl font-bold text-foreground">
                        Join the Sitterwise Team
                    </h1>
                    <p className="my-8 text-muted-foreground">
                        We hire on a referral basis only. Complete this
                        application to begin your journey with San Diego's most
                        trusted childcare agency.
                    </p>
                </div>

                {/* Progress Bar */}
                <div className="mb-8">
                    <div className="mb-4 flex justify-between">
                        {[1, 2, 3, 4, 5, 6, 7, 8].map((step) => (
                            <button
                                key={step}
                                type="button"
                                onClick={() => goToStep(step)}
                                className={`flex h-10 w-10 cursor-pointer items-center justify-center rounded-full text-sm font-medium ${
                                    step === currentStep
                                        ? 'bg-coral text-white'
                                        : step < currentStep
                                          ? 'bg-teal-500 text-white'
                                          : 'bg-muted text-muted-foreground'
                                }`}
                            >
                                {step}
                            </button>
                        ))}
                    </div>
                    <div className="h-2 w-full rounded-full bg-muted">
                        <div
                            className="h-2 rounded-full bg-coral transition-all"
                            style={{ width: `${(currentStep / 8) * 100}%` }}
                        />
                    </div>
                    <p className="mt-2 text-center text-sm text-muted-foreground">
                        Step {currentStep} of 8
                    </p>
                </div>

                <form
                    onSubmit={(e) => e.preventDefault()}
                    className="rounded-lg border border-border bg-card p-6 shadow-xs"
                >
                    {Object.keys(form.errors).length > 0 && (
                        <div className="mb-6 flex items-start gap-3 rounded-lg border border-destructive bg-destructive/10 p-4 text-sm text-destructive">
                            <AlertCircle className="mt-0.5 h-5 w-5 shrink-0" />
                            <div>
                                <p className="mb-1 font-medium">
                                    Please fix the following errors before submitting:
                                </p>
                                <ul className="list-inside list-disc space-y-0.5">
                                    {Object.entries(form.errors).map(
                                        ([key, message]) => (
                                            <li key={key}>{message as string}</li>
                                        ),
                                    )}
                                </ul>
                            </div>
                        </div>
                    )}

                    {/* Step 1: Sponsor & Personal */}
                    {currentStep === 1 && (
                        <div>
                            <h2 className="mb-2 text-2xl font-bold">
                                Sponsor & Personal Information
                            </h2>

                            <div className="mb-6 border-l-4 border-coral pl-4">
                                <h3 className="mb-2 text-lg font-semibold">
                                    Sponsor Information
                                </h3>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    Who referred you to Sitterwise? This person
                                    will also serve as a reference.
                                </p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-first-name">
                                            First Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="sponsor-first-name"
                                            type="text"
                                            placeholder="Sponsor's first name"
                                            value={form.data.sponsor.first_name}
                                            onChange={(e) =>
                                                form.setData('sponsor', {
                                                    ...form.data.sponsor,
                                                    first_name: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['sponsor.first_name'] && (
                                            <p className="text-sm text-destructive">{form.errors['sponsor.first_name']}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-last-name">
                                            Last Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="sponsor-last-name"
                                            type="text"
                                            placeholder="Sponsor's last name"
                                            value={form.data.sponsor.last_name}
                                            onChange={(e) =>
                                                form.setData('sponsor', {
                                                    ...form.data.sponsor,
                                                    last_name: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['sponsor.last_name'] && (
                                            <p className="text-sm text-destructive">{form.errors['sponsor.last_name']}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-email">
                                            Email{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="sponsor-email"
                                            type="email"
                                            placeholder="sponsor@email.com"
                                            value={form.data.sponsor.email}
                                            onChange={(e) =>
                                                form.setData('sponsor', {
                                                    ...form.data.sponsor,
                                                    email: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['sponsor.email'] && (
                                            <p className="text-sm text-destructive">{form.errors['sponsor.email']}</p>
                                        )}
                                    </div>
                                    <PhoneInput
                                        value={form.data.sponsor.phone}
                                        onChange={(value) => form.setData('sponsor', { ...form.data.sponsor, phone: value })}
                                        label="Phone"
                                        placeholder="(619) 555-0000"
                                    />
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="sponsor-relationship">
                                            Relationship to You
                                        </Label>
                                        <Input
                                            id="sponsor-relationship"
                                            type="text"
                                            placeholder="How does this person know you?"
                                            value={
                                                form.data.sponsor.relationship
                                            }
                                            onChange={(e) =>
                                                form.setData('sponsor', {
                                                    ...form.data.sponsor,
                                                    relationship:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-4 text-lg font-semibold">
                                    Your Information
                                </h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-first-name">
                                            First Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="personal-first-name"
                                            type="text"
                                            placeholder="Your first name"
                                            value={
                                                form.data.personal.first_name
                                            }
                                            onChange={(e) =>
                                                form.setData('personal', {
                                                    ...form.data.personal,
                                                    first_name: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['personal.first_name'] && (
                                            <p className="text-sm text-destructive">{form.errors['personal.first_name']}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-last-name">
                                            Last Name{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="personal-last-name"
                                            type="text"
                                            placeholder="Your last name"
                                            value={form.data.personal.last_name}
                                            onChange={(e) =>
                                                form.setData('personal', {
                                                    ...form.data.personal,
                                                    last_name: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['personal.last_name'] && (
                                            <p className="text-sm text-destructive">{form.errors['personal.last_name']}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <AddressAutocomplete
                                            form={form}
                                            label="Address"
                                            prefix="personal.address_"
                                        />
                                    </div>
                                    <PhoneInput
                                        value={form.data.personal.phone}
                                        onChange={(value) => form.setData('personal', { ...form.data.personal, phone: value })}
                                        label="Phone"
                                        placeholder="(858) 555-1234"
                                        required
                                        error={form.errors['personal.phone']}
                                    />
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-email">
                                            Email{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="personal-email"
                                            type="email"
                                            placeholder="your@email.com"
                                            value={form.data.personal.email}
                                            onChange={(e) =>
                                                form.setData('personal', {
                                                    ...form.data.personal,
                                                    email: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['personal.email'] && (
                                            <p className="text-sm text-destructive">{form.errors['personal.email']}</p>
                                        )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label>
                                            Date of Birth{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <DatePicker
                                            value={form.data.personal.dob}
                                            onChange={(date) =>
                                                form.setData('personal', {
                                                    ...form.data.personal,
                                                    dob: date,
                                                })
                                            }
                                            placeholder="Select date of birth"
                                            fromYear={1940}
                                            toYear={
                                                new Date().getFullYear() - 18
                                            }
                                        />
                                        {form.errors['personal.dob'] && (
                                            <p className="text-sm text-destructive">{form.errors['personal.dob']}</p>
                                        )}
                                    </div>
                                    {/* Profile Photo upload disabled */}
                                    {/* <div className="space-y-2">
                                        <Label htmlFor="personal-photo">
                                            Profile Photo
                                        </Label>
                                        <Input
                                            id="personal-photo"
                                            type="file"
                                            accept="image/*"
                                            onChange={(e) =>
                                                form.setData('personal', {
                                                    ...form.data.personal,
                                                    photo:
                                                        e.target.files?.[0] ||
                                                        null,
                                                })
                                            }
                                        />
                                    </div> */}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Position, Availability & Education */}
                    {currentStep === 2 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Position, Availability & Education
                            </h2>

                            <div className="mb-6">
                                <h3 className="mb-3 text-lg font-semibold">
                                    Position
                                </h3>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    What are you applying for? Check all that
                                    apply. <span className="text-destructive">*</span>
                                </p>
                                {(
                                    [
                                        'babysitting',
                                        'petsitting',
                                        'group_events',
                                    ] as const
                                ).map((pos) => (
                                    <label
                                        key={pos}
                                        className={`mb-2 flex cursor-pointer items-center gap-2 rounded border p-3 transition-colors ${form.data.position[pos] ? 'border-accent bg-secondary' : 'bg-background'}`}
                                    >
                                        <Checkbox
                                            checked={form.data.position[pos]}
                                            onCheckedChange={(checked) =>
                                                form.setData('position', {
                                                    ...form.data.position,
                                                    [pos]: checked === true,
                                                })
                                            }
                                        />
                                        <span className="text-sm">
                                            {positionLabels[pos]}
                                        </span>
                                    </label>
                                ))}
                                {form.errors.position && (
                                    <p className="text-sm text-destructive">{form.errors.position}</p>
                                )}
                            </div>

                            <div className="mb-6">
                                <h3 className="mb-3 text-lg font-semibold">
                                    Availability
                                </h3>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    When are you generally available? Check all
                                    that apply.
                                </p>
                                <div className="grid gap-2 md:grid-cols-2">
                                    {(
                                        [
                                            'weekday_mornings',
                                            'weekday_afternoons',
                                            'weekday_evenings',
                                            'weekends',
                                            'overnights',
                                        ] as const
                                    ).map((avail) => (
                                        <label
                                            key={avail}
                                            className={`flex cursor-pointer items-center gap-2 rounded border p-3 transition-colors ${form.data.availability[avail] ? 'border-accent bg-secondary' : 'bg-background'}`}
                                        >
                                            <Checkbox
                                                checked={
                                                    form.data.availability[
                                                        avail
                                                    ]
                                                }
                                                onCheckedChange={(checked) =>
                                                    form.setData(
                                                        'availability',
                                                        {
                                                            ...form.data
                                                                .availability,
                                                            [avail]:
                                                                checked ===
                                                                true,
                                                        },
                                                    )
                                                }
                                            />
                                            <span className="text-sm">
                                                {avail
                                                    .replace('_', ' ')
                                                    .replace(/\b\w/g, (l) =>
                                                        l.toUpperCase(),
                                                    )}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                <div className="mt-4 space-y-2">
                                    <Label htmlFor="availability-notes">
                                        Availability Notes
                                    </Label>
                                    <Textarea
                                        id="availability-notes"
                                        placeholder="Any scheduling nuance? E.g., 'Not available Tuesdays' or 'Available June-August only'"
                                        value={form.data.availability.notes}
                                        onChange={(e) =>
                                            form.setData('availability', {
                                                ...form.data.availability,
                                                notes: e.target.value,
                                            })
                                        }
                                    />
                                </div>
                            </div>

                            <div>
                                <h3 className="mb-3 text-lg font-semibold">
                                    Education
                                </h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="education-level">
                                            Highest Level Completed{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Select
                                            value={form.data.education.level}
                                            onValueChange={(value) =>
                                                form.setData('education', {
                                                    ...form.data.education,
                                                    level: value,
                                                })
                                            }
                                        >
                                            <SelectTrigger id="education-level">
                                                <SelectValue placeholder="Select education level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="high_school">
                                                    High School
                                                </SelectItem>
                                                <SelectItem value="associate">
                                                    Associate Degree
                                                </SelectItem>
                                                <SelectItem value="bachelor">
                                                    Bachelor's Degree
                                                </SelectItem>
                                                <SelectItem value="master">
                                                    Master's Degree
                                                </SelectItem>
                                                <SelectItem value="phd">
                                                    PhD
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {form.data.education.level !==
                                        'high_school' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="education-degree">
                                                Degree / Major
                                            </Label>
                                            <Input
                                                id="education-degree"
                                                type="text"
                                                placeholder="Child & Family Development"
                                                value={
                                                    form.data.education.degree
                                                }
                                                onChange={(e) =>
                                                    form.setData('education', {
                                                        ...form.data.education,
                                                        degree: e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                    {form.data.education.level !==
                                        'high_school' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="education-college">
                                                College
                                            </Label>
                                            <Input
                                                id="education-college"
                                                type="text"
                                                placeholder="College / Institution"
                                                value={
                                                    form.data.education.college
                                                }
                                                onChange={(e) =>
                                                    form.setData('education', {
                                                        ...form.data.education,
                                                        college: e.target.value,
                                                    })
                                                }
                                            />
                                        </div>
                                    )}
                                    {form.data.education.level !==
                                        'high_school' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="education-graduation-year">
                                                Graduation Year
                                            </Label>
                                            <Select
                                                value={
                                                    form.data.education
                                                        .graduation_year
                                                }
                                                onValueChange={(value) =>
                                                    form.setData('education', {
                                                        ...form.data.education,
                                                        graduation_year: value,
                                                    })
                                                }
                                            >
                                                <SelectTrigger id="education-graduation-year">
                                                    <SelectValue placeholder="Select year" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    {graduationYears.map(
                                                        (y) => (
                                                            <SelectItem
                                                                key={y}
                                                                value={y}
                                                            >
                                                                {y}
                                                            </SelectItem>
                                                        ),
                                                    )}
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="education-high-school-name">
                                            High School Name
                                        </Label>
                                        <Input
                                            id="education-high-school-name"
                                            type="text"
                                            placeholder="High school attended"
                                            value={
                                                form.data.education
                                                    .high_school_name
                                            }
                                            onChange={(e) =>
                                                form.setData('education', {
                                                    ...form.data.education,
                                                    high_school_name:
                                                        e.target.value,
                                                })
                                            }
                                        />
                                    </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="education-high-school-graduation-year">
                                                    High School Graduation Year
                                                </Label>
                                                <Select
                                                    value={
                                                        form.data.education
                                                            .high_school_graduation_year
                                                    }
                                                    onValueChange={(value) =>
                                                        form.setData(
                                                            'education',
                                                            {
                                                                ...form.data
                                                                    .education,
                                                                high_school_graduation_year:
                                                                    value,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <SelectTrigger id="education-high-school-graduation-year">
                                                        <SelectValue placeholder="Select year" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {graduationYears.map(
                                                            (y) => (
                                                                <SelectItem
                                                                    key={y}
                                                                    value={y}
                                                                >
                                                                    {y}
                                                                </SelectItem>
                                                            ),
                                                        )}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                        </div>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Employment & Experience */}
                    {currentStep === 3 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Employment & Experience
                            </h2>
                            <p className="mb-4 text-muted-foreground">
                                Add your childcare experience (at least one
                                entry required)
                            </p>

                            <div className="mb-6 space-y-2">
                                <Label htmlFor="employment-status">
                                    Are you currently employed?{' '}
                                    <span className="text-destructive">*</span>
                                </Label>
                                <Select
                                    value={form.data.employment_status ?? ''}
                                    onValueChange={(value) =>
                                        form.setData('employment_status', value)
                                    }
                                >
                                    <SelectTrigger id="employment-status">
                                        <SelectValue placeholder="Select employment status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="full_time">
                                            Yes &mdash; full-time
                                        </SelectItem>
                                        <SelectItem value="part_time">
                                            Yes &mdash; part-time
                                        </SelectItem>
                                        <SelectItem value="no">No</SelectItem>
                                        <SelectItem value="student">
                                            Student
                                        </SelectItem>
                                    </SelectContent>
                                </Select>
                                {form.errors.employment_status && (
                                    <p className="text-sm text-destructive">{form.errors.employment_status}</p>
                                )}
                            </div>

                            {(form.data.employment_status === 'full_time' ||
                                form.data.employment_status ===
                                    'part_time') && (
                                <div className="mb-6 space-y-2">
                                    <Label htmlFor="current-employer">
                                        Current Employer{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Input
                                        id="current-employer"
                                        type="text"
                                        placeholder="Your current employer"
                                        value={form.data.current_employer}
                                        onChange={(e) =>
                                            form.setData(
                                                'current_employer',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    {form.errors.current_employer && (
                                        <p className="text-sm text-destructive">{form.errors.current_employer}</p>
                                    )}
                                </div>
                            )}

                            {form.data.experiences.map((exp, index) => (
                                <div
                                    key={index}
                                    className="mb-4 rounded border p-4"
                                >
                                    <div className="mb-3 flex justify-between">
                                        <h4 className="font-semibold">
                                            Experience #{index + 1}
                                        </h4>
                                        {index > 0 && (
                                            <Button
                                                type="button"
                                                onClick={() =>
                                                    removeExperience(index)
                                                }
                                            >
                                                <Trash2 className="mr-1 inline h-4 w-4" />
                                                Remove
                                            </Button>
                                        )}
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label>
                                                Start Date{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <div className="grid grid-cols-2 gap-2">
                                                <Select
                                                    value={
                                                        exp.start_date
                                                            ? exp.start_date
                                                                      .length >=
                                                                  7
                                                                ? exp.start_date.slice(
                                                                      5,
                                                                      7,
                                                                  )
                                                                : exp.start_date
                                                                          .startsWith(
                                                                              '-',
                                                                          ) &&
                                                                    exp.start_date
                                                                        .length ===
                                                                        3
                                                                    ? exp.start_date.slice(
                                                                          1,
                                                                          3,
                                                                      )
                                                                    : ''
                                                            : ''
                                                    }
                                                    onValueChange={(month) => {
                                                        const newExp = [
                                                            ...form.data
                                                                .experiences,
                                                        ];
                                                        const year =
                                                            exp.start_date &&
                                                            exp.start_date
                                                                .length >= 4
                                                                ? exp.start_date.slice(
                                                                      0,
                                                                      4,
                                                                  )
                                                                : String(
                                                                      currentYear,
                                                                  );
                                                        newExp[
                                                            index
                                                        ].start_date = `${year}-${month}`;
                                                        form.setData(
                                                            'experiences',
                                                            newExp,
                                                        );
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Month" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {months.map((m) => (
                                                            <SelectItem
                                                                key={m.value}
                                                                value={m.value}
                                                            >
                                                                {m.label}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                                <Select
                                                    value={
                                                        exp.start_date &&
                                                        exp.start_date.length >=
                                                            4
                                                            ? exp.start_date.slice(
                                                                  0,
                                                                  4,
                                                              )
                                                            : ''
                                                    }
                                                    onValueChange={(year) => {
                                                        const newExp = [
                                                            ...form.data
                                                                .experiences,
                                                        ];
                                                        const month =
                                                            exp.start_date
                                                                ? exp.start_date
                                                                          .length >=
                                                                      7
                                                                    ? exp.start_date.slice(
                                                                          5,
                                                                          7,
                                                                      )
                                                                    : exp.start_date
                                                                              .startsWith(
                                                                                  '-',
                                                                              ) &&
                                                                        exp.start_date
                                                                            .length ===
                                                                            3
                                                                        ? exp.start_date.slice(
                                                                              1,
                                                                              3,
                                                                          )
                                                                        : ''
                                                                : '';
                                                        newExp[
                                                            index
                                                        ].start_date = `${year}-${month || '01'}`;
                                                        form.setData(
                                                            'experiences',
                                                            newExp,
                                                        );
                                                    }}
                                                >
                                                    <SelectTrigger>
                                                        <SelectValue placeholder="Year" />
                                                    </SelectTrigger>
                                                    <SelectContent>
                                                        {years.map((y) => (
                                                            <SelectItem
                                                                key={y}
                                                                value={y}
                                                            >
                                                                {y}
                                                            </SelectItem>
                                                        ))}
                                                    </SelectContent>
                                                </Select>
                                            </div>
                                            {form.errors[`experiences.${index}.start_date`] && (
                                                <p className="text-sm text-destructive">{form.errors[`experiences.${index}.start_date`]}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            {!exp.present ? (
                                                <>
                                                    <Label>End Date</Label>
                                                    <div className="grid grid-cols-2 gap-2">
                                                        <Select
                                                    value={
                                                        exp.end_date
                                                            ? exp.end_date
                                                                      .length >=
                                                                  7
                                                                ? exp.end_date.slice(
                                                                      5,
                                                                      7,
                                                                  )
                                                                : exp.end_date
                                                                          .startsWith(
                                                                              '-',
                                                                          ) &&
                                                                    exp.end_date
                                                                        .length ===
                                                                        3
                                                                    ? exp.end_date.slice(
                                                                          1,
                                                                          3,
                                                                      )
                                                                    : ''
                                                            : ''
                                                    }
                                                    onValueChange={(
                                                        month,
                                                    ) => {
                                                        const newExp = [
                                                            ...form.data
                                                                .experiences,
                                                        ];
                                                        const year =
                                                            exp.end_date &&
                                                            exp.end_date
                                                                .length >= 4
                                                                ? exp.end_date.slice(
                                                                      0,
                                                                      4,
                                                                  )
                                                                : String(
                                                                      currentYear,
                                                                  );
                                                        newExp[
                                                            index
                                                        ].end_date = `${year}-${month}`;
                                                        form.setData(
                                                            'experiences',
                                                            newExp,
                                                        );
                                                    }}
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Month" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {months
                                                                    .filter(
                                                                        (m) => {
                                                                            if (
                                                                                !exp.start_date
                                                                            ) {
                                                                                return true;
                                                                            }

                                                                            const startYear =
                                                                                exp.start_date.slice(
                                                                                    0,
                                                                                    4,
                                                                                );
                                                                            const startMonth =
                                                                                exp.start_date.slice(
                                                                                    5,
                                                                                    7,
                                                                                );
                                                                            const endYear =
                                                                                exp.end_date &&
                                                                                exp
                                                                                    .end_date
                                                                                    .length >=
                                                                                    4
                                                                                    ? exp.end_date.slice(
                                                                                          0,
                                                                                          4,
                                                                                      )
                                                                                    : '';

                                                                            if (
                                                                                !endYear
                                                                            ) {
                                                                                return true;
                                                                            }

                                                                            if (
                                                                                endYear ===
                                                                                startYear
                                                                            ) {
                                                                                return (
                                                                                    m.value >
                                                                                    startMonth
                                                                                );
                                                                            }

                                                                            return true;
                                                                        },
                                                                    )
                                                                    .map(
                                                                        (m) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    m.value
                                                                                }
                                                                                value={
                                                                                    m.value
                                                                                }
                                                                            >
                                                                                {
                                                                                    m.label
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                            </SelectContent>
                                                        </Select>
                                                        <Select
                                                            value={
                                                                exp.end_date &&
                                                                exp.end_date
                                                                    .length >= 4
                                                                    ? exp.end_date.slice(
                                                                          0,
                                                                          4,
                                                                      )
                                                                    : ''
                                                            }
                                                            onValueChange={(
                                                                year,
                                                            ) => {
                                                                const newExp = [
                                                                    ...form.data
                                                                        .experiences,
                                                                ];
                                                            const month =
                                                                exp.end_date
                                                                    ? exp.end_date
                                                                              .length >=
                                                                          7
                                                                        ? exp.end_date.slice(
                                                                              5,
                                                                              7,
                                                                          )
                                                                        : exp.end_date
                                                                                  .startsWith(
                                                                                      '-',
                                                                                  ) &&
                                                                            exp.end_date
                                                                                .length ===
                                                                                3
                                                                            ? exp.end_date.slice(
                                                                                  1,
                                                                                  3,
                                                                              )
                                                                            : ''
                                                                    : '';

                                                                if (
                                                                    month &&
                                                                    exp.start_date
                                                                ) {
                                                                    const startYear =
                                                                        exp.start_date.slice(
                                                                            0,
                                                                            4,
                                                                        );
                                                                    const startMonth =
                                                                        exp.start_date.slice(
                                                                            5,
                                                                            7,
                                                                        );

                                                                if (
                                                                    year ===
                                                                        startYear &&
                                                                    month <=
                                                                        startMonth
                                                                ) {
                                                                    return;
                                                                }
                                                            }

                                                            newExp[
                                                                index
                                                            ].end_date = `${year}-${month || '01'}`;
                                                                form.setData(
                                                                    'experiences',
                                                                    newExp,
                                                                );
                                                            }}
                                                        >
                                                            <SelectTrigger>
                                                                <SelectValue placeholder="Year" />
                                                            </SelectTrigger>
                                                            <SelectContent>
                                                                {years
                                                                    .filter(
                                                                        (y) => {
                                                                            if (
                                                                                !exp.start_date
                                                                            ) {
                                                                                return true;
                                                                            }

                                                                            const startYear =
                                                                                exp.start_date.slice(
                                                                                    0,
                                                                                    4,
                                                                                );

                                                                            return (
                                                                                y >=
                                                                                startYear
                                                                            );
                                                                        },
                                                                    )
                                                                    .map(
                                                                        (y) => (
                                                                            <SelectItem
                                                                                key={
                                                                                    y
                                                                                }
                                                                                value={
                                                                                    y
                                                                                }
                                                                            >
                                                                                {
                                                                                    y
                                                                                }
                                                                            </SelectItem>
                                                                        ),
                                                                    )}
                                                            </SelectContent>
                                                        </Select>
                                                    </div>
                                                </>
                                            ) : (
                                                <div className="space-y-2">
                                                    <Label>End Date</Label>
                                                    <div className="flex h-11 items-center rounded-[3px] border border-input bg-muted px-3 text-sm text-muted-foreground">
                                                        Present
                                                    </div>
                                                </div>
                                            )}
                                            <label
                                                className={`flex items-center gap-2 ${index === 0 && (form.data.employment_status === 'full_time' || form.data.employment_status === 'part_time') ? 'cursor-not-allowed opacity-60' : 'cursor-pointer'}`}
                                            >
                                                <Checkbox
                                                    checked={exp.present}
                                                    disabled={
                                                        index === 0 &&
                                                        (form.data
                                                            .employment_status ===
                                                            'full_time' ||
                                                            form.data
                                                                .employment_status ===
                                                                'part_time')
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) => {
                                                        const newExp = [
                                                            ...form.data
                                                                .experiences,
                                                        ];
                                                        newExp[index].present =
                                                            checked === true;

                                                        if (checked) {
                                                            newExp[
                                                                index
                                                            ].end_date = '';
                                                        }

                                                        form.setData(
                                                            'experiences',
                                                            newExp,
                                                        );
                                                    }}
                                                />
                                                <span className="text-xs text-muted-foreground">
                                                    I currently work here
                                                </span>
                                            </label>
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`exp-role-${index}`}
                                            >
                                                Role / Title
                                            </Label>
                                            <Input
                                                id={`exp-role-${index}`}
                                                type="text"
                                                placeholder="e.g. Nanny, Babysitter"
                                                value={exp.role}
                                                onChange={(e) => {
                                                    const newExp = [
                                                        ...form.data
                                                            .experiences,
                                                    ];
                                                    newExp[index].role =
                                                        e.target.value;
                                                    form.setData(
                                                        'experiences',
                                                        newExp,
                                                    );
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`exp-org-${index}`}>
                                                Family / Organization
                                            </Label>
                                            <Input
                                                id={`exp-org-${index}`}
                                                type="text"
                                                placeholder="e.g. The Rodriguez Family"
                                                value={exp.organization}
                                                onChange={(e) => {
                                                    const newExp = [
                                                        ...form.data
                                                            .experiences,
                                                    ];
                                                    newExp[index].organization =
                                                        e.target.value;
                                                    form.setData(
                                                        'experiences',
                                                        newExp,
                                                    );
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label
                                                htmlFor={`exp-desc-${index}`}
                                            >
                                                Description{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Textarea
                                                id={`exp-desc-${index}`}
                                                rows={3}
                                                placeholder="Please explain your role and responsibilities. Include children's ages."
                                                value={exp.description}
                                                onChange={(e) => {
                                                    const newExp = [
                                                        ...form.data
                                                            .experiences,
                                                    ];
                                                    newExp[index].description =
                                                        e.target.value;
                                                    form.setData(
                                                        'experiences',
                                                        newExp,
                                                    );
                                                }}
                                            />
                                            {form.errors[`experiences.${index}.description`] && (
                                                <p className="text-sm text-destructive">{form.errors[`experiences.${index}.description`]}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label>Ages Served <span className="text-destructive">*</span></Label>
                                            <p className="text-sm text-muted-foreground">
                                                Select all age groups you worked
                                                with in this role.
                                            </p>
                                            <div className="grid gap-2 md:grid-cols-2">
                                                {(
                                                    Object.keys(
                                                        ageLabels,
                                                    ) as Array<
                                                        keyof typeof ageLabels
                                                    >
                                                ).map((ageKey) => (
                                                    <label
                                                        key={ageKey}
                                                        className={`flex cursor-pointer items-center gap-2 rounded border p-3 transition-colors ${exp.ages_served.includes(ageKey) ? 'border-accent bg-secondary' : 'bg-background'}`}
                                                    >
                                                        <Checkbox
                                                            checked={exp.ages_served.includes(
                                                                ageKey,
                                                            )}
                                                            onCheckedChange={(
                                                                checked,
                                                            ) => {
                                                                const newExp = [
                                                                    ...form.data
                                                                        .experiences,
                                                                ];

                                                                if (checked) {
                                                                    newExp[
                                                                        index
                                                                    ].ages_served =
                                                                        [
                                                                            ...newExp[
                                                                                index
                                                                            ]
                                                                                .ages_served,
                                                                            ageKey,
                                                                        ];
                                                                } else {
                                                                    newExp[
                                                                        index
                                                                    ].ages_served =
                                                                        newExp[
                                                                            index
                                                                        ].ages_served.filter(
                                                                            (
                                                                                a,
                                                                            ) =>
                                                                                a !==
                                                                                ageKey,
                                                                        );
                                                                }

                                                                form.setData(
                                                                    'experiences',
                                                                    newExp,
                                                                );
                                                            }}
                                                        />
                                                        <span className="text-sm">
                                                            {ageLabels[ageKey]}
                                                        </span>
                                                    </label>
                                                ))}
                                            </div>
                                            {form.errors[`experiences.${index}.ages_served`] && (
                                                <p className="text-sm text-destructive">{form.errors[`experiences.${index}.ages_served`]}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <Button
                                type="button"
                                onClick={addExperience}
                                disabled={form.data.experiences.length >= 3}
                                className="disabled:opacity-50"
                            >
                                + Add Another Experience
                            </Button>
                        </div>
                    )}

                    {/* Step 4: Screening Questions */}
                    {currentStep === 4 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Screening Questions
                            </h2>

                            <div className="space-y-6">
                                {/* Authorized to work in the U.S.? */}
                                <div className="space-y-2">
                                    <Label>
                                        Authorized to work in the U.S.?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.authorized_to_work ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('authorized_to_work', value)
                                        }
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="authorized-yes" />
                                            <Label htmlFor="authorized-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="authorized-no" />
                                            <Label htmlFor="authorized-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                    {form.data.authorized_to_work === 'no' && (
                                        <div className="mt-2 rounded border border-destructive/50 bg-destructive/10 p-3 text-sm text-destructive">
                                            Sitterwise is required by federal
                                            law to verify work authorization for
                                            all employees. If you are not
                                            currently authorized to work in the
                                            United States, we cannot move
                                            forward with your application. If
                                            your status changes, please reach
                                            out to us directly.
                                        </div>
                                    )}
                                </div>

                                {/* Do you smoke? */}
                                <div className="space-y-2">
                                    <Label>
                                        Do you smoke?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.smokes ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('smokes', value)
                                        }
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="smokes-yes" />
                                            <Label htmlFor="smokes-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="smokes-no" />
                                            <Label htmlFor="smokes-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {/* Do you drink alcohol? */}
                                <div className="space-y-2">
                                    <Label>
                                        Do you drink alcohol?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Select
                                        value={form.data.alcohol ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('alcohol', value)
                                        }
                                    >
                                        <SelectTrigger>
                                            <SelectValue placeholder="Select" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="no">
                                                No
                                            </SelectItem>
                                            <SelectItem value="socially">
                                                Socially/Occasionally
                                            </SelectItem>
                                            <SelectItem value="regularly">
                                                Regularly
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>

                                {/* Substance abuse history? */}
                                <div className="space-y-2">
                                    <Label>
                                        Substance abuse history?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Textarea
                                        rows={3}
                                        placeholder="Please explain..."
                                        value={form.data.substance_abuse}
                                        onChange={(e) =>
                                            form.setData(
                                                'substance_abuse',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                {/* Physical/psychological limitations? */}
                                <div className="space-y-2">
                                    <Label>
                                        Physical/psychological limitations?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <Textarea
                                        rows={3}
                                        placeholder="Unable to lift, afraid of dogs, etc.?"
                                        value={form.data.limitations}
                                        onChange={(e) =>
                                            form.setData(
                                                'limitations',
                                                e.target.value,
                                            )
                                        }
                                    />
                                </div>

                                {/* Allergic to dogs or cats? */}
                                <div className="space-y-2">
                                    <Label>
                                        Allergic to dogs or cats?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.allergic_to_pets ?? ''}
                                        onValueChange={(value) => {
                                            form.setData('allergic_to_pets', value);

                                            if (value === 'no') {
form.setData('allergic_to_what', '');
}
                                        }}
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="allergic-yes" />
                                            <Label htmlFor="allergic-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="allergic-no" />
                                            <Label htmlFor="allergic-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {form.data.allergic_to_pets === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            Allergic to which?{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Select
                                            value={form.data.allergic_to_what ?? ''}
                                            onValueChange={(value) =>
                                                form.setData('allergic_to_what', value)
                                            }
                                        >
                                            <SelectTrigger>
                                                <SelectValue placeholder="Select" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="dogs">Dogs</SelectItem>
                                                <SelectItem value="cats">Cats</SelectItem>
                                                <SelectItem value="both">Both</SelectItem>
                                            </SelectContent>
                                        </Select>
                                        {form.errors.allergic_to_what && (
                                            <p className="text-sm text-destructive">{form.errors.allergic_to_what}</p>
                                        )}
                                    </div>
                                )}

                                {/* Visible tattoos? */}
                                <div className="space-y-2">
                                    <Label>
                                        Visible tattoos?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.visible_tattoos ?? ''}
                                        onValueChange={(value) => {
                                            form.setData('visible_tattoos', value);

                                            if (value === 'no') {
form.setData('tattoo_description', '');
}
                                        }}
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="tattoos-yes" />
                                            <Label htmlFor="tattoos-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="tattoos-no" />
                                            <Label htmlFor="tattoos-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {form.data.visible_tattoos === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            Please describe location and whether they can be covered with standard work attire{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Textarea
                                            rows={2}
                                            placeholder="Describe location and whether they can be covered..."
                                            value={form.data.tattoo_description ?? ''}
                                            onChange={(e) =>
                                                form.setData('tattoo_description', e.target.value)
                                            }
                                        />
                                        {form.errors.tattoo_description && (
                                            <p className="text-sm text-destructive">{form.errors.tattoo_description}</p>
                                        )}
                                    </div>
                                )}

                                {/* Reliable vehicle? */}
                                <div className="space-y-2">
                                    <Label>
                                        Reliable vehicle?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.reliable_vehicle ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('reliable_vehicle', value)
                                        }
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="vehicle-yes" />
                                            <Label htmlFor="vehicle-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="vehicle-no" />
                                            <Label htmlFor="vehicle-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {/* CPR & First Aid certified? */}
                                <div className="space-y-2">
                                    <Label>
                                        CPR & First Aid certified?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.cpr_certified ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('cpr_certified', value)
                                        }
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="cpr-yes" />
                                            <Label htmlFor="cpr-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="cpr-no" />
                                            <Label htmlFor="cpr-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {/* CPR & First Aid Expiration Date (conditional) */}
                                {form.data.cpr_certified === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            CPR & First Aid Expiration Date{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <DatePicker
                                            value={form.data.cpr_expiration}
                                            onChange={(date) =>
                                                form.setData('cpr_expiration', date)
                                            }
                                            fromYear={currentYear - 2}
                                            toYear={currentYear + 10}
                                            placeholder="Select expiration date"
                                        />
                                        {form.errors.cpr_expiration && (
                                            <p className="text-sm text-destructive">{form.errors.cpr_expiration}</p>
                                        )}
                                    </div>
                                )}

                                {/* CPR & First Aid Card Upload (conditional) */}
                                {form.data.cpr_certified === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            CPR & First Aid Card Upload{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="file"
                                            accept="image/*,.pdf"
                                            onChange={(e) =>
                                                form.setData('cpr_card', e.target.files?.[0] || null)
                                            }
                                        />
                                        {form.errors.cpr_card && (
                                            <p className="text-sm text-destructive">{form.errors.cpr_card}</p>
                                        )}
                                    </div>
                                )}

                                {/* Trustline certified? */}
                                <div className="space-y-2">
                                    <Label>
                                        Trustline certified?{' '}
                                        <span className="text-destructive">*</span>
                                    </Label>
                                    <RadioGroup
                                        value={form.data.trustline_certified ?? ''}
                                        onValueChange={(value) =>
                                            form.setData('trustline_certified', value)
                                        }
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="trustline-yes" />
                                            <Label htmlFor="trustline-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="trustline-no" />
                                            <Label htmlFor="trustline-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {/* Trustline Upload (conditional) */}
                                {form.data.trustline_certified === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            Trustline Upload{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="file"
                                            accept="image/*,.pdf"
                                            onChange={(e) =>
                                                form.setData('trustline_upload', e.target.files?.[0] || null)
                                            }
                                        />
                                    </div>
                                )}

                                {/* Languages */}
                                <div className="space-y-2">
                                    <Label>Languages (other than English)</Label>
                                    <Input
                                        type="text"
                                        placeholder="e.g. Spanish, Tagalog, ASL"
                                        value={form.data.languages}
                                        onChange={(e) =>
                                            form.setData('languages', e.target.value)
                                        }
                                    />
                                </div>

                                {/* Do you have children of your own? */}
                                <div className="space-y-2">
                                    <Label>Do you have children of your own?</Label>
                                    <RadioGroup
                                        value={form.data.has_children ?? ''}
                                        onValueChange={(value) => {
                                            form.setData('has_children', value);

                                            if (value === 'no') {
form.setData('children_ages', '');
}
                                        }}
                                        className="flex gap-4"
                                    >
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="yes" id="children-yes" />
                                            <Label htmlFor="children-yes">Yes</Label>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <RadioGroupItem value="no" id="children-no" />
                                            <Label htmlFor="children-no">No</Label>
                                        </div>
                                    </RadioGroup>
                                </div>

                                {form.data.has_children === 'yes' && (
                                    <div className="space-y-2">
                                        <Label>
                                            Children ages{' '}
                                            <span className="text-destructive">*</span>
                                        </Label>
                                        <Input
                                            type="text"
                                            placeholder="e.g., 2, 5, 8"
                                            value={form.data.children_ages ?? ''}
                                            onChange={(e) =>
                                                form.setData('children_ages', e.target.value)
                                            }
                                        />
                                        {form.errors.children_ages && (
                                            <p className="text-sm text-destructive">{form.errors.children_ages}</p>
                                        )}
                                    </div>
                                )}
                            </div>


                        </div>
                    )}

                    {/* Step 5: References */}
                    {currentStep === 5 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Additional References
                            </h2>
                            <p className="mb-4 text-muted-foreground">
                                Provide 3 additional references (plus your
                                sponsor = 4 total)
                            </p>

                            {form.data.references.map((ref, index) => (
                                <div
                                    key={index}
                                    className="mb-4 rounded border p-4"
                                >
                                    <h4 className="mb-3 font-semibold">
                                        Reference #{index + 1}
                                    </h4>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`ref-first-name-${index}`}
                                            >
                                                First Name{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id={`ref-first-name-${index}`}
                                                type="text"
                                                placeholder="Reference's first name"
                                                value={ref.first_name}
                                                onChange={(e) => {
                                                    const newRefs = [
                                                        ...form.data.references,
                                                    ];
                                                    newRefs[index].first_name =
                                                        e.target.value;
                                                    form.setData(
                                                        'references',
                                                        newRefs,
                                                    );
                                                }}
                                            />
                                            {form.errors[`references.${index}.first_name`] && (
                                                <p className="text-sm text-destructive">{form.errors[`references.${index}.first_name`]}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`ref-last-name-${index}`}
                                            >
                                                Last Name{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id={`ref-last-name-${index}`}
                                                type="text"
                                                placeholder="Reference's last name"
                                                value={ref.last_name}
                                                onChange={(e) => {
                                                    const newRefs = [
                                                        ...form.data.references,
                                                    ];
                                                    newRefs[index].last_name =
                                                        e.target.value;
                                                    form.setData(
                                                        'references',
                                                        newRefs,
                                                    );
                                                }}
                                            />
                                            {form.errors[`references.${index}.last_name`] && (
                                                <p className="text-sm text-destructive">{form.errors[`references.${index}.last_name`]}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`ref-email-${index}`}
                                            >
                                                Email{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id={`ref-email-${index}`}
                                                type="email"
                                                placeholder="reference@email.com"
                                                value={ref.email}
                                                onChange={(e) => {
                                                    const newRefs = [
                                                        ...form.data.references,
                                                    ];
                                                    newRefs[index].email =
                                                        e.target.value;
                                                    form.setData(
                                                        'references',
                                                        newRefs,
                                                    );
                                                }}
                                            />
                                            {form.errors[`references.${index}.email`] && (
                                                <p className="text-sm text-destructive">{form.errors[`references.${index}.email`]}</p>
                                            )}
                                        </div>
                                        <PhoneInput
                                            value={ref.phone}
                                            onChange={(value) => {
                                                const newRefs = [...form.data.references];
                                                newRefs[index] = { ...newRefs[index], phone: value };
                                                form.setData('references', newRefs);
                                            }}
                                            label="Phone"
                                            placeholder="(619) 555-0000"
                                            required
                                            error={form.errors[`references.${index}.phone`]}
                                        />
                                        <div className="space-y-2">
                                            <Label
                                                htmlFor={`ref-relationship-${index}`}
                                            >
                                                Relationship{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Input
                                                id={`ref-relationship-${index}`}
                                                type="text"
                                                placeholder="How do you know this person?"
                                                value={ref.relationship}
                                                onChange={(e) => {
                                                    const newRefs = [
                                                        ...form.data.references,
                                                    ];
                                                    newRefs[
                                                        index
                                                    ].relationship =
                                                        e.target.value;
                                                    form.setData(
                                                        'references',
                                                        newRefs,
                                                    );
                                                }}
                                            />
                                            {form.errors[`references.${index}.relationship`] && (
                                                <p className="text-sm text-destructive">{form.errors[`references.${index}.relationship`]}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label
                                                htmlFor={`ref-years-known-${index}`}
                                            >
                                                Years Known{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Select
                                                value={ref.years_known}
                                                onValueChange={(value) => {
                                                    const newRefs = [
                                                        ...form.data.references,
                                                    ];
                                                    newRefs[index].years_known =
                                                        value;
                                                    form.setData(
                                                        'references',
                                                        newRefs,
                                                    );
                                                }}
                                            >
                                                <SelectTrigger
                                                    id={`ref-years-known-${index}`}
                                                >
                                                    <SelectValue placeholder="Years Known" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="<1">
                                                        Less than 1 year
                                                    </SelectItem>
                                                    <SelectItem value="1-3">
                                                        1-3 years
                                                    </SelectItem>
                                                    <SelectItem value="3-5">
                                                        3-5 years
                                                    </SelectItem>
                                                    <SelectItem value="5-10">
                                                        5-10 years
                                                    </SelectItem>
                                                    <SelectItem value="10+">
                                                        10+ years
                                                    </SelectItem>
                                                </SelectContent>
                                            </Select>
                                            {form.errors[`references.${index}.years_known`] && (
                                                <p className="text-sm text-destructive">{form.errors[`references.${index}.years_known`]}</p>
                                            )}
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Step 6: Location & Age Groups */}
                    {currentStep === 6 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Location & Age Groups
                            </h2>

                            <div className="mb-6">
                                <h3 className="mb-3 text-lg font-semibold">
                                    Location Preferences
                                </h3>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Where are you willing to work? <span className="text-destructive">*</span>
                                </p>
                                {(
                                    [
                                        'north_county',
                                        'south_east_county',
                                        'flexible',
                                    ] as const
                                ).map((loc) => (
                                    <label
                                        key={loc}
                                        className={`mb-2 flex cursor-pointer items-start gap-2 rounded border p-3 transition-colors ${form.data.location[loc] ? 'border-accent bg-secondary' : 'bg-background'}`}
                                    >
                                        <Checkbox
                                            checked={form.data.location[loc]}
                                            onCheckedChange={(checked) =>
                                                form.setData('location', {
                                                    ...form.data.location,
                                                    [loc]: checked === true,
                                                })
                                            }
                                        />
                                        <div>
                                            <span className="font-medium">
                                                {locationLabels[loc]}
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {locationDescriptions[loc]}
                                            </p>
                                        </div>
                                    </label>
                                ))}
                                {form.errors.location && (
                                    <p className="text-sm text-destructive">{form.errors.location}</p>
                                )}
                            </div>

                            <div>
                                <h3 className="mb-3 text-lg font-semibold">
                                    Age Groups
                                </h3>
                                <p className="mb-3 text-sm text-muted-foreground">
                                    Check each age group you feel comfortable
                                    caring for. By checking, you're agreeing
                                    with the statement below.
                                </p>
                                {(
                                    [
                                        'babies',
                                        'toddlers',
                                        'preschool',
                                        'school_age',
                                    ] as const
                                ).map((age) => (
                                    <label
                                        key={age}
                                        className={`mb-2 flex cursor-pointer items-start gap-2 rounded border p-3 transition-colors ${form.data.age_groups[age] ? 'border-accent bg-secondary' : 'bg-background'}`}
                                    >
                                        <Checkbox
                                            checked={form.data.age_groups[age]}
                                            onCheckedChange={(checked) =>
                                                form.setData('age_groups', {
                                                    ...form.data.age_groups,
                                                    [age]: checked === true,
                                                })
                                            }
                                        />
                                        <div>
                                            <span className="font-medium">
                                                {age.charAt(0).toUpperCase() +
                                                    age
                                                        .slice(1)
                                                        .replace('_', ' ')}
                                            </span>
                                            <p className="mt-1 text-sm text-muted-foreground">
                                                {ageGroupDescriptions[age]}
                                            </p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Step 7: Qualifications, Activities & Bio */}
                    {currentStep === 7 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Qualifications, Activities & Bio
                            </h2>

                            <div className="space-y-6">
                                <div>
                                    <h3 className="mb-4 text-lg font-semibold">
                                        Care Qualifications
                                    </h3>
                                    <div className="space-y-3">
                                        {(
                                            [
                                                [
                                                    'special_needs',
                                                    'Special Needs',
                                                    'Autism, Down syndrome, ADHD, allergies, anxiety, behavioral needs',
                                                ],
                                                [
                                                    'companion_care',
                                                    'Companion Care',
                                                    'Elderly or adults with special needs',
                                                ],
                                                [
                                                    'sick_care',
                                                    'Sick Care',
                                                    'Comfortable caring for mildly sick children',
                                                ],
                                                [
                                                    'work_from_home',
                                                    'Work-From-Home Parents',
                                                    'Comfortable with parent present in another room',
                                                ],
                                                [
                                                    'driving',
                                                    'Driving',
                                                    'Can transport children safely',
                                                ],
                                                [
                                                    'dogsitting',
                                                    'Dogsitting',
                                                    'Comfortable with all dog sizes/breeds',
                                                ],
                                                [
                                                    'swimming',
                                                    'Swimming',
                                                    'Pool/beach supervision and water safety',
                                                ],
                                                [
                                                    'overnight_care',
                                                    'Overnight Care',
                                                    'Comfortable staying overnight with children',
                                                ],
                                            ] as const
                                        ).map(([key, label, description]) => (
                                            <label
                                                key={key}
                                                className={`flex cursor-pointer items-start gap-3 rounded border p-3 transition-colors ${(form.data.qualifications?.[key] ?? false) ? 'border-accent bg-secondary' : 'bg-background'}`}
                                            >
                                                <Checkbox
                                                    checked={
                                                        form.data
                                                            .qualifications?.[
                                                            key
                                                        ] ?? false
                                                    }
                                                    onCheckedChange={(
                                                        checked,
                                                    ) =>
                                                        form.setData(
                                                            'qualifications',
                                                            {
                                                                ...(form.data
                                                                    .qualifications ??
                                                                    {}),
                                                                [key]:
                                                                    checked ===
                                                                    true,
                                                            },
                                                        )
                                                    }
                                                />
                                                <div>
                                                    <span className="font-medium">
                                                        {label}
                                                    </span>
                                                    <p className="mt-1 text-sm text-muted-foreground">
                                                        {description}
                                                    </p>
                                                </div>
                                            </label>
                                        ))}
                                    </div>
                                </div>

                                <div>
                                    <h3 className="mb-4 text-lg font-semibold">
                                        Activities & Engagement
                                    </h3>
                                    <div className="space-y-2">
                                        <Label htmlFor="things-i-bring">
                                            Things you bring to a job
                                        </Label>
                                        <Textarea
                                            id="things-i-bring"
                                            rows={3}
                                            placeholder="What toys, games, or activities do you bring to engage children?"
                                            value={form.data.things_i_bring}
                                            onChange={(e) =>
                                                form.setData(
                                                    'things_i_bring',
                                                    e.target.value,
                                                )
                                            }
                                        />
                                    </div>
                                </div>

                                <div>
                                    <h3 className="mb-4 text-lg font-semibold">
                                        Bio & Interests
                                    </h3>
                                    <div className="space-y-4">
                                        <div className="space-y-2">
                                            <Label htmlFor="bio">
                                                Bio{' '}
                                                <span className="text-destructive">
                                                    *
                                                </span>
                                            </Label>
                                            <Textarea
                                                id="bio"
                                                rows={5}
                                                placeholder="Write a public-facing introduction about yourself (200–500 words recommended)"
                                                value={form.data.bio}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'bio',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                            {form.errors.bio && (
                                                <p className="text-sm text-destructive">{form.errors.bio}</p>
                                            )}
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor="interests">
                                                Interests / Hobbies
                                            </Label>
                                            <Input
                                                id="interests"
                                                type="text"
                                                placeholder="e.g. hiking, painting, reading, playing guitar"
                                                value={form.data.interests}
                                                onChange={(e) =>
                                                    form.setData(
                                                        'interests',
                                                        e.target.value,
                                                    )
                                                }
                                            />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 8: Agreements */}
                    {currentStep === 8 && (
                        <div>
                            <h2 className="mb-6 text-2xl font-bold">
                                Agreements
                            </h2>

                            <div className="mb-6 border-l-4 border-coral pl-4">
                                <h3 className="mb-2 text-lg font-semibold">
                                    Caregiver Statement of Verification
                                </h3>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    I certify under penalty of perjury that the
                                    answers given herein are true and complete
                                    to the best of my knowledge. I authorize the
                                    investigation of all statements contained in
                                    this document, and I understand that this
                                    document is not intended to be a contract of
                                    employment.
                                </p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="verification-signature">
                                            Typed Signature{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="verification-signature"
                                            type="text"
                                            placeholder="Type your full name"
                                            value={
                                                form.data.verification.signature
                                            }
                                            onChange={(e) =>
                                                form.setData('verification', {
                                                    ...form.data.verification,
                                                    signature: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['verification.signature'] && (
                                            <p className="text-sm text-destructive">{form.errors['verification.signature']}</p>
                                        )}
                                        {form.data.verification.signature &&
                                            form.data.verification.signature !==
                                                expectedSignature && (
                                                <p className="text-xs text-yellow-600 dark:text-yellow-400">
                                                    Signature must match your
                                                    full name (
                                                    {expectedSignature})
                                                </p>
                                            )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="verification-date">
                                            Today's Date
                                        </Label>
                                        <Input
                                            id="verification-date"
                                            type="text"
                                            value={today}
                                            disabled
                                        />
                                    </div>
                                </div>
                                <label
                                    className={`mt-4 flex cursor-pointer items-center gap-2 rounded border p-3 transition-colors ${form.data.verification.agree ? 'border-accent bg-secondary' : 'bg-background'}`}
                                >
                                    <Checkbox
                                        checked={form.data.verification.agree}
                                        onCheckedChange={(checked) =>
                                            form.setData('verification', {
                                                ...form.data.verification,
                                                agree: checked === true,
                                            })
                                        }
                                    />
                                    <span className="text-sm">
                                        By typing your name in the above box,
                                        you agree that this constitutes the
                                        equivalent of a physical signature.
                                    </span>
                                </label>
                                {form.errors['verification.agree'] && (
                                    <p className="text-sm text-destructive">{form.errors['verification.agree']}</p>
                                )}
                            </div>

                            <div className="mb-6 border-l-4 border-coral pl-4">
                                <h3 className="mb-2 text-lg font-semibold">
                                    Conditional Offer Acknowledgment
                                </h3>
                                <p className="mb-4 text-sm text-muted-foreground">
                                    I understand that this application does not
                                    constitute an offer of employment. If
                                    Sitterwise extends an offer, I will receive
                                    a separate written offer letter outlining my
                                    role, pay rate, and at-will employment
                                    terms, which I will sign through OnPay
                                    before beginning work. I understand that all
                                    Sitterwise caregivers are W-2 employees and
                                    that all pay is processed through OnPay,
                                    with applicable taxes withheld. I agree to
                                    maintain current CPR certification
                                    and to submit a Trustline application within
                                    7 days of activation, as conditions of
                                    employment.
                                </p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="agreement-signature">
                                            Typed Signature{' '}
                                            <span className="text-destructive">
                                                *
                                            </span>
                                        </Label>
                                        <Input
                                            id="agreement-signature"
                                            type="text"
                                            placeholder="Type your full name"
                                            value={
                                                form.data.agreement.signature
                                            }
                                            onChange={(e) =>
                                                form.setData('agreement', {
                                                    ...form.data.agreement,
                                                    signature: e.target.value,
                                                })
                                            }
                                        />
                                        {form.errors['agreement.signature'] && (
                                            <p className="text-sm text-destructive">{form.errors['agreement.signature']}</p>
                                        )}
                                        {form.data.agreement.signature &&
                                            form.data.agreement.signature !==
                                                expectedSignature && (
                                                <p className="text-xs text-yellow-600 dark:text-yellow-400">
                                                    Signature must match your
                                                    full name (
                                                    {expectedSignature})
                                                </p>
                                            )}
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="agreement-date">
                                            Today's Date
                                        </Label>
                                        <Input
                                            id="agreement-date"
                                            type="text"
                                            value={today}
                                            disabled
                                        />
                                    </div>
                                </div>
                                <label
                                    className={`mt-4 flex cursor-pointer items-center gap-2 rounded border p-3 transition-colors ${form.data.agreement.agree ? 'border-accent bg-secondary' : 'bg-background'}`}
                                >
                                    <Checkbox
                                        checked={form.data.agreement.agree}
                                        onCheckedChange={(checked) =>
                                            form.setData('agreement', {
                                                ...form.data.agreement,
                                                agree: checked === true,
                                            })
                                        }
                                    />
                                    <span className="text-sm">
                                        By typing your name in the above box,
                                        you agree that this constitutes the
                                        equivalent of a physical signature.
                                    </span>
                                </label>
                                {form.errors['agreement.agree'] && (
                                    <p className="text-sm text-destructive">{form.errors['agreement.agree']}</p>
                                )}
                            </div>
                        </div>
                    )}

                    <div className="mt-6 flex justify-between border-t pt-6">
                        {currentStep > 1 ? (
                            <Button
                                type="button"
                                variant="secondary"
                                onClick={prevStep}
                            >
                                ← Back
                            </Button>
                        ) : (
                            <div />
                        )}

                        {currentStep < 8 ? (
                            <Button
                                type="button"
                                onClick={nextStep}
                                disabled={
                                    currentStep === 4 &&
                                    form.data.authorized_to_work === 'no'
                                }
                                className="hover:bg-coral-dark rounded bg-coral px-4 py-2 text-white disabled:opacity-50"
                            >
                                Next →
                            </Button>
                        ) : (
                            <Button
                                type="button"
                                onClick={handleSubmit}
                                disabled={
                                    form.processing ||
                                    form.data.authorized_to_work === 'no'
                                }
                                className="hover:bg-coral-dark rounded bg-coral px-6 py-2 text-white disabled:opacity-50"
                            >
                                {form.processing
                                    ? 'Submitting...'
                                    : 'Submit Application'}
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
