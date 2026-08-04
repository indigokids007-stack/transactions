<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\CategoryResource;
use App\Http\Resources\DimensionResource;
use App\Http\Resources\UserResource;
use App\Models\Category;
use App\Models\Currency;
use App\Models\Dimension;
use App\Models\User;
use App\Support\StickyDefaults;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;

class BootstrapController extends Controller
{
    /** @return array<string, mixed> */
    public function __invoke(Request $request): array
    {
        /** @var User $user */
        $user = $request->user();

        return [
            'user' => UserResource::make($user->loadMissing(['department', 'managedDepartments'])),
            'categories' => CategoryResource::collection($this->categoryTree()),
            'dimensions' => DimensionResource::collection($this->activeDimensions()),
            'currencies' => Currency::exponents(),
            'defaults' => StickyDefaults::for($user),
        ];
    }

    /** @return Collection<int, Category> */
    private function categoryTree(): Collection
    {
        $categories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->get();

        return $this->attachChildren($categories, null);
    }

    /**
     * @param  Collection<int, Category>  $categories
     * @return Collection<int, Category>
     */
    private function attachChildren(Collection $categories, ?int $parentId): Collection
    {
        return $categories
            ->filter(fn (Category $category) => $category->parent_id === $parentId)
            ->values()
            ->each(fn (Category $category) => $category->setRelation(
                'children',
                $this->attachChildren($categories, $category->id),
            ));
    }

    /** @return Collection<int, Dimension> */
    private function activeDimensions(): Collection
    {
        return Dimension::query()
            ->where('is_active', true)
            ->orderBy('sort')
            ->orderBy('name')
            ->with(['values' => fn ($query) => $query->where('is_active', true)->orderBy('sort')->orderBy('name')])
            ->get();
    }
}
