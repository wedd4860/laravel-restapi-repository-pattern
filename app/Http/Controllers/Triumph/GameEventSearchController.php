<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\GamesEventSearchRequest;
use App\Http\Resources\Triumph\EventSearchResource;
use App\Models\Triumph\Events;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\DB;

class GameEventSearchController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GamesEventSearchRequest $request, int $gameId)
    {
        try {
            $aValidated = $request->validated();

            $iEventStatus = null;
            if (Arr::get($aValidated, 'event_status')) {
                $strEventStatus = Arr::get($aValidated, 'event_status');
                $aConfigEventStatus = [
                    'ready' => 0,
                    'completed' => 1,
                    'progress' => 2,
                    'finished' => 3,
                ];
                $iEventStatus = $aConfigEventStatus[$strEventStatus] ?? null;
            }

            $iFormat = null;
            if (Arr::get($aValidated, 'bracket_type')) {
                $strBracketType = Arr::get($aValidated, 'bracket_type');
                $aConfigFormat = [
                    'tournament' => 0,
                ];
                $iFormat = $aConfigFormat[$strBracketType] ?? null;
            }

            $iTeamSize = Arr::get($aValidated, 'entry_type', null);

            $strFilterBy = 'games_game_id:=' . $gameId;
            if (!is_null($iEventStatus)) {
                $strFilterBy .= ' && status:=' . $iEventStatus;
            } else {
                $strFilterBy .= ' && status:!=4';
            }

            if (!is_null($iFormat)) {
                $strFilterBy .= ' && format:=' . $iFormat;
            }
            if (!is_null($iTeamSize)) {
                $strFilterBy .= ' && team_size:=' . $iTeamSize;
            }
            if (Arr::get($aValidated, 'search')) {
                //검색어 있을때 검색엔진 사용
                $searchQuery = Events::search($aValidated['search'])->options([
                    'query_by' => 'title,description,embedding',
                    'vector_query' => 'embedding:([], distance_threshold:0.43)',
                    'language' => 'korean,english',
                    'sort_by' => Arr::get($aValidated, 'order_type', 'date') == 'date' ? 'event_start_dt:desc' : 'status:asc,event_start_dt:desc',
                    'filter_by' => $strFilterBy
                ]);
                $aSearchEvents = $searchQuery->paginate(12);
                $aPaginate = $aSearchEvents;
                $aEventsIdx = collect($aSearchEvents->items())->pluck('event_id')->all();
                // 검색 결과를 기반으로 재조회
                $aEvents = Events::with('games', 'members')
                    ->withCount(['participants' => function ($query) {
                        $query->whereNotNull('checkin_dt');
                    }])
                    ->whereIn('event_id', $aEventsIdx);
                if (count($aEventsIdx) > 0) {
                    $aEvents->orderByRaw('FIELD(event_id, ' . implode(',', $aEventsIdx) . ')');
                }
                $aEvents = $aEvents->get();
            } else {
                // 검색어 없을때
                $query = Events::with(['games', 'members'])
                    ->whereHas('games', function ($query) use ($gameId) {
                        $query->where('game_id', $gameId);
                    })
                    ->withCount(['participants' => function ($query) {
                        $query->whereNotNull('checkin_dt');
                    }]);
                if (!is_null($iEventStatus)) {
                    $query->where('status', $iEventStatus);
                } else {
                    $query->where('status', '<', 4);
                }
                if (!is_null($iFormat)) {
                    $query->where('format', $iFormat);
                }
                if (!is_null($iTeamSize)) {
                    $query->where('team_size', $iTeamSize);
                }
                if (Arr::get($aValidated, 'order_type', 'date') == 'status') {
                    $query->orderBy('status', 'asc');
                }
                $query->orderBy('event_start_dt', 'desc');
                $aEvents = $query->paginate(12);
                $aPaginate = $aEvents;
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => EventSearchResource::collection($aEvents),
                'paginate' => [
                    'total' => $aPaginate->total(),
                    'count' => $aPaginate->count(),
                    'per_page' => $aPaginate->perPage(),
                    'current_page' => $aPaginate->currentPage(),
                    'total_pages' => $aPaginate->lastPage()
                ]
            ];
            return response()->json($aJsonData, $aJsonData['code']);
        } catch (\Exception $e) {
            $iCode = $e->getCode();
            $returnCode = ($iCode >= 300 && $iCode < 600) ? $iCode : 500;
            $aJsonData = [
                'status' => 'error',
                'code' => $e->getCode(),
                'message' => $e->getMessage(),
                'data' => []
            ];
            return response()->json($aJsonData, $returnCode);
        }
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(string $id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        return response()->json([
            'status' => 'error',
            'code' => 404,
            'message' => __('messages.Bad Request'),
            'data' => []
        ], 404);
    }
}
