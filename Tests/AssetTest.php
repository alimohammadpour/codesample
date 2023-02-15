<?php

namespace Tests\Feature\Asset;

use App\Models\Asset\Asset;
use App\Models\Core\ApiSet;
use App\Models\Core\ApiRoute;
use App\Models\Logger\DeviceType;
use App\Http\Controllers\Report\Data\ReportDataController;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;
use Illuminate\Http\UploadedFile as File;
use App\Imports\ImportAssets;
use App\Models\Report\Collector;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;
use Mockery;
use Mockery\MockInterface;

class AssetTest extends TestCase
{
    use DatabaseTransactions;

    public function setUp(): void
    {
        parent::setup();
        $this->signIn();
    }

    /**
     * Used in functions like search or update, 
     * that we need for a asset instance in database
     */
    private function createAsset()
    {
        $api_set = ApiSet::where('name', 'Asset Create')->first();

        $api_route = ApiRoute::where('name', 'api-assets-store')->first();

        // avoid uploading fake icon
        Storage::fake('asset_icons');

        $asset = factory(Asset::class)->states('apiCalls')->raw();
        $response = $this->grantAccessTo($api_set)->requestTo($api_route, $asset);
        return $response->original;
    }

    /**
     * Get random substring from given string
     */
    private function getRandomSubstring($string)
    {
        $start = rand(0, mb_strlen($string));
        $length = rand($start, mb_strlen($string));
        return substr($string, $start, $length);
    }

    /** @test */
    public function a_user_searchs_an_asset()
    {
        $fakeAsset = $this->createAsset();
        
        $api_set = ApiSet::where('name', 'Assets List')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-index')->first();
        $this->assertNotNull($api_route);

        $fakeAssetService = json_decode($fakeAsset['services'])[0];
        $searchForm = [
            'hostname'                 => $this->getRandomSubstring($fakeAsset['hostname']),
            'ip'                       => $this->getRandomSubstring($fakeAsset['ip']),
            'collector_id'             => $fakeAsset['collector_id'],
            'description'              => $this->getRandomSubstring($fakeAsset['description']),
            'asset_value'              => $fakeAsset['asset_value'],
            'asset_software_id'        => json_decode($fakeAsset['softwares'])[0]->id,
            'fqdn'                     => $this->getRandomSubstring($fakeAsset['fqdn']),
            'cpu'                      => $this->getRandomSubstring($fakeAsset['cpu']),
            'ram'                      => $this->getRandomSubstring($fakeAsset['ram']),
            'hdd'                      => $this->getRandomSubstring($fakeAsset['hdd']),
            'is_notification_excluded' => $fakeAsset['is_notification_excluded']
        ];

        $searchString = '';

        foreach ($searchForm as $field => $value) {
            if ($value) $searchString .= "$field:$value,";
        }

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, [], [
            'fields' => 'assets.*',
            'page'   => 1,
            'sort'   => '-updated_at',
            'equal'  => '',
            'search' => $searchString,
            'limit'  => 5
        ])->assertStatus(200);

        $searchResult = $response->original->items();

        // search query should returns only 1 asset($fakeAsset)
        $this->assertCount(1, $searchResult);

        $asset = $searchResult[0];
        
        // check if returned asset and fakeAsset are the same
        $this->assertTrue($asset->is($fakeAsset));

        return;
    }

    /** @test */
    public function a_user_can_load_an_asset()
    {
        $fakeAsset = $this->createAsset();

        $api_set = ApiSet::where('name', 'Asset Edit')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->get('api/assets/' . $fakeAsset->id, [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        // check if returned asset and fakeAsset are the same
        $this->assertTrue($response->original->is($fakeAsset));
    }

    /** @test */
    public function a_user_can_delete_an_asset()
    {
        $fakeAsset = $this->createAsset();

        $api_set = ApiSet::where('name', 'Asset Delete')->first();
        $this->assertNotNull($api_set);
        $response = $this->grantAccessTo($api_set)->delete('api/assets/' . $fakeAsset->id, [], [
            'Authorization' => 'Bearer ' . $this->token
        ])->assertStatus(200);
        
        $this->assertEmpty(Storage::disk('asset_icons')->files());

        $this->assertDatabaseMissing('assets', [
            'id' => $fakeAsset->id
        ]);

        $this->assertDatabaseMissing('assets_asset_users', [
            'asset_id' => $fakeAsset->id,
        ]);

        $this->assertDatabaseMissing('assets_asset_softwares', [
            'asset_id' => $fakeAsset->id,
        ]);

        $this->assertDatabaseMissing('assets_asset_services', [
            'asset_id' => $fakeAsset->id
        ]);
    }

    /** @test */
    public function get_device_types_when_a_user_creates_an_asset()
    {
        $api_set = ApiSet::where('name', 'Asset Create')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-create-getDeviceTypes')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route);

        $this->assertEquals($response->original->count(), DeviceType::count());
    }

    /** @test */
    public function get_device_types_when_a_user_edits_an_asset()
    {
        $api_set = ApiSet::where('name', 'Asset Edit')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-edit-getDeviceTypes')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route);

        $this->assertEquals($response->original->count(), DeviceType::count());
    }

    /** @test */
    public function a_user_can_imports_file()
    {
        $api_set = ApiSet::where('name', 'Asset Import')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-import')->first();
        $this->assertNotNull($api_route);

        Excel::fake();
        
        $file = File::fake()->create('test-import.xlsx');

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, ['file' => $file])->assertStatus(200);
        
        Excel::assertImported($file->name, function(ImportAssets $import) {
            return true;
        });
    }

    /** @test */
    public function a_user_can_exports_assets()
    {
        $api_set = ApiSet::where('name', 'Asset Export')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-export')->first();
        $this->assertNotNull($api_route);

        Excel::fake();

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        // Cannot use assertDownloaded function that suggested by Excel, because filename generated at controller side.
        // Excel::download method returns BinaryFileResponse
        $this->assertTrue(
            $response->baseResponse instanceof BinaryFileResponse && 
            is_file($response->baseResponse->getFile())
        );
    }

    /** @test */
    public function a_user_can_get_export_sample()
    {
        $api_set = ApiSet::where('name', 'Asset Import')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-export-sample')->first();
        $this->assertNotNull($api_route);

        Excel::fake();
        
        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        // Argument is a special filename that used in controller to get import sample.
        Excel::assertDownloaded('Asset_import_sample.xlsx');
    }

    /** @test */
    public function a_user_can_imports_logsources_from_elasticsearch()
    {
        if (send_curl(config('elastic.event.host') . ':' . config('elastic.event.port')) === "false")
            $this->markTestSkipped('Could not connect to host, Elasticsearch down?');

        /**
         * Overload the ReportDataController class using Mockery and 
         * then change the implementation of the getAllLogSources method
         */
        $getAllLogsourcesResultSample = [
            'ip'           => '192.168.101.116',
            'collector_id' => 1,
            'asset_value'  => 2
        ];
        $mock = Mockery::mock(ReportDataController::class);
        $mock->shouldReceive('getAllLogSources')
            ->once()
            ->andReturn($getAllLogsourcesResultSample);

        $this->app->instance(ReportDataController::class, $mock);

        $api_set = ApiSet::where('name', 'Asset Import')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-assets-import-log-sources')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);

        $this->assertDatabaseHas('assets', $getAllLogsourcesResultSample);
    }

    /** @test */
    public function a_user_can_includes_or_excludes_an_asset_from_notification()
    {
        $fakeAsset = factory(Asset::class)->create();

        $api_set = ApiSet::where('name', 'Include/Exclude From Notification')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-asset-includeExclude-from-notification')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route, ['id' => $fakeAsset->id])->assertStatus(200);
        
        $this->assertDatabaseHas('assets', [
            'id'                       => $fakeAsset->id,
            'is_notification_excluded' => !$fakeAsset->is_notification_excluded
        ]);
    }

    /** @test */
    public function a_user_can_gets_asset_notification_users()
    {
        $api_set = ApiSet::where('name', 'Asset Notification Setting View')->first();
        $this->assertNotNull($api_set);

        $api_route = ApiRoute::where('name', 'api-asset-notificationSettings-getUsers')->first();
        $this->assertNotNull($api_route);

        $response = $this->grantAccessTo($api_set)->requestTo($api_route)->assertStatus(200);
        $data = $response->original['data'];

        $this->assertNotNull($data);
        $this->assertTrue(collect($data)->contains('label', $this->user->email));
    }
}
