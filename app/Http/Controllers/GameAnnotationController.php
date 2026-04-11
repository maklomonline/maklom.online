<?php

namespace App\Http\Controllers;

use App\Models\Game;
use App\Models\GameAnnotation;
use Illuminate\Http\Request;

class GameAnnotationController extends Controller
{
    public function create(Game $game, Request $request)
    {
        $this->ensureFinishedGame($game);

        $game->load('blackPlayer', 'whitePlayer', 'moves');

        return view('games.annotation', [
            'game' => $game,
            'annotation' => null,
            'annotationMeta' => [
                'id' => null,
                'title' => '',
                'user' => $request->user()->getDisplayName(),
                'user_id' => $request->user()->id,
                'updated_at' => now()->toDateTimeString(),
            ],
            'annotationPayload' => $this->defaultPayload(),
            'canEdit' => true,
            'myColor' => $game->getPlayerColor($request->user()),
            'chatRoom' => $game->chatRoom(),
        ]);
    }

    public function show(Game $game, GameAnnotation $annotation, Request $request)
    {
        $this->ensureFinishedGame($game);
        $this->ensureAnnotationBelongsToGame($game, $annotation);

        $game->load('blackPlayer', 'whitePlayer', 'moves');
        $annotation->load('user');

        return view('games.annotation', [
            'game' => $game,
            'annotation' => $annotation,
            'annotationMeta' => [
                'id' => $annotation->id,
                'title' => $annotation->title,
                'user' => $annotation->user?->getDisplayName() ?? '?',
                'user_id' => $annotation->user_id,
                'updated_at' => $annotation->updated_at?->toDateTimeString(),
            ],
            'annotationPayload' => $annotation->payload ?? $this->defaultPayload(),
            'canEdit' => $annotation->user_id === $request->user()->id,
            'myColor' => $game->getPlayerColor($request->user()),
            'chatRoom' => $game->chatRoom(),
        ]);
    }

    public function store(Game $game, Request $request)
    {
        $this->ensureFinishedGame($game);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'payload' => ['required', 'array'],
        ]);

        $payload = $this->normalizePayload($data['payload']);

        $annotation = GameAnnotation::create([
            'game_id' => $game->id,
            'user_id' => $request->user()->id,
            'title' => trim($data['title']),
            'payload' => $payload,
            'positions_count' => count($payload['positions']),
            'last_position_key' => $payload['last_position_key'],
        ]);

        return response()->json([
            'id' => $annotation->id,
            'title' => $annotation->title,
            'user' => $request->user()->getDisplayName(),
            'updated_at' => $annotation->updated_at?->toDateTimeString(),
            'view_url' => route('games.annotation.show', [$game, $annotation]),
        ], 201);
    }

    public function update(Game $game, GameAnnotation $annotation, Request $request)
    {
        $this->ensureFinishedGame($game);
        $this->ensureAnnotationBelongsToGame($game, $annotation);

        if ($annotation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์แก้ไข'], 403);
        }

        $data = $request->validate([
            'title' => ['required', 'string', 'max:120'],
            'payload' => ['required', 'array'],
        ]);

        $payload = $this->normalizePayload($data['payload']);

        $annotation->update([
            'title' => trim($data['title']),
            'payload' => $payload,
            'positions_count' => count($payload['positions']),
            'last_position_key' => $payload['last_position_key'],
        ]);

        return response()->json([
            'success' => true,
            'updated_at' => $annotation->fresh()->updated_at?->toDateTimeString(),
        ]);
    }

    public function destroy(Game $game, GameAnnotation $annotation, Request $request)
    {
        $this->ensureAnnotationBelongsToGame($game, $annotation);

        if ($annotation->user_id !== $request->user()->id) {
            return response()->json(['error' => 'ไม่มีสิทธิ์ลบ'], 403);
        }

        $annotation->delete();

        return response()->json(['success' => true]);
    }

    private function ensureFinishedGame(Game $game): void
    {
        abort_unless($game->status === 'finished', 404);
    }

    private function ensureAnnotationBelongsToGame(Game $game, GameAnnotation $annotation): void
    {
        abort_unless($annotation->game_id === $game->id, 404);
    }

    private function defaultPayload(): array
    {
        return [
            'version' => 2,
            'last_position_key' => 'base-0',
            'positions' => [],
        ];
    }

    private function normalizePayload(array $payload): array
    {
        $positions = [];

        foreach (($payload['positions'] ?? []) as $key => $position) {
            if (! is_string($key) || $key === '') {
                continue;
            }

            $positions[$key] = $this->normalizePosition($key, is_array($position) ? $position : []);
        }

        $lastPositionKey = $payload['last_position_key'] ?? 'base-0';
        if (! is_string($lastPositionKey) || $lastPositionKey === '') {
            $lastPositionKey = 'base-0';
        }

        return [
            'version' => 2,
            'last_position_key' => $lastPositionKey,
            'positions' => $positions,
        ];
    }

    private function normalizePosition(string $key, array $position): array
    {
        $children = array_values(array_filter(
            $position['children'] ?? [],
            fn ($child) => is_string($child) && $child !== ''
        ));

        $normalized = [
            'comment' => trim((string) ($position['comment'] ?? '')),
            'marks' => $this->normalizeMarks($position['marks'] ?? []),
            'children' => $children,
        ];

        if (! str_starts_with($key, 'base-')) {
            $normalized['parent'] = is_string($position['parent'] ?? null) ? $position['parent'] : 'base-0';
            $normalized['color'] = in_array($position['color'] ?? null, ['black', 'white'], true)
                ? $position['color']
                : 'black';
            $normalized['coordinate'] = is_string($position['coordinate'] ?? null) && $position['coordinate'] !== ''
                ? strtoupper($position['coordinate'])
                : null;
            $normalized['is_pass'] = (bool) ($position['is_pass'] ?? false);
            $normalized['order'] = max(0, (int) ($position['order'] ?? 0));
        }

        return $normalized;
    }

    private function normalizeMarks(array $marks): array
    {
        $allowedTypes = ['triangle', 'square', 'circle', 'label', 'number'];
        $normalized = [];

        foreach ($marks as $mark) {
            if (! is_array($mark)) {
                continue;
            }

            $type = $mark['type'] ?? null;
            $coordinate = $mark['coordinate'] ?? null;

            if (! in_array($type, $allowedTypes, true) || ! is_string($coordinate) || $coordinate === '') {
                continue;
            }

            $normalized[] = [
                'type' => $type,
                'coordinate' => strtoupper($coordinate),
                'text' => isset($mark['text']) ? mb_substr((string) $mark['text'], 0, 8) : null,
            ];
        }

        return $normalized;
    }
}
