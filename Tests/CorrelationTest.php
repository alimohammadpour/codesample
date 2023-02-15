<?php

namespace Tests\Feature\Correlation;

use App\Enums\PluginIdEnum;
use App\Jobs\CorrelationJob;
use App\Models\Core\ApiSet;
use App\Models\Core\ApiRoute;
use App\Models\Correlation\Scenario;
use App\Models\Logger\Plugin;
use App\Models\Logger\PluginSubCategory;
use App\Models\Report\Collector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Bus;

class CorrelationTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setup();
        $this->signIn();
    }

    /** @test */
    public function a_user_can_get_list_of_scenarios()
    {
        $fakeScenario = $this->createFake();
        
        $api_set = ApiSet::where('name', 'Scenarios List')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-list')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        
        $this->assertTrue($response->original->items()[0]->id === $fakeScenario->id);
    }

    /** @test */
    public function a_user_can_get_plugins_list() 
    {
        $fakePlugin = factory(Plugin::class)->create();

        $api_set = ApiSet::where('name', 'Scenario Create')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/correlation/scenario/create/plugins/' . explode(' ', $fakePlugin->name)[0], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->greaterThanOrEqual(1, $response->original->count());
        $this->assertContains($fakePlugin->name, $response->original->pluck('name')->toArray());
    }

    /** @test */
    public function a_user_can_get_events_list()
    {
        $fakeEvent = factory(PluginSubCategory::class)->create();

        $api_set = ApiSet::where('name', 'Scenario Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-create-pluginSids')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [], [
            'plugin_id'=> $fakeEvent->plugin_id, 
            'pattern' => explode(' ', $fakeEvent->name)[0]
            ])->assertStatus(200);

        $this->assertArraySubset($response->original->first()->toArray(), $fakeEvent->toArray());
    }

    /** @test */
    public function a_user_can_get_collectors_list()
    {
        $fakeCollector = factory(Collector::class)->create();

        $api_set = ApiSet::where('name', 'Scenario Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-create-getCollectors')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        
        $this->assertContains($fakeCollector->id, $response->original->pluck('id')->toArray());
    }

    /** @test */
    public function a_user_can_create_a_scenario()
    {
        $fakeScenario = factory(Scenario::class)->raw();
        
        $api_set = ApiSet::where('name', 'Scenario Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-store')->first();
        $this->assertNotNull($api_route);

        $parameters = [
            'field_history' => json_decode($fakeScenario['field_history'], true),
            'tree'          => json_decode($fakeScenario['rules'], true)
        ];
        $response = $this->grantAccessTo($api_set)->requestTo($api_route, $parameters)->assertStatus(200);
    
        $this->assertDatabaseHas('correlation_scenarios', [
            'name'          => $parameters['tree'][0]['value']['name'],
            'priority'      => $parameters['tree'][0]['value']['priority'],
            'rules'         => DB::raw("CAST('". $fakeScenario['rules'] ."' AS JSON)"),
            'field_history' => DB::raw("CAST('". $fakeScenario['field_history'] ."' AS JSON)")
        ]);

        $this->assertDatabaseHas('plugin_sid', [
            'name' => $parameters['tree'][0]['value']['name'],
            'priority' => 1,
            'reliability' => 1
        ]);
    }

    /** @test */
    public function a_user_can_load_a_scenario()
    {
        $fakeScenario = $this->createFake();

        $api_set = ApiSet::where('name', 'Scenario Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/correlation/scenario/load/' . $fakeScenario->id, [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->assertEquals(1, $response->original->count());
        $this->assertEquals($fakeScenario->id, $response->original->first()->id);
    }

    /** @test */
    public function a_user_can_update_a_scenario()
    {
        $fakeScenario = $this->createFake();

        $updatedScenario = factory(Scenario::class)->raw();
        $parameters = [
            'field_history' => json_decode($updatedScenario['field_history'], true),
            'tree'          => json_decode($updatedScenario['rules'], true)
        ];

        $api_set = ApiSet::where('name', 'Scenario Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->post('api/correlation/scenario/update/' . $fakeScenario->id, $parameters, [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->assertDatabaseHas('correlation_scenarios', [
            'name'          => $parameters['tree'][0]['value']['name'],
            'priority'      => $parameters['tree'][0]['value']['priority'],
            'rules'         => DB::raw("CAST('". $updatedScenario['rules'] ."' AS JSON)"),
            'field_history' => DB::raw("CAST('". $updatedScenario['field_history'] ."' AS JSON)")
        ]);

        $this->assertDatabaseHas('plugin_sid', [
            'name'     => $parameters['tree'][0]['value']['name'],
            'priority' => $parameters['tree'][0]['value']['priority']
        ]);
    }

    /** @test */
    public function a_user_can_delete_a_scenario()
    {
        $fakeScenario = $this->createFake();

        $api_set = ApiSet::where('name', 'Scenario Delete')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->delete('api/correlation/scenario/delete/' . $fakeScenario->id, [], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        $this->assertDatabaseMissing('correlation_scenarios', [
            'id' => $fakeScenario
        ]);

        $this->assertDatabaseMissing('plugin_sid', [
            'plugin_id'  => PluginIdEnum::ALARM_PLUGIN_ID,
            'plugin_sid' => $fakeScenario->id
        ]);
    }

    /** @test */
    public function a_user_get_enabled_scenarios()
    {
        $api_set = ApiSet::where('name', 'Scenarios List')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-enabled-xml')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertTrue($response->original->every(function ($scenario) {
            return $scenario->enable;
        }));
    }

    /** @test */
    public function a_user_can_enable_a_scenario()
    {
        $fakeScenario = $this->createFake();

        Bus::fake();

        $api_set = ApiSet::where('name', 'Scenario Enable/Disable')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->post('api/correlation/scenario/enable/' . $fakeScenario->id, [], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        $this->assertDatabaseHas('correlation_scenarios', [
            'id'     => $fakeScenario->id,
            'enable' => !$fakeScenario->enable
        ]);

        Bus::assertDispatched(CorrelationJob::class);
    }

    /** @test */
    public function get_events_when_a_user_creates_a_scenario()
    {
        $plugin = factory(Plugin::class)->create();
        $events = factory(PluginSubCategory::class, 3)->create(['plugin_id' => $plugin->id]);
        
        $api_set = ApiSet::where('name', 'Scenario Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-create-getPluginSids')->first();
        $this->assertNotNull($api_route);

        $plugin_sids = $events->pluck('plugin_sid')->toArray();
        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [
            'plugin_id'    => $plugin->id, 
            'plugins_sids' => $plugin_sids
        ])->assertStatus(200);
        
        $this->assertEquals(3, $response->original['data']->count());

        $this->assertTrue($response->original['data']->every(function ($event) use ($plugin_sids) {
            return in_array($event->plugin_sid, $plugin_sids);
        }));
    }

    /** @test */
    public function get_events_when_a_user_updates_a_scenario()
    {
        $plugin = factory(Plugin::class)->create();
        $events = factory(PluginSubCategory::class, 3)->create(['plugin_id' => $plugin->id]);
        
        $api_set = ApiSet::where('name', 'Scenario Edit')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-scenario-edit-getPluginSids')->first();
        $this->assertNotNull($api_route);

        $plugin_sids = $events->pluck('plugin_sid')->toArray();
        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [
            'plugin_id'    => $plugin->id, 
            'plugins_sids' => $plugin_sids
        ])->assertStatus(200);
        
        $this->assertEquals(3, $response->original['data']->count());

        $this->assertTrue($response->original['data']->every(function ($event) use ($plugin_sids) {
            return in_array($event->plugin_sid, $plugin_sids);
        }));
    }

    private function createFake()
    {
        $fakeScenario = factory(Scenario::class)->create();

        $fakeEvent = factory(PluginSubCategory::class)->create([
            'name' => $fakeScenario->name,
            'plugin_id' => PluginIdEnum::ALARM_PLUGIN_ID,
            'plugin_sid' => $fakeScenario->id,
            'priority' => 1,
            'reliability' => 1
        ]);

        return $fakeScenario;
    }
} 
