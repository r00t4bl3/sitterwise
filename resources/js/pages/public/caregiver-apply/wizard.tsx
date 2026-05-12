import { useState, useEffect } from 'react';
import { useForm } from '@inertiajs/react';

interface Experience {
    start_month: string;
    end_month: string;
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

export default function Wizard() {
    const [currentStep, setCurrentStep] = useState(1);

    const form = useForm({
        sponsor: { first_name: '', last_name: '', email: '', phone: '', relationship: '' },
        personal: { first_name: '', last_name: '', address: '', phone: '', dob: '', photo: null },
        position: { babysitting: false, petsitting: false, group_events: false },
        availability: { weekday_mornings: false, weekday_afternoons: false, weekday_evenings: false, weekends: false, overnights: false, notes: '' },
        education: { level: 'bachelor', college: '', graduation_year: '', degree: '' },
        experiences: [{ start_month: '', end_month: '', role: '', organization: '', description: '', ages_served: [] }] as Experience[],
        certifications: [],
        skills: { special_needs: false, work_from_home: false, swimming: false, driving: false, other: '' },
        references: [{ name: '', email: '', phone: '', relationship: '', years_known: '' }] as Reference[],
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
        form.setData('experiences', [...form.data.experiences, { start_month: '', end_month: '', role: '', organization: '', description: '', ages_served: [] }]);
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
                    <p className="text-gray-600 mt-2">We hire on a referral basis only. Complete this application to begin.</p>
                </div>

                {/* Progress Bar */}
                <div className="mb-8">
                    <div className="flex justify-between mb-2">
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
                            <p className="text-gray-600 mb-6">Who referred you and your basic details</p>

                            <div className="border-l-4 border-coral pl-4 mb-6">
                                <h3 className="text-lg font-semibold mb-2">Sponsor Information</h3>
                                <p className="text-sm text-gray-600 mb-4">Who referred you to Sitterwise? This person will also serve as a reference.</p>
                                <div className="grid grid-cols-2 gap-4">
                                    <input type="text" placeholder="First Name *" className="border rounded p-2" value={form.data.sponsor.first_name} onChange={e => form.setData('sponsor', { ...form.data.sponsor, first_name: e.target.value })} />
                                    <input type="text" placeholder="Last Name *" className="border rounded p-2" value={form.data.sponsor.last_name} onChange={e => form.setData('sponsor', { ...form.data.sponsor, last_name: e.target.value })} />
                                    <input type="email" placeholder="Email *" className="border rounded p-2" value={form.data.sponsor.email} onChange={e => form.setData('sponsor', { ...form.data.sponsor, email: e.target.value })} />
                                    <input type="tel" placeholder="Phone" className="border rounded p-2" value={form.data.sponsor.phone} onChange={e => form.setData('sponsor', { ...form.data.sponsor, phone: e.target.value })} />
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-4">Your Information</h3>
                                <div className="grid grid-cols-2 gap-4">
                                    <input type="text" placeholder="First Name *" className="border rounded p-2" value={form.data.personal.first_name} onChange={e => form.setData('personal', { ...form.data.personal, first_name: e.target.value })} />
                                    <input type="text" placeholder="Last Name *" className="border rounded p-2" value={form.data.personal.last_name} onChange={e => form.setData('personal', { ...form.data.personal, last_name: e.target.value })} />
                                    <input type="text" placeholder="Address *" className="border rounded p-2 col-span-2" value={form.data.personal.address} onChange={e => form.setData('personal', { ...form.data.personal, address: e.target.value })} />
                                    <input type="tel" placeholder="Phone *" className="border rounded p-2" value={form.data.personal.phone} onChange={e => form.setData('personal', { ...form.data.personal, phone: e.target.value })} />
                                    <input type="date" placeholder="Date of Birth *" className="border rounded p-2" value={form.data.personal.dob} onChange={e => form.setData('personal', { ...form.data.personal, dob: e.target.value })} />
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
                                {['babysitting', 'petsitting', 'group_events'].map(pos => (
                                    <label key={pos} className="flex items-center gap-2 p-3 bg-gray-50 border rounded mb-2 cursor-pointer">
                                        <input type="checkbox" checked={form.data.position[pos]} onChange={e => form.setData('position', { ...form.data.position, [pos]: e.target.checked })} />
                                        <span className="font-medium">{pos.charAt(0).toUpperCase() + pos.slice(1).replace('_', ' ')}</span>
                                    </label>
                                ))}
                            </div>

                            <div className="mb-6">
                                <h3 className="text-lg font-semibold mb-3">Availability</h3>
                                <div className="grid grid-cols-2 gap-2">
                                    {['weekday_mornings', 'weekday_afternoons', 'weekday_evenings', 'weekends', 'overnights'].map(avail => (
                                        <label key={avail} className="flex items-center gap-2 p-3 bg-gray-50 border rounded cursor-pointer">
                                            <input type="checkbox" checked={form.data.availability[avail]} onChange={e => form.setData('availability', { ...form.data.availability, [avail]: e.target.checked })} />
                                            <span className="text-sm">{avail.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-3">Education</h3>
                                <div className="grid grid-cols-2 gap-4">
                                    <select className="border rounded p-2" value={form.data.education.level} onChange={e => form.setData('education', { ...form.data.education, level: e.target.value })}>
                                        <option value="high_school">High School</option>
                                        <option value="associate">Associate Degree</option>
                                        <option value="bachelor">Bachelor's Degree</option>
                                        <option value="master">Master's Degree</option>
                                        <option value="phd">PhD</option>
                                    </select>
                                    <input type="text" placeholder="College/Institution" className="border rounded p-2" value={form.data.education.college} onChange={e => form.setData('education', { ...form.data.education, college: e.target.value })} />
                                    <input type="text" placeholder="Graduation Year" className="border rounded p-2" value={form.data.education.graduation_year} onChange={e => form.setData('education', { ...form.data.education, graduation_year: e.target.value })} />
                                    <input type="text" placeholder="Degree/Major" className="border rounded p-2" value={form.data.education.degree} onChange={e => form.setData('education', { ...form.data.education, degree: e.target.value })} />
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
                                    <div className="grid grid-cols-2 gap-4">
                                        <input type="month" placeholder="Start Month" className="border rounded p-2" value={exp.start_month} onChange={e => {
                                            const newExp = [...form.data.experiences];
                                            newExp[index].start_month = e.target.value;
                                            form.setData('experiences', newExp);
                                        }} />
                                        <input type="month" placeholder="End Month (or Present)" className="border rounded p-2" value={exp.end_month} onChange={e => {
                                            const newExp = [...form.data.experiences];
                                            newExp[index].end_month = e.target.value;
                                            form.setData('experiences', newExp);
                                        }} />
                                        <input type="text" placeholder="Role/Title *" className="border rounded p-2" value={exp.role} onChange={e => {
                                            const newExp = [...form.data.experiences];
                                            newExp[index].role = e.target.value;
                                            form.setData('experiences', newExp);
                                        }} />
                                        <input type="text" placeholder="Organization *" className="border rounded p-2" value={exp.organization} onChange={e => {
                                            const newExp = [...form.data.experiences];
                                            newExp[index].organization = e.target.value;
                                            form.setData('experiences', newExp);
                                        }} />
                                        <textarea placeholder="Description" className="border rounded p-2 col-span-2" rows={3} value={exp.description} onChange={e => {
                                            const newExp = [...form.data.experiences];
                                            newExp[index].description = e.target.value;
                                            form.setData('experiences', newExp);
                                        }} />
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
                                {['special_needs', 'work_from_home', 'swimming', 'driving'].map(skill => (
                                    <label key={skill} className="flex items-center gap-2 p-3 bg-gray-50 border rounded mb-2 cursor-pointer">
                                        <input type="checkbox" checked={form.data.skills[skill]} onChange={e => form.setData('skills', { ...form.data.skills, [skill]: e.target.checked })} />
                                        <span>{skill.replace(/_/g, ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                    </label>
                                ))}
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-3">Other Qualifications</h3>
                                <textarea
                                    placeholder="Any additional skills or certifications..."
                                    className="border rounded p-2 w-full"
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
                                    <div className="grid grid-cols-2 gap-4">
                                        <input type="text" placeholder="Full Name *" className="border rounded p-2" value={ref.name} onChange={e => {
                                            const newRefs = [...form.data.references];
                                            newRefs[index].name = e.target.value;
                                            form.setData('references', newRefs);
                                        }} />
                                        <input type="email" placeholder="Email *" className="border rounded p-2" value={ref.email} onChange={e => {
                                            const newRefs = [...form.data.references];
                                            newRefs[index].email = e.target.value;
                                            form.setData('references', newRefs);
                                        }} />
                                        <input type="tel" placeholder="Phone" className="border rounded p-2" value={ref.phone} onChange={e => {
                                            const newRefs = [...form.data.references];
                                            newRefs[index].phone = e.target.value;
                                            form.setData('references', newRefs);
                                        }} />
                                        <input type="text" placeholder="Relationship *" className="border rounded p-2" value={ref.relationship} onChange={e => {
                                            const newRefs = [...form.data.references];
                                            newRefs[index].relationship = e.target.value;
                                            form.setData('references', newRefs);
                                        }} />
                                        <select className="border rounded p-2" value={ref.years_known} onChange={e => {
                                            const newRefs = [...form.data.references];
                                            newRefs[index].years_known = e.target.value;
                                            form.setData('references', newRefs);
                                        }}>
                                            <option value="">Years Known *</option>
                                            <option value="<1">Less than 1 year</option>
                                            <option value="1-3">1-3 years</option>
                                            <option value="3-5">3-5 years</option>
                                            <option value="5-10">5-10 years</option>
                                            <option value="10+">10+ years</option>
                                        </select>
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
                                {['north_county', 'south_east_county', 'flexible'].map(loc => (
                                    <label key={loc} className="flex items-center gap-2 p-3 bg-gray-50 border rounded mb-2 cursor-pointer">
                                        <input type="checkbox" checked={form.data.location[loc]} onChange={e => form.setData('location', { ...form.data.location, [loc]: e.target.checked })} />
                                        <span>{loc.replace('_', ' ').replace(/\b\w/g, l => l.toUpperCase())}</span>
                                    </label>
                                ))}
                            </div>

                            <div>
                                <h3 className="text-lg font-semibold mb-3">Age Groups</h3>
                                <p className="text-sm text-gray-600 mb-3">Check each age group you feel comfortable caring for.</p>
                                {['babies', 'toddlers', 'preschool', 'school_age'].map(age => (
                                    <label key={age} className="flex items-start gap-2 p-3 bg-gray-50 border rounded mb-2 cursor-pointer">
                                        <input type="checkbox" checked={form.data.age_groups[age]} onChange={e => form.setData('age_groups', { ...form.data.age_groups, [age]: e.target.checked })} />
                                        <div>
                                            <span className="font-medium">{age.charAt(0).toUpperCase() + age.slice(1).replace('_', ' ')}</span>
                                            <p className="text-sm text-gray-600">I am comfortable caring for this age group.</p>
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
                                    <p className="text-sm text-gray-600">{form.data.personal.address}</p>
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

                            <label className="flex items-center gap-2">
                                <input type="checkbox" checked={form.data.terms.agree} onChange={e => form.setData('terms', { ...form.data.terms, agree: e.target.checked })} />
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
                                <p className="text-sm text-gray-600 mb-4">I certify under penalty of perjury that the answers given herein are true and complete...</p>
                                <input type="text" placeholder="Typed Signature *" className="border rounded p-2 w-full mb-2" value={form.data.verification.signature} onChange={e => form.setData('verification', { ...form.data.verification, signature: e.target.value })} />
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" checked={form.data.verification.agree} onChange={e => form.setData('verification', { ...form.data.verification, agree: e.target.checked })} />
                                    <span className="text-sm">Typing my name constitutes a signature.</span>
                                </label>
                            </div>

                            <div className="border-l-4 border-coral pl-4 mb-6">
                                <h3 className="text-lg font-semibold mb-2">Caregiver Statement of Agreement</h3>
                                <p className="text-sm text-gray-600 mb-4">I understand that I am working as an independent contractor for Sitterwise, Inc...</p>
                                <input type="text" placeholder="Typed Signature *" className="border rounded p-2 w-full mb-2" value={form.data.agreement.signature} onChange={e => form.setData('agreement', { ...form.data.agreement, signature: e.target.value })} />
                                <label className="flex items-center gap-2">
                                    <input type="checkbox" checked={form.data.agreement.agree} onChange={e => form.setData('agreement', { ...form.data.agreement, agree: e.target.checked })} />
                                    <span className="text-sm">Typing my name constitutes a signature.</span>
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
