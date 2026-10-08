<?php

namespace App\Http\Presenters;

use App\Models\Scheme;

final class SchemePresenter
{
    /**
     * The scheme fields every portfolio page shows.
     *
     * @return array<string, mixed>
     */
    public static function summary(Scheme $scheme): array
    {
        return [
            'id' => $scheme->id,
            'name' => $scheme->name,
            'category' => $scheme->category,
            'plan' => $scheme->plan?->value,
            'latest_nav' => $scheme->latest_nav,
            'latest_nav_date' => $scheme->latest_nav_date?->toDateString(),
        ];
    }
}
