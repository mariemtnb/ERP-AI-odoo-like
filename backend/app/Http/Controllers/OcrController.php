<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

/** Proxies invoice images to the AI service's local vision model. */
class OcrController extends Controller
{
    public function invoice(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'mimes:png,jpg,jpeg,webp', 'max:10240'],
        ]);

        $file = $request->file('file');
        $base = rtrim(env('AI_SERVICE_URL', 'http://ai-service:8001'), '/');
        $token = $request->bearerToken();

        $response = Http::timeout((float) env('AI_TIMEOUT', 600))
            ->withToken($token)
            ->attach('file', fopen($file->getRealPath(), 'r'), $file->getClientOriginalName(), [
                'Content-Type' => $file->getMimeType(),
            ])
            ->post("{$base}/extract-invoice");

        if ($response->failed()) {
            return response()->json(['detail' => 'OCR service unavailable.'], 502);
        }

        $data = $response->json();

        // Convenience: resolve the supplier by name if we already know it.
        if (! empty($data['supplier_name'])) {
            $supplier = Supplier::where('is_active', true)
                ->whereRaw('LOWER(name) LIKE ?', ['%'.mb_strtolower($data['supplier_name']).'%'])
                ->first();
            $data['matched_supplier_id'] = $supplier?->id;
        }

        AuditLog::create([
            'user_id' => $request->user()->id,
            'actor' => 'user',
            'action' => 'ocr_extract_invoice',
            'payload' => ['filename' => $file->getClientOriginalName()],
            'created_at' => now(),
        ]);

        return response()->json($data);
    }
}
