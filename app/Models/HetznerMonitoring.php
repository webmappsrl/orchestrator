<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;

class HetznerMonitoring extends Model
{
    protected $table = 'hetzner_monitoring';

    protected $fillable = [
        'properties',
    ];

    protected $casts = [
        'properties' => 'array',
    ];

    protected function projectSlug(): Attribute
    {
        return Attribute::get(fn () => $this->properties['project_slug'] ?? null);
    }

    protected function resourceType(): Attribute
    {
        return Attribute::get(fn () => $this->properties['resource_type'] ?? null);
    }

    protected function resourceId(): Attribute
    {
        return Attribute::get(fn () => $this->properties['resource_id'] ?? null);
    }

    public static function findResource(string $projectSlug, string $resourceType, int $resourceId): ?self
    {
        return static::where('properties->project_slug', $projectSlug)
            ->where('properties->resource_type', $resourceType)
            ->where('properties->resource_id', $resourceId)
            ->first();
    }

    public static function findOrCreateResource(string $projectSlug, string $resourceType, int $resourceId): self
    {
        $existing = static::findResource($projectSlug, $resourceType, $resourceId);

        if ($existing) {
            return $existing;
        }

        return static::create([
            'properties' => [
                'project_slug'  => $projectSlug,
                'resource_type' => $resourceType,
                'resource_id'   => $resourceId,
            ],
        ]);
    }

    public function getNote(): ?array
    {
        return $this->properties['note'] ?? null;
    }

    public function setNote(string $text, int $userId, string $userName): void
    {
        $properties = $this->properties ?? [];

        // Defensive: in case DB returns json/jsonb as string in some envs
        if (is_string($properties)) {
            $decoded = json_decode($properties, true);
            $properties = is_array($decoded) ? $decoded : [];
        }

        $properties = array_merge($properties, [
            'note' => [
                'text'       => $text,
                'user_id'    => $userId,
                'user_name'  => $userName,
                'updated_at' => now()->toIso8601String(),
            ],
        ]);

        $this->forceFill(['properties' => $properties])->save();
    }

    public function deleteNote(): void
    {
        $properties = $this->properties ?? [];

        if (is_string($properties)) {
            $decoded = json_decode($properties, true);
            $properties = is_array($decoded) ? $decoded : [];
        }

        unset($properties['note']);
        $this->forceFill(['properties' => $properties])->save();
    }
}
