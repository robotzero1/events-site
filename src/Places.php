<?php

declare(strict_types=1);

namespace EventsSite;

use EventsSite\Api\Asking;

/**
 * The Regions with their Locations, as a visitor chooses where to look. The listing's one place filter takes either:
 * a Location by its ref, a Region by its ref after `region:`, a Region and a Location being able to share a name
 * (Granada).
 */
final class Places
{
    private const REGION = 'region:';

    /** @param list<array{ref: string, name: string, locations: list<array{ref: string, name: string}>}> $regions */
    private function __construct(public readonly array $regions)
    {
    }

    public static function of(Asking $asking): self
    {
        $regions = [];

        foreach ($asking->get('/regions', [], Asking::REFERENCE_LIST)['data'] as $region) {
            $locations = $asking->get("/regions/{$region['ref']}/locations", [], Asking::REFERENCE_LIST)['data'];

            $regions[] = [
                'ref' => $region['ref'],
                'name' => $region['name'],
                'value' => self::REGION . $region['ref'],
                'locations' => array_map(fn (array $location) => ['ref' => $location['ref'], 'name' => $location['name'], 'value' => $location['ref']], $locations),
            ];
        }

        return new self($regions);
    }

    /** What the API is asked for a place chosen: `region` or `location`, by ref. */
    public static function query(string $place): array
    {
        return str_starts_with($place, self::REGION)
            ? ['region' => substr($place, strlen(self::REGION))]
            : ['location' => $place];
    }

    /** The name of a place chosen, or null if it is none of these. */
    public function nameOf(string $place): ?string
    {
        foreach ($this->regions as $region) {
            if ($region['value'] === $place) {
                return $region['name'];
            }

            foreach ($region['locations'] as $location) {
                if ($location['value'] === $place) {
                    return $location['name'];
                }
            }
        }

        return null;
    }
}
