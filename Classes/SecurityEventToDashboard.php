<?php

namespace App\Classes;

use App\Enums\DataProviderEnum;
use App\Models\Report\Chart;
use App\Models\Report\ReportSubType;

class SecurityEventToDashboard
{
    private $data_provider = null;

    private $reportSubTypeJSONParams = '{"report": true, "dashboard": {"active": true}, "visualization": false}';

    private $beforeChangeValue = null;

    public function makeReportSubType($parameters)
    {
        $query = $this->changeValues($parameters['query']);

        $aggsCount = count($parameters['aggregationFields']);

        // dd($query);
        $reportSubType = ReportSubType::create([
            'name'           => $parameters['alias'],
            'alias_name'     => 'SearchProfile',
            'handler'        => 'searchProfile',
            'params'         => json_decode($this->reportSubTypeJSONParams),
            'report_type_id' => 8,
            'data_provider'  => $this->makeDataProvider($parameters, $query)
        ]);

        if ($this->getType($aggsCount, $parameters['aggregationFields']) === DataProviderEnum::THREE_OR_MORE_LEVEL_AGG) {
            $reportSubType->charts()->attach(Chart::where('name', 'Donut chart')->pluck('id')->toArray());
        } else {
            $reportSubType->charts()->attach(Chart::whereNotNull('highcharts_alias')->pluck('id')->toArray());
        }

        return $reportSubType;
    }

    public function setDataProvider($data_provider)
    {
        $this->data_provider = $data_provider;

        return $this;
    }

    public function setReportSubTypeJSONParams($JSONParams)
    {
        $this->reportSubTypeJSONParams = $JSONParams;
    }

    public function setBeforeChangeValue($beforeChangeValue)
    {
        $this->beforeChangeValue = $beforeChangeValue;
    }

    private function makeDataProvider($parameters, $query)
    {
        if ($this->data_provider) {
            return call_user_func($this->data_provider, $parameters, $query);
        } else {
            $aggsCount = count($parameters['aggregationFields']);
            return [
                'query'    => $query,
                'metadata' => $this->makeMetaData($aggsCount, $parameters['aggregationFields'])
            ];
        }
    }

    private function makeMetaData($aggsCount, $aggregationFields)
    {
        $type = $this->getType($aggsCount, $aggregationFields);

        return [
            'type'   => $type,
            'fields' => $aggregationFields
        ];
    }

    private function getType($aggsCount, $aggregationFields)
    {
        $type = '';

        if ($aggsCount > 2 || (array_contains($aggregationFields, 'Event Type') && $aggsCount > 1)) {
            $type = DataProviderEnum::THREE_OR_MORE_LEVEL_AGG;
        } else if ($aggsCount == 2 || array_contains($aggregationFields, 'Event Type')) {
            $type = DataProviderEnum::TWO_LEVEL_AGG;
        } else {
            $type = DataProviderEnum::ONE_LEVEL_AGG;
        }

        return $type;
    }

    /**
     * Change some parameters in the query for using it when creating dashboard
     *
     * @param $query
     *
     * @return false|string
     */
    private function changeValues($query)
    {
        if ($this->beforeChangeValue) {
            $query = call_user_func($this->beforeChangeValue, $query);
        }

        if ($this->hasBool($query['query'])) {
            $query['query']['bool']['must']['range']['timestamp_tz']['gte'] = md5('<gte>');
            $query['query']['bool']['must']['range']['timestamp_tz']['lt'] = md5('<lt>');
        } else {
            $query['query']['range']['timestamp_tz']['gte'] = md5('<gte>');
            $query['query']['range']['timestamp_tz']['lt'] = md5('<lt>');
        }
        $this->recursiveChange($query);

        return $query;
    }

    private function recursiveChange(&$query)
    {
        if (array_key_exists('aggs', $query)) {

            $agg_keys = array_keys($query['aggs']);
            for ($i = 0; $i < count($agg_keys); $i++) {
                if ($agg_keys[$i] !== 'buckets_count' && $agg_keys[$i] !== 'histogram_for_se_chart' && $agg_keys[$i] !== 'aggs' && $agg_keys[$i] !== 'selector') {

                    $query['aggs']['agg_result'] = $query['aggs'][$agg_keys[$i]];

                    unset($query['aggs'][$agg_keys[$i]]);

                    $query['aggs']['agg_result']['terms']['size'] = md5('<size>');

                    $this->recursiveChange($query['aggs']['agg_result']);
                }
            }
        }
    }

    private function hasBool($query)
    {
        return array_key_exists('bool', $query);
    }
}
