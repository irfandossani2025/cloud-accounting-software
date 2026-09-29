<?php

namespace App\Http\Controllers;

use App\Support\PintOm;
use Illuminate\Http\Request;

/** Type-ahead search over the official Oman HS and ISIC code lists. */
class CodeSearchController extends Controller
{
    public function __invoke(Request $request, string $list)
    {
        abort_unless(in_array($list, ['hs', 'isic'], true), 404);

        $results = PintOm::search($list, (string) $request->query('q'), 25);

        return response()->json(array_map(fn ($code, $description) => compact('code', 'description'), array_keys($results), $results));
    }
}
