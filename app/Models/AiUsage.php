<?php

namespace App\Models;

use App\Enums\AiFeature;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One call to the AI provider. Cached answers aren't logged.
 *
 * @property int $id
 * @property int $user_id
 * @property AiFeature $feature
 * @property string $provider
 * @property string $model
 * @property int $input_tokens
 * @property int $output_tokens
 * @property bool $succeeded
 * @property CarbonImmutable $created_at
 */
#[Table('ai_usage', timestamps: false)]
#[Fillable(['user_id', 'feature', 'provider', 'model', 'input_tokens', 'output_tokens', 'succeeded', 'created_at'])]
class AiUsage extends Model
{
    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'feature' => AiFeature::class,
            'input_tokens' => 'integer',
            'output_tokens' => 'integer',
            'succeeded' => 'boolean',
            'created_at' => 'datetime',
        ];
    }
}
