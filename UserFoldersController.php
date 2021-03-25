<?php

namespace App\Http\Controllers;

use App\FileEntry;
use App\Folder;
use Common\Core\BaseController;
use Illuminate\Http\JsonResponse;

class UserFoldersController extends BaseController
{
    /**
     * @var Folder
     */
    private $folder;

    /**
     * @param Folder $folder
     */
    public function __construct(Folder $folder)
    {
        $this->folder = $folder;
    }

    /**
     * Display a listing of the resource.
     *
     * @param $userId
     * @return JsonResponse
     */
    public function index($userId)
    {
        $this->authorize('index', [FileEntry::class, null, $userId]);

        $folders = $this->folder
            ->whereOwner($userId)
            ->select('file_entries.id', 'name', 'parent_id', 'path', 'type')
            ->orderByRaw('LENGTH(path)')
            ->limit(100)
            ->get();

        return $this->success(['folders' => $folders]);
    }
}
