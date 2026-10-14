<?php

namespace App\Http\Controllers;

use App\Models\Food;
use App\Models\Restaurant;
use Illuminate\Http\Request;

class ApiV1RestaurantController extends Controller
{
    public function index()
    {
        return response()->json(
            Restaurant::with(['categories', 'foods'])->where('approval_status', 'approved')->paginate(15)
        );
    }

    public function show(Restaurant $restaurant)
    {
        return response()->json(
            $restaurant->load(['categories', 'foods'])
        );
    }

    public function menu(Restaurant $restaurant)
    {
        return response()->json(
            $restaurant->foods()->with('category')->get()
        );
    }

    public function foodDetail(Food $food)
    {
        return response()->json(
            $food->load('restaurant', 'category')
        );
    }
}
