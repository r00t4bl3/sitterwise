export default function PasswordRequirements() {
    return (
        <ul className="mt-1 list-inside list-disc space-y-0.5 text-xs text-muted-foreground">
            <li>At least 8 characters</li>
            <li>Uppercase and lowercase letters</li>
            <li>At least one number</li>
            <li>At least one symbol</li>
        </ul>
    );
}
