<?php

namespace App\Support;

final class PersonIdentityGroups
{
    public static function group(array $people): array
    {
        $groups = [];
        foreach ($people as $person) {
            $target = null;
            foreach ($groups as $index => $group) {
                // Every member must agree: missing identifiers must not bridge two homonyms.
                foreach ($group['members'] as $member) {
                    if (!self::matches($person, $member)) continue 2;
                }
                $target = $index;
                break;
            }
            if ($target === null) {
                $groups[] = $person + ['ids' => [$person['id']], 'members' => [$person]];
                continue;
            }
            $group = &$groups[$target];
            $group['ids'] = array_values(array_unique([...$group['ids'], $person['id']]));
            $group['members'][] = $person;
            $group['known_titles'] = array_values(array_unique([...$group['known_titles'], ...$person['known_titles']]));
            if (empty($group['profile'])) $group['profile'] = $person['profile'];
            unset($group);
        }

        return $groups;
    }

    private static function matches(array $a, array $b): bool
    {
        if ($a['id'] === $b['id']) return true;
        if (SearchRelevance::normalize($a['name']) !== SearchRelevance::normalize($b['name'])) return false;
        $shared = false;
        foreach (['imdb_id' => '/^nm\d+$/', 'wikidata_id' => '/^Q\d+$/'] as $key => $pattern) {
            $left = $a['identity']['external_ids'][$key] ?? '';
            $right = $b['identity']['external_ids'][$key] ?? '';
            if (!is_string($left) || !is_string($right)
                || !preg_match($pattern, $left) || !preg_match($pattern, $right)) continue;
            if ($left !== $right) return false;
            $shared = true;
        }
        if ($shared) return true;
        $left = $a['identity']['birthday'] ?? null;
        $right = $b['identity']['birthday'] ?? null;
        if (!is_string($left) || $left !== $right
            || !preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $left, $parts)) return false;

        return checkdate((int) $parts[2], (int) $parts[3], (int) $parts[1]);
    }
}
