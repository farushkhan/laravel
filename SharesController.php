<?php

namespace App\Http\Controllers;

use App\FileEntry;
use App\Notifications\FileEntrySharedNotif;
use App\Services\Shares\AttachUsersToEntry;
use App\Services\Shares\DetachUsersFromEntries;
use App\Services\Shares\GetUsersWithAccessToEntry;
use App\Services\Shares\UpdateEntryUsers;
use App\ShareableLink;
use Auth;
use Common\Core\BaseController;
use Common\Settings\Settings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Notification;

class SharesController extends BaseController
{
    /**
     * @var Request
     */
    private $request;

    /**
     * @var Settings
     */
    private $settings;

    /**
     * @param Request $request
     * @param Settings $settings
     */
    public function __construct(Request $request, Settings $settings)
    {
        $this->request = $request;
        $this->settings = $settings;
    }

    /**
     * Import entry into current user's drive using specified shareable link.
     *
     * @param int $linkId
     * @param AttachUsersToEntry $action
     * @param ShareableLink $linkModel
     * @return JsonResponse
     */
    public function addCurrentUser($linkId, AttachUsersToEntry $action, ShareableLink $linkModel)
    {
        /* @var ShareableLink $link */
        $link = $linkModel->with('entry')->findOrFail($linkId);

        $this->authorize('show', [$link->entry, $link]);

        $permissions = [
            'view' => true,
            'edit' => $link->allow_edit,
            'download' => $link->allow_download,
        ];

        $action->execute(
            [$this->request->user()->email],
            [$link->entry_id],
            $permissions
        );

        $users = app(GetUsersWithAccessToEntry::class)
            ->execute($link->entry_id);

        return $this->success(['users' => $users]);
    }

    /**
     * Share drive entries with specified users.
     *
     * @param AttachUsersToEntry $action
     * @return Response
     */
    public function addUsers(AttachUsersToEntry $action)
    {
        $entryIds = $this->request->get('entries');
        $shareeEmails = $this->request->get('emails');

        $this->authorize('update', [FileEntry::class, $entryIds]);

        // TODO: refactor messages into custom validator, so can reuse elsewhere
        $emails =  $this->request->get('emails', []);

        $messages = [];
        foreach ($emails as $key => $email) {
            $messages["emails.$key"] = $email;
        }

        $this->validate($this->request, [
            'emails' => 'required|min:1',
            'emails.*' => 'required|email|exists:users,email',
            'permissions' => 'required|array',
            'entries' => 'required|min:1',
            'entries.*' => 'required|integer',
        ], [], $messages);

        $sharees = $action->execute(
            $shareeEmails,
            $entryIds,
            $this->request->get('permissions')
        );

        if ($this->settings->get('drive.send_share_notification')) {
            Notification::send($sharees, new FileEntrySharedNotif($entryIds, Auth::user()));
        }

        $users = app(GetUsersWithAccessToEntry::class)
            ->execute(head($entryIds));

        return $this->success(['users' => $users]);
    }

    /**
     * Update permissions that specified users have for entries.
     *
     * @param UpdateEntryUsers $action
     * @return JsonResponse
     */
    public function updateUsers(UpdateEntryUsers $action)
    {
        $entryIds = $this->request->get('entries');

        $this->authorize('update', [FileEntry::class, $entryIds]);

        $this->validate($this->request, [
            'entries' => 'required|array|min:1',
            'entries.*' => 'required|integer',
            'users' => 'required|array|min:1',
            'users.*.id' => 'required|exists:users,id',
            'users.*.permissions' => 'required|array',
            'users.*.removed' => 'boolean',
        ]);

        $action->execute(
            $this->request->get('users'),
            $entryIds
        );

        $users = app(GetUsersWithAccessToEntry::class)
            ->execute(head($entryIds));

        return $this->success(['users' => $users]);
    }

    /**
     * Detach user from specified entries.
     *
     * @param int $userId
     * @param DetachUsersFromEntries $action
     * @return JsonResponse
     */
    public function removeUser($userId, DetachUsersFromEntries $action)
    {
        $entryIds = $this->request->get('entries');

        // there's no need to authorize if user is
        // trying to remove himself from the entry
        if ((int) $userId !== $this->request->user()->id) {
            $this->authorize('update', [FileEntry::class, $entryIds]);
        }

        $action->execute($entryIds, [$userId]);

        return $this->success();
    }
}
