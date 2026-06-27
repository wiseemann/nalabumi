<?php
App\Models\Article::where('is_scraped', true)->where('is_analyzed', false)->get()->each(function($a) {
    App\Jobs\AnalyzeArticleJob::dispatch($a);
});
echo "Done\n";
