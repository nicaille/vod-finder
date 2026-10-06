<?php

namespace App\Http\Controllers;

use App\Models\SitePage;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class AboutController extends Controller
{
    public function show()
    {
        $page = SitePage::where('slug', 'about')->firstOrFail();
        $html = Str::markdown($page->body, ['html_input' => 'strip', 'allow_unsafe_links' => false]);
        return view('pages.about', compact('page', 'html'));
    }

    public function edit()
    {
        $page = SitePage::where('slug', 'about')->firstOrFail();
        return view('admin.about', compact('page'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:20000'],
        ]);
        SitePage::where('slug', 'about')->firstOrFail()->update($data);
        return redirect()->route('admin.index')->with('status', 'La page À propos a été mise à jour.');
    }
}
