import {
    Elements,
    CardElement,
    useStripe,
    useElements,
} from '@stripe/react-stripe-js';
import { loadStripe } from '@stripe/stripe-js';
import { useState } from 'react';

const stripePromise = loadStripe(
    import.meta.env.VITE_STRIPE_KEY || 'pk_test_placeholder',
);

interface StripeCardInputProps {
    onPaymentMethodReady: (paymentMethodId: string | null) => void;
    error?: string;
}

function CardInput({ onPaymentMethodReady, error }: StripeCardInputProps) {
    const stripe = useStripe();
    const elements = useElements();
    const [cardReady, setCardReady] = useState(false);
    const [processing, setProcessing] = useState(false);

    const handleChange = async (event: { complete: boolean; error?: { message?: string } }) => {
        const cardElement = elements?.getElement(CardElement);

        if (!cardElement || !stripe) {
            return;
        }

        if (!event.complete) {
            onPaymentMethodReady(null);
            setCardReady(false);

            return;
        }

        setProcessing(true);

        try {
            const { paymentMethod } = await stripe.createPaymentMethod({
                type: 'card',
                card: cardElement,
            });

            if (paymentMethod) {
                onPaymentMethodReady(paymentMethod.id);
                setCardReady(true);
            } else {
                onPaymentMethodReady(null);
                setCardReady(false);
            }
        } catch {
            onPaymentMethodReady(null);
            setCardReady(false);
        } finally {
            setProcessing(false);
        }
    };

    return (
        <div className="space-y-2">
            <CardElement
                onChange={handleChange}
                options={{
                    style: {
                        base: {
                            fontSize: '16px',
                            color: '#424770',
                            '::placeholder': {
                                color: '#aab7c4',
                            },
                        },
                        invalid: {
                            color: '#9e2146',
                        },
                    },
                }}
            />
            {error && <p className="text-sm text-destructive">{error}</p>}
            {cardReady && !processing && (
                <p className="text-xs text-green-600">Card ready for payment</p>
            )}
            {processing && (
                <p className="text-xs text-muted-foreground">Processing card...</p>
            )}
        </div>
    );
}

export function StripeCardInput(props: StripeCardInputProps) {
    return (
        <Elements stripe={stripePromise}>
            <CardInput {...props} />
        </Elements>
    );
}