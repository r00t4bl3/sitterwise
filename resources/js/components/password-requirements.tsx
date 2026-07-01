export default function PasswordRequirements() {
    return (
        <ul className="mt-1 text-xs text-muted-foreground list-disc list-inside space-y-0.5">
            <li>At least 8 characters</li>
            <li>Uppercase and lowercase letters</li>
            <li>At least one number</li>
            <li>At least one symbol</li>
        </ul>
    );
}
