<?php
namespace Tivoli\Service;

use RuntimeException;
use Tivoli\Domain\Stay\StayRules;
use Tivoli\Repository\AccessRepository;

final class ExtraCategories
{
    public static function current(AccessRepository $repo): array
    {
        $row = $repo->run("SELECT id,details FROM security_audit
            WHERE action='stay.extra_categories' AND outcome='succeeded' ORDER BY id DESC LIMIT 1")->fetch();
        $entries = $row ? json_decode($row['details'],true,512,JSON_THROW_ON_ERROR)['entries']
            : ['General'=>true,'Drinks'=>true];
        foreach ($repo->run("SELECT DISTINCT BINARY category FROM stay_extra_settings WHERE category<>''")->fetchAll(\PDO::FETCH_COLUMN) as $name) {
            $entries[$name] ??= true;
        }
        uksort($entries,'strnatcasecmp');
        return ['revision'=>$row ? (string)$row['id'] : 'initial','entries'=>$entries];
    }

    public static function change(AccessRepository $repo,array $input): array
    {
        $list = self::current($repo);
        if (($input['category_revision'] ?? '') !== $list['revision']) {
            throw new RuntimeException('The category list changed. Reload before saving.');
        }
        $name = StayRules::text($input['name'] ?? '',80,true);
        $status = $input['active'] ?? '';
        if (!in_array($status,['0','1'],true)) throw new RuntimeException('Choose Active or Inactive.');
        $entries = $list['entries'];
        $previous = $entries[$name] ?? null;
        if (($input['category_mode'] ?? '') === 'add') {
            foreach (array_keys($entries) as $existing) {
                if (mb_strtolower((string)$existing) === mb_strtolower($name)) {
                    throw new RuntimeException('A category with this name already exists.');
                }
            }
            if ($status !== '1') throw new RuntimeException('New categories must be active.');
        } elseif (($input['category_mode'] ?? '') !== 'status' || $previous === null) {
            throw new RuntimeException('Choose an existing category.');
        }
        $entries[$name] = $status === '1';
        if (!in_array(true,$entries,true)) throw new RuntimeException('Keep at least one category active.');
        return ['audit'=>['category'=>$name,'previous_active'=>$previous,'active'=>$entries[$name],'entries'=>$entries]];
    }
}
