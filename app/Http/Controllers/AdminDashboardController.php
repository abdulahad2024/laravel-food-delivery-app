<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\Restaurant;
use App\Models\User;
use Illuminate\Http\Request;

class AdminDashboardController extends Controller
{
    public function index(Request $request)
    {
        $customers = User::whereHas('roles', fn ($query) => $query->where('name', 'customer'))->count();
        $restaurants = Restaurant::count();
        $orders = Order::count();
        $grossSales = Order::sum('total');

        return view('admin.dashboard', [
            'customers' => $customers,
            'restaurants' => $restaurants,
            'orders' => $orders,
            'grossSales' => $grossSales,
            'recentOrders' => Order::with('customer', 'restaurant')->latest()->take(5)->get(),
        ]);
    }
}
