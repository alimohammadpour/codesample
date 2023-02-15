<?php

namespace App\Repositories\AnomalyDetection;

use App\Interfaces\AnomalyDetection\AnomalyDetectionProfileRepositoryInterface;
use App\Models\AnomalyDetection\AnomalyDetectionProfile;
use App\Repositories\BaseRepository;

class AnomalyDetectionProfileRepository extends BaseRepository implements AnomalyDetectionProfileRepositoryInterface
{
    protected $model;

    public function __construct(AnomalyDetectionProfile $model)
    {
        $this->model = $model;
    }

    public function query()
    {
        return $this->model->query();
    }

    public function find($id)
    {
        return $this->model->findOrFail($id);
    }

    public function create(array $parameters)
    {
        return $this->model->create([
            'name'          => $parameters['name'],
            'algorithm_id'  => $parameters['algorithm']
        ])->syncfeature($parameters['features']);
    }

    public function update($id, array $parameters)
    {
        $profile = $this->find($id);
        $profile->update([
            'name'          => $parameters['name'],
            'algorithm_id'  => $parameters['algorithm'],
        ]);
        $profile->syncfeature($parameters['features']);
        return $profile;
    }

    public function delete($id)
    {
        $profile = $this->find($id);
        $profile->features()->detach();
        $profile->delete();
        return $profile;
    }
}
