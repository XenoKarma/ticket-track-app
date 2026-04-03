<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\DashboardResource;

class DashboardController extends Controller
{
    public function getStatistics()
    {

        $currentMonth = Carbon::now()->startOfMonth();
        $endofMonth = Carbon::now()->endOfMonth();

        $totalTickets = Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])->count();
        $activeTickets = Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])
        ->where('status', '!=', 'resolved')
        ->count();
        $resolvedTickets = Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])
        ->where('status', 'resolved')
        ->count();
        $avgResolutionTime = Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])
        ->where('status', 'resolved')
        ->whereNotNull('completed_at')
        ->select(DB::raw('TIMESTAMPDIFF(HOUR, created_at, completed_at) as avg_time'))
        ->avg('avg_time') ?? 0;

        $statusDistribution = [
            'open' =>Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])->where('status', 'open')->count(),
            'onprogress' => Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])->where('status', 'onprogress')->count(),
            'resolved' => Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])->where('status', 'resolved')->count(),
            'rejected' => Ticket::whereBetween('created_at', [$currentMonth, $endofMonth])->where('status', 'rejected')->count(),
        ];

        $dashboardData = [
            'total_tickets' => $totalTickets,
            'active_tickets' => $activeTickets,
            'resolved_tickets' => $resolvedTickets,
            'avg_resolution_time' => $avgResolutionTime,
            'status_distribution' => $statusDistribution,
        ];

        return response()->json([
            'message' => 'Dashboard statistics retrieved successfully',
            'data' => new DashboardResource($dashboardData) ,
        ]);

    }
}
