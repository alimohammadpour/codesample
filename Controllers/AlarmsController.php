<?php

namespace App\Http\Controllers\Api\Alarms;

use App\Classes\Ticketing\TicketAction;
use App\Enums\PluginIdEnum;
use App\Http\Requests\CreateAlarmTicketRequest;
use App\Http\Requests\IndexAlarmsRequest;
use App\Interfaces\Report\CollectorRepositoryInterface;
use App\Interfaces\Authentication\UserRepositoryInterface;
use App\Interfaces\Ticket\TicketRepositoryInterface;
use App\Interfaces\Ticket\TicketCategoryRepositoryInterface;
use App\Interfaces\Ticket\TicketHistoryRepositoryInterface;
use App\Interfaces\Ticket\TicketPriorityRepositoryInterface;
use App\Interfaces\Ticket\TicketStatusRepositoryInterface;
use App\Interfaces\Logger\SecurityEventDescriptorRepositoryInterface;
use App\Transformers\AlarmsTransformer;
use App\Transformers\CollectorTransformer;
use App\Classes\LoggerQueryFactory;
use App\Classes\Alarms\BuildElasticQuery;
use App\Http\Controllers\ApiController;
use Elastica\Client;
use Elastica\Search;
use Elastica\Document;
use Elastica\Script\AbstractScript;
use App\Transformers\PluginSidTransformer;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use App\Enums\LogTypeEnum;
use App\Events\UserLogActionEvent;
use App\SecurityEventFields\FieldMapper;

/**
 * Class AlarmController
 * @package App\Http\Controllers\Api\Alarms
 */
class AlarmsController extends ApiController {

    /**
     * @var Client
     */
    private $client;
    private $aggregation;
    private $collectorRepository, 
            $userRepository, 
            $ticketRepository, 
            $ticketCategoryRepository, 
            $ticketHistoryRepository, 
            $ticketPriorityRepository, 
            $ticketStatusRepository,
            $securityEventDescriptorRepository;

    public function __construct(CollectorRepositoryInterface $collectorRepository,
                                UserRepositoryInterface $userRepository,
                                TicketRepositoryInterface $ticketRepository,
                                TicketCategoryRepositoryInterface $ticketCategoryRepository,
                                TicketHistoryRepositoryInterface $ticketHistoryRepository,
                                TicketPriorityRepositoryInterface $ticketPriorityRepository,
                                TicketStatusRepositoryInterface $ticketStatusRepository,
                                SecurityEventDescriptorRepositoryInterface $securityEventDescriptorRepository)
    {
      parent::__construct();
      $this->setClient();
      $this->collectorRepository = $collectorRepository;
      $this->userRepository = $userRepository;
      $this->ticketRepository = $ticketRepository;
      $this->ticketCategoryRepository = $ticketCategoryRepository;
      $this->ticketHistoryRepository = $ticketHistoryRepository;
      $this->ticketPriorityRepository = $ticketPriorityRepository;
      $this->ticketStatusRepository = $ticketStatusRepository;
      $this->securityEventDescriptorRepository = $securityEventDescriptorRepository;
    }

    public function show($id)
    {
      $query = LoggerQueryFactory::get()->whereMust([
        LoggerQueryFactory::get()->whereMatch([
          'field' => '_id',
          'value' => $id
        ])
      ]);

      $result = $this->sendRequest($query);
      $source = $result['hits']['hits'][0]['_source'];

      $events = [$this->makeCorrelationLevel($source)];

      for ($i = 2 ; $i <= $source['highestLevel'] ; $i++) {
        $attackSource = json_decode($source["ATTACK_$i"] , true);
        $events[] = $this->makeCorrelationLevel($attackSource);
      }

      return $events;
    }

    private function makeCorrelationLevel($source) {
      $events = [];
      $result = [
        'Name'        => $source['directiveName'] ?? '',
        'Date'        => $source['attack_date'],
        'Source'      => $source['source'] . ":" . $source['sport'],
        'Destination' => $source['destination'] . ":" . $source['dport'],
        'Level'       => $source['level'],
        'Events'      => []
      ];

      foreach ($source['events'] as $event) {
        $key = $event['pluginID'].','.$event['pluginSID'];
        if (!array_key_exists($key , $events)) {
          $events[$key] = $this->getEventName($event['pluginID'] , $event['pluginSID']);
        }

        $result['Events'][] = [
          'Name'         => $events[$key],
          'Date'         => $event['Date'],
          'Source'       => $event['source'] . ":" . $event['sport'],
          'Destination'  => $event['destination'] . ":" . $event['dport'],
          'Collector'    => $event['Collector'],
          'Username'     => $event['username'],
          'Event source' => $event['eventSource'],
          'Sevenrity'    => $event['myServerity'],
          'Action'       => $event['action']
        ];
      }

      return $result;
    }

    private function getEventName($pid , $psid) {
      $event = $this->securityEventDescriptorRepository->getEventName($pid, $psid);
      return $event['name'] ?? "($pid , $psid)";
    }

    public function index(IndexAlarmsRequest $request)
    {
        $builer = new BuildElasticQuery(collect($request->all()));
        $query  = $builer->buildQuery();
        $result = $this->sendRequest($query);

        if (array_key_exists('aggregations' , $result)) {
          // Aggregation Result
          $this->aggregation = $request->aggregation;
          $result = $this->transformAggregationResult($result['aggregations'] , $request->limit);
        }
        else {
          // Search Result
          $result = $this->transformSearchResult($result , $request->page, $request->limit);
        }
        return $result;
    }

    private function sendRequest($query) {
      $search = new Search($this->client);
      $search->addIndex(config('elastic.alarm.index'));
      $result = $search->search($query->getQuery(), true)->getResponse()->getData();
      return $result;
    }

    private function transformAggregationResult($result , $limit) {
      $data = collect($result['agg_result']['buckets']);
      $pagination = new LengthAwarePaginator($data , $this->aggregationResultTotalCount($result) , $limit , 1);
      return $this->respondWithPagination($pagination, [
          'data' => $data->toArray()
      ]);
    }

    private function transformSearchResult($result , $page , $limit) {
      $collection = collect($result['hits']['hits']);
      $pagination = new LengthAwarePaginator($collection , $this->searchResultTotalCount($result) , $limit , $page);
      $transformer = new AlarmsTransformer();
      $result = $transformer->transformCollection($pagination);
      return $this->respondWithPagination($pagination, [
          'data' => $result
      ]);
    }

    private function searchResultTotalCount($result) {
      return $result['hits']['total'];
    }

    private function aggregationResultTotalCount($result) {
      return $result['agg_total']['value'];
    }

    /**
     * Return Client object of Elastica
     */
    private function setClient()
    {
      $this->client = $client = new Client([
          'host'     => config('elastic.alarm.host'),
          'port'     => config('elastic.alarm.port'),
          'username' => config('elastic.alarm.username'),
          'password' => config('elastic.alarm.password')
      ]);
    }

    public function getDirectiveNames()
    {
        $names = $this->securityEventDescriptorRepository->getCollection('plugin_id', PluginIdEnum::ALARM_PLUGIN_ID);
        return $this->response->collection($names, new PluginSidTransformer);
    }

    public function changeStatus(Request $request)
    {
      $document = new Document($request->id, ['status' => $request->newStatus]);
      $response = $this->client->getIndex($request->index)->getType('alarm')->updateDocument($document)->getData();
      event(new UserLogActionEvent(LogTypeEnum::ALARM_EDITED, $this->user, $request->id));
      return $this->response->array([
          'status' => $response['result']
      ]);
    }

    public function destroy(Request $request)
    {
      $document = new Document($request->id);
      $response = $this->client->getIndex($request->index)->getType('alarm')->deleteDocument($document)->getData();
      event(new UserLogActionEvent(LogTypeEnum::ALARM_DELETED, $this->user, $request->id));
      return $this->response->array([
          'status' => $response['result']
      ]);
    }

    public function createTicket(CreateAlarmTicketRequest $request)
    {
      $owner = $this->userRepository->getFirst('username', 'admin');
      $ticketStatusId = $this->ticketStatusRepository->getByName('New')->id;
      $ticket = $this->ticketRepository->create([
          'subject' => 'Alarm: ' . $request->alarm,
          'subject_html' => '<p><strong>Alarm</strong></p>:' .$request->alarm,
          'content' => 'Source IP: ' . $request->source . ',Destination IP: ' . $request->destination . ',Time: ' . $request->alarm_date,
          'content_html'    => '<p><strong>Source IP</strong>: ' . $request->source . ',</p>' .
              '<p><strong>Destination IP</strong>: ' . $request->destination . ',</p>' .
              '<p><strong>Time:</strong>: ' . $request->alarm_date . ',</p>',

          'owner'       => $owner->id,
          'responsible' => $request->responsible_id,
          'category'    => $this->ticketCategoryRepository->get('name', 'Alarm')->id,
          'priority'    => $this->ticketPriorityRepository->get('name', 'High')->id,
          'status'      => $ticketStatusId
      ]);

      $this->ticketHistoryRepository->create([$owner->id, $ticket->id, $ticketStatusId, $ticket->category_id, $ticket->priority_id, $ticket->responsible_id]);

      $actions = new TicketAction($ticket);
      $actions->sendNotification('created');
      $actions->sendMail();

      return $this->response->array([
          'id'     => $request->id,
          'status' => 'Ticket was created successfully.'
      ]);
    }

    public function getCollectors()
    {
        $collectors = $this->collectorRepository->all();
        return $this->response->collection($collectors, new CollectorTransformer);
    }

    public function changeSelectedAlarmsStatus(Request $request)
    {
      $this->{$request->parameters['type']}($request->parameters['data']);
      
      event(new UserLogActionEvent(LogTypeEnum::ALARMS_EDITED, $this->user));

      return;
    }

    private function updateAlarmsByDocuments($alarms) {
      $documents = [];
      foreach ($alarms as $alarm) {
        $documents[] = new Document($alarm['id'], ['status' => $alarm['status'] == 'open' ? 'closed' : 'open'] , 'alarm' , $alarm['index']);
      }
      return $this->client->updateDocuments($documents)->getData();
    }

    private function updateAlarmsByQuery($form) {
      $script = AbstractScript::create([
        "script" => [
          "source" => "ctx._source.status = ctx._source.status == 'open' ? 'closed' : 'open'"
        ]
      ]);

      return $this->client->getIndex('*')->updateByQuery(
        [
          'query' => $this->buildBulkActionsQuery($form)
        ], 
        $script
      );
    }

    public function deleteSeletedAlarms(Request $request)
    {
      $this->{$request->parameters['type']}($request->parameters['data']);

      event(new UserLogActionEvent(LogTypeEnum::ALARMS_DELETED, $this->user));

      return;
    }

    private function deleteAlarmsByDocuments($alarms) {
      $documents = [];
      foreach ($alarms as $alarm) {
        $documents[] = new Document($alarm['id'], [] , 'alarm' , $alarm['index']);
      }
      return $this->client->deleteDocuments($documents)->getData();
    }

    private function deleteAlarmsByQuery($form) {
      return $this->client->getIndex('*')->deleteByQuery([
        'query' => $this->buildBulkActionsQuery($form)
      ]);
    }

    private function buildBulkActionsQuery($form) {
      $builer = new BuildElasticQuery(collect($form));
      $query  = $builer->buildQuery();
      $query->whereMust([
        LoggerQueryFactory::get()->whereTermsQuery(
          [
            'field' => FieldMapper::get()->map('alarm.'.$form['aggregatedTerms']['field']),
            'terms' => $form['aggregatedTerms']['terms']
          ]
        )
      ]);

      return $query->getQuery()->toArray()['query'];
    }
}
