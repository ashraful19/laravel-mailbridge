<?php

namespace Ashraful19\LaravelMailbridge\Support;

use Ashraful19\LaravelMailbridge\Data\Address;

final class AddressFormatter
{
    /**
     * @param list<Address> $addresses
     * @return list<array{email: string, name?: string}>
     */
    public static function arrays(array $addresses): array
    {
        return array_map(fn (Address $address): array => $address->toArray(), $addresses);
    }

    /**
     * @param list<Address> $addresses
     * @return list<string>
     */
    public static function strings(array $addresses): array
    {
        return array_map(fn (Address $address): string => self::string($address), $addresses);
    }

    public static function string(Address $address): string
    {
        if ($address->name === null || $address->name === '') {
            return $address->email;
        }

        $name = $address->name;

        if (preg_match('/[()<>\[\]:;@\\\\,."]/u', $name)) {
            $name = '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
        }

        return sprintf('%s <%s>', $name, $address->email);
    }
}
