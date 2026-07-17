<?php

namespace App\Console\Commands;

use App\Enums\CaregiverStatus;
use App\Mail\CaregiverBirthdayMail;
use App\Models\Caregiver;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

#[Signature('caregivers:send-birthday-greetings')]
#[Description('Email a Happy Birthday card to active caregivers whose birthday is today (once per year).')]
class SendBirthdayGreetings extends Command
{
    public function handle(): int
    {
        $today = now();
        $year = $today->year;

        $caregivers = Caregiver::query()
            ->with('user')
            ->whereNotNull('date_of_birth')
            ->where('status', CaregiverStatus::Active)
            ->whereDoesntHave('activePause')
            ->where(function ($query) use ($year) {
                // Only caregivers who have not already been greeted this year.
                $query->whereNull('last_birthday_greeted_year')
                    ->orWhere('last_birthday_greeted_year', '<', $year);
            })
            ->where(function ($query) use ($today) {
                $query->where(function ($birthday) use ($today) {
                    $birthday->whereMonth('date_of_birth', $today->month)
                        ->whereDay('date_of_birth', $today->day);
                });

                // Feb-29 birthdays never match in a common year, so greet them on Feb 28.
                if (! $today->isLeapYear() && $today->month === 2 && $today->day === 28) {
                    $query->orWhere(function ($leapling) {
                        $leapling->whereMonth('date_of_birth', 2)
                            ->whereDay('date_of_birth', 29);
                    });
                }
            })
            ->get();

        $sent = 0;

        foreach ($caregivers as $caregiver) {
            $email = $caregiver->user?->email;

            if (! $email) {
                continue;
            }

            Mail::to($email)->queue(
                new CaregiverBirthdayMail(caregiverFirstName: $caregiver->first_name),
            );

            $caregiver->update(['last_birthday_greeted_year' => $year]);

            $this->info("Birthday greeting queued for {$email}.");
            $sent++;
        }

        $this->info("Done. Sent {$sent} birthday greeting(s).");

        return self::SUCCESS;
    }
}
