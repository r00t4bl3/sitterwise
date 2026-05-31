import { Head, usePage, useForm } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { PhoneInput } from '@/components/ui/phone-input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Spinner } from '@/components/ui/spinner';
import AuthLayout from '@/layouts/auth-layout';
import { login } from '@/routes';
import { store } from '@/routes/register';

export default function Register() {
    const { discovery_sources } = usePage<{
        discovery_sources: Array<{ value: string; label: string }>;
    }>().props;

    const form = useForm({
        first_name: '',
        last_name: '',
        phone: '',
        email: '',
        password: '',
        password_confirmation: '',
        how_did_you_hear: '',
    });

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        form.post(store.url(), {
            onFinish: () => form.reset('password', 'password_confirmation'),
        });
    };

    return (
        <AuthLayout
            title="Create an account"
            description="Enter your details below to create your account"
        >
            <Head title="Register" />
            <form
                onSubmit={submit}
                className="flex flex-col gap-6"
            >
                <div className="grid gap-6">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <div className="grid gap-2">
                            <Label htmlFor="first_name">
                                First name
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="first_name"
                                type="text"
                                required
                                autoFocus
                                tabIndex={1}
                                autoComplete="given-name"
                                value={form.data.first_name}
                                onChange={(e) => form.setData('first_name', e.target.value)}
                                placeholder="First name"
                            />
                            <InputError
                                message={form.errors.first_name}
                                className="mt-2"
                            />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="last_name">
                                Last name
                                <span className="text-red-500">*</span>
                            </Label>
                            <Input
                                id="last_name"
                                type="text"
                                required
                                tabIndex={2}
                                autoComplete="family-name"
                                value={form.data.last_name}
                                onChange={(e) => form.setData('last_name', e.target.value)}
                                placeholder="Last name"
                            />
                            <InputError
                                message={form.errors.last_name}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    <div className="grid gap-2">
                        <PhoneInput
                            value={form.data.phone}
                            onChange={(v) => form.setData('phone', v)}
                            error={form.errors.phone}
                            required
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="email">Email address</Label>
                        <Input
                            id="email"
                            type="email"
                            required
                            tabIndex={4}
                            autoComplete="email"
                            value={form.data.email}
                            onChange={(e) => form.setData('email', e.target.value)}
                            placeholder="email@example.com"
                        />
                        <InputError message={form.errors.email} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password">Password</Label>
                        <PasswordInput
                            id="password"
                            required
                            tabIndex={5}
                            autoComplete="new-password"
                            value={form.data.password}
                            onChange={(e) => form.setData('password', e.target.value)}
                            placeholder="Password"
                        />
                        <InputError message={form.errors.password} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="password_confirmation">
                            Confirm password
                        </Label>
                        <PasswordInput
                            id="password_confirmation"
                            required
                            tabIndex={6}
                            autoComplete="new-password"
                            value={form.data.password_confirmation}
                            onChange={(e) => form.setData('password_confirmation', e.target.value)}
                            placeholder="Confirm password"
                        />
                        <InputError
                            message={form.errors.password_confirmation}
                        />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="how_did_you_hear">
                            How did you hear about us?
                        </Label>
                        <Select
                            value={form.data.how_did_you_hear}
                            onValueChange={(value) => form.setData('how_did_you_hear', value)}
                        >
                            <SelectTrigger tabIndex={7}>
                                <SelectValue placeholder="Select..." />
                            </SelectTrigger>
                            <SelectContent>
                                {discovery_sources.map((source) => (
                                    <SelectItem
                                        key={source.value}
                                        value={source.value}
                                    >
                                        {source.label}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                        <InputError message={form.errors.how_did_you_hear} />
                    </div>

                    <Button
                        type="submit"
                        className="mt-2 w-full"
                        tabIndex={8}
                        disabled={form.processing}
                        data-test="register-user-button"
                    >
                        {form.processing && <Spinner />}
                        Create account
                    </Button>
                </div>

                <div className="text-center text-sm text-muted-foreground">
                    Already have an account?{' '}
                    <TextLink href={login()} tabIndex={9}>
                        Log in
                    </TextLink>
                </div>
            </form>
        </AuthLayout>
    );
}
