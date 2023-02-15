<?php

namespace Tests\Feature\DashboardDiagramOrder;

use Tests\TestCase;
use App\Models\Core\ApiSet;
use App\Models\Core\ApiRoute;
use App\Models\Dashboard\DashboardDiagram;
use Illuminate\Foundation\Testing\WithFaker;
use App\Models\Dashboard\DashboardDiagramOrder;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class DashboardDiagramOrderTest extends TestCase
{
  use WithFaker, DatabaseTransactions;
  /**
   * A basic test example.
   *
   * @return void
   */
  public function setup(): void
  {
    parent::setup();
    $this->signIn();
  }

  public function createDiagram($order = 1)
  {
    $diagram = factory(DashboardDiagram::class)->states('apiCalls')->raw(['order' => $order]);

    $api_set = ApiSet::where('name', 'Dashboard Diagram Create')->first();
    $this->assertNotNull($api_set);

    $api_route = ApiRoute::where('name', 'api-dashboard-storeDiagram')->first();
    $this->assertNotNull($api_route);

    $response = $this->grantAccessTo($api_set)->requestTo($api_route, $diagram)->assertStatus(200);

    $diagram = [
      'id' => $response->original->id
    ];
    $this->assertDatabaseHas('dashboard_diagrams', $diagram);

    $diagramOrder = [
      'id' => $response->original->dashboardDiagramOrder->id,
      'dashboard_tab_id' => $response->original->dashboardDiagramOrder->dashboard_tab_id,
      'dashboard_diagram_id' => $response->original->dashboardDiagramOrder->dashboard_diagram_id,
      'number' => $response->original->dashboardDiagramOrder->number
    ];
    $this->assertDatabaseHas('dashboard_diagram_orders', $diagramOrder);

    return $response->original;
  }

  /** @test */
  public function a_user_can_update_diagram_order()
  {
    $diagram_1 = $this->createDiagram();
    $diagram_2 = $this->createDiagram(2);

    $api_set = ApiSet::where('name', 'Dashboard Diagram Edit')->first();
    $this->assertNotNull($api_set);

    $api_route = ApiRoute::where('name', 'api-dashboard-edit-diagram')->first();
    $this->assertNotNull($api_route);

    $report = $diagram_2->report->getOriginal();
    unset($report['search_profile_id']);
    $report['report_id'] = $report['id']; //couldn't define report_id inside Factory
    $params = [
      'report' => $report,
      'diagram_1' => $diagram_1,
      'diagram_2' => $diagram_2
    ];

    $editedDiagram = factory(DashboardDiagram::class)->states('updateDiagramOrder')->raw($params);
    $response = $this->grantAccessTo($api_set)
      ->post('/api/dashboards/chart/update', $editedDiagram, [
        'Authorization' => 'Bearer ' . $this->token
      ])->assertStatus(200);

    $this->assertDatabaseMissing('dashboard_diagram_orders', [
      'dashboard_diagram_id'             => $diagram_1->id,
      'number'                           => $diagram_1->dashboardDiagramOrder->number,
    ]);
    $this->assertDatabaseMissing('dashboard_diagram_orders', [
      'id'                               => $diagram_2->id,
      'number'                           => $diagram_2->dashboardDiagramOrder->number,
    ]);
    $this->assertDatabaseHas('dashboard_diagram_orders', [
      'dashboard_diagram_id'             => $diagram_2->id,
      'number'                           => $editedDiagram['order'],
    ]);
  }
}
