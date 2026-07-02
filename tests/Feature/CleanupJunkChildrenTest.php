<?php

use App\Models\BookingGroup;
use App\Models\Client;
use App\Models\ClientChild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->client = Client::factory()->create();
});

describe('cleanup:junk-children command', function () {
    test('removes junk client children with no birth data', function () {
        $junk = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'None',
            'birth_date' => null,
        ]);
        $real = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Alice',
            'birth_date' => '2018-01-01',
        ]);
        $junkNameButHasDob = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'None',
            'birth_date' => '2019-05-01',
        ]);

        $this->artisan('app:cleanup-junk-children')->assertSuccessful();

        expect(ClientChild::find($junk->id))->toBeNull();
        expect(ClientChild::find($real->id))->not->toBeNull();
        expect(ClientChild::find($junkNameButHasDob->id))->not->toBeNull();
    });

    test('strips junk and null entries from booking group children json', function () {
        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'children' => [
                ['name' => 'Alice', 'birth_year' => 2018],
                ['name' => 'None'],
                null,
            ],
        ]);

        $this->artisan('app:cleanup-junk-children')->assertSuccessful();

        $children = json_decode(
            DB::table('booking_groups')->where('id', $group->id)->value('children'),
            true,
        );

        expect($children)->toBe([['name' => 'Alice', 'birth_year' => 2018]]);
    });

    test('empties a group whose children is a junk string', function () {
        $group = BookingGroup::factory()->create(['client_id' => $this->client->id]);
        DB::table('booking_groups')->where('id', $group->id)->update(['children' => '"None"']);

        $this->artisan('app:cleanup-junk-children')->assertSuccessful();

        $children = json_decode(
            DB::table('booking_groups')->where('id', $group->id)->value('children'),
            true,
        );

        expect($children)->toBe([]);
    });

    test('purge permanently deletes rows a prior soft-delete run removed', function () {
        $junk = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'None',
            'birth_date' => null,
        ]);

        // First run soft-deletes it (recoverable).
        $this->artisan('app:cleanup-junk-children')->assertSuccessful();
        expect(ClientChild::withTrashed()->find($junk->id)->trashed())->toBeTrue();

        // Second run with --purge sweeps up the soft-deleted junk permanently.
        $this->artisan('app:cleanup-junk-children --purge')->assertSuccessful();
        expect(ClientChild::withTrashed()->find($junk->id))->toBeNull();
    });

    test('purge leaves unrelated soft-deleted children alone', function () {
        $realDeleted = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'Alice',
            'birth_date' => '2018-01-01',
        ]);
        $realDeleted->delete();

        $this->artisan('app:cleanup-junk-children --purge')->assertSuccessful();

        expect(ClientChild::withTrashed()->find($realDeleted->id))->not->toBeNull();
    });

    test('purge dry-run previews without deleting', function () {
        $junk = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'None',
            'birth_date' => null,
        ]);
        $junk->delete();

        $this->artisan('app:cleanup-junk-children --purge --dry-run')->assertSuccessful();

        expect(ClientChild::withTrashed()->find($junk->id))->not->toBeNull();
    });

    test('dry run reports but changes nothing', function () {
        $junk = ClientChild::factory()->create([
            'client_id' => $this->client->id,
            'name' => 'None',
            'birth_date' => null,
        ]);
        $group = BookingGroup::factory()->create([
            'client_id' => $this->client->id,
            'children' => [['name' => 'None']],
        ]);

        $this->artisan('app:cleanup-junk-children --dry-run')->assertSuccessful();

        expect(ClientChild::find($junk->id))->not->toBeNull();
        expect(DB::table('booking_groups')->where('id', $group->id)->value('children'))
            ->toBe(json_encode([['name' => 'None']]));
    });
});
