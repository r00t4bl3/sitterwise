import {
    EmbeddedCheckoutProvider,
    EmbeddedCheckout,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';

const stripePromise = loadStripe(
    import.meta.env.VITE_STRIPE_KEY || 'pk_test_placeholder',
);

interface StripeCheckoutProps {
    clientSecret: string;
}

export function StripeCheckout({ clientSecret }: StripeCheckoutProps) {
    return (
        <EmbeddedCheckoutProvider
            stripe={stripePromise}
            options={{ clientSecret }}
        >
            <EmbeddedCheckout />
        </EmbeddedCheckoutProvider>
    );
}
