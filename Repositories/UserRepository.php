<?php

namespace App\Repositories\Authentication;

use App\Interfaces\Authentication\UserRepositoryInterface;
use App\Models\Authentication\User;
use App\Repositories\BaseRepository;

class UserRepository extends BaseRepository implements UserRepositoryInterface
{
    protected $model;

    public function __construct(User $model)
    {
        $this->model = $model;
    }

    private function currentUser()
    {
        return authenticated_user();
    }

    public function all()
    {
        return $this->model->all();
    }

    public function query()
    {
        return $this->model->query();
    }

    public function getFirst(...$whereParams)
    {
        return $this->model->where(...$whereParams)->first();   
    }

    public function getCollection(...$whereParams)
    {
        return $this->model->where(...$whereParams)->get();
    }

    public function create(array $parameters)
    {
        return $this->model->create($parameters);
    }

    public function find(...$ids)
    {
        return $this->model->find($ids);
    }

    public function findOrFail($id)
    {
        return $this->model->findOrFail($id);
    }

    public function enableShouldChangePassword($id)
    {
        $user = $this->findOrFail($id);
        $user->password_reset_required = $user->password_reset_required ? false: true;
        $user->save();
        return $user;
    }

    public function update($id, array $parameters)
    {
        $user = $this->findOrFail($id);
        $user->update($parameters);
        return $user;
    }

    public function destroy($id)
    {
        return $this->model->destroy($id);   
    }

    public function except($id)
    {
        return $this->model->all()->except($id);
    }

    public function resetDashboardTabDefault($tabId)
    {
        $defaultTabs = $this->currentUser()->dashboardTabs()->where('default', 1);
        if ($tabId != -1) 
            $defaultTabs->where('dashboard_tabs.id', '!=', $tabId)->update(['default' => 0]);
        else
            $defaultTabs->update(['default' => 0]);

        return;
    }

    public function getSecurityEventResultColumn()
    {
        return $this->currentUser()->searchEventResults()->get();
    }

    public function updateSecurityEventResultColumn($resultId, $visible)
    {
        return $this->currentUser()->searchEventResults()->sync([$resultId => ['visible' => $visible]]);
    }

    public function getSecurityHistories()
    {
        return $this->currentUser()->securityHistories()->orderBy('created_at', 'desc')->get();
    }

    public function deleteSecurityHistory($historyId)
    {
        return $this->currentUser()->securityHistories()->find($historyId)->delete();
    }

    public function createSecurityHistory(array $parameters)
    {
        $user = $this->currentUser();

        $userHistories = $user->securityHistories()->latest('created_at')->get();
        
        if ($userHistories->count() == config('security_history.security_history_number')) {
            $userHistories->last()->delete();
        }

        return $user->securityHistories()->create([
            'fields' => $parameters['fields'],
            'hash'   => md5($parameters['query'])
        ]);
    }

    public function markAllNotificationsAsRead()
    {
        return $this->currentUser()->unreadNotifications()->update(['read_at' => now()]);
    }

    public function markNotificationsAsRead(array $parameters)
    {
        $user = $this->currentUser();

        $selected = $parameters['selection'];
        $notSelected = $parameters['not_selected'];
        $returnNotifications = array_merge($selected, ($notSelected !== null ? $notSelected : []));
        
        if (isset($parameters['mode'])) {
            $user->unreadNotifications()->whereIn('id', $selected)->update(['read_at' => now()]);
        } else {
            $user->readNotifications()->whereIn('id', $notSelected)->update(['read_at' => null]);
            $user->unreadNotifications()->whereIn('id', $selected)->update(['read_at' => now()]);
        }

        return $user->notifications()->whereIn('id', $returnNotifications)->get();
    }

    public function getUnreadNotifications()
    {
        $unread_notifications = $this->currentUser()->unreadNotifications()->get();

        return [
            'data' => [
                'count' => $unread_notifications->count(),
                'slice' => $unread_notifications->take(10)
            ]
        ];
    }

    public function deleteReadNotifications()
    {
        $user = $this->currentUser();

        $user->readNotifications()->delete();

        return $user->notifications()->orderBy('created_at', 'desc');
    }

    public function getSharedTemplateFiles()
    {
        return $this->currentUser()->sharedTemplates()->with('reportFiles')->get();
    }

    public function getUserPermittedApiSets()
    {
        return $this->currentUser()->role->permittedApiSets()->get();
    }

    public function getAdminUserIds()
    {
        return $this->model->whereHas('role', function ($query) {
            $query->where('is_admin', true);
        })->pluck('id')->toArray();
    }
}
