<?php

namespace App\Repositories\Asset;

use App\Interfaces\Asset\AssetDiscoveryRepositoryInterface;
use App\Models\Asset\AssetDiscovery;
use App\Repositories\BaseRepository;

class AssetDiscoveryRepository extends BaseRepository implements AssetDiscoveryRepositoryInterface
{
    protected $model;

    public function __construct(AssetDiscovery $model)
    {
        $this->model = $model;
    }

    public function orderQuery($column, $direction)
    {
        return $this->model->query()->orderBy($column, $direction);
    }

    public function getNotPermittedScanCount($status)
    {
        return $this->model->query()->where('status', '<', $status)->count();
    }

    public function create(array $parameters)
    {
        return $this->model->create($parameters);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function stopScan($id)
    {
        $scan = $this->findOrFail($id);
        if ($scan->stopScan()) {
            $scan->status = "Stopped";
            $scan->pid = null;
            $scan->update();
        }
        return $scan;
    }

    public function delete($id)
    {
        $scan = $this->findOrFail($id);
        $scan->delete();
        return $scan;
    }
}
