<?php

namespace App\Http\Controllers;

use Auth;
use Common\Core\BaseController;
use Common\Files\Actions\GetUserSpaceUsage;
use Illuminate\Http\JsonResponse;

class UserDiskSpaceController extends BaseController
{
    /**
     * Get current user's space usage.
     *
     * @param GetUserSpaceUsage $action
     * @return JsonResponse
     */
    public function getSpaceUsage(GetUserSpaceUsage $action)
    {
        $this->authorize('show', Auth::user());

        return $this->success($action->execute());
    }
}
