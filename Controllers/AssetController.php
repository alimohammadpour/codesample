<?php

namespace App\Http\Controllers\Api\Asset;

use App\Classes\ChangeNotificationSender;
use App\Enums\LogTypeEnum;
use App\Events\UserLogActionEvent;
use App\Exports\AssetExport;
use App\Http\Controllers\ApiController;
use App\Http\Controllers\Report\Data\ReportDataController;
use App\Http\Requests\Asset\CreateAssetRequest;
use App\Http\Requests\Asset\EditAssetRequest;
use App\Http\Requests\Asset\ImportAssetRequest;
use App\Http\Requests\SaveLogSourceNotificationSettingsRequest;
use App\Http\Requests\UpdateLogSourceNotificationSettingsRequest;
use App\Imports\ImportAssets;
use App\Interfaces\Asset\AssetRepositoryInterface;
use App\Interfaces\Authentication\UserRepositoryInterface;
use App\Interfaces\Logger\DeviceTypeRepositoryInterface;
use App\Interfaces\Notification\NotificationTypeRepositoryInterface;
use App\Interfaces\Report\CollectorRepositoryInterface;
use App\Transformers\Asset\AssetTransformer;
use App\Transformers\CollectorTransformer;
use App\Transformers\DeviceTypeTransformer;
use App\Transformers\Asset\AssetNotificationTypeSettingsTransformer;
use App\Transformers\NotificationTypeTransformer;
use Carbon\Carbon;
use Dingo\Api\Http\Request;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class AssetController extends ApiController
{

    private $assetRepository, $notificationTypeRepository, $deviceTypeRepository, $collectorRepository, $userRepository;

    public function __construct(AssetRepositoryInterface $assetRepository,
                                NotificationTypeRepositoryInterface $notificationTypeRepository,
                                DeviceTypeRepositoryInterface $deviceTypeRepository,
                                CollectorRepositoryInterface $collectorRepository,
                                UserRepositoryInterface $userRepository)
    {
        parent::__construct();
        $this->assetRepository = $assetRepository;
        $this->notificationTypeRepository = $notificationTypeRepository;
        $this->deviceTypeRepository = $deviceTypeRepository;
        $this->collectorRepository = $collectorRepository;
        $this->userRepository = $userRepository;
    }
    /**
     * @return \Dingo\Api\Http\Response
     */
    public function index(Request $request)
    {
        $assets = $request->input('search') ? 
                  $this->assetRepository->search(explode(',', $request->all()['search'])) : 
                  $this->assetRepository->query();
        $assets = $this->paginate($assets);
        return $this->response->paginator($assets, new AssetTransformer());
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    public function assetSearch(Request $request)
    {
        $assets = $request->input('search') ? 
                  $this->assetRepository->search(explode(',', $request->all()['search'])) : 
                  $this->assetRepository->query();
        $assets = $this->paginate($assets);
        return $this->response->paginator($assets, new AssetTransformer());
    }

    /**
     * @param $id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function show($id)
    {

        $asset = $this->assetRepository->findOrFail($id);

        return $this->response->item($asset, new AssetTransformer());
    }

    /**
     * @param CreateAssetRequest $request
     *
     * @return \Dingo\Api\Http\Response
     */
    public function store(CreateAssetRequest $request)
    {
        if ($request->hasFile('icon_file')) {
            $newName = md5(uniqid() . time());
            $request['icon'] = $newName;
            $file = $request->file('icon_file');
            Storage::Disk('asset_icons')->put($newName, file_get_contents($file));
        }

        $asset = $this->assetRepository->create($request->all());

        event(new UserLogActionEvent(LogTypeEnum::ASSET_CREATED, $this->user, $asset->id));

        $this->sendNotification($asset, 'An asset was created');

        return $this->response->item($asset, new AssetTransformer());
    }

    /**
     * @param EditAssetRequest $request
     * @param                  $id
     *
     * @return \Dingo\Api\Http\Response
     */
    public function update(EditAssetRequest $request, $id)
    {

        $asset = $this->assetRepository->update($id, $request->all());
        if ($request->hasFile('icon_file')) {
            $file = $request->file('icon_file');
            $newName = md5(uniqid() . time());
            Storage::Disk('asset_icons')->put($newName, file_get_contents($file));
            $request['icon'] = $newName;
            if ($asset->icon) {
                Storage::Disk('asset_icons')->delete($asset->icon);
            }
        }

        event(new UserLogActionEvent(LogTypeEnum::ASSET_EDITED, $this->user, $asset->id));

        $this->sendNotification($asset, 'An asset was updated');

        return $this->response->item($asset, new AssetTransformer());
    }

    /**
     * @param $id
     *
     * @return mixed
     * @throws \ErrorException
     */
    public function destroy($id)
    {

        $asset = $this->assetRepository->delete($id);
        if ($asset->icon) {
            Storage::disk('asset_icons')->delete(
                $asset->icon
            );
        }

        event(new UserLogActionEvent(LogTypeEnum::ASSET_DELETED, $this->user, $asset->id));

        $this->sendNotification($asset, 'An asset was removed');

        return $this->response->array([
            'id' => $id,
            'status' => 'Resource removed successfully.'
        ]);
    }

    public function sendNotification($asset, $message)
    {
        return (new ChangeNotificationSender($asset))->send($message);
    }

    public function getDeviceTypes()
    {
        $users = $this->get($this->deviceTypeRepository->query());
        return $this->response->collection($users, new DeviceTypeTransformer());
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    public function getCustomizableNotificationTypes(Request $request)
    {
        $parameters = $request->all();
        $parameters['collector'] = $this->collectorRepository->all();

        $result = $this->notificationTypeRepository->getLogSourceCustomizableNotificationTypesSetting($parameters);

        return $this->respondOk([
            'data' => (new AssetNotificationTypeSettingsTransformer())->transformCollection($result['data']),
            'meta' => $result['meta']
        ]);
    }

    /**
     * @return \Dingo\Api\Http\Response
     */
    public function showNotificationSetting($id)
    {
        $setting = $this->notificationTypeRepository->findLogSourceCustomizableNotificationTypesSetting($id);

        $collectors = $this->collectorRepository->all();

        $setting['collector_name'] = $collectors->where('id', $setting['collector'])->get('name');

        $result = collect($setting);

        return $this->respondOk([
            'data' => (new AssetNotificationTypeSettingsTransformer())->transform($result),
        ]);
    }

    /**
     * @param \App\Http\Requests\SaveLogSourceNotificationSettingsRequest $request
     *
     * @return \Dingo\Api\Http\Response
     */
    public function saveCustomizableNotificationTypesSetting(SaveLogSourceNotificationSettingsRequest $request)
    {
        $notification_type = $this->notificationTypeRepository->createLogSourceCustomizableNotificationTypesSetting($request->all());
        return $this->response->item($notification_type, new NotificationTypeTransformer());
    }

    public function updateNotificationSetting(UpdateLogSourceNotificationSettingsRequest $request, $setting_id)
    {
        $notification_type = $this->notificationTypeRepository->updateLogSourceCustomizableNotificationTypesSetting($setting_id, $request->all()['settings']);
        return $this->response->item($notification_type, new NotificationTypeTransformer());
    }

    public function deleteNotificationSetting($setting_id)
    {
        $notification_type = $this->notificationTypeRepository->deleteLogSourceCustomizableNotificationTypesSetting($setting_id);
        return $this->response->item($notification_type, new NotificationTypeTransformer());
    }

    public function getCollectors()
    {
        $collectors = $this->collectorRepository->all();
        return $this->response->collection($collectors, new CollectorTransformer());
    }

    /**
     * @param \App\Http\Requests\Asset\ImportAssetRequest $request
     */
    public function import(ImportAssetRequest $request)
    {
        try {

            $rows = Excel::import(new ImportAssets(), $request->file('file'));

            event(new UserLogActionEvent(LogTypeEnum::ASSET_IMPORTED, $this->user));
        } catch (\Exception $e) {
            return $this->response->error(json_encode(array_flatten($e->errors())), 422);
        }
    }

    /**
     * @return \Symfony\Component\HttpFoundation\BinaryFileResponse
     */
    public function export(Request $request)
    {
        $searchResult = json_decode($request->getContent(), true);
        try {

            $filename = Carbon::now()->format('Ymdhms') . '-assets.xlsx';
            event(new UserLogActionEvent(LogTypeEnum::ASSET_EXPORTED, $this->user));
            return Excel::download(new AssetExport(false, $searchResult), $filename);
        } catch (\Exception $e) {

            dd($e);
        }
    }

    public function getExportSample()
    {
        try {

            $filename = 'Asset_import_sample.xlsx';
            return Excel::download(new AssetExport(true, null), $filename);
        } catch (\Exception $e) {

            dd($e);
        }
    }

    public function importLogSources()
    {
        $controller = new ReportDataController();
        $log_sources = $controller->getAllLogSources();
        $assets = $this->assetRepository->insert($log_sources);
        return;
    }

    /**
     * This function first, finds an asset base on request's id.
     * next, toggles the is_notification_excluded of that asset.
     * then, returns the updated asset.
     *
     * @param Request $request
     * @return Dingo\Api\Http\Response\Factory
     */
    public function includeExcludeFromNotification(Request $request)
    {
        $asset = $this->assetRepository->changeAssetNotificationExcluded($request->id);        
        return $this->response->item($asset, new AssetTransformer());
    }


    public function assetNotificationsGetUsers()
    {
        $users = $this->userRepository->all();

        $emails = [];

        foreach ($users as $user) {
            array_push($emails, [
                'label' => $user->email,
                'value' => $user->email
            ]);
        }

        $emails = $this->uniqueMultidimArray($emails, 'label');

        return [
            'data' => $emails
        ];
    }

    public function uniqueMultidimArray($array, $key)
    {
        $temp_array = array();
        $i = 0;
        $key_array = array();

        foreach ($array as $val) {
            if (!in_array($val[$key], $key_array)) {
                $key_array[$i] = $val[$key];
                $temp_array[$i] = $val;
            }
            $i++;
        }
        return $temp_array;
    }
}
