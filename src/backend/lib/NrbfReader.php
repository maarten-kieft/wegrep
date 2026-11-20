<?php
declare(strict_types=1);

namespace Wegrep;

/**
 * Minimal reader for the .NET BinaryFormatter format (MS-NRBF).
 *
 * hockeydata returns some responses (e.g. GetListOfAllLeagues) as BinaryFormatter
 * output instead of JSON. This reads the stream into plain PHP arrays:
 * classes become associative arrays, List<T> becomes a list, and
 * auto-property names like "<Id>k__BackingField" become "Id".
 */
final class NrbfReader
{
    private string $data;
    private int $pos = 0;
    /** @var array<int, mixed> objectId => value */
    private array $objects = [];
    /** @var array<int, array> metadataId => class info */
    private array $classes = [];
    private ?int $rootId = null;

    public static function decode(string $bytes): mixed
    {
        $reader = new self($bytes);
        return $reader->read();
    }

    private function __construct(string $bytes)
    {
        $this->data = $bytes;
    }

    private function read(): mixed
    {
        while ($this->pos < strlen($this->data)) {
            $type = $this->byte();
            if ($type === 11) { // MessageEnd
                break;
            }
            $this->readRecord($type);
        }
        if ($this->rootId === null) {
            throw new \RuntimeException('NRBF: no root object');
        }
        $seen = [];
        return $this->simplify($this->resolve($this->objects[$this->rootId] ?? null, $seen));
    }

    /** Reads one record. Returns a value, a reference marker, or a null-run marker. */
    private function readRecord(int $type): mixed
    {
        switch ($type) {
            case 0: // SerializedStreamHeader
                $this->rootId = $this->int32();
                $this->int32(); // headerId
                $this->int32(); // major
                $this->int32(); // minor
                return null;
            case 12: // BinaryLibrary
                $this->int32();
                $this->string();
                return $this->readRecord($this->byte());
            case 1: // ClassWithId
                $id = $this->int32();
                $metaId = $this->int32();
                return $this->readClassValues($id, $this->classes[$metaId]);
            case 2: // SystemClassWithMembers
            case 3: // ClassWithMembers
                [$id, $name, $members] = $this->classInfo();
                if ($type === 3) {
                    $this->int32();
                }
                $info = ['name' => $name, 'members' => $members, 'types' => array_fill(0, count($members), [2, null])];
                $this->classes[$id] = $info;
                return $this->readClassValues($id, $info);
            case 4: // SystemClassWithMembersAndTypes
            case 5: // ClassWithMembersAndTypes
                [$id, $name, $members] = $this->classInfo();
                $types = $this->memberTypes(count($members));
                if ($type === 5) {
                    $this->int32(); // libraryId
                }
                $info = ['name' => $name, 'members' => $members, 'types' => $types];
                $this->classes[$id] = $info;
                return $this->readClassValues($id, $info);
            case 6: // BinaryObjectString
                $id = $this->int32();
                $this->objects[$id] = $this->string();
                return $this->objects[$id];
            case 7: // BinaryArray
                $id = $this->int32();
                $arrayType = $this->byte();
                $rank = $this->int32();
                $length = 1;
                for ($i = 0; $i < $rank; $i++) {
                    $length *= $this->int32();
                }
                if (in_array($arrayType, [3, 4, 5], true)) {
                    for ($i = 0; $i < $rank; $i++) {
                        $this->int32(); // lower bounds
                    }
                }
                $itemType = $this->memberTypes(1)[0];
                return $this->objects[$id] = $this->readArrayItems($length, $itemType);
            case 8: // MemberPrimitiveTyped
                return $this->primitive($this->byte());
            case 9: // MemberReference
                return ['__ref' => $this->int32()];
            case 10: // ObjectNull
                return null;
            case 13: // ObjectNullMultiple256
                return ['__nulls' => $this->byte()];
            case 14: // ObjectNullMultiple
                return ['__nulls' => $this->int32()];
            case 15: // ArraySinglePrimitive
                $id = $this->int32();
                $length = $this->int32();
                $prim = $this->byte();
                $items = [];
                for ($i = 0; $i < $length; $i++) {
                    $items[] = $this->primitive($prim);
                }
                return $this->objects[$id] = $items;
            case 16: // ArraySingleObject
            case 17: // ArraySingleString
                $id = $this->int32();
                $length = $this->int32();
                return $this->objects[$id] = $this->readArrayItems($length, [2, null]);
            default:
                throw new \RuntimeException(sprintf('NRBF: unsupported record type %d at offset %d', $type, $this->pos - 1));
        }
    }

    private function readArrayItems(int $length, array $itemType): array
    {
        $items = [];
        while (count($items) < $length) {
            $value = $this->readValue($itemType);
            if (is_array($value) && isset($value['__nulls'])) {
                for ($i = 0; $i < $value['__nulls']; $i++) {
                    $items[] = null;
                }
            } else {
                $items[] = $value;
            }
        }
        return $items;
    }

    private function readClassValues(int $id, array $info): array
    {
        $obj = ['__class' => $info['name']];
        $this->objects[$id] = &$obj;
        foreach ($info['members'] as $i => $member) {
            $obj[$member] = $this->readValue($info['types'][$i]);
        }
        unset($this->objects[$id]);
        $this->objects[$id] = $obj;
        return $obj;
    }

    /** @param array{0:int,1:mixed} $type [BinaryTypeEnum, additionalInfo] */
    private function readValue(array $type): mixed
    {
        if ($type[0] === 0) { // Primitive: stored inline
            return $this->primitive($type[1]);
        }
        return $this->readRecord($this->byte());
    }

    private function classInfo(): array
    {
        $id = $this->int32();
        $name = $this->string();
        $count = $this->int32();
        $members = [];
        for ($i = 0; $i < $count; $i++) {
            $members[] = $this->string();
        }
        return [$id, $name, $members];
    }

    private function memberTypes(int $count): array
    {
        $kinds = [];
        for ($i = 0; $i < $count; $i++) {
            $kinds[] = $this->byte();
        }
        $types = [];
        foreach ($kinds as $kind) {
            $info = null;
            switch ($kind) {
                case 0: // Primitive
                case 7: // PrimitiveArray
                    $info = $this->byte();
                    break;
                case 3: // SystemClass
                    $info = $this->string();
                    break;
                case 4: // Class
                    $info = $this->string();
                    $this->int32();
                    break;
            }
            $types[] = [$kind, $info];
        }
        return $types;
    }

    private function primitive(int $type): mixed
    {
        return match ($type) {
            1 => $this->byte() !== 0,
            2 => $this->byte(),
            3 => $this->char(),
            5 => $this->string(), // Decimal is stored as a string
            6 => $this->unpack('e', 8),
            7 => $this->unpack('s', 2),
            8 => $this->int32(),
            9, 12, 13 => $this->unpack('q', 8),
            10 => $this->unpack('c', 1),
            11 => $this->unpack('g', 4),
            14 => $this->unpack('v', 2),
            15 => $this->unpack('V', 4),
            16 => $this->unpack('P', 8),
            17 => null,
            18 => $this->string(),
            default => throw new \RuntimeException("NRBF: unknown primitive type $type"),
        };
    }

    private function resolve(mixed $value, array &$seen): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        if (isset($value['__ref'])) {
            $id = $value['__ref'];
            if (isset($seen[$id])) {
                return null; // cycle
            }
            $seen[$id] = true;
            $result = $this->resolve($this->objects[$id] ?? null, $seen);
            unset($seen[$id]);
            return $result;
        }
        foreach ($value as $k => $v) {
            $value[$k] = $this->resolve($v, $seen);
        }
        return $value;
    }

    /** Turns .NET object shapes into plain PHP data. */
    private function simplify(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }
        $class = $value['__class'] ?? null;
        if ($class !== null && str_starts_with($class, 'System.Collections.Generic.List`1')) {
            $items = array_slice($value['_items'] ?? [], 0, (int) ($value['_size'] ?? 0));
            return array_map(fn($v) => $this->simplify($v), $items);
        }
        $out = [];
        foreach ($value as $k => $v) {
            if ($k === '__class') {
                continue;
            }
            if (is_string($k) && preg_match('/^<(.+)>k__BackingField$/', $k, $m)) {
                $k = $m[1];
            }
            $out[$k] = $this->simplify($v);
        }
        return $out;
    }

    private function byte(): int
    {
        return ord($this->take(1));
    }

    private function int32(): int
    {
        return $this->unpack('l', 4);
    }

    private function unpack(string $format, int $size): mixed
    {
        return unpack($format, $this->take($size))[1];
    }

    private function string(): string
    {
        $length = 0;
        $shift = 0;
        do {
            $b = $this->byte();
            $length |= ($b & 0x7F) << $shift;
            $shift += 7;
        } while ($b & 0x80);
        return $this->take($length);
    }

    private function char(): string
    {
        $first = ord($this->data[$this->pos]);
        $size = $first < 0x80 ? 1 : ($first < 0xE0 ? 2 : ($first < 0xF0 ? 3 : 4));
        return $this->take($size);
    }

    private function take(int $n): string
    {
        if ($this->pos + $n > strlen($this->data)) {
            throw new \RuntimeException('NRBF: unexpected end of data');
        }
        $s = substr($this->data, $this->pos, $n);
        $this->pos += $n;
        return $s;
    }
}
