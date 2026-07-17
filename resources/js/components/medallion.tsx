import React from 'react';

interface MedallionProps {
    tier: 'teal' | 'coral' | 'navy';
    variant: string;
    earned: boolean;
    size?: 'sm' | 'md';
}

const TIER_COLORS = {
    teal: {
        fill: '#84D0D2',
        inner: '#E8F5F5',
        ribbonBg: '#5FBFC2',
        ribbonStroke: '#84D0D2',
        dot: '#5FBFC2',
    },
    coral: {
        fill: '#F48A91',
        inner: '#FDF0F1',
        ribbonBg: '#E96F77',
        ribbonStroke: '#F48A91',
        dot: '#E96F77',
    },
    navy: {
        fill: '#1B3A5C',
        inner: '#1B3A5C',
        ribbonBg: '#1B3A5C',
        ribbonStroke: '#1B3A5C',
        dot: '#1B3A5C',
    },
};

const LOCKED_COLORS = {
    fill: '#ECF0F2',
    inner: '#FAFBFB',
    icon: '#B7C3C9',
    dot: '#DDE4E7',
    dash: '#C3CDD2',
};

function Icon({
    variant,
    fill,
    label,
}: {
    variant: string;
    fill: string;
    label?: string;
}) {
    const commonProps = {
        fill: 'none',
        stroke: fill,
        strokeWidth: 4,
        strokeLinecap: 'round' as const,
        strokeLinejoin: 'round' as const,
    };

    switch (variant) {
        case 'checklist':
            return (
                <>
                    <rect
                        x="50"
                        y="40"
                        width="40"
                        height="52"
                        fill={fill}
                        rx="3"
                    />
                    <path
                        d="M58 56 L63 61 L72 51"
                        {...commonProps}
                        stroke="#FFFFFF"
                    />
                    <path
                        d="M58 72 L63 77 L72 67"
                        {...commonProps}
                        stroke="#FFFFFF"
                    />
                    <rect x="76" y="54" width="9" height="4" fill="#FFFFFF" />
                    <rect x="76" y="70" width="9" height="4" fill="#FFFFFF" />
                </>
            );
        case 'star':
            return (
                <path
                    d="M70 38 L78.8 56.4 L99 59.1 L84.3 73.2 L88 93.2 L70 83.5 L52 93.2 L55.7 73.2 L41 59.1 L61.2 56.4 Z"
                    fill={fill}
                />
            );
        case 'star-filled':
            return (
                <>
                    <path
                        d="M70 34 L76.6 47.8 L91.8 49.8 L80.8 60.4 L83.5 75.4 L70 68.2 L56.5 75.4 L59.2 60.4 L48.2 49.8 L63.4 47.8 Z"
                        fill={fill}
                        transform="translate(70 66) scale(1.6) translate(-70 -55)"
                    />
                    {label && (
                        <text
                            x="70"
                            y="75"
                            textAnchor="middle"
                            fontFamily="'Playfair Display', Georgia, serif"
                            fontWeight={700}
                            fontSize={26}
                            fill={fill === '#FFFFFF' ? '#1B3A5C' : '#FFFFFF'}
                        >
                            {label}
                        </text>
                    )}
                </>
            );
        case 'shield':
            return (
                <>
                    <path
                        d="M70 36 L96 45 L96 66 C96 84 84 94 70 99 C56 94 44 84 44 66 L44 45 Z"
                        fill={fill}
                    />
                    <path
                        d="M58 66 L67 75 L84 56"
                        {...commonProps}
                        stroke="#FFFFFF"
                        strokeWidth={7}
                    />
                </>
            );
        case 'number':
            return null;
        case 'crosshair':
            return (
                <>
                    <circle
                        cx="70"
                        cy="68"
                        r="24"
                        fill="none"
                        stroke={fill}
                        strokeWidth={13}
                    />
                    <rect x="64.5" y="38" width="11" height="13" fill={fill} />
                    <rect x="64.5" y="85" width="11" height="13" fill={fill} />
                    <rect x="40" y="62.5" width="13" height="11" fill={fill} />
                    <rect x="87" y="62.5" width="13" height="11" fill={fill} />
                </>
            );
        case 'building':
            return (
                <>
                    <rect
                        x="48"
                        y="78"
                        width="44"
                        height="16"
                        rx="3"
                        fill={fill}
                    />
                    <rect
                        x="54"
                        y="42"
                        width="32"
                        height="36"
                        rx="2"
                        fill={fill}
                    />
                    <rect
                        x="60"
                        y="48"
                        width="8"
                        height="8"
                        fill={fill !== '#FFFFFF' ? '#FFFFFF' : fill}
                    />
                    <rect
                        x="72"
                        y="48"
                        width="8"
                        height="8"
                        fill={fill !== '#FFFFFF' ? '#FFFFFF' : fill}
                    />
                    <rect
                        x="60"
                        y="60"
                        width="8"
                        height="8"
                        fill={fill !== '#FFFFFF' ? '#FFFFFF' : fill}
                    />
                    <rect
                        x="72"
                        y="60"
                        width="8"
                        height="8"
                        fill={fill !== '#FFFFFF' ? '#FFFFFF' : fill}
                    />
                    <rect
                        x="66"
                        y="72"
                        width="8"
                        height="6"
                        fill={fill === '#1B3A5C' ? '#F48A91' : fill}
                    />
                </>
            );
        case 'sun': {
            const line = { ...commonProps, strokeWidth: 5 };

            return (
                <>
                    <circle cx="70" cy="66" r="18" fill={fill} />
                    <line x1="96.0" y1="66.0" x2="106.0" y2="66.0" {...line} />
                    <line x1="88.4" y1="84.4" x2="95.5" y2="91.5" {...line} />
                    <line x1="70.0" y1="92.0" x2="70.0" y2="102.0" {...line} />
                    <line x1="51.6" y1="84.4" x2="44.5" y2="91.5" {...line} />
                    <line x1="44.0" y1="66.0" x2="34.0" y2="66.0" {...line} />
                    <line x1="51.6" y1="47.6" x2="44.5" y2="40.5" {...line} />
                    <line x1="70.0" y1="40.0" x2="70.0" y2="30.0" {...line} />
                    <line x1="88.4" y1="47.6" x2="95.5" y2="40.5" {...line} />
                    {fill !== '#FAFBFB' && (
                        <path
                            d="M62 66 Q70 74 78 66"
                            fill="none"
                            stroke="#FAFBFB"
                            strokeWidth={3.5}
                            strokeLinecap="round"
                        />
                    )}
                </>
            );
        }
        case 'sparkles':
            return (
                <>
                    <ellipse cx="56" cy="58" rx="13" ry="16" fill={fill} />
                    <ellipse cx="84" cy="55" rx="12" ry="15" fill={fill} />
                    <ellipse cx="70" cy="72" rx="13" ry="16" fill={fill} />
                    <path
                        d="M56 74 Q58 84 54 95 M84 70 Q86 82 82 95 M70 88 Q72 93 70 99"
                        fill="none"
                        stroke={fill}
                        strokeWidth={2.5}
                        strokeLinecap="round"
                    />
                </>
            );
        case 'heart':
            return (
                <>
                    <circle
                        cx="70"
                        cy="78"
                        r="14"
                        fill="none"
                        stroke={fill}
                        strokeWidth={7}
                    />
                    <ellipse cx="70" cy="58" rx="22" ry="9" fill={fill} />
                    <circle cx="70" cy="46" r="9" fill={fill} />
                </>
            );
        case 'cake':
            return (
                <>
                    <text
                        x="70"
                        y="74"
                        textAnchor="middle"
                        fontFamily="'Playfair Display', Georgia, serif"
                        fontWeight={700}
                        fontSize={36}
                        fill={fill}
                    >
                        {label ?? '1'}
                    </text>
                    <text
                        x="70"
                        y="91"
                        textAnchor="middle"
                        fontFamily="'Poppins', sans-serif"
                        fontWeight={600}
                        fontSize={10}
                        letterSpacing={2}
                        fill={fill}
                    >
                        {label && label !== '1' ? 'YEARS' : 'YEAR'}
                    </text>
                    <path
                        d="M40 62 Q44 48 56 42 M40 62 l6 -2 M40 62 l1 -7"
                        fill="none"
                        stroke={fill}
                        strokeWidth={3}
                        strokeLinecap="round"
                    />
                    <path
                        d="M100 62 Q96 48 84 42 M100 62 l-6 -2 M100 62 l-1 -7"
                        fill="none"
                        stroke={fill}
                        strokeWidth={3}
                        strokeLinecap="round"
                    />
                </>
            );
        default:
            return null;
    }
}

const DOT_CENTERS = [
    [124.0, 66.0],
    [118.7, 89.4],
    [103.7, 108.2],
    [82.0, 118.6],
    [58.0, 118.6],
    [36.3, 108.2],
    [21.3, 89.4],
    [16.0, 66.0],
    [21.3, 42.6],
    [36.3, 23.8],
    [58.0, 13.4],
    [82.0, 13.4],
    [103.7, 23.8],
    [118.7, 42.6],
];

export default function Medallion({
    tier,
    variant,
    earned,
    size = 'sm',
}: MedallionProps) {
    const colors = earned ? TIER_COLORS[tier] : LOCKED_COLORS;
    const dotFill = earned ? colors.dot : LOCKED_COLORS.dot;
    const iconColor = earned ? '#FFFFFF' : LOCKED_COLORS.icon;
    // Variants may carry a display number, e.g. "number:50" or "cake:3".
    const [baseVariant, variantParam] = variant.split(':');

    return (
        <svg
            viewBox="0 0 140 152"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            className={size === 'md' ? 'h-20 w-20' : 'h-14 w-14'}
        >
            {/* Ribbon */}
            {earned && (
                <>
                    <polygon
                        points="52,108 40,142 54,134 60,146 66,112"
                        fill={TIER_COLORS[tier].ribbonBg}
                    />
                    <polygon
                        points="88,108 100,142 86,134 80,146 74,112"
                        fill={TIER_COLORS[tier].ribbonBg}
                    />
                </>
            )}

            {/* Outer dots */}
            {DOT_CENTERS.map(([cx, cy], i) => (
                <circle key={i} cx={cx} cy={cy} r={9.5} fill={dotFill} />
            ))}

            {/* Main circle */}
            {earned ? (
                <circle cx={70} cy={66} r={56} fill={colors.fill} />
            ) : (
                <>
                    <circle cx={70} cy={66} r={56} fill={colors.fill} />
                    <circle
                        cx={70}
                        cy={66}
                        r={60}
                        fill="none"
                        stroke={LOCKED_COLORS.dash}
                        strokeWidth={2}
                        strokeDasharray="5 6"
                    />
                </>
            )}

            {/* Inner circle */}
            <circle cx={70} cy={66} r={45} fill={colors.inner} />

            {/* Icon */}
            {baseVariant === 'number' ? (
                <text
                    x={70}
                    y={76}
                    textAnchor="middle"
                    fontFamily="'Playfair Display', Georgia, serif"
                    fontWeight={700}
                    fontSize={(variantParam?.length ?? 2) >= 3 ? 30 : 38}
                    fill={earned ? '#1B3A5C' : LOCKED_COLORS.icon}
                >
                    {variantParam ?? '25'}
                </text>
            ) : (
                <Icon
                    variant={baseVariant}
                    label={variantParam}
                    fill={iconColor}
                />
            )}
        </svg>
    );
}
