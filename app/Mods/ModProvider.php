<?php

namespace App\Mods;

use App\Models\Mod;
use App\Models\Modversion;
use App\Mods\ImportedModData;
use Illuminate\Support\Str;
use ZipArchive;

abstract class ModProvider
{
    abstract public static function name() : string;
    abstract protected static function apiUrl() : string;
    abstract public static function search(string $query, int $page = 1) : object;
    abstract public static function mod(string $modId) : ?ImportedModData;


    protected static function apiHeaders() : array
    {
        return array(
            "User-Agent: TechnicPack/TechnicSolder/" . SOLDER_VERSION
        );
    }

    protected static function zipFolder() : string
    {
        return "mods";
    }

    protected static function useRawVersion() : bool
    {
        return false;
    }

    private static function installVersion(int $modId, string $slug, ImportedModData $modData, string $version)
    {
        $url = $modData->versions[$version]->url;
        $fileName = $modData->versions[$version]->filename;
        if (empty($url)) {
            return ["mod_corrupt" => "Unable to find download url for version $version"];
        }
        // Create a temp file to download to
        $tmpFileName = tempnam(sys_get_temp_dir(), "mod");

        // Download the file
        $tmpFile = fopen($tmpFileName, "wb");
        
        $curl_h = curl_init($url);
        curl_setopt($curl_h, CURLOPT_FILE, $tmpFile);
        curl_setopt($curl_h, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($curl_h, CURLOPT_HTTPHEADER, static::apiHeaders());
        curl_exec($curl_h);
        curl_close($curl_h);
        fclose($tmpFile);
        $modVersion = "";

        if (!static::useRawVersion()) {
            // Open the downloaded mod zip file
            $zip = new ZipArchive();
            $res = $zip->open($tmpFileName, ZipArchive::RDONLY);
            if ($res === false) {
                unlink($tmpFileName);
                return ["mod_corrupt" => "Unable to open mod file for version $version, its likely corrupt"];
            }


            // Try load the version from forge
            $forgeData = $zip->getFromName('mcmod.info');
            if ($forgeData !== false) {
                $tmpData = json_decode($forgeData)[0];
                $modVersion = "$tmpData->mcversion-$tmpData->version";
            }

            $forgeToml = $zip->getFromName('META-INF/mods.toml');
            if ($forgeToml !== false) {
                $modsTomlData = explode("\n", $forgeToml);
                foreach ($modsTomlData as $line) {
                    if (strpos($line, 'version=') !== false) {
                        $modVersion = trim(explode('=', $line)[1], "\"");
                        break;
                    }
                }
                if (strpos($modVersion, '${file.jarVersion}') !== false) {
                    $modVersion = "";
                    $manifest = $zip->getFromName('META-INF/MANIFEST.MF');
                    if ($manifest !== false) {
                        $manifestData = explode("\n", $manifest);
                        foreach ($manifestData as $line) {
                            if (strpos($line, 'Implementation-Version: ') !== false) {
                                $modVersion = trim(explode('Implementation-Version: ', $line)[1]);
                                break;
                            }
                        }
                    }
                }
            }
            if (!empty($modVersion)) {
                error_log(print_r($modVersion, true));
            } else {
                error_log("Mod version is empty or invalid.");
            }

            // Try load the version from fabric
            $fabricData = $zip->getFromName('fabric.mod.json');
            if ($fabricData !== false) {
                $tmpData = json_decode($fabricData);
                if ($tmpData === null) {
                    unlink($tmpFileName);
                    return ["mod_corrupt" => "Unable to parse fabric.mod.json for version $version, its likely invalid"];
                }

                $mcVersion = "";
                if (property_exists($tmpData, "depends") && property_exists($tmpData->depends, "minecraft")) {
                    $mcVersion = $tmpData->depends->minecraft;
                    $mcVersion = preg_replace("/[^0-9\.]/i", "", explode('-', $mcVersion)[0]); // Clean the depend version
                    $mcVersion = "$mcVersion-";
                }
                $modVersion = $mcVersion.$tmpData->version;
            }

            // Try load the version from rift
            $riftData = $zip->getFromName('riftmod.json');
            if ($riftData !== false) {
                $modVersion = json_decode($riftData)->version;
            }

            $zip->close();

            // Make sure we have been given a version
            if (empty($modVersion)) {
                unlink($tmpFileName);
                return ["version_missing" => "Unable to detect version number for $version"];
            }
        }
        if ($modVersion == "") {
            $modVersion = $version;
        }
        // Check if the version already exists for the mod
        if (Modversion::where([
            'mod_id' => $modId,
            'version' => $modVersion,
        ])->count() > 0) {
            unlink($tmpFileName);
            return ["version_exists" => "$modVersion already exists"];
        }

        // Check if the final path isnt a url
        $location = config('solder.repo_location');
        $finalPath = $location."mods/$slug/$slug-$modVersion.zip";
        if (filter_var($finalPath, FILTER_VALIDATE_URL)) {
            unlink($tmpFileName);
            return ["remote_repo" => "Mod repo in a remote location so unable to download $modVersion"];
        }

        // Create the mod dir
        if (!file_exists(dirname($finalPath))) {
            mkdir(dirname($finalPath), 0777, true);
        }

        // Create the final mod zip
        $zip = new ZipArchive();
        $zip->open($finalPath, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFile($tmpFileName, static::zipFolder() . "/" . $fileName);
        $zip->close();

        // Add the version to the db
        $ver = new Modversion();
        $ver->mod_id = $modId;
        $ver->version = $modVersion;
        $ver->filesize = filesize($finalPath);
        $ver->md5 = md5_file($finalPath);
        $ver->save();
    }

    public static function install(string $modId, array $versions)
    {
        $modData = static::mod($modId);
        $response = (object) [
            "success" => true,
            "id" => -1,
            "errors" => array()
        ];

        $slug = Str::slug($modData->slug);

        $mod = Mod::where('name', $slug)->first();
        if (empty($mod)) {
            // Create the mod entry
            $mod = new Mod();
            $mod->name = $slug;
            $mod->pretty_name = $modData->name;
            $mod->author = $modData->authors;
            $mod->description = $modData->summary;
            $mod->link = $modData->websiteUrl;
            $mod->save();
        }

        $response->id = $mod->id;

        foreach ($versions as $version) {
            $error = static::installVersion($mod->id, $slug, $modData, $version);
            if (!empty($error)) {
                $response->errors = array_merge($response->errors, $error);
            }
        }

        return $response;
    }

    protected static function request(string $url, bool $skipJsonDecode = false)
    {
        $curl_h = curl_init(static::apiUrl() . $url);

        curl_setopt($curl_h, CURLOPT_HTTPHEADER, static::apiHeaders());

        # do not output, but store to variable
        curl_setopt($curl_h, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($curl_h);
        if ($skipJsonDecode) {
            return $response;
        } else {
            return json_decode($response);
        }
    }
}
