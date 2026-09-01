<?php

namespace App\Http\Controllers;

use App\Support\DrfPagination;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/** Shared CRUD for customers and suppliers (mirrors the Django partners app). */
abstract class PartnerController extends Controller
{
    /** @var class-string<Model> */
    protected string $model;

    public function index(Request $request)
    {
        $query = $this->model::query()->orderBy('name');

        if ($search = $request->query('search')) {
            $query->where(fn ($q) => $q
                ->where('name', 'ilike', "%{$search}%")
                ->orWhere('email', 'ilike', "%{$search}%")
                ->orWhere('phone', 'ilike', "%{$search}%"));
        }
        if (! is_null($request->query('is_active'))) {
            $query->where('is_active', filter_var($request->query('is_active'), FILTER_VALIDATE_BOOL));
        }

        return response()->json(
            DrfPagination::paginate($query, $request, fn ($p) => $p->toApi())
        );
    }

    protected function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:200'],
            'email' => ['sometimes', 'nullable', 'email'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:30'],
            'address' => ['sometimes', 'nullable', 'string'],
            'notes' => ['sometimes', 'nullable', 'string'],
            'is_active' => ['sometimes', 'boolean'],
            // Customers only; harmless for suppliers.
            'pricelist_id' => ['sometimes', 'nullable', 'integer', 'exists:pricelists,id'],
        ];
    }

    private static function clean(array $data): array
    {
        foreach (['email', 'phone', 'address', 'notes'] as $field) {
            if (array_key_exists($field, $data) && is_null($data[$field])) {
                $data[$field] = '';
            }
        }

        return $data;
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());
        if (! isset($data['name'])) {
            return response()->json(['name' => ['This field is required.']], 400);
        }

        return response()->json($this->model::create(self::clean($data))->toApi(), 201);
    }

    public function showById(int $id)
    {
        return response()->json($this->model::findOrFail($id)->toApi());
    }

    public function updateById(Request $request, int $id)
    {
        $partner = $this->model::findOrFail($id);
        $partner->update(self::clean($request->validate($this->rules())));

        return response()->json($partner->toApi());
    }

    public function destroyById(int $id)
    {
        $this->model::findOrFail($id)->update(['is_active' => false]);

        return response()->json(null, 204);
    }
}
