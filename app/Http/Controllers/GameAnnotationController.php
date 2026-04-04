<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameAnnotation;
use App\Services\SgfService;
use Illuminate\Http\Request;

class GameAnnotationController extends Controller
{
    public function __construct(private SgfService $sgfService) {}

    /**
     * Save a new annotation for a finished game.
     */
    public function store(Game $game, Request $request)
    {
        if ($game->status !== 'finished') {
            return response()->json(['error' => 'ยังไม่สามารถ annotate เกมที่ยังไม่จบได้'], 422);
        }

        $data = $request->validate([
            'title'       => ['required', 'string', 'max:120'],
            'sgf_content' => ['required', 'string', 'max:500000'],
        ]);

        $annotation = GameAnnotation::create([
            'game_id'     => $game->id,
            'user_id'     => $request->user()->id,
            'title'       => $data['title'],
            'sgf_content' => $data['sgf_content'],
        ]);

        return response()->json([
            'id'         => $annotation->id,
            'title'      => $annotation->title,
            'user'       => $request->user()->getDisplayName(),
            'created_at' => $annotation->created_at->toDateTimeString(),
        ], 201);
    }

    /**
     * Update an existing annotation (owner only).
     */
    public function update(Game $game, GameAnnotation $annotation, Request $request)
    {
        if ($annotation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์แก้ไข'], 403);
        }

        $data = $request->validate([
            'title'       => ['sometimes', 'string', 'max:120'],
            'sgf_content' => ['sometimes', 'string', 'max:500000'],
        ]);

        $annotation->update($data);

        return response()->json(['success' => true]);
    }

    /**
     * Delete an annotation (owner only).
     */
    public function destroy(Game $game, GameAnnotation $annotation, Request $request)
    {
        if ($annotation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์ลบ'], 403);
        }

        $annotation->delete();

        return response()->json(['success' => true]);
    }

    /**
     * Return the base SGF for a game (generated from DB moves, no annotations).
     */
    public function baseSgf(Game $game)
    {
        if ($game->status !== 'finished') {
            return response()->json(['error' => 'เกมยังไม่จบ'], 422);
        }

        $game->load('blackPlayer', 'whitePlayer', 'moves');

        return response()->json(['sgf' => $this->sgfService->generateSgf($game)]);
    }
}
