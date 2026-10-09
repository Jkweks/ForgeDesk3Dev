<?php

namespace Tests\Feature;

use App\Models\BusinessJob;
use App\Models\DoorFrameConfiguration;
use App\Models\DoorFrameConfigurationDoor;
use App\Models\DoorFrameDoorConfig;
use App\Models\DoorFrameOpeningSpec;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ConfiguratorMergePairTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Sanctum::actingAs(User::factory()->create(['role' => 'admin', 'is_active' => true]), ['*']);
    }

    private function opening(string $tag, ?string $hand, float $width = 36): DoorFrameConfiguration
    {
        $job = BusinessJob::firstOrCreate(['job_number' => 'MP-1'], ['job_name' => 'Merge job', 'status' => 'active']);
        $config = DoorFrameConfiguration::create(['business_job_id' => $job->id, 'job_scope' => 'door_and_frame', 'status' => 'draft', 'quantity' => 1]);
        DoorFrameConfigurationDoor::create(['configuration_id' => $config->id, 'door_tag' => $tag]);
        if ($hand) {
            DoorFrameOpeningSpec::create([
                'configuration_id' => $config->id, 'opening_type' => 'single', 'hand_single' => $hand,
                'door_opening_width' => $width, 'door_opening_height' => 84, 'hinging' => 'continuous', 'finish' => 'c2',
            ]);
            DoorFrameDoorConfig::create(['configuration_id' => $config->id, 'door_series' => 'STANDARD', 'stile_width' => 'MEDIUM STILE', 'leaf_type' => 'single']);
        }

        return $config;
    }

    private function merge(DoorFrameConfiguration $into, DoorFrameConfiguration $from)
    {
        return $this->postJson("/api/v1/door-frame-configurations/{$into->id}/merge-pair", ['source_id' => $from->id]);
    }

    public function test_two_doors_of_the_same_hand_cannot_be_merged(): void
    {
        $a = $this->opening('1403', 'lhr');
        $b = $this->opening('1409', 'lhr');

        $this->merge($a, $b)->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'opposite hands') && str_contains($m, 'RHR'));
        $this->assertNull($a->fresh()->doors->first()->leaf);
    }

    public function test_an_opening_with_no_hand_set_can_only_take_an_rhr_partner(): void
    {
        $unset = $this->opening('1403', null);

        $this->merge($unset, $this->opening('1409', 'lhr'))->assertStatus(422);
        $this->merge($unset, $this->opening('1410', null))->assertStatus(422);
        $this->merge($unset, $this->opening('1411', 'rhr'))->assertOk();
    }

    public function test_merging_makes_a_pair_with_a_door_config_and_tag_per_leaf(): void
    {
        $lhr = $this->opening('1403', 'lhr', 36);
        $rhr = $this->opening('1409', 'rhr', 36);

        $this->merge($lhr, $rhr)->assertOk();

        $lhr = $lhr->fresh(['doors', 'doorConfigs', 'openingSpecs']);
        $this->assertNull(DoorFrameConfiguration::find($rhr->id));
        $this->assertTrue($lhr->isMergedPair());
        $this->assertSame(1, $lhr->quantity);

        $this->assertSame('pair', $lhr->openingSpecs->opening_type);
        $this->assertSame('rhr_active', $lhr->openingSpecs->hand_pair);
        $this->assertNull($lhr->openingSpecs->hand_single);
        $this->assertEquals(72, $lhr->openingSpecs->door_opening_width);

        // The right-hand leaf is the active one (RHR Active), Door 1.
        $this->assertEquals(['active' => '1409', 'inactive' => '1403'], $lhr->doors->pluck('door_tag', 'leaf')->all());
        $this->assertSame(['active', 'inactive'], $lhr->doorConfigs->pluck('leaf')->all());
        $this->assertSame(['PAIR-RHRA'], $lhr->doorConfigs->pluck('handing')->unique()->values()->all());
    }

    public function test_each_leaf_door_config_saves_on_its_own(): void
    {
        $lhr = $this->opening('1403', 'lhr');
        $this->merge($lhr, $this->opening('1409', 'rhr'))->assertOk();

        \App\Models\ConfiguratorSetting::current()->update(['bottom_gap' => 0.6875]);
        $stile = \App\Models\ConfiguratorDoorType::create(['series' => 'STANDARD', 'stile_name' => 'WIDE STILE', 'stile_height' => 5])->stile_name;
        $payload = ['door_series' => 'STANDARD', 'stile_width' => $stile, 'top_rail_label' => '5"', 'bot_rail_label' => '10"'];

        $this->putJson("/api/v1/door-frame-configurations/{$lhr->id}/door-config", $payload)->assertStatus(422);
        $this->putJson("/api/v1/door-frame-configurations/{$lhr->id}/door-config", $payload + ['leaf' => 'inactive'])->assertOk();

        $byLeaf = $lhr->fresh('doorConfigs')->doorConfigs->keyBy('leaf');
        $this->assertSame('WIDE STILE', $byLeaf['inactive']->stile_width);
        $this->assertSame('MEDIUM STILE', $byLeaf['active']->stile_width);
    }

    public function test_a_merged_pair_cannot_be_switched_back_to_a_single(): void
    {
        $lhr = $this->opening('1403', 'lhr');
        $this->merge($lhr, $this->opening('1409', 'rhr'))->assertOk();

        $this->putJson("/api/v1/door-frame-configurations/{$lhr->id}/opening-specs", [
            'opening_type' => 'single', 'hand_single' => 'lhr', 'door_opening_width' => 36, 'door_opening_height' => 84,
            'hinging' => 'continuous', 'finish' => 'c2',
        ])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'merged pair'));
    }

    public function test_merged_leaves_produce_one_door_line_per_tag_and_one_frame_line(): void
    {
        $lhr = $this->opening('1403', 'lhr');
        $this->merge($lhr, $this->opening('1409', 'rhr'))->assertOk();

        $this->assertSame(
            [['type' => 'Door', 'tag' => '1409'], ['type' => 'Frame', 'tag' => '1409'], ['type' => 'Door', 'tag' => '1403']],
            $lhr->fresh()->desiredElevationLines(),
        );
    }
}
