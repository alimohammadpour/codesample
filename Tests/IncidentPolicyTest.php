<?php

namespace Tests\Feature\Correlation;

use App\Jobs\CorrelationJob;
use App\Models\Core\ApiSet;
use App\Models\Core\ApiRoute;
use App\Models\Correlation\IncidentAction;
use App\Models\Correlation\IncidentPolicy;
use App\Models\Logger\Plugin;
use App\Models\Logger\PluginSubCategory;
use App\Models\Report\Collector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

use function PHPUnit\Framework\assertTrue;

class IncidentPolicyTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setup();
        $this->signIn();
    }

    /** @test */
    public function a_user_can_get_all_policies()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create();

        $api_set = ApiSet::where('name', 'Policies List')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-list')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        
        $this->assertEquals($fakePolicy->id, $response->original->first()->id);
    }

    /** @test */
    public function get_plugins_list_when_a_user_creates_a_policy()
    {
        $fakePlugin = factory(Plugin::class)->create();

        $api_set = ApiSet::where('name', 'Policy Create')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/correlation/policy/create/plugins/' . explode(' ', $fakePlugin->name)[0], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->greaterThanOrEqual(1, $response->original->count());
        $this->assertContains($fakePlugin->name, $response->original->pluck('name')->toArray());
    }

    /** @test */
    public function get_events_list_when_a_user_creates_a_policy()
    {
        $fakeEvent = factory(PluginSubCategory::class)->create();

        $api_set = ApiSet::where('name', 'Policy Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-create-pluginSids')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [], [
            'plugin_id' => $fakeEvent->plugin_id, 
            'pattern'   => explode(' ', $fakeEvent->name)[0]
        ])->assertStatus(200);

        $this->assertArraySubset($response->original->first()->toArray(), $fakeEvent->toArray());
    }

    /** @test */
    public function get_collectors_list_when_a_user_creates_a_policy()
    {
        $fakeCollector = factory(Collector::class)->create();

        $api_set = ApiSet::where('name', 'Policy Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-create-getCollectors')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertContains($fakeCollector->id, $response->original->pluck('id')->toArray());
    }

    /** @test */
    public function get_actions_list_when_a_user_creates_a_policy()
    {
        $fakeAction = factory(IncidentAction::class)->create();

        $api_set = ApiSet::where('name', 'Policy Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-create-getActions')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertContains($fakeAction->id, $response->original->pluck('id')->toArray());
    }

    /** @test */
    public function a_user_can_get_enabled_policies_list()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create(['enable' => true]);

        $api_set = ApiSet::where('name', 'Policies List')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-enabled-params')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        
        $this->assertContains($fakePolicy->id, $response->original->pluck('id')->toArray());
    }

    /** @test */
    public function a_user_can_create_an_incident_policy()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('arrayParamsForApiCallCreation')->raw();

        $api_set = ApiSet::where('name', 'Policy Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-store')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, $fakePolicy)->assertStatus(200);

        $pluginId = (int)explode(' - ', $fakePolicy['params']['data_source_events'][0]['plugin_id'])[0];
        $fakePolicyParams = [
            'plugin_id'  => $pluginId,
            'plugin_sid' => [
                PluginSubCategory::where([
                    ['plugin_id' , $pluginId], 
                    ['name' , $fakePolicy['params']['data_source_events'][0]['plugin_sid'][0]]
                ])->first()->plugin_sid
            ]
        ];

        $policy = IncidentPolicy::where([
            ['name', $fakePolicy['name']],
            ['action', $fakePolicy['action']],
            ['params->data_source_events', DB::raw("CAST('". json_encode([$fakePolicyParams]) ."' AS JSON)")],
        ])->first();
        $this->assertNotNull($policy);
    }

    /** @test */
    public function a_user_can_load_a_policy()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/correlation/policy/load/' . $fakePolicy->id, [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->assertEquals($response->original->first()->id, $fakePolicy->id);
    }
    
    /** @test */
    public function a_user_can_update_an_incident_policy()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create();

        $updatedPolicy = factory(IncidentPolicy::class)->states('arrayParamsForApiCallCreation')->raw();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->post('api/correlation/policy/update/' . $fakePolicy->id, $updatedPolicy, [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        $pluginId = (int)explode(' - ', $updatedPolicy['params']['data_source_events'][0]['plugin_id'])[0];
        $updatedPolicy['params']['data_source_events'][0] = [
            'plugin_id'  => $pluginId,
            'plugin_sid' => [
                PluginSubCategory::where([
                    ['plugin_id' , $pluginId], 
                    ['name' , $updatedPolicy['params']['data_source_events'][0]['plugin_sid'][0]]
                ])->first()->plugin_sid
            ]
        ];

        $this->assertEquals(md5(json_encode($updatedPolicy['params'])), md5($response->original->params));
    }

    /** @test */
    public function a_user_can_delete_an_incident_policy()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create();

        $api_set = ApiSet::where('name', 'Policy Delete')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->delete('api/correlation/policy/delete/' . $fakePolicy->id, [], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        $this->assertDatabaseMissing('incident_policies', [
            'id' => $fakePolicy->id
        ]);
    }

    /** @test */
    public function a_user_can_restart_engine()
    {
        $api_set = ApiSet::where('name', 'Correlation policy engine restart')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-engine-restart')->first();
        $this->assertNotNull($api_route);

        Bus::fake();

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        Bus::assertDispatched(CorrelationJob::class);
    }

    /** @test */
    public function a_user_can_enable_policy()
    {
        $fakePolicy = factory(IncidentPolicy::class)->states('jsonParamsForFakeCreation')->create();

        $api_set = ApiSet::where('name', 'Policy Enable/Disable')->first();
        $this->assertNotNull($api_set);

        Bus::fake();

        $response = $this->grantAccessTo($api_set)->post('api/correlation/policy/enable/' . $fakePolicy->id, [], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->assertDatabaseHas('incident_policies', [
            'id'     => $fakePolicy->id,
            'enable' => !$fakePolicy->enable
        ]);

        Bus::assertDispatched(CorrelationJob::class);
    }

    /** @test */
    public function get_plugins_list_when_a_user_updates_a_policy()
    {
        $fakePlugin = factory(Plugin::class)->create();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/correlation/policy/edit/plugins/' . explode(' ', $fakePlugin->name)[0], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);

        $this->greaterThanOrEqual(1, $response->original->count());
        $this->assertContains($fakePlugin->name, $response->original->pluck('name')->toArray());
    }

    /** @test */
    public function get_events_list_when_a_user_updates_a_policy()
    {
        $fakeEvent = factory(PluginSubCategory::class)->create();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-edit-pluginSids')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [], [
            'plugin_id' => $fakeEvent->plugin_id, 
            'pattern'   => explode(' ', $fakeEvent->name)[0]
        ])->assertStatus(200);

        $this->assertArraySubset($response->original->first()->toArray(), $fakeEvent->toArray());
    }

    /** @test */
    public function get_collectors_list_when_a_user_updates_a_policy()
    {
        $fakeCollector = factory(Collector::class)->create();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-edit-getCollectors')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertContains($fakeCollector->id, $response->original->pluck('id')->toArray());
    }

    /** @test */
    public function get_actions_list_when_a_user_updates_a_policy()
    {
        $fakeAction = factory(IncidentAction::class)->create();

        $api_set = ApiSet::where('name', 'Policy Edit')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-correlation-policy-edit-getActions')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertContains($fakeAction->id, $response->original->pluck('id')->toArray());
    }
}
