import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { DatePicker } from '@/components/ui/date-picker';
import { AddressAutocomplete } from '@/components/ui/address-autocomplete';

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
    name: string;
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

export default function Wizard() {
    const [currentStep, setCurrentStep] = useState(1);

    const today = new Date().toLocaleDateString('en-US', {
        month: '2-digit',
        day: '2-digit',
        year: 'numeric',
    });

    const form = useForm({
        sponsor: { first_name: '', last_name: '', email: '', phone: '', relationship: '' },
        personal: { first_name: '', last_name: '', address_line1: '', address_line2: '', address_city: '', address_state: '', address_zip: '', phone: '', email: '', dob: '', photo: null as File | null },
        position: { babysitting: false, petsitting: false, group_events: false },
        availability: { weekday_mornings: false, weekday_afternoons: false, weekday_evenings: false, weekends: false, overnights: false, notes: '' },
        education: { level: 'bachelor', college: '', graduation_year: '', degree: '', high_school_name: '', high_school_graduation_year: '' },
        experiences: [
            { start_date: '', end_date: '', present: false, role: '', organization: '', description: '', ages_served: [] },
        ] as Experience[],
        certifications: [],
        skills: { special_needs: false, work_from_home: false, swimming: false, driving: false, other: '' },
        references: [
            { name: '', email: '', phone: '', relationship: '', years_known: '' },
            { name: '', email: '', phone: '', relationship: '', years_known: '' },
            { name: '', email: '', phone: '', relationship: '', years_known: '' },
        ] as Reference[],
        location: { north_county: false, south_east_county: false, flexible: false },
        age_groups: { babies: false, toddlers: false, preschool: false, school_age: false },
        terms: { agree: false },
        verification: { signature: '', agree: false },
        agreement: { signature: '', agree: false },
    });

    // Load draft from sessionStorage
    useEffect(() => {
        const saved = sessionStorage.getItem('caregiver_application_draft');
        if (saved) {
            const draft = JSON.parse(saved);
            if (draft.step && draft.data) {
                setCurrentStep(draft.step);
                form.setData(draft.data);
            }
        }
    }, []);

    // Save draft to sessionStorage on step change
    const saveDraft = () => {
        sessionStorage.setItem('caregiver_application_draft', JSON.stringify({
            step: currentStep,
            data: form.data,
        }));
    };

    const nextStep = () => {
        saveDraft();
        setCurrentStep(prev => Math.min(prev + 1, 8));
    };

    const prevStep = () => {
        saveDraft();
        setCurrentStep(prev => Math.max(prev - 1, 1));
    };

    const goToStep = (step: number) => {
        saveDraft();
        setCurrentStep(step);
    };

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        sessionStorage.removeItem('caregiver_application_draft');
        form.post('/caregiver/apply/submit');
    };

    const addExperience = () => {
        form.setData('experiences', [...form.data.experiences, { start_date: '', end_date: '', present: false, role: '', organization: '', description: '', ages_served: [] }]);
    };

    const removeExperience = (index: number) => {
        form.setData('experiences', form.data.experiences.filter((_, i) => i !== index));
    };

    const addReference = () => {
        if (form.data.references.length < 3) {
            form.setData('references', [...form.data.references, { name: '', email: '', phone: '', relationship: '', years_known: '' }]);
        }
    };

    return (
        <div className="min-h-screen bg-gray-50 py-12">
            <div className="max-w-3xl mx-auto px-4">
                {/* Header */}
                <div className="mb-8 text-center">
                    <h1 className="text-3xl font-bold text-navy">Join the Sitterwise Team</h1>
                    <p className="text-gray-600 my-8">We hire on a referral basis only. Complete this application to begin your journey with San Diego's most trusted childcare agency.</p>
                </div>

                {/* Progress Bar */}
                <div className="mb-8">
                    <div className="flex justify-between mb-4">
                        {[1, 2, 3, 4, 5, 6, 7, 8].map((step) => (
                            <button
                                key={step}
                                onClick={() => goToStep(step)}
                                className={`w-10 h-10 rounded-full flex items-center justify-center text-sm font-medium ${
                                    step === currentStep
                                        ? 'bg-coral text-white'
                                        : step < currentStep
                                        ? 'bg-teal-500 text-white'
                                        : 'bg-gray-300 text-gray-600'
                                }`}
                            >
                                {step}
                            </button>
                        ))}
                    </div>
                    <div className="w-full bg-gray-200 rounded-full h-2">
                        <div
                            className="bg-coral h-2 rounded-full transition-all"
                            style={{ width: `${(currentStep / 8) * 100}%` }}
                        />
                    </div>
                    <p className="text-center text-sm text-gray-600 mt-2">
                        Step {currentStep} of 8
                    </p>
                </div>

                <form onSubmit={submit} className="bg-white shadow rounded-lg p-6">
                    {/* Step 1: Sponsor & Personal */}
                    {currentStep === 1 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-2">Sponsor & Personal Information</h2>

                            <div className="border-l-4 border-coral pl-4 mb-6">
                                <h3 className="text-lg font-semibold mb-2">Sponsor Information</h3>
                                <p className="text-sm text-gray-600 mb-4">Who referred you to Sitterwise? This person will also serve as a reference.</p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-first-name">
                                            First Name <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="sponsor-first-name"
                                            type="text"
                                            placeholder="Sponsor's first name"
                                            value={form.data.sponsor.first_name}
                                            onChange={e => form.setData('sponsor', { ...form.data.sponsor, first_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-last-name">
                                            Last Name <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="sponsor-last-name"
                                            type="text"
                                            placeholder="Sponsor's last name"
                                            value={form.data.sponsor.last_name}
                                            onChange={e => form.setData('sponsor', { ...form.data.sponsor, last_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-email">
                                            Email <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="sponsor-email"
                                            type="email"
                                            placeholder="sponsor@email.com"
                                            value={form.data.sponsor.email}
                                            onChange={e => form.setData('sponsor', { ...form.data.sponsor, email: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="sponsor-phone">Phone</Label>
                                        <Input
                                            id="sponsor-phone"
                                            type="tel"
                                            placeholder="(619) 555-0000"
                                            value={form.data.sponsor.phone}
                                            onChange={e => form.setData('sponsor', { ...form.data.sponsor, phone: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <Label htmlFor="sponsor-relationship">Relationship to You</Label>
                                        <Input
                                            id="sponsor-relationship"
                                            type="text"
                                            placeholder="How does this person know you?"
                                            value={form.data.sponsor.relationship}
                                            onChange={e => form.setData('sponsor', { ...form.data.sponsor, relationship: e.target.value })}
                                        />
                                    </div>
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-4">Your Information</h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-first-name">
                                            First Name <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="personal-first-name"
                                            type="text"
                                            placeholder="Your first name"
                                            value={form.data.personal.first_name}
                                            onChange={e => form.setData('personal', { ...form.data.personal, first_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-last-name">
                                            Last Name <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="personal-last-name"
                                            type="text"
                                            placeholder="Your last name"
                                            value={form.data.personal.last_name}
                                            onChange={e => form.setData('personal', { ...form.data.personal, last_name: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2 md:col-span-2">
                                        <AddressAutocomplete
                                            form={form}
                                            label="Address"
                                            prefix="personal.address_"
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-phone">
                                            Phone <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="personal-phone"
                                            type="tel"
                                            placeholder="(858) 555-1234"
                                            value={form.data.personal.phone}
                                            onChange={e => form.setData('personal', { ...form.data.personal, phone: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-email">
                                            Email <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="personal-email"
                                            type="email"
                                            placeholder="your@email.com"
                                            value={form.data.personal.email}
                                            onChange={e => form.setData('personal', { ...form.data.personal, email: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label>
                                            Date of Birth <span className="text-red-500">*</span>
                                        </Label>
                                        <DatePicker
                                            value={form.data.personal.dob}
                                            onChange={date => form.setData('personal', { ...form.data.personal, dob: date })}
                                            placeholder="Select date of birth"
                                            fromYear={1940}
                                            toYear={new Date().getFullYear() - 18}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="personal-photo">Profile Photo</Label>
                                        <Input
                                            id="personal-photo"
                                            type="file"
                                            accept="image/*"
                                            onChange={e => form.setData('personal', { ...form.data.personal, photo: e.target.files?.[0] || null })}
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 2: Position, Availability & Education */}
                    {currentStep === 2 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Position, Availability & Education</h2>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Position</h3>
                                <p className="text-sm text-gray-600 mb-3">What are you applying for? Check all that apply.</p>
                                {(['babysitting', 'petsitting', 'group_events'] as const).map(pos => (
                                    <label key={pos} className={`flex items-center gap-2 p-3 border rounded mb-2 cursor-pointer transition-colors ${form.data.position[pos] ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                        <Checkbox checked={form.data.position[pos]} onCheckedChange={(checked) => form.setData('position', { ...form.data.position, [pos]: checked === true })} />
                                        <span className="text-sm">{positionLabels[pos]}</span>
                                    </label>
                                ))}
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Availability</h3>
                                <p className="text-sm text-gray-600 mb-3">When are you generally available? Check all that apply.</p>
                                <div className="grid gap-2 md:grid-cols-2">
                                    {(['weekday_mornings', 'weekday_afternoons', 'weekday_evenings', 'weekends', 'overnights'] as const).map(avail => (
                                        <label key={avail} className={`flex items-center gap-2 p-3 border rounded cursor-pointer transition-colors ${form.data.availability[avail] ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                            <Checkbox checked={form.data.availability[avail]} onCheckedChange={(checked) => form.setData('availability', { ...form.data.availability, [avail]: checked === true })} />
                                            <span className="text-sm">{avail.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                        </label>
                                    ))}
                                </div>
                                <div className="mt-4 space-y-2">
                                    <Label htmlFor="availability-notes">Availability Notes</Label>
                                    <Textarea
                                        id="availability-notes"
                                        placeholder="Any scheduling nuance? E.g., 'Not available Tuesdays' or 'Available June-August only'"
                                        value={form.data.availability.notes}
                                        onChange={e => form.setData('availability', { ...form.data.availability, notes: e.target.value })}
                                    />
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-3">Education</h3>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="education-level">
                                            Highest Level Completed <span className="text-red-500">*</span>
                                        </Label>
                                        <Select value={form.data.education.level} onValueChange={value => form.setData('education', { ...form.data.education, level: value })}>
                                            <SelectTrigger id="education-level">
                                                <SelectValue placeholder="Select education level" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="high_school">High School</SelectItem>
                                                <SelectItem value="associate">Associate Degree</SelectItem>
                                                <SelectItem value="bachelor">Bachelor's Degree</SelectItem>
                                                <SelectItem value="master">Master's Degree</SelectItem>
                                                <SelectItem value="phd">PhD</SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    {form.data.education.level !== 'high_school' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="education-degree">Degree / Major</Label>
                                            <Input
                                                id="education-degree"
                                                type="text"
                                                placeholder="Child & Family Development"
                                                value={form.data.education.degree}
                                                onChange={e => form.setData('education', { ...form.data.education, degree: e.target.value })}
                                            />
                                        </div>
                                    )}
                                    {form.data.education.level !== 'high_school' && (
                                        <div className="space-y-2">
                                            <Label htmlFor="education-college">College</Label>
                                            <Input
                                                id="education-college"
                                                type="text"
                                                placeholder="College / Institution"
                                                value={form.data.education.college}
                                                onChange={e => form.setData('education', { ...form.data.education, college: e.target.value })}
                                            />
                                        </div>
                                    )}
                                    <div className="space-y-2">
                                        <Label htmlFor="education-graduation-year">Graduation Year</Label>
                                        <Input
                                            id="education-graduation-year"
                                            type="text"
                                            placeholder="e.g. 2017"
                                            value={form.data.education.graduation_year}
                                            onChange={e => form.setData('education', { ...form.data.education, graduation_year: e.target.value })}
                                        />
                                    </div>
                                    {form.data.education.level !== 'high_school' && (
                                        <>
                                            <div className="space-y-2">
                                                <Label htmlFor="education-high-school-name">High School Name</Label>
                                                <Input
                                                    id="education-high-school-name"
                                                    type="text"
                                                    placeholder="High school attended"
                                                    value={form.data.education.high_school_name}
                                                    onChange={e => form.setData('education', { ...form.data.education, high_school_name: e.target.value })}
                                                />
                                            </div>
                                            <div className="space-y-2">
                                                <Label htmlFor="education-high-school-graduation-year">High School Graduation Year</Label>
                                                <Input
                                                    id="education-high-school-graduation-year"
                                                    type="text"
                                                    placeholder="e.g. 2013"
                                                    value={form.data.education.high_school_graduation_year}
                                                    onChange={e => form.setData('education', { ...form.data.education, high_school_graduation_year: e.target.value })}
                                                />
                                            </div>
                                        </>
                                    )}
                                </div>
                            </div>
                        </div>
                    )}

                    {/* Step 3: Work Experience */}
                    {currentStep === 3 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Work Experience</h2>
                            <p className="text-gray-600 mb-4">Add your childcare experience (at least one entry required)</p>

                            {form.data.experiences.map((exp, index) => (
                                <div key={index} className="border rounded p-4 mb-4">
                                    <div className="flex justify-between mb-3">
                                        <h4 className="font-semibold">Experience #{index + 1}</h4>
                                        {index > 0 && (
                                            <button type="button" onClick={() => removeExperience(index)} className="text-red-600 text-sm">Remove</button>
                                        )}
                                    </div>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor={`exp-start-${index}`}>
                                                Start Date <span className="text-red-500">*</span>
                                            </Label>
                                            <DatePicker
                                                value={exp.start_date}
                                                onChange={date => {
                                                    const newExp = [...form.data.experiences];
                                                    newExp[index].start_date = date;
                                                    form.setData('experiences', newExp);
                                                }}
                                                placeholder="Select start date"
                                                fromYear={1980}
                                                toYear={new Date().getFullYear() + 1}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            {!exp.present ? (
                                                <>
                                                    <Label htmlFor={`exp-end-${index}`}>End Date</Label>
                                                    <DatePicker
                                                        value={exp.end_date}
                                                        onChange={date => {
                                                            const newExp = [...form.data.experiences];
                                                            newExp[index].end_date = date;
                                                            form.setData('experiences', newExp);
                                                        }}
                                                        placeholder="Select end date"
                                                        fromYear={1980}
                                                        toYear={new Date().getFullYear() + 1}
                                                    />
                                                </>
                                            ) : (
                                                <div className="space-y-2">
                                                    <Label>End Date</Label>
                                                    <div className="flex h-11 items-center rounded-[3px] border border-input bg-gray-100 px-3 text-sm text-muted-foreground">
                                                        Present
                                                    </div>
                                                </div>
                                            )}
                                            <label className="flex items-center gap-2 cursor-pointer">
                                                <Checkbox
                                                    checked={exp.present}
                                                    onCheckedChange={(checked) => {
                                                        const newExp = [...form.data.experiences];
                                                        newExp[index].present = checked === true;
                                                        if (checked) {
                                                            newExp[index].end_date = '';
                                                        }
                                                        form.setData('experiences', newExp);
                                                    }}
                                                />
                                                <span className="text-xs text-gray-600">I currently work here</span>
                                            </label>
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`exp-role-${index}`}>
                                                Role / Title <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id={`exp-role-${index}`}
                                                type="text"
                                                placeholder="e.g. Nanny, Babysitter"
                                                value={exp.role}
                                                onChange={e => {
                                                    const newExp = [...form.data.experiences];
                                                    newExp[index].role = e.target.value;
                                                    form.setData('experiences', newExp);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`exp-org-${index}`}>
                                                Family / Organization <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id={`exp-org-${index}`}
                                                type="text"
                                                placeholder="e.g. The Rodriguez Family"
                                                value={exp.organization}
                                                onChange={e => {
                                                    const newExp = [...form.data.experiences];
                                                    newExp[index].organization = e.target.value;
                                                    form.setData('experiences', newExp);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor={`exp-desc-${index}`}>
                                                Description <span className="text-red-500">*</span>
                                            </Label>
                                            <Textarea
                                                id={`exp-desc-${index}`}
                                                rows={3}
                                                placeholder="Describe your responsibilities and daily activities..."
                                                value={exp.description}
                                                onChange={e => {
                                                    const newExp = [...form.data.experiences];
                                                    newExp[index].description = e.target.value;
                                                    form.setData('experiences', newExp);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label>
                                                Ages Served <span className="text-red-500">*</span>
                                            </Label>
                                            <p className="text-sm text-gray-600">Select all age groups you worked with in this role.</p>
                                            <div className="grid gap-2 md:grid-cols-2">
                                                {(Object.keys(ageLabels) as Array<keyof typeof ageLabels>).map(ageKey => (
                                                    <label key={ageKey} className={`flex items-center gap-2 p-3 border rounded cursor-pointer transition-colors ${exp.ages_served.includes(ageKey) ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                                        <Checkbox
                                                            checked={exp.ages_served.includes(ageKey)}
                                                            onCheckedChange={(checked) => {
                                                                const newExp = [...form.data.experiences];
                                                                if (checked) {
                                                                    newExp[index].ages_served = [...newExp[index].ages_served, ageKey];
                                                                } else {
                                                                    newExp[index].ages_served = newExp[index].ages_served.filter(a => a !== ageKey);
                                                                }
                                                                form.setData('experiences', newExp);
                                                            }}
                                                        />
                                                        <span className="text-sm">{ageLabels[ageKey]}</span>
                                                    </label>
                                                ))}
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            ))}

                            <button type="button" onClick={addExperience} className="text-coral font-medium">+ Add Another Experience</button>
                        </div>
                    )}

                    {/* Step 4: Certifications & Skills */}
                    {currentStep === 4 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Certifications & Skills</h2>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Special Skills</h3>
                                {(['special_needs', 'work_from_home', 'swimming', 'driving'] as const).map(skill => (
                                    <label key={skill} className={`flex items-center gap-2 p-3 border rounded mb-2 cursor-pointer transition-colors ${form.data.skills[skill] ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                        <Checkbox checked={form.data.skills[skill]} onCheckedChange={(checked) => form.setData('skills', { ...form.data.skills, [skill]: checked === true })} />
                                        <span className="cursor-pointer">{skill.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                    </label>
                                ))}
                            </div>

                            <div className="space-y-2">
                                <Label htmlFor="skills-other">Other Qualifications</Label>
                                <Textarea
                                    id="skills-other"
                                    placeholder="Any additional skills or certifications..."
                                    rows={4}
                                    value={form.data.skills.other}
                                    onChange={e => form.setData('skills', { ...form.data.skills, other: e.target.value })}
                                />
                            </div>
                        </div>
                    )}

                    {/* Step 5: References */}
                    {currentStep === 5 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Additional References</h2>
                            <p className="text-gray-600 mb-4">Provide 3 additional references (plus your sponsor = 4 total)</p>

                            {form.data.references.map((ref, index) => (
                                <div key={index} className="border rounded p-4 mb-4">
                                    <h4 className="font-semibold mb-3">Reference #{index + 1}</h4>
                                    <div className="grid gap-4 md:grid-cols-2">
                                        <div className="space-y-2">
                                            <Label htmlFor={`ref-name-${index}`}>
                                                Full Name <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id={`ref-name-${index}`}
                                                type="text"
                                                placeholder="Reference's full name"
                                                value={ref.name}
                                                onChange={e => {
                                                    const newRefs = [...form.data.references];
                                                    newRefs[index].name = e.target.value;
                                                    form.setData('references', newRefs);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`ref-email-${index}`}>
                                                Email <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id={`ref-email-${index}`}
                                                type="email"
                                                placeholder="reference@email.com"
                                                value={ref.email}
                                                onChange={e => {
                                                    const newRefs = [...form.data.references];
                                                    newRefs[index].email = e.target.value;
                                                    form.setData('references', newRefs);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`ref-phone-${index}`}>Phone</Label>
                                            <Input
                                                id={`ref-phone-${index}`}
                                                type="tel"
                                                placeholder="(619) 555-0000"
                                                value={ref.phone}
                                                onChange={e => {
                                                    const newRefs = [...form.data.references];
                                                    newRefs[index].phone = e.target.value;
                                                    form.setData('references', newRefs);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2">
                                            <Label htmlFor={`ref-relationship-${index}`}>
                                                Relationship <span className="text-red-500">*</span>
                                            </Label>
                                            <Input
                                                id={`ref-relationship-${index}`}
                                                type="text"
                                                placeholder="How do you know this person?"
                                                value={ref.relationship}
                                                onChange={e => {
                                                    const newRefs = [...form.data.references];
                                                    newRefs[index].relationship = e.target.value;
                                                    form.setData('references', newRefs);
                                                }}
                                            />
                                        </div>
                                        <div className="space-y-2 md:col-span-2">
                                            <Label htmlFor={`ref-years-known-${index}`}>
                                                Years Known <span className="text-red-500">*</span>
                                            </Label>
                                            <Select value={ref.years_known} onValueChange={value => {
                                                const newRefs = [...form.data.references];
                                                newRefs[index].years_known = value;
                                                form.setData('references', newRefs);
                                            }}>
                                                <SelectTrigger id={`ref-years-known-${index}`}>
                                                    <SelectValue placeholder="Years Known *" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectItem value="<1">Less than 1 year</SelectItem>
                                                    <SelectItem value="1-3">1-3 years</SelectItem>
                                                    <SelectItem value="3-5">3-5 years</SelectItem>
                                                    <SelectItem value="5-10">5-10 years</SelectItem>
                                                    <SelectItem value="10+">10+ years</SelectItem>
                                                </SelectContent>
                                            </Select>
                                        </div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    )}

                    {/* Step 6: Location & Age Groups */}
                    {currentStep === 6 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Location & Age Groups</h2>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Location Preferences</h3>
                                <p className="text-sm text-gray-600 mb-3">Where are you willing to work?</p>
                                {(['north_county', 'south_east_county', 'flexible'] as const).map(loc => (
                                    <label key={loc} className={`flex items-start gap-2 p-3 border rounded mb-2 cursor-pointer transition-colors ${form.data.location[loc] ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                        <Checkbox checked={form.data.location[loc]} onCheckedChange={(checked) => form.setData('location', { ...form.data.location, [loc]: checked === true })} />
                                        <div>
                                            <span className="font-medium">{loc.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                            <p className="text-sm text-gray-600 mt-1">{locationDescriptions[loc]}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-3">Age Groups</h3>
                                <p className="text-sm text-gray-600 mb-3">Check each age group you feel comfortable caring for. By checking, you're agreeing with the statement below.</p>
                                {(['babies', 'toddlers', 'preschool', 'school_age'] as const).map(age => (
                                    <label key={age} className={`flex items-start gap-2 p-3 border rounded mb-2 cursor-pointer transition-colors ${form.data.age_groups[age] ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                        <Checkbox checked={form.data.age_groups[age]} onCheckedChange={(checked) => form.setData('age_groups', { ...form.data.age_groups, [age]: checked === true })} />
                                        <div>
                                            <span className="font-medium">{age.charAt(0).toUpperCase() + age.slice(1).replace('_', ' ')}</span>
                                            <p className="text-sm text-gray-600 mt-1">{ageGroupDescriptions[age]}</p>
                                        </div>
                                    </label>
                                ))}
                            </div>
                        </div>
                    )}

                    {/* Step 7: Review */}
                    {currentStep === 7 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Review Your Application</h2>
                            <p className="text-gray-600 mb-6">Please review all information before submitting.</p>

                            <div className="space-y-4 mb-6">
                                <div className="border rounded p-4">
                                    <h4 className="font-semibold mb-2">Personal Info</h4>
                                    <p className="text-sm text-gray-600">{form.data.personal.first_name} {form.data.personal.last_name}</p>
                                    <p className="text-sm text-gray-600">{[form.data.personal.address_line1, form.data.personal.address_line2, form.data.personal.address_city, form.data.personal.address_state, form.data.personal.address_zip].filter(Boolean).join(', ')}</p>
                                </div>

                                <div className="border rounded p-4">
                                    <h4 className="font-semibold mb-2">Sponsor</h4>
                                    <p className="text-sm text-gray-600">{form.data.sponsor.first_name} {form.data.sponsor.last_name} ({form.data.sponsor.email})</p>
                                </div>

                                <div className="border rounded p-4">
                                    <h4 className="font-semibold mb-2">Experience</h4>
                                    <p className="text-sm text-gray-600">{form.data.experiences.length} experience entries</p>
                                </div>

                                <div className="border rounded p-4">
                                    <h4 className="font-semibold mb-2">References</h4>
                                    <p className="text-sm text-gray-600">1 sponsor + {form.data.references.length} additional references</p>
                                </div>
                            </div>

                            <label className={`flex items-center gap-2 p-3 border rounded cursor-pointer transition-colors ${form.data.terms.agree ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                <Checkbox checked={form.data.terms.agree} onCheckedChange={(checked) => form.setData('terms', { ...form.data.terms, agree: checked === true })} />
                                <span className="text-sm">I certify that all information provided is true and complete.</span>
                            </label>
                        </div>
                    )}

                    {/* Step 8: Agreements */}
                    {currentStep === 8 && (
                        <div>
                            <h2 className="text-2xl font-bold mb-6">Agreements</h2>

                            <div className="border-l-4 border-coral pl-4 mb-6">
                                <h3 className="text-lg font-semibold mb-2">Caregiver Statement of Verification</h3>
                                <p className="text-sm text-gray-600 mb-4">I certify under penalty of perjury that the answers given herein are true and complete to the best of my knowledge. I authorize the investigation of all statements contained in this document, and I understand that this document is not intended to be a contract of employment.</p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="verification-signature">
                                            Typed Signature <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="verification-signature"
                                            type="text"
                                            placeholder="Type your full name"
                                            value={form.data.verification.signature}
                                            onChange={e => form.setData('verification', { ...form.data.verification, signature: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="verification-date">Today's Date</Label>
                                        <Input
                                            id="verification-date"
                                            type="text"
                                            value={today}
                                            disabled
                                        />
                                    </div>
                                </div>
                                <label className={`flex items-center gap-2 p-3 border rounded cursor-pointer transition-colors mt-4 ${form.data.verification.agree ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                    <Checkbox checked={form.data.verification.agree} onCheckedChange={(checked) => form.setData('verification', { ...form.data.verification, agree: checked === true })} />
                                    <span className="text-sm">By typing your name in the above box, you agree that this constitutes the equivalent of a physical signature.</span>
                                </label>
                            </div>

                            <div className="border-l-4 border-coral pl-4 mb-6">
                                <h3 className="text-lg font-semibold mb-2">Caregiver Statement of Agreement</h3>
                                <p className="text-sm text-gray-600 mb-4">I understand that I am working as an independent contractor for Sitterwise, Inc. I am free to accept or reject any job offered. I will not provide childcare for families originally referred by Sitterwise "under the table" without notifying Sitterwise...</p>
                                <div className="grid gap-4 md:grid-cols-2">
                                    <div className="space-y-2">
                                        <Label htmlFor="agreement-signature">
                                            Typed Signature <span className="text-red-500">*</span>
                                        </Label>
                                        <Input
                                            id="agreement-signature"
                                            type="text"
                                            placeholder="Type your full name"
                                            value={form.data.agreement.signature}
                                            onChange={e => form.setData('agreement', { ...form.data.agreement, signature: e.target.value })}
                                        />
                                    </div>
                                    <div className="space-y-2">
                                        <Label htmlFor="agreement-date">Today's Date</Label>
                                        <Input
                                            id="agreement-date"
                                            type="text"
                                            value={today}
                                            disabled
                                        />
                                    </div>
                                </div>
                                <label className={`flex items-center gap-2 p-3 border rounded cursor-pointer transition-colors mt-4 ${form.data.agreement.agree ? 'bg-teal-50 border-teal-500' : 'bg-gray-50'}`}>
                                    <Checkbox checked={form.data.agreement.agree} onCheckedChange={(checked) => form.setData('agreement', { ...form.data.agreement, agree: checked === true })} />
                                    <span className="text-sm">By typing your name in the above box, you agree that this constitutes the equivalent of a physical signature.</span>
                                </label>
                            </div>
                        </div>
                    )}

                    <div className="flex justify-between mt-6 pt-6 border-t">
                        {currentStep > 1 ? (
                            <button type="button" onClick={prevStep} className="px-4 py-2 border border-gray-300 rounded text-gray-700 hover:bg-gray-50">
                                ← Back
                            </button>
                        ) : (
                            <div />
                        )}

                        {currentStep < 8 ? (
                            <button type="button" onClick={nextStep} className="px-4 py-2 bg-coral text-white rounded hover:bg-coral-dark">
                                Next →
                            </button>
                        ) : (
                            <button type="submit" disabled={form.processing} className="px-6 py-2 bg-coral text-white rounded hover:bg-coral-dark disabled:opacity-50">
                                {form.processing ? 'Submitting...' : 'Submit Application'}
                            </button>
                        )}
                    </div>
                </form>
            </div>
        </div>
    );
}
