<?php

declare(strict_types=1);

namespace Lectern\Observability\Support;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;

final class RouteLabelResolver
{
    public function resolve(Request $request): string
    {
        $route = $request->route();

        if (! $route instanceof Route) {
            return '_unmatched';
        }

        $name = $route->getName();

        if (is_string($name) && $name !== '') {
            return $name;
        }

        $uri = $route->uri();

        if ($uri !== '') {
            return $uri;
        }

        return '_unmatched';
    }
}
