<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Sichikawa\LaravelSendgridDriver\SendGrid;

/**
 * Shared base for emails backed by a branded SendGrid dynamic template.
 *
 * Dual-mode: the SendGrid trait's sendgrid() is a no-op unless
 * config('mail.default') === 'sendgrid'. So in production the branded template
 * is embedded and SendGrid renders it; in local/test (array/log/smtp) the
 * Blade view returned by bladeView() renders instead, keeping preview and
 * content assertions working. See tests/Feature/SendGridDualModeTest.php.
 */
abstract class SendGridDynamicMail extends Mailable
{
    use Queueable, SendGrid, SerializesModels;

    /**
     * The SendGrid dynamic template id (used only when the sendgrid mailer sends).
     */
    abstract protected function templateId(): string;

    /**
     * Snake_case data for the SendGrid dynamic template.
     *
     * @return array<string, mixed>
     */
    abstract protected function templateData(): array;

    /**
     * The email subject.
     */
    abstract protected function subjectLine(): string;

    /**
     * The Blade view rendered by local/test (non-sendgrid) mailers. Return null
     * once the branded template is production-verified and the view is removed.
     */
    protected function bladeView(): ?string
    {
        return null;
    }

    /**
     * Extra data for the Blade view; the mailable's public properties are passed
     * automatically, so this is only for computed values.
     *
     * @return array<string, mixed>
     */
    protected function bladeData(): array
    {
        return [];
    }

    /**
     * Admin-facing emails override this to opt out of the team BCC (they are
     * addressed to the team directly, so a BCC would duplicate).
     */
    protected function shouldBccTeam(): bool
    {
        return true;
    }

    public function envelope(): Envelope
    {
        $this->sendgrid([
            'personalizations' => [
                ['dynamic_template_data' => $this->templateData()],
            ],
            'template_id' => $this->templateId(),
        ]);

        $teamBcc = $this->shouldBccTeam() ? config('mail.team_bcc') : null;

        return new Envelope(
            from: new Address(
                config('mail.from.address', 'admin@sitterwise.io'),
                config('mail.from.name') ?? config('app.name'),
            ),
            subject: $this->subjectLine(),
            bcc: array_values(array_filter([$teamBcc])),
        );
    }

    public function content(): Content
    {
        if ($view = $this->bladeView()) {
            return new Content(view: $view, with: $this->bladeData());
        }

        return new Content(htmlString: ' ');
    }
}
