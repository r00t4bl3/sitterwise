<?php

namespace App\Mail;

use Illuminate\Contracts\Queue\ShouldQueue;

class CaregiverBirthdayMail extends SendGridDynamicMail implements ShouldQueue
{
    public function __construct(
        public string $caregiverFirstName,
    ) {}

    protected function templateId(): string
    {
        // TODO(deploy): provision the branded "Happy Birthday" SendGrid dynamic
        // template and set its id here. Until then, local/test render the Blade
        // view below; production uses the SendGrid template id returned here.
        return 'd-REPLACE_WITH_BIRTHDAY_TEMPLATE_ID';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_first_name' => $this->caregiverFirstName,
        ];
    }

    protected function subjectLine(): string
    {
        return "Happy Birthday, {$this->caregiverFirstName}!";
    }

    protected function bladeView(): ?string
    {
        return 'emails.caregiver-birthday';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
