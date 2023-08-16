<?php

namespace App\Services\MediaLibrary;

use Illuminate\Support\Facades\App;
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
        if ($model->name) {
            $folder = $model->name;
        } else {
            $folder = $model->id;
        }
        if ($prefix !== '') {
            return  $prefix . '/' . $folder;
        }

        return $folder;
    }
}
