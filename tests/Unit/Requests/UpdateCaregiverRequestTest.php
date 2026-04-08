<?php

use App\Http\Requests\UpdateCaregiverRequest;
use App\Models\CaregiverStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\Validator;
use Tests\TestCase;

class UpdateCaregiverRequestTest extends TestCase
{
    use RefreshDatabase;

    private function rules(): array
    {
        return (new UpdateCaregiverRequest)->rules();
    }

    public function test_requires_status_id()
    {
        $data = ['status_id' => null];
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
    }

    public function test_requires_valid_status_id()
    {
        $data = ['status_id' => 999];
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
    }

    public function test_valid_status_id_passes()
    {
        $status = CaregiverStatus::factory()->create();
        $data = ['status_id' => $status->id];
        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_first_name_requires_last_name_when_present()
    {
        $data = [
            'first_name' => 'John',
            'last_name' => '',
        ];
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
    }

    public function test_phone_is_nullable()
    {
        $status = CaregiverStatus::factory()->create();
        $data = [
            'status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone' => null,
        ];
        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_rating_within_range()
    {
        $status = CaregiverStatus::factory()->create();
        $data = [
            'status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'rating' => 5,
        ];
        $validator = $this->validate($data);
        $this->assertTrue($validator->passes());
    }

    public function test_rating_out_of_range_fails()
    {
        $status = CaregiverStatus::factory()->create();
        $data = [
            'status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'rating' => 6,
        ];
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
    }

    public function test_profile_photo_must_be_image()
    {
        $status = CaregiverStatus::factory()->create();
        $data = [
            'status_id' => $status->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'profile_photo' => 'not-an-image',
        ];
        $validator = $this->validate($data);
        $this->assertFalse($validator->passes());
    }

    private function validate(array $data): Validator
    {
        return Illuminate\Support\Facades\Validator::make($data, $this->rules());
    }
}
