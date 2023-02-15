<?php
/**
 * Created by PhpStorm.
 * User: a.hosseinikia
 * Date: 7/23/2018
 * Time: 10:53 AM
 */

namespace App\Classes;

use Elastica\Client;
use Elastica\Index;
use Elastica\Request;
use Elastica\Search;

class ElasticExecutor
{

    /**
     * @var Client
     */
    private $client;

    public function __construct()
    {
        $this->setClient();
    }

    /**
     * Executes query
     *
     * @param $query
     *
     * @return array
     */
    public function execute($query)
    {
        $search = new Search($this->client);

        $search->addIndex($this->indices(2));

        $result = $search->count($query->getQuery(), true)->getResponse()->getData();

        return $result;
    }

    /**
     * Call search api with the given JSON query
     *
     * @param $indices
     * @param $query
     *
     * @return array
     */
    public function search($query, $indices = 'logstash-*')
    {
        $indices = $this->indexExists('.kibana') ? $indices . ',-.kibana' : $indices;

        $path = "$indices/_search";

        $res = $this->client->request($path, \Elastica\Request::POST, $query);

        return $res->getData();
    }

    public function hasResult($result)
    {
        if (array_key_exists('aggregations', $result)) {
            return count(array_first($result['aggregations'])['buckets']) > 0;
        } else if (array_key_exists('hits', $result)) {
            return $result['hits']['total'] > 0;
        } else {
            return false;
        }
    }

    /**
     * @param $range
     * This function build list of elastic index base on report->time_range
     *
     * @return string
     */
    public function indices($range)
    {

        $time = time();
        $indices = '';
        for ($day = 0; $day < $range; $day++) {
            $index = 'logstash-' . date('Y.m.d', ($time - $day * 60 * 60 * 24));
            if ($this->indexExists($index)) {
                $indices .= $index . ',';
            }

        }

        return rtrim($indices, ", ");

    }

    public function fields()
    {
        $request = new Request('logstash-*/_mapping');

        $request->setConnection($this->client->getConnection());

        return $request->send()->getData();
    }

    /**
     * @param $index
     *
     * @return boolean
     */
    public function indexExists($index)
    {

        $indexInstance = new Index($this->client, $index);

        return $indexInstance->exists();
    }

    private function setClient()
    {
        $client = '';

        if ($this->client === null) {
            $client = new Client([
                'host'     => config('elastic.event.host'),
                'port'     => config('elastic.event.port'),
                'username' => config('elastic.event.username'),
                'password' => config('elastic.event.password')
            ]);
        }

        $this->client = $client;

    }
}
