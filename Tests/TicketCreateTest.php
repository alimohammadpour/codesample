<?php

namespace Tests\Feature\Ticketing;

use App\Events\SendTicketMailEvent;
use App\Models\Core\ApiSet;
use App\Models\Core\ApiRoute;
use App\Models\Authentication\Role;
use App\Models\Authentication\User;
use App\Models\Ticketing\Ticket;
use App\Models\Ticketing\TicketCategory;
use App\Models\Ticketing\TicketPriority;
use App\Models\Ticketing\TicketTag;
use App\Models\Notification\NotificationType;
use App\Notifications\ChangeNotification;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class TicketCreateTest extends TestCase
{
  use DatabaseTransactions;

  public function setup()
  {
    parent::setup();
    $this->signIn();
  }

  /** @test */
  public function get_ticket_priorities_list()
  {
    $priority = factory(TicketPriority::class)->create()->toArray();
    $api = ApiRoute::where('url', 'ticketing/ticket/create/getPriorities')->first();
    $response = $this->requestTo($api)->assertStatus(200);
    return $this->assertContains($priority, $response->original->toArray());
  }

  /** @test */
  public function get_ticket_categories_list()
  {
    $category = factory(TicketCategory::class)->create();
    $api = ApiRoute::where('url', 'ticketing/ticket/create/getCategories')->first();
    $response = $this->requestTo($api)->assertStatus(200);
    $categories = $response->original->map(function ($category) {
      return $category->name;
    })->toArray();
    return $this->assertContains($category->name, $categories);
  }

  /** @test */
  public function get_ticket_responsible_roles_list()
  {
    $role = factory(Role::class)->create();
    $api = ApiRoute::where('url', 'ticketing/ticket/create/getRoles')->first();
    $response = $this->requestTo($api)->assertStatus(200);
    $roles = $response->original->map(function ($role) {
      return $role->name;
    })->toArray();
    return $this->assertContains($role->name, $roles);
  }

  /** @test */
  public function get_ticket_responsibe_role_users_list()
  {
    $role = factory(Role::class)->create();
    $user = factory(User::class)->create(['role_id' => $role->id])->toArray();
    $api_set = ApiSet::where('name', 'Ticket Create')->first();
    $this->assertNotNull($api_set);
    $response = $this->grantAccessTo($api_set)->get('api/ticketing/ticket/create/getUsers/' . $role->id, [
      'Authorization' => 'Bearer ' . $this->token
    ])->assertStatus(200);
    return $this->assertCount(1, $response->original->toArray());
  }

  /** @test */
  public function a_user_can_create_a_ticket()
  {
    $storage_files = Storage::Disk('ticket_attachments')->files();
    $last_ticket = factory(Ticket::class)->states('DatabaseFactory')->create();
    $ticket = factory(Ticket::class)->states('FormFactory')->raw();
    Event::fake();
    Notification::fake();
    $notification_type = NotificationType::where('type', 'ticket')->first();
    $notification_type->roles()->sync(User::find($ticket['responsible'])->role_id);
    $api = ApiRoute::where('url', 'ticketing/ticket/store')->first();
    $response = $this->requestTo($api, $ticket)->assertStatus(200);
    $ticket_id = $last_ticket->id + 1;
    // Ticket Assertion
    $this->assertDatabaseHas('tickets', [
      'subject_html'   => $ticket['subject_html'],
      'content_html'   => $ticket['content_html'],
      'priority_id'    => $ticket['priority'],
      'category_id'    => $ticket['category'],
      'responsible_id' => $ticket['responsible'],
      'owner_id'       => $this->user->id,
      'status_id'      => 1
    ]);
    // Ticket History Assertion
    $this->assertDatabaseHas('ticket_histories', [
      'user_id'        => $this->user->id,
      'ticket_id'      => $ticket_id,
      'status_id'      => 1,
      'category_id'    => $ticket['category'],
      'priority_id'    => $ticket['priority'],
      'responsible_id' => $ticket['responsible']
    ]);
    // Ticket Tags
    $this->assertDatabaseHas('tickets_ticket_tags', [
      'ticket_id'     => $ticket_id,
      'ticket_tag_id' => TicketTag::where('name', json_decode($ticket['tags'])[0])->first()->id
    ]);
    // Ticket Subsctibers
    $this->assertDatabaseHas('ticket_subscribers', [
      'ticket_id'     => $ticket_id,
      'user_id'       => json_decode($ticket['subscribers'])[0]
    ]);
    // Ticket Attachments
    $this->assertDatabaseHas('ticket_attachments', [
      'ticket_id' => $ticket_id,
      'name'      => $ticket['attachments'][0]->getClientOriginalName()
    ]);
    $this->assertCount(count($storage_files) + 1, Storage::Disk('ticket_attachments')->files());
    // Ticket Notification
    Notification::assertSentTo(
      [User::find($ticket['responsible'])],
      ChangeNotification::class
    );
    // Ticket Mail
    Event::assertDispatched(SendTicketMailEvent::class, function ($event) use ($ticket) {
      return $event->responsible === User::find($ticket['responsible'])->email;
    });
    // Ticket Option
    $custom_field = json_decode($ticket['custom_fields'])[0];
    $this->assertDatabaseHas('ticket_options', [
      'ticket_id' => $ticket_id,
      'label'     => $custom_field->label,
      'value'     => $custom_field->value,
      'type'      => $custom_field->type
    ]);

    return;
  }

  protected function requestTo(ApiRoute $api, $body = [], $stringQuery = array())
  {
    $api_set = ApiSet::where('name', 'Ticket Create')->first();
    $this->assertNotNull($api_set);
    $this->grantAccessTo($api_set);
    $response = parent::requestTo($api, $body, $stringQuery);
    return $response;
  }
}
