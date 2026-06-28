<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MediaReputation;
use Illuminate\Http\Request;
use Inertia\Inertia;

class MediaReputationController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        
        $query = MediaReputation::orderBy('name');
        
        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }
        
        $mediaReputations = $query->paginate(20)->withQueryString()->through(fn($media) => [
            'id' => $media->id,
            'name' => $media->name,
            'score' => $media->score,
            'created_at' => $media->created_at?->format('d M Y'),
        ]);

        return Inertia::render('Admin/MediaReputation', [
            'mediaReputations' => $mediaReputations,
            'filters' => [
                'search' => $search,
            ],
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:media_reputations,name',
            'score' => 'required|integer|min:0|max:100',
        ]);

        MediaReputation::create($validated);

        return back()->with('success', "Media {$validated['name']} berhasil ditambahkan.");
    }

    public function update(Request $request, $id)
    {
        $media = MediaReputation::findOrFail($id);
        
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:media_reputations,name,' . $media->id,
            'score' => 'required|integer|min:0|max:100',
        ]);

        $media->update($validated);

        return back()->with('success', "Media {$validated['name']} berhasil diperbarui.");
    }

    public function destroy($id)
    {
        $media = MediaReputation::findOrFail($id);
        $name = $media->name;
        $media->delete();

        return back()->with('success', "Media {$name} berhasil dihapus.");
    }
}
