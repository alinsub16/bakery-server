<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\ActivityLogResource;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;

class ActivityLogController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = ActivityLog::with('user:id,name');

        if ($request->filled('subject_type')) {
            $query->where('subject_type', $request->string('subject_type'));
        }

        if ($request->filled('action')) {
            $query->where('action', $request->string('action'));
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->integer('user_id'));
        }

        if ($request->filled('from')) {
            $query->whereDate('created_at', '>=', Carbon::parse($request->string('from'))->toDateString());
        }

        if ($request->filled('to')) {
            $query->whereDate('created_at', '<=', Carbon::parse($request->string('to'))->toDateString());
        }

        return ActivityLogResource::collection(
            $query->orderByDesc('created_at')->paginate(20)
        );
    }
}