<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;

/**
 * RAG document store: text goes in, gets chunked and embedded (local model),
 * and becomes searchable by meaning — for users and for the AI agent.
 */
class DocumentController extends Controller
{
    private const CHUNK_SIZE = 1200;
    private const CHUNK_OVERLAP = 150;

    private function embed(Request $request, array $texts): array
    {
        $base = rtrim(env('AI_SERVICE_URL', 'http://ai-service:8001'), '/');
        $response = Http::timeout(120)
            ->withToken($request->bearerToken())
            ->post("{$base}/embed", ['texts' => $texts]);
        abort_if($response->failed(), 502, 'Embedding service unavailable.');

        return $response->json('embeddings');
    }

    private static function chunk(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $chunks = [];
        $start = 0;
        $len = mb_strlen($text);
        while ($start < $len) {
            $chunks[] = mb_substr($text, $start, self::CHUNK_SIZE);
            $start += self::CHUNK_SIZE - self::CHUNK_OVERLAP;
        }

        return $chunks ?: [''];
    }

    public function index()
    {
        $docs = DB::table('documents')
            ->select('title', DB::raw('COUNT(*) as chunks'), DB::raw('MIN(created_at) as created_at'))
            ->groupBy('title')
            ->orderByDesc(DB::raw('MIN(created_at)'))
            ->get();

        return response()->json(['results' => $docs]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'content' => ['required', 'string', 'max:200000'],
        ]);

        $chunks = self::chunk($data['content']);
        $embeddings = $this->embed($request, $chunks);

        DB::transaction(function () use ($data, $chunks, $embeddings, $request) {
            DB::table('documents')->where('title', $data['title'])->delete(); // re-upload replaces
            foreach ($chunks as $i => $chunk) {
                DB::insert(
                    'INSERT INTO documents (title, chunk_index, content, created_by, created_at, embedding)
                     VALUES (?, ?, ?, ?, NOW(), ?::vector)',
                    [$data['title'], $i, $chunk, $request->user()->id, json_encode($embeddings[$i])]
                );
            }
        });

        return response()->json(['title' => $data['title'], 'chunks' => count($chunks)], 201);
    }

    public function search(Request $request)
    {
        $query = trim((string) $request->query('q', ''));
        abort_if($query === '', 422, 'q is required');

        [$embedding] = $this->embed($request, [$query]);

        $rows = DB::select(
            'SELECT title, chunk_index, content, 1 - (embedding <=> ?::vector) AS similarity
             FROM documents
             ORDER BY embedding <=> ?::vector
             LIMIT 5',
            [json_encode($embedding), json_encode($embedding)]
        );

        return response()->json([
            'query' => $query,
            'results' => array_map(fn ($r) => [
                'title' => $r->title,
                'chunk_index' => $r->chunk_index,
                'content' => $r->content,
                'similarity' => round((float) $r->similarity, 4),
            ], $rows),
        ]);
    }

    public function destroy(Request $request, string $title)
    {
        DB::table('documents')->where('title', $title)->delete();

        return response()->json(null, 204);
    }
}
