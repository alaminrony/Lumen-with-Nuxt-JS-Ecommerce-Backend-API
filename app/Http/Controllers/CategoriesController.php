<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\CategoryFeature;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Http\Resources\CategoryCollection;
use App\Http\Resources\SingleCategory;
use Illuminate\Http\Response;

class CategoriesController extends Controller {

    public function __construct() {
        $this->middleware('super_admin_check:store-update-destroy');
    }

    public function index(Request $request) {
        $query = Category::with('parent');

        $categories = $this->filterAndResponse($request, $query);
        return (new CategoryCollection($categories))->response()->setStatusCode(Response::HTTP_OK);

//        return response()->json(['categories' => $categories], 200);
    }

    public function store(Request $request) {
        $rules = [
            'title' => 'required'
        ];

        $validator = Validator::make($request->only('title'), $rules);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Please fix these errors', 'errors' => $validator->errors()], 500);
        }

        $category = new Category();
        $category->title = $request->title;
        $category->description = $request->description;
        $category->parent_id = $request->parent_id != '' ? $request->parent_id : null;
        $category->featured = $request->featured;

        if ($category->save()) {
            $this->insertFeatures($request, $category);
            return response()->json(['success' => 1, 'message' => 'Created successfully', 'category' => $category], 201);
        }
        return response()->json(['success' => 0, 'message' => 'Does not Created successfully'], 500);
    }

    public function show($id) {
        $category = Category::with('parent', 'features')->findOrFail($id);

//        return (new SingleCategory($category))->response()->setStatusCode(Response::HTTP_OK);
//        echo "<pre>";print_r($category->toArray());exit;
        return response()->json(['category' => $category], 200);
    }

    public function update(Request $request, $id) {
        $category = Category::with('parent')->findOrFail($id);

        $validator = Validator::make($request->only('title'), [
                    'title' => 'required'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => 0, 'message' => 'Please fix these errors', 'errors' => $validator->errors()], 500);
        }

        $category->title = $request->title;
        $category->description = $request->description;
        $category->parent_id = $request->parent_id != '' ? $request->parent_id : null;
        $category->featured = $request->featured;

        if ($category->save()) {
            $category->features()->delete();
            $this->insertFeatures($request, $category);
            return response()->json(['success' => 1, 'message' => 'Updated successfully', 'category' => $category], 200);
        }

        return response()->json(['success' => 1, 'message' => 'Does not Updated successfully'], 500);
    }

    public function destroy($id) {
        $category = Category::findOrFail($id);
        if ($category->delete()) {
            return response()->json(['success' => 1, 'message' => 'Deleted successfully'], Response::HTTP_OK);
        }
        return response()->json(['success' => 0, 'message' => 'Deleted done not successfull'], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    protected function filterAndResponse(Request $request, \Illuminate\Database\Eloquent\Builder $query) {
        if ($request->filter_by_id) {
            $query->where('id', $request->filter_by_id);
        }

        if ($request->filter_by_title) {
            $query->where('title', 'like', "%" . $request->filter_by_title . "%");
        }

        if ($request->filter_by_parent_id) {
            $query->where('parent_id', $request->filter_by_parent_id);
        }

        $categories = $query->paginate(5);
        return $categories;
    }

    protected function insertFeatures($request, $category) {
        if ($request->has('features')) {
            foreach ($request->input('features') as $feature) {
                if (!empty($feature["field_title"])) {
                    $categoryFeature = new CategoryFeature();
                    $categoryFeature->field_title = $feature["field_title"];
                    $categoryFeature->field_type = $feature["field_type"];

                    $category->features()->save($categoryFeature);
                }
            }
        }
    }

    public function getCategoryHtmlTree(Request $request, $parent_id = null) {
        $categories = Category::where('parent_id', $parent_id);

        if ($request->except_id) {
            $categories->where('id', '!=', $request->except_id)->get();
        }

        $categories = $categories->get();

        foreach ($categories as $category) {
            echo '<option value="' . $category->id . '">' . str_repeat('-', Category::getCategoryLevel($category->id)) . ' ' . $category->title . '</option>';

            if ($category->children->count() > 0) {
                $this->getCategoryHtmlTree($request, $category->id);
            }
        }
    }

}
