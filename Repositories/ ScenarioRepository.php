<?php

namespace App\Repositories\Correlation;

use App\Interfaces\Correlation\ScenarioRepositoryInterface;
use App\Models\Correlation\Scenario;
use App\Repositories\BaseRepository;

class ScenarioRepository extends BaseRepository implements ScenarioRepositoryInterface
{
    protected $model;

    public function __construct(Scenario $model)
    {
        $this->model = $model;
    }

    public function getAlarmNamesByPattern($pattern)
    {
        return $this->model->query()->where('name', 'like', '%' . $pattern . '%')->get(['name', 'id']);
    }   

    public function query()
    {
        return $this->model->query()->orderBy('created_at', 'desc');
    }

    public function create(array $parameters)
    {
        $scenario = $this->model->create([
            'name' => $parameters['tree'][0]['value']['name'],
            'priority' => $parameters['tree'][0]['value']['priority'],
            'rules' => json_encode($parameters['tree']),
            'field_history' => json_encode($parameters['field_history'])
        ]);

        $scenario->update([
            'xml' => (new Scenario())->parse($parameters['tree'][0], $scenario->id)
        ]);
        return $scenario;
    }

    public function find($id)
    {
        return $this->model->where('id', $id)->get();   
    }

    public function update($id, array $parameters)
    {
        $scenario = $this->model->where('id', $id)->first();
        $result = (new Scenario())->parse($parameters['tree'][0], $id);

        $scenario->update([
            'name' => $parameters['tree'][0]['value']['name'],
            'priority' => $parameters['tree'][0]['value']['priority'],
            'rules' => json_encode($parameters['tree']),
            'xml' => $result,
            'field_history' => json_encode($parameters['field_history'])
        ]);
        return $scenario;
    }

    public function delete($id)
    {
        $scenario = $this->model->findOrFail($id);
        $scenario->delete();
        return $scenario;
    }

    public function getEnabledScenarios()
    {
        return $this->model->where('enable', true)->get();
    }

    public function enableScenario($id)
    {
        $scenario = $this->model->find($id);
        if ($scenario->enable) {
            $scenario->enable = false;
        } else {
            $scenario->enable = true;
        }
        $scenario->save();
        return;
    }
}
