<?php

namespace App\Modules\Content\Http\Controllers;

use App\Modules\Content\Actions\BuildCategoryTree;
use App\Modules\Core\Http\CurrentMember;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CategoryController
{
    use CurrentMember;

    public function index(Request $request, BuildCategoryTree $build): JsonResponse
    {
        return new JsonResponse(['data' => $build->handle($this->currentMember($request))]);
    }
}
