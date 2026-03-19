<?php

namespace App\Services\MediaLibrary;

use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Storage;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Spatie\MediaLibrary\Support\PathGenerator\PathGenerator;

class CustomPathGenerator implements PathGenerator
{
    public function getPathForConversions(Media $media): string
    {
        return $this->getBasePath($media) . '/conversions/';
    }

    public function getPath(Media $media): string
    {
        return $this->getBasePath($media) . '/';
    }

    public function getPathForResponsiveImages(Media $media): string
    {
        return $this->getBasePath($media) . '/responsive-images/';
    }

    protected function getBasePath(Media $media): string
    {
        $prefix = 'media';

        $modelType = $media->model_type;
        $prefix = $prefix . '/' . class_basename($modelType);
        $model = App::make($modelType)->find($media->model_id);
        if ($model && ! empty($model->name)) {
            $folder = $model->name;
        } else {
            $folder = (string) $media->model_id;
        }

        // Old layout (shared folder):
        //   media/<Model>/<name-or-id>/<filename>
        // New layout (isolated per media):
        //   media/<Model>/<name-or-id>/<media_id>/<filename>
        //
        // The shared folder layout can cause a delete() to remove the whole directory,
        // breaking remaining attachments. We isolate each media in its own directory.
        // For backward compatibility, if the file exists in the old layout we keep using it.
        $oldBase = $prefix !== '' ? ($prefix . '/' . $folder) : $folder;
        $newBase = $oldBase . '/' . $media->getKey();

        $diskName = $media->disk ?: config('media-library.disk_name');
        // Per gestire i media che sono già presenti nella cartella oldBase (path precedente condivisa)
        if (Storage::disk($diskName)->exists($oldBase . '/' . $media->file_name)) {
            return $oldBase;
        }

        return $newBase;
    }
}
