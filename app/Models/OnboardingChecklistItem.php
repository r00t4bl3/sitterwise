<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnboardingChecklistItem extends Model
{
    protected $fillable = [
        'caregiver_id',
        'item_key',
        'label',
        'description',
        'completed_at',
    ];

    protected $casts = [
        'completed_at' => 'datetime',
    ];

    public static function seedForCaregiver(Caregiver $caregiver): void
    {
        $items = [
            [
                'item_key' => 'on_pay_setup',
                'label' => 'OnPay Setup',
                'description' => 'Offer letter signed, I-9 and W-4 complete, identity documents uploaded, direct deposit confirmed.',
            ],
            [
                'item_key' => 'background_check',
                'label' => 'Background Check',
                'description' => 'S2Verify completed. You\'re good to go.',
            ],
            [
                'item_key' => 'cpr_uploaded',
                'label' => 'CPR & First Aid Uploaded',
                'description' => 'CPR & First Aid certification uploaded and valid. We\'ll remind you 90 days before renewal.',
            ],
            [
                'item_key' => 'trustline_submitted',
                'label' => 'Trustline Submitted',
                'description' => 'Submit your Trustline application within 7 days of activation. You can work during the state\'s processing period — submission is what\'s required.',
            ],
            [
                'item_key' => 'dress_code',
                'label' => 'Dress Code Acknowledged',
                'description' => 'Review the Sitterwise dress code and confirm you\'ve read it.',
            ],
            [
                'item_key' => 'training_quiz',
                'label' => 'Training Quiz Passed',
                'description' => '10-minute quiz covering Sitterwise policies and best practices.',
            ],
        ];

        foreach ($items as $item) {
            static::firstOrCreate(
                ['caregiver_id' => $caregiver->id, 'item_key' => $item['item_key']],
                $item,
            );
        }
    }

    public function caregiver(): BelongsTo
    {
        return $this->belongsTo(Caregiver::class);
    }
}
