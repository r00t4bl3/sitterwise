<?php

namespace App\Mail;

use App\Models\Caregiver;

class TrustlineReimbursementEarnedMail extends SendGridDynamicMail
{
    public function __construct(
        public Caregiver $caregiver,
        public int $completedJobs,
        public int $rewardAmount,
    ) {}

    protected function templateId(): string
    {
        // TODO: provision the branded SendGrid dynamic template and set its id here.
        return 'd-trustline-reimbursement-earned';
    }

    protected function templateData(): array
    {
        return [
            'caregiver_name' => trim($this->caregiver->first_name.' '.$this->caregiver->last_name),
            'completed_jobs' => $this->completedJobs,
            'reward_amount' => number_format($this->rewardAmount),
            'caregiver_url' => route('caregivers.show', $this->caregiver),
        ];
    }

    protected function subjectLine(): string
    {
        $name = trim($this->caregiver->first_name.' '.$this->caregiver->last_name);

        return "Trustline reimbursement earned — {$name}";
    }

    protected function bladeView(): ?string
    {
        return 'emails.trustline-reimbursement-earned';
    }

    protected function shouldBccTeam(): bool
    {
        return false;
    }
}
