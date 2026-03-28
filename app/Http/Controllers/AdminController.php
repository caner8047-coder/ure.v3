<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function index()
    {
        return view('admin.index');
    }

    public function orders()
    {
        return view('orders.index');
    }

    public function special()
    {
        return view('orders.special');
    }

    public function criticalStocks()
    {
        return view('stocks.critical');
    }

    public function productMatch()
    {
        return view('products.match');
    }

    public function productSettings()
    {
        return view('products.settings');
    }

    public function stocks()
    {
        return view('stocks.index');
    }

    public function statistics()
    {
        return view('reports.statistics');
    }

    public function database()
    {
        return view('admin.database');
    }
}
