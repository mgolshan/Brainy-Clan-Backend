<?php

namespace App\Modules\Content\Http\Controllers;

use App\Modules\Content\Actions\BuildCategoryTree;
use App\Modules\Core\Http\CurrentMember;
use Dedoc\Scramble\Attributes\Group;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

#[Group('Content', 'The category tree the member can play.', 3)]
class CategoryController
{
    use CurrentMember;

    /**
     * Category tree.
     *
     * Active categories whose age range contains the member's age. Top-level categories beyond the
     * tier's `max_categories` have `is_locked: true`.
     */
    public function index(Request $request, BuildCategoryTree $build): JsonResponse
    {
        return new JsonResponse(['data' => $build->handle($this->currentMember($request))]);
    }
}
