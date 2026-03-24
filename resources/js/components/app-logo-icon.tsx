import type { SVGAttributes } from 'react';

export default function AppLogoIcon(props: SVGAttributes<SVGElement>) {
    return (
        <img
            src="/submark.png"
            alt="Sitterwise"
            className={props.className}
            style={{ ...props.style, objectFit: 'contain' }}
        />
    );
}
