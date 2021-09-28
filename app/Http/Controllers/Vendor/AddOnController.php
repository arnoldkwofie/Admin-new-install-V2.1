<?php

namespace App\Http\Controllers\Vendor;

use App\Http\Controllers\Controller;
use App\Models\AddOn;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Http\Request;

class AddOnController extends Controller
{
    public function index()
    {
        $addons = AddOn::orderBy('name')->paginate(25);
        return view('vendor-views.addon.index', compact('addons'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'name' => 'required',
        ], [
            'name.required' => 'Name is required!',
        ]);

        $addon = new AddOn();
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->restaurant_id = \App\CentralLogics\Helpers::get_restaurant_id();
        $addon->save();
        Toastr::success('Addon added successfully!');
        return back();
    }

    public function edit($id)
    {
        $addon = AddOn::find($id);
        return view('vendor-views.addon.edit', compact('addon'));
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'name' => 'required',
            'price' => 'required',
        ], [
            'name.required' => 'Name is required!',
        ]);

        $addon = AddOn::find($id);
        $addon->name = $request->name;
        $addon->price = $request->price;
        $addon->save();
        Toastr::success('Addon updated successfully!');
        return back();
    }

    public function delete(Request $request)
    {
        $addon = AddOn::find($request->id);
        $addon->delete();
        Toastr::success('Addon removed!');
        return back();
    }
}
