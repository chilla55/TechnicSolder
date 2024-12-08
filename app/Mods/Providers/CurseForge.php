<?php

namespace App\Mods\Providers;

use App\Mods\ImportedModData;
use App\Mods\ModProvider;

class CurseForge extends ModProvider
{
    public static function name() : string
    {
        return "CurseForge";
    }

    protected static function apiUrl() : string
    {
        return "https://api.curseforge.com";
    }

    protected static function apiHeaders() : array
    {
        return array(
            'x-api-key: ' . config('solder.curseforge_api_key')
        );
    }

    public static function search(string $query, int $page = 1) : object
    {
        $pageSize = 20;
        $offset = ($page - 1) * $pageSize;
        $mods = [];
        $data = static::request("/v1/mods/search?gameid=432&sortOrder=desc&pageSize=$pageSize&index=$offset&searchFilter=" . urlencode($query));
        if ($data) {
            foreach ($data->data as $mod) {
                array_push($mods, static::generateModData($mod));
            }

            return (object) [
                'mods' => $mods,
                'pagination' => (object) [
                    'currentPage' => ($data->pagination->index / $pageSize) + 1,
                    'totalPages' => ceil($data->pagination->totalCount / $pageSize),
                    'totalItems' => $data->pagination->	totalCount
                ]
            ];
        } else {
            return (object) [
                'mods' => array(),
                'errors' => ["bad_api_key" => "Bad API key specified for CurseForge"],
                'pagination' => (object) [
                    'currentPage' => 1,
                    'totalPages' => 1,
                    'totalItems' => 0
                ]
                ];
        }
    }

    public static function mod(string $modId) : ?ImportedModData
    {
        $mod = static::request("/v1/mods/$modId");
        if ($mod === null) {
            return null;
        }
        return static::generateModData($mod->data, true);
    }
    private static function generateModData($mod, $search = false)
    {
        $modData = new ImportedModData();

        $modData->id = strval($mod->id);
        $modData->slug = $mod->slug;

        $modData->name = $mod->name;
        $modData->summary = $mod->summary;

        $authors = [];
        foreach ($mod->authors as $author) {
            array_push($authors, $author->name);
        }
        $modData->authors = implode(", ", $authors);

        $modData->thumbnailUrl = empty($mod->logo->thumbnailUrl) ? "https://www.curseforge.com/favicon.ico" : $mod->logo->thumbnailUrl;
        $modData->thumbnailDesc = empty($mod->logo->description) ? $mod->name : $mod->logo->description;
        $modData->websiteUrl = $mod->links->websiteUrl;

        $modData->versions = [];
        if ($search){
            $index = 0;
            while (true)
            {
                $files = static::request("/v1/mods/$modData->id/files?pageSize=50&index=$index");
                if ($files === null) {
                    return null;
                }
                foreach ($files->data as $file) {
                    $modData->versions[$file->displayName] = (object) [
                        "url" => $file->downloadUrl,
                        "filename" => $file->fileName,
                        "gameVersions" => $file->gameVersions
                    ];
                }
                if ($index >= $files->pagination->totalCount)
                {
                    return $modData;
                }
                $index += 50;
            }
        }
        else
        {
            foreach ($mod->latestFiles as $file) {
                $modData->versions[$file->displayName] = (object) [
                    "url" => $file->downloadUrl,
                    "filename" => $file->fileName,
                    "gameVersions" => $file->gameVersions
                ];
            }
        }

        return $modData;
    }
}