<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Attribute;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;
use Rap2hpoutre\FastExcel\FastExcel;
use Illuminate\Support\Facades\DB;

class AttributeController extends Controller
{
    function index()
    {
        $attributes = Attribute::orderBy('name')->paginate(25);
        return view('admin-views.attribute.index', compact('attributes'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required|unique:attributes',
        ], [
            'name.required' => 'Name is required!',
        ]);

        $attribute = new Attribute;
        $attribute->name = $request->name;
        $attribute->save();
        Toastr::success('Attribute added successfully!');
        return back();
    }

    public function edit($id)
    {
        $attribute = Attribute::find($id);
        return view('admin-views.attribute.edit', compact('attribute'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required|unique:attributes,name,'.$id,
        ], [
            'name.required' => 'Name is required!',
        ]);

        $attribute = Attribute::find($id);
        $attribute->name = $request->name;
        $attribute->save();
        Toastr::success('Attribute updated successfully!');
        return back();
    }

    public function delete(Request $request)
    {
        $attribute = Attribute::find($request->id);
        $attribute->delete();
        Toastr::success('Attribute removed!');
        return back();
    }

    public function search(Request $request){
        $key = explode(' ', $request['search']);
        $attributes=Attribute::where(function ($q) use ($key) {
            foreach ($key as $value) {
                $q->orWhere('name', 'like', "%{$value}%");
            }
        })->limit(50)->get();
        return response()->json([
            'view'=>view('admin-views.attribute.partials._table',compact('attributes'))->render()
        ]);
    }

    public function bulk_import_index()
    {
        return view('admin-views.attribute.bulk-import');
    }

    public function bulk_import_data(Request $request)
    {
        try {
            $collections = (new FastExcel)->import($request->file('products_file'));
        } catch (\Exception $exception) {
            Toastr::error('You have uploaded a wrong format file, please upload the right file.');
            return back();
        }

        $data = [];
        $skip = ['youtube_video_url'];
        foreach ($collections as $collection) {
                if ($collection['name'] === "" ) {
                    Toastr::error('Please fill all the required fields');
                    return back();
                }


            array_push($data, [
                'name' => $collection['name'],
                'created_at'=>now(),
                'updated_at'=>now()
            ]);
        }
        DB::table('attributes')->insert($data);
        Toastr::success(count($data) . ' - Attributes imported successfully!');
        return back();
    }

    public function bulk_export_index()
    {
        return view('admin-views.attribute.bulk-export');
    }

    public function bulk_export_data(Request $request)
    {
        $request->validate([
            'type'=>'required',
            'start_id'=>'required_if:type,id_wise',
            'end_id'=>'required_if:type,id_wise',
            'from_date'=>'required_if:type,date_wise',
            'to_date'=>'required_if:type,date_wise'
        ]);
        $attributes = Attribute::when($request['type']=='date_wise', function($query)use($request){
            $query->whereBetween('created_at', [$request['from_date'].' 00:00:00', $request['to_date'].' 23:59:59']);
        })
        ->when($request['type']=='id_wise', function($query)use($request){
            $query->whereBetween('id', [$request['start_id'], $request['end_id']]);
        })
        ->get();
        return (new FastExcel($attributes))->download('Attributes.xlsx');
    }
}
