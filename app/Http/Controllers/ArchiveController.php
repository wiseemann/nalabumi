<?php

namespace App\Http\Controllers;

use App\Models\Article;
use App\Models\Category;
use App\Models\Location;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ArchiveController extends Controller
{
    public function index(Request $request)
    {
        $query = $this->buildArchiveQuery($request);
        $articles = $query->paginate(20);

        $categories = Category::all();
        $provinces = Location::select('province_name')->distinct()->whereNotNull('province_name')->pluck('province_name');

        return Inertia::render('Archive', [
            'articles' => $articles,
            'categories' => $categories,
            'provinces' => $provinces,
            'filters' => $request->only(['search', 'region', 'category', 'date_from', 'date_to', 'status']),
        ]);
    }

    public function export(Request $request)
    {
        $query = $this->buildArchiveQuery($request);
        $query->with(['cases']);
        
        $articles = $query->get();

        $fileName = 'arsip-nalabumi-' . date('Y-m-d_H-i-s') . '.csv';

        return response()->streamDownload(function () use ($articles) {
            $handle = fopen('php://output', 'w');
            
            // Add BOM for Excel UTF-8 compatibility
            fputs($handle, chr(0xEF) . chr(0xBB) . chr(0xBF));

            // Headers
            fputcsv($handle, [
                'Tanggal Publikasi',
                'Wilayah Terkait',
                'Kategori',
                'Sumber',
                'Judul Berita',
                'Status Lingkungan (Severity)',
                'Tautan Asli'
            ]);

            foreach ($articles as $article) {
                // Calculate severity string
                $severityStr = '—';
                if ($article->cases && $article->cases->isNotEmpty()) {
                    $sev = $article->cases->first()->severity;
                    if ($sev >= 61) $severityStr = "Kritis ($sev)";
                    elseif ($sev >= 41) $severityStr = "Tinggi ($sev)";
                    elseif ($sev >= 21) $severityStr = "Sedang ($sev)";
                    else $severityStr = "Rendah ($sev)";
                }

                $locations = $article->locations->map(fn($l) => $l->regency_name ?? $l->name)->join(', ');
                $categories = $article->categories->map(fn($c) => $c->name)->join(', ');

                fputcsv($handle, [
                    $article->published_at ? $article->published_at->format('Y-m-d H:i') : '—',
                    $locations,
                    $categories,
                    $article->source,
                    $article->title,
                    $severityStr,
                    $article->url
                ]);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    protected function buildArchiveQuery(Request $request)
    {
        $query = Article::with(['categories', 'locations', 'cases'])
            ->where('is_analyzed', true)
            ->has('cases')
            ->orderByDesc('published_at');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('content', 'like', "%{$search}%");
            });
        }

        if ($request->filled('region')) {
            $query->whereHas('locations', function ($q) use ($request) {
                $q->where('regency_name', 'like', "%{$request->region}%")
                    ->orWhere('province_name', 'like', "%{$request->region}%");
            });
        }

        if ($request->filled('category')) {
            $query->whereHas('categories', function ($q) use ($request) {
                $q->where('categories.id', $request->category);
            });
        }

        if ($request->filled('date_from')) {
            $query->whereDate('published_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('published_at', '<=', $request->date_to);
        }

        if ($request->filled('status')) {
            $statusMap = [
                'rendah' => [0, 20],
                'sedang' => [21, 40],
                'tinggi' => [41, 60],
                'kritis' => [61, 100],
            ];
            if (isset($statusMap[$request->status])) {
                $range = $statusMap[$request->status];
                $query->whereHas('cases', function ($q) use ($range) {
                    $q->whereBetween('severity', $range);
                });
            }
        }

        return $query;
    }
}
