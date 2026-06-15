<?php

namespace App\Services\MediaLibrary;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class OrchestratorPathGenerator implements PathGenerator
{
    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $disk = $media->disk ?: config('media-library.disk_name');

        // Layout C — WmfePathGenerator (mag 2026–oggi)
        // orchestrator/media/{id}/
        $layoutC = 'orchestrator/media/' . $media->getKey();
        if (Storage::disk($disk)->exists($layoutC . '/' . $media->file_name)) {
            return $layoutC;
        }

        // Layout B — CustomPathGenerator aggiornato (apr–mag 2026)
        // media/{Model}/{name-or-id}/{media_id}/
        $legacyBase = $this->getLegacyBase($media);
        $layoutB = $legacyBase . '/' . $media->getKey();
        if (Storage::disk($disk)->exists($layoutB . '/' . $media->file_name)) {
            return $layoutB;
        }

        // Layout A — CustomPathGenerator vecchio (fino ad apr 2026)
        // media/{Model}/{name-or-id}/
        if (Storage::disk($disk)->exists($legacyBase . '/' . $media->file_name)) {
            return $legacyBase;
        }

        // Default: nuovi upload → Layout C
        return $layoutC;
    }

    protected function getLegacyBase(Media $media): string
    {
        $prefix = 'media/' . class_basename($media->model_type);
        $model = App::make($media->model_type)->find($media->model_id);
        $folder = ($model && ! empty($model->name)) ? $model->name : (string) $media->model_id;

        return $prefix . '/' . $folder;
    }
}
