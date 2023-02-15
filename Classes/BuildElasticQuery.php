<?php

namespace App\Classes\Alarms;

use App\Classes\Alarms\MixedInputQueryBuilder;
use App\Classes\LoggerQueryFactory;
use App\SecurityEventFields\FieldMapper;
use Carbon\Carbon;
use Illuminate\Support\Collection;

class BuildElasticQuery
{

    private $query;
    private $fields;
    private $from;
    private $size;
    private $sort;

    public function __construct(Collection $fields)
    {
        $this->from = ($fields->get('page') - 1) * $fields->get('limit');
        $this->size = $fields->get('limit');
        $this->sort = $fields->get('sort');
        $this->fields = $this->getQueryAndAggregationFields($fields);
        $this->setupQuery();
    }

    private function setupQuery()
    {
        $this->query = LoggerQueryFactory::get();
        // beacuse we need risks with value  greater than 1
        $this->rangeQuery('risk', ['gte' => 1]);
        if (is_null($this->fields->get('aggregation'))) {
            $this->query->setQueryFrom($this->from)
                ->setQuerySize($this->size)
                ->setQueryOrder([$this->mapFormFieldToElastic($this->sort['prop']) => [
                    'order' => $this->sort['order'],
                    'unmapped_type' => $this->getSortablesUnmappedTypes($this->sort['prop'])
                ]]);
        }
    }

    private function simpleInputQuery($key, $value)
    {
        $this->query->whereMust([
            LoggerQueryFactory::get()->whereMatch(
                [
                    'field' => $this->mapFormFieldToElastic($key),
                    'value' => $value
                ]
            )
        ]);
    }

    private function mixedInputQuery($key, $values)
    {

        $query = $this->query;
        $check = collect($values)->filter(function ($value) {
            return is_null($value) || $value === '';
        })->toArray();

        if (empty($check)) { // mixed input, so apply join option, parameters must be all of $values;
            list($function, $parameters) = $this->getFunction($values);
            $mixedQuery = new MixedInputQueryBuilder($query, $function, $parameters);
            $mixedQuery->get();
        } elseif (sizeof($check) == 1) { // like a simple input field, parameters must be single field value with it's option;
            $name_option = array_keys($check)[0] . "_option";
            $check[$name_option] = $values[$name_option];
            $check['join_option'] = $values['join_option'];
            $fields = array_diff_assoc($values, $check);

            list($function, $parameters) = $this->getFunction($fields);
            $mixedQuery = new MixedInputQueryBuilder($query, $function, $parameters);
            $mixedQuery->get();
        } else { // both fields are empty;
            return;
        }
    }

    private function aggregationInputQuery($key, $value)
    {
        $this->query->whereAgg([
            LoggerQueryFactory::get()->whereTotal(
                [
                    'field' => $this->mapFormFieldToElastic($value)
                ]
            )
        ]);

        $this->query->whereAgg(
            [
                LoggerQueryFactory::get()->whereTerms(
                    [
                        'field' => $this->mapFormFieldToElastic($value)
                    ]
                )->setOrder($this->mapFormFieldToElastic($this->sort['prop']), $this->sort['order'])->setSize($this->size)
            ]
        );
    }

    private function comparisonInputQuery($key, $values)
    {
        $value = $values['value'];
        switch ($values['option']) {
            case '<=':
                $this->rangeQuery($key, ['lte' => $value]);
                break;

            case '>=':
                $this->rangeQuery($key, ['gte' => $value]);
                break;

            default:
                $this->simpleInputQuery($key, $value);
        }
    }

    private function rangeQuery($key, $params)
    {
        $this->query->whereMust([
            LoggerQueryFactory::get()->whereRange(
                [
                    'fieldName' => $this->mapFormFieldToElastic($key),
                    'params' => $params
                ]
            )
        ]);
    }

    private function dateInputQuery($key, $values)
    {
        $from = Carbon::createFromFormat('Y-m-d H:i:s', $values['value'][0])->timestamp;
        $to = Carbon::createFromFormat('Y-m-d H:i:s', $values['value'][1])->timestamp;
        $this->query->whereMust([
            LoggerQueryFactory::get()->whereRange(
                [
                    'fieldName' => $this->mapFormFieldToElastic($key),
                    'params' => [
                        "gte" => $from,
                        "lt" => $to,
                        "time_zone" => "Asia/Tehran"
                    ]
                ]
            )
        ]);
    }

    private function mapFormFieldToElastic($field)
    {
        $field = 'alarm.' . $field;
        return FieldMapper::get()->map($field);
    }

    private function mapFormFieldToType($field)
    {
        $mapper = [
            'address' => [
                'type' => 'mixed'
            ],
            'port' => [
                'type' => 'mixed'
            ],
            'scenario_name' => [
                'type' => 'simple',
                'constraints' => 'ifValueIsSet'
            ],
            'collector' => [
                'type' => 'simple',
                'constraints' => 'ifValueIsSet'
            ],
            'status' => [
                'type' => 'simple',
                'constraints' => 'ifValueIsSet'
            ],
            'aggregation' => [
                'type' => 'aggregation',
                'constraints' => 'ifValueIsSet'
            ],
            'date' => [
                'type' => 'date',
                'constraints' => 'ifNotAllDates'
            ],
            'risk' => [
                'type' => 'comparison',
                'constraints' => 'ifZeroValueOrNotEmpty'
            ],
            'event_count' => [
                'type' => 'comparison',
                'constraints' => 'ifZeroValueOrNotEmpty'
            ],
            'id' => [
                'type' => 'simple'
            ]
        ];

        return $mapper[$field];
    }

    private function getFunction($values)
    {
        $mapper = [
            '=' => 'Must',
            '!=' => 'MustNot',
            'And' => 'Must',
            'Or' => 'Should'
        ];

        $parameters = [];
        $name = 'set';
        foreach ($values as $key => $value) {
            if (array_key_exists($value, $mapper)) {
                $name .= $mapper[$value];
            } else {
                $field = $this->mapFormFieldToElastic($key);
                $parameters[$field] = $value;
            }
        }
        return [$name, $parameters];
    }

    private function getSortablesUnmappedTypes($sortable)
    {
        $types = [
            'alarm_date' => 'keyword',
            'risk' => 'long',
            'level' => 'long'
        ];

        return $types[$sortable] ?? 'keyword';
    }

    public function buildQuery()
    {
        $this->fields->each(function ($values, $key) {
            
            $mapper = $this->mapFormFieldToType($key);

            $typeConstraints = array_key_exists('constraints', $mapper) ? $this->{$mapper['constraints']}($values) : true;
            if ($typeConstraints) {
                $function = $mapper['type'] . "InputQuery";
                $this->{$function}($key, $values);
            }
            
        });

        return $this->query;
    }

    private function ifValueIsSet($value)
    {
        return boolval($value);
    }

    private function ifNotAllDates($value)
    {
        return !$value['all'];
    }

    private function ifZeroValueOrNotEmpty($value)
    {
        return isset($value['value']);
    }

    private function getQueryAndAggregationFields($fields)
    {
        // The last three items came from js-side while building a form.
        return $fields->except(['page', 'limit', 'sort', 'busy', 'errors', 'originalData']); 
    }
}
