<?php

namespace App\Http\Controllers\Triumph;

use App\Http\Controllers\Controller;
use App\Http\Requests\Triumph\CommonRequest;
use App\Http\Requests\Triumph\GamesRequest;
use App\Http\Resources\Triumph\GameSearchResource;
use App\Http\Resources\Triumph\GameListResource;
use App\Http\Resources\Triumph\GamesResource;
use App\Models\Triumph\Games;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Validator;

class GamesController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(GamesRequest $request)
    {
        try {
            $aValidated = $request->validated();
            $searchQuery = Games::search($aValidated['search'])->options([
                'query_by' => 'game_name',
                'language' => 'korean,english',
            ]);
            $aSearchGames = $searchQuery->paginate(5);
            $aGamesIdx = collect($aSearchGames->items())->pluck('game_id');
            // 검색 결과를 기반으로 재조회
            $aGames = Games::whereIn('game_id', $aGamesIdx)->get();
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => GameSearchResource::collection($aGames),
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

    public function all(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'service' => 'required|string',
                'device' => 'required|in:mobile,web,android,ios',
            ]);
            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 400);
            }
            $aValidated = $validator->validated();
            //$games = PlatformGames::with(['games'])->get();
            $games = Games::get();
            // $games = $games->sortByDesc(['games.order', 'games.name']);
            $games = $games->sortByDesc(['order', 'name']);
            $outName = 'Other';
            $outGame = $games->filter(function ($game) use ($outName) {
                // return $game->games->name == $outName;
                return $game->name == $outName;
            });
            $tmpGames = $games->filter(function ($game) use ($outName) {
                // return $game->games->name != $outName;
                return $game->name != $outName;
            });
            $games = $tmpGames->concat($outGame);
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => GameListResource::collection($games)
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
     * Display the specified resource.
     */
    public function show(CommonRequest $request, int $gameId)
    {
        try {
            $aValidated = $request->validated();
            $aGames = Games::with('gameBanners')
                ->where('game_id', $gameId)
                ->get();
            if ($aGames->count() == 0) {
                throw new \Exception(__('messages.Not Found'), 404);
            }
            $aJsonData = [
                'status' => 'success',
                'code' => 200,
                'message' => __('messages.Request successful'),
                'data' => GamesResource::collection($aGames),
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
}
