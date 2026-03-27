interface SpecialtyTagProps {
    name: string;
}

const specialtyColors: Record<string, { bg: string; text: string }> = {
    Babies: { bg: '#E0F7FA', text: '#006064' },
    Toddlers: { bg: '#E8F5E9', text: '#2E7D32' },
    Preschool: { bg: '#FFF3E0', text: '#E65100' },
    'School Age': { bg: '#EDE7F6', text: '#4527A0' },
    'Special Needs': { bg: '#FCE4EC', text: '#880E4F' },
};

export function SpecialtyTag({ name }: SpecialtyTagProps) {
    const style = specialtyColors[name] || { bg: '#E8F5F5', text: '#1B3A5C' };

    return (
        <span
            className="inline-block rounded-[10px] px-2 py-0.5 text-[10px] font-medium"
            style={{ backgroundColor: style.bg, color: style.text }}
        >
            {name}
        </span>
    );
}