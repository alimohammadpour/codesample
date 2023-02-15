<?php

namespace App\Http\Controllers\Api\Asset;

use App\Classes\Nmap\Nmap;
use App\Exceptions\AssetManagement\MaxScanProcessException;
use App\Http\Controllers\ApiController;
use App\Http\Requests\Asset\CreateAssetDiscoveryScanRequest;
use App\Http\Requests\Asset\StoreAssetDiscoveryScanRequest;
use App\Jobs\AssetDiscoverJob;
use App\Interfaces\Asset\AssetRepositoryInterface;
use App\Interfaces\Asset\AssetDiscoveryRepositoryInterface;
use App\Interfaces\Asset\AssetOsRepositoryInterface;
use App\Interfaces\Asset\AssetServiceRepositoryInterface;
use App\Interfaces\Report\CollectorRepositoryInterface;
use App\Transformers\Asset\AssetDiscoveryTransformer;
use App\Transformers\CollectorTransformer;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class AssetDiscoveryController extends ApiController
{

    /**
     * @var int
     */
    private $scan_status_permitted = 2;
    private $assetRepository, $assetDiscoveryRepository, $assetOsRepository, $assetServiceRepository, $collectorRepository;

    public function __construct(AssetRepositoryInterface $assetRepository,
                                AssetDiscoveryRepositoryInterface $assetDiscoveryRepository,
                                AssetOsRepositoryInterface $assetOsRepository,
                                AssetServiceRepositoryInterface $assetServiceRepository,
                                CollectorRepositoryInterface $collectorRepository)
    {
        parent::__construct();
        $this->assetRepository = $assetRepository;
        $this->assetDiscoveryRepository = $assetDiscoveryRepository;
        $this->assetOsRepository = $assetOsRepository;
        $this->assetServiceRepository = $assetServiceRepository;
        $this->collectorRepository = $collectorRepository;
    }
    
    public function index()
    {
        $scans = $this->paginate($this->assetDiscoveryRepository->orderQuery('id', 'desc'));
        return $this->response->paginator($scans, new AssetDiscoveryTransformer());
    }

    /**
     * @param \App\Http\Requests\Asset\CreateAssetDiscoveryRequest $profile
     */
    public function scan(CreateAssetDiscoveryScanRequest $profile)
    {
        $collector = isset($profile->collector) ? $this->collectorRepository->get('name', $profile->collector)->id : null;
        if (!$profile->is_scheduled) {
            $scans_run = $this->assetDiscoveryRepository->getNotPermittedScanCount($this->scan_status_permitted);

            if (intval(config('asset.max_scans_permitted')) >= $scans_run) {

                $path = Storage::disk('discovery')->getAdapter()->getPathPrefix() . 'nmap-scan-output.xml' . $this->user->username . microtime(true) * 1000;
                $new_scan = new Nmap(null, $path,
                    'nmap', $collector, false);

                $new_scan->setTimeout($profile->timeout);
                $new_scan->setTarget(['hosts' => $profile->hosts, 'ports' => $profile->ports]);
                $new_scan->enableOsDetection()->enableServiceInfo();

                $discovery = $this->assetDiscoveryRepository->create([
                    'profile' => $profile->all(),
                    'collector_id' => $collector,
                    'user_id' => Auth::user()->id
                ]);

                AssetDiscoverJob::dispatch($new_scan, $discovery)->onQueue('assetDiscovery');
            } else {
                throw new MaxScanProcessException('Please wait until some scans finish their task.');
            }

        } else {

            $discovery = $this->assetDiscoveryRepository->create([
                'profile' => $profile->all(),
                'collector_id' => $collector,
                'user_id'      => Auth::user()->id,
                'is_scheduled' => $profile->is_scheduled,
                'run_at'       => $profile->run_at,
                'run_period'   => $profile->run_period
            ]);
        }
    }

    /**
     * @param $id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function loadScan($id)
    {
        $scan = $this->assetDiscoveryRepository->findOrFail($id);
        return $this->response->item($scan, new AssetDiscoveryTransformer());
    }

    /**
     * @param $id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function stopScan($id)
    {
        $scan = $this->assetDiscoveryRepository->stopScan($id);
        return $this->response->item($scan, new AssetDiscoveryTransformer());
    }

    public function destroy($id)
    {
        $scan = $this->assetDiscoveryRepository->delete($id);
        return $this->response->item($scan, new AssetDiscoveryTransformer());
    }

    public function getCollectors()
    {
        $collectors = $this->collectorRepository->all();
        return $this->response->collection($collectors, new CollectorTransformer());
    }

    public function store(StoreAssetDiscoveryScanRequest $request)
    {

        foreach ($request->selection as $item) {
            if (isset($item['os'])) {
                $asset_os = $this->assetOsRepository->updateOrCreate(['name' => $item['os']]);
            }

            $asset = $this->assetRepository->updateOrCreate(
                ['ip' => $item['address'], 'collector_id' => $item['collector']],
                [
                    'hostname'    => !empty($item['hostname']) ? $item['hostname'][0] : null,
                    'asset_value' => 2,
                    'os_id'       => isset($item['os']) ? $asset_os->id : null
                ]
            );

            $unique_service_is = [];
            foreach ($item['ports'] as $service) {
                
                $asset_service = $this->assetServiceRepository->updateOrCreate(['name' => $service['service']]);

                if (!in_array($asset_service->id, $unique_service_is)) {
                    $unique_service_is[] = $asset_service->id;
                    $asset->services()->detach();
                }

                $asset->services()->attach([$asset_service->id => [
                    'port'     => $service['number'],
                    'protocol' => $service['protocol']
                ]]);
            }
        }
    }
}
