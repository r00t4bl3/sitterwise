<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class UpdateProfilePhotoRequest extends FormRequest
{
    /**
     * Self-service profile-photo upload is limited to admins/superadmins for now.
     */
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && ($user->isAdmin() || $user->isSuperAdmin());
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'profile_photo' => ['required', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:1024'],
        ];
    }
}
