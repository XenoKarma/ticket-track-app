<?php

namespace App\Http\Controllers;

use App\Http\Requests\TicketReplyStoreRequest;
use App\Http\Requests\TicketStoreRequest;
use App\Models\Ticket;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use App\Http\Resources\TicketResource;
use App\Http\Resources\TicketReplyResource;
use App\Models\TicketReply;

class TicketController extends Controller
{
    public function index(Request $request)
    {
        try {
        $query = Ticket::with(['user', 'replies']);

        $query->orderBy('created_at', 'desc');
        if($request->search){
            $query->where(function($q) use ($request) {
            $q->where('title', 'like', '%' . $request->search . '%')
            ->orWhere('description', 'like', '%' . $request->search . '%');
        });
        }

        if($request->status){
            $query->where('status', $request->status);
        }

        if($request->priority){
            $query->where('priority', $request->priority);
        }

        if($request->user()->role == 'user'){
            $query->where('user_id', $request->user()->id);
        }

        $tickets = $query->paginate(10);

        return response()->json([
            'message' => 'Tickets retrieved successfully',
            'data' => TicketResource::collection($tickets),
            'meta' => [
                'current_page' => $tickets->currentPage(),
                'last_page' => $tickets->lastPage(),
                'per_page' => $tickets->perPage(),
                'total' => $tickets->total(),
            ]
        ], 200);
        }catch(\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving tickets',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function show(Request $request, $code)
    {
        try {
            $ticket = Ticket::with(['user','replies.user'])
            ->where('code', $code)
            ->first();

            if(!$ticket){
                return response()->json([
                    'message' => 'Ticket not found',
                    'data' => null
                ], 404);
            }

            if($request->user()->role == 'user' && $ticket->user_id != $request->user()->id){
                return response()->json([
                    'message' => 'Unauthorized',
                    'data' => null
                ], 401);
            }

            return response()->json([
                'message' => 'Ticket retrieved successfully',
                'data' => new TicketResource($ticket)
            ], 200);

        }catch(\Exception $e) {
            return response()->json([
                'message' => 'Error retrieving ticket',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    public function store(TicketStoreRequest $request)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $ticket = new Ticket;
            $ticket->user_id = $request->user()->id;
            $ticket->code = 'TICKET-' . now()->format('YmdHis') . '-' . rand(1000, 9999);
            $ticket->title = $data['title'];
            $ticket->description = $data['description'];
            $ticket->priority = $data['priority'];
            $ticket->save();
            DB::commit();
            return response()->json([
                'message' => 'Ticket created successfully',
                'data' => new TicketResource($ticket)
            ], 201);
        }catch(\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error creating ticket',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function storeReply(TicketReplyStoreRequest $request, $code)
    {
        $data = $request->validated();

        DB::beginTransaction();

        try {
            $ticket = Ticket::where('code', $code)->first();

            if(!$ticket){
                return response()->json([
                    'message' => 'Ticket not found',
                    'data' => null
                ], 404);
            }

            if($request->user()->role == 'user' && $ticket->user_id != $request->user()->id){
                return response()->json([
                    'message' => 'Unauthorized',
                    'data' => null
                ], 401);
            }

            $ticketReply = new TicketReply();
            $ticketReply->ticket_id =$ticket->id ;
            $ticketReply->user_id = $request->user()->id;
            $ticketReply->content = $data['content'];
            $ticketReply->save();

            if ($request->user()->role == 'admin') {
                if(isset($data['status'])) {
                $ticket->status = $data['status'];

                if($data['status'] === 'resolved'){
                    $ticket->resolved_at = now();
                }
            }
                $ticket->save();
            }

            DB::commit();

            return response()->json([
                'message' => 'Reply added successfully',
                'data' => new TicketReplyResource($ticketReply)
            ], 201);

        }catch(\Exception $e) {
            DB::rollBack();
            
            return response()->json([
                'message' => 'Error adding reply',
                'data' => null,
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
