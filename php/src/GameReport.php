<?php
declare(strict_types=1);

namespace Wegrep;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Reads and writes the EGREP game report XML (<game>...</game>).
 *
 * build() starts from the XML the server handed out and only replaces the parts
 * the web app edits (roster details, officials, goals, penalties, shots, live state),
 * so every field we don't know about is passed through unchanged.
 *
 * Game times are seconds since the start of the game (not per period);
 * the XML stores them as MMSS, e.g. 2512 = 25:12.
 */
final class GameReport
{
    private const SHOT_PERIODS = ['1', '2', '3', 'OVT', 'GWS'];
    // The official functions EGREP always writes, in its order. The server template can leave them out.
    private const OFFICIAL_FUNCTIONS = [
        'referee1', 'referee2', 'linesman1', 'linesman2', 'goaljudge1', 'goaljudge2', 'videogoaljudge',
        'scorekeeper', 'timekeeper', 'gamesupervisor', 'tmanagerA', 'hcoachA', 'acoachA',
        'tmanagerB', 'hcoachB', 'acoachB', 'medicalstaff', 'REFCOACH',
    ];

    public static function parse(string $xml): array
    {
        [$doc, $xp] = self::load($xml);
        $game = $doc->documentElement;

        $gamedata = [];
        foreach (self::children($xp->query('gamedata', $game)->item(0)) as $el) {
            $gamedata[$el->nodeName] = $el->textContent;
        }

        $officials = [];
        foreach (self::OFFICIAL_FUNCTIONS as $fn) {
            $officials[$fn] = ['function' => $fn, 'lastname' => '', 'firstname' => ''];
        }
        foreach ($xp->query('gameofficials/gameofficial', $game) as $el) {
            $officials[self::text($xp, $el, 'gofunction')] = [
                'function' => self::text($xp, $el, 'gofunction'),
                'lastname' => self::text($xp, $el, 'lastname'),
                'firstname' => self::text($xp, $el, 'firstname'),
            ];
        }

        $players = ['home' => [], 'away' => []];
        foreach (['home' => 'playersA', 'away' => 'playersB'] as $side => $tag) {
            foreach ($xp->query("$tag/player", $game) as $el) {
                $players[$side][] = [
                    'id' => self::text($xp, $el, 'id'),
                    'jersey' => self::text($xp, $el, 'jerseynr'),
                    'lastname' => self::text($xp, $el, 'name1'),
                    'firstname' => self::text($xp, $el, 'name2'),
                    'licensenr' => self::text($xp, $el, 'licensenr'),
                    'birthdate' => self::text($xp, $el, 'birthdate'),
                    'captain' => self::text($xp, $el, 'captain'),
                    'position' => self::text($xp, $el, 'position'),
                    'line' => self::text($xp, $el, 'line'),
                    'starting' => self::text($xp, $el, 'startingsix') === 'Y',
                ];
            }
        }

        $goals = [];
        foreach (['home' => 'goalsA', 'away' => 'goalsB'] as $side => $tag) {
            foreach ($xp->query("$tag/goal", $game) as $el) {
                $goals[] = [
                    'team' => $side,
                    'time' => self::mmssToSec(self::text($xp, $el, 'time')),
                    'scorer' => self::jersey(self::text($xp, $el, 'player')),
                    'assist1' => self::jersey(self::text($xp, $el, 'assist')),
                    'assist2' => self::jersey(self::text($xp, $el, 'assist2')),
                    'strength' => (int) self::text($xp, $el, 'gamestrength'),
                    'special' => self::text($xp, $el, 'special'),
                ];
            }
        }

        $penalties = [];
        foreach (['home' => 'penaltiesA', 'away' => 'penaltiesB'] as $side => $tag) {
            foreach ($xp->query("$tag/penalty", $game) as $el) {
                $penalties[] = [
                    'team' => $side,
                    'time' => self::mmssToSec(self::text($xp, $el, 'time')),
                    'player' => self::jersey(self::text($xp, $el, 'player')),
                    'pim' => (int) self::text($xp, $el, 'pim'),
                    'offence' => self::text($xp, $el, 'offence'),
                    'start' => self::mmssToSec(self::text($xp, $el, 'start')),
                    'end' => self::mmssToSec(self::text($xp, $el, 'end')),
                ];
            }
        }

        $shots = array_fill_keys(self::SHOT_PERIODS, ['home' => 0, 'away' => 0]);
        foreach ($xp->query('shotsongoal/period', $game) as $el) {
            [$h, $a] = array_pad(explode(':', self::text($xp, $el, 'shots')), 2, '0');
            $shots[self::text($xp, $el, 'periodnr')] = ['home' => (int) $h, 'away' => (int) $a];
        }

        return [
            'gamedata' => (object) $gamedata,
            'officials' => array_values($officials),
            'players' => $players,
            'goals' => $goals,
            'penalties' => $penalties,
            'shots' => $shots,
            'livetime' => self::text($xp, $game, 'livetime'),
        ];
    }

    /** @param array $state the same shape parse() returns (fields the app edits) */
    public static function build(string $templateXml, array $state): string
    {
        [$doc, $xp] = self::load($templateXml);
        $game = $doc->documentElement;

        // Roster: update known players by id, leave everything else as-is.
        foreach (['home' => 'playersA', 'away' => 'playersB'] as $side => $tag) {
            $byId = [];
            foreach ($state['players'][$side] ?? [] as $p) {
                $byId[(string) $p['id']] = $p;
            }
            foreach ($xp->query("$tag/player", $game) as $el) {
                $p = $byId[self::text($xp, $el, 'id')] ?? null;
                if ($p === null) {
                    continue;
                }
                self::set($doc, $xp, $el, 'jerseynr', (string) ($p['jersey'] ?? ''));
                self::set($doc, $xp, $el, 'captain', (string) ($p['captain'] ?? ''));
                self::set($doc, $xp, $el, 'position', (string) ($p['position'] ?? ''));
                self::set($doc, $xp, $el, 'line', (string) ($p['line'] ?? ''));
                self::set($doc, $xp, $el, 'startingsix', !empty($p['starting']) ? 'Y' : '');
            }
        }

        // Officials: update names by function.
        $officials = [];
        foreach ($state['officials'] ?? [] as $o) {
            $officials[$o['function']] = $o;
        }
        $officialsEl = $xp->query('gameofficials', $game)->item(0) ?? $game->appendChild($doc->createElement('gameofficials'));
        $present = [];
        foreach ($xp->query('gameofficial/gofunction', $officialsEl) as $fn) {
            $present[$fn->textContent] = true;
        }
        foreach (self::OFFICIAL_FUNCTIONS as $fn) {
            if (!isset($present[$fn])) {
                $el = $officialsEl->appendChild($doc->createElement('gameofficial'));
                self::add($doc, $el, ['gofunction' => $fn, 'name' => '']);
            }
        }
        foreach ($xp->query('gameofficial', $officialsEl) as $el) {
            $o = $officials[self::text($xp, $el, 'gofunction')] ?? null;
            if ($o === null) {
                continue;
            }
            $last = trim((string) ($o['lastname'] ?? ''));
            $first = trim((string) ($o['firstname'] ?? ''));
            self::set($doc, $xp, $el, 'name', trim("$last $first"));
            if ($last !== '' || $first !== '') {
                self::set($doc, $xp, $el, 'lastname', $last);
                self::set($doc, $xp, $el, 'firstname', $first);
            }
        }

        $now = date('j-n-Y H:i:s');

        // Goals, numbered per team in time order.
        foreach (['home' => 'goalsA', 'away' => 'goalsB'] as $side => $tag) {
            $list = self::clear($doc, $xp, $game, $tag);
            $goals = array_values(array_filter($state['goals'] ?? [], fn($g) => $g['team'] === $side));
            usort($goals, fn($a, $b) => $a['time'] <=> $b['time']);
            foreach ($goals as $i => $g) {
                $el = $list->appendChild($doc->createElement('goal'));
                self::add($doc, $el, [
                    'goalnr' => (string) ($i + 1),
                    'time' => self::secToMmss((int) $g['time']),
                    'player' => self::jerseyOut($g['scorer'] ?? ''),
                    'assist' => self::jerseyOut($g['assist1'] ?? ''),
                    'assist2' => self::jerseyOut($g['assist2'] ?? ''),
                    'gamestrength' => (string) (int) ($g['strength'] ?? 0),
                    'special' => (string) ($g['special'] ?? ''),
                    'p1' => '0', 'p2' => '0', 'p3' => '0', 'p4' => '0', 'p5' => '0', 'p6' => '0',
                    'n1' => '0', 'n2' => '0', 'n3' => '0', 'n4' => '0', 'n5' => '0', 'n6' => '0',
                    'extra3' => '', 'extra4' => '',
                    'timestamp' => $now,
                ]);
            }
        }

        // Penalties.
        foreach (['home' => 'penaltiesA', 'away' => 'penaltiesB'] as $side => $tag) {
            $list = self::clear($doc, $xp, $game, $tag);
            $pens = array_values(array_filter($state['penalties'] ?? [], fn($p) => $p['team'] === $side));
            usort($pens, fn($a, $b) => $a['time'] <=> $b['time']);
            foreach ($pens as $p) {
                $el = $list->appendChild($doc->createElement('penalty'));
                self::add($doc, $el, [
                    'time' => self::secToMmss((int) $p['time']),
                    'player' => self::jerseyOut($p['player'] ?? ''),
                    'pim' => (string) (int) $p['pim'],
                    'offence' => (string) $p['offence'],
                    'start' => self::secToMmss((int) ($p['start'] ?? $p['time'])),
                    'end' => self::secToMmss((int) ($p['end'] ?? $p['time'])),
                    'extra5' => '-1',
                    'timestamp' => $now,
                    'extra6' => '',
                ]);
            }
        }

        // Shots on goal per period.
        $shotsEl = self::clear($doc, $xp, $game, 'shotsongoal');
        foreach (self::SHOT_PERIODS as $nr) {
            $s = $state['shots'][$nr] ?? ['home' => 0, 'away' => 0];
            $el = $shotsEl->appendChild($doc->createElement('period'));
            self::add($doc, $el, ['periodnr' => $nr, 'shots' => (int) $s['home'] . ':' . (int) $s['away']]);
        }

        if (isset($state['livetime'])) {
            self::set($doc, $xp, $game, 'livetime', (string) $state['livetime']);
        }

        return $doc->saveXML();
    }

    private static function load(string $xml): array
    {
        $xml = preg_replace('/^\xEF\xBB\xBF/', '', trim($xml));
        // The server declares utf-16 but sends utf-8 text (EGREP does the same replace).
        $xml = preg_replace('/^(<\?xml[^>]*encoding=")utf-16(")/i', '$1utf-8$2', $xml);
        $doc = new DOMDocument();
        $doc->preserveWhiteSpace = false;
        if (!@$doc->loadXML($xml)) {
            throw new \RuntimeException('Game report XML is invalid');
        }
        return [$doc, new DOMXPath($doc)];
    }

    /** @return DOMElement[] */
    private static function children(?\DOMNode $node): array
    {
        $out = [];
        foreach ($node?->childNodes ?? [] as $c) {
            if ($c instanceof DOMElement) {
                $out[] = $c;
            }
        }
        return $out;
    }

    private static function text(DOMXPath $xp, \DOMNode $ctx, string $tag): string
    {
        $n = $xp->query($tag, $ctx)->item(0);
        return $n ? $n->textContent : '';
    }

    private static function set(DOMDocument $doc, DOMXPath $xp, DOMElement $parent, string $tag, string $value): void
    {
        $n = $xp->query($tag, $parent)->item(0);
        if (!$n) {
            $n = $parent->appendChild($doc->createElement($tag));
        }
        $n->textContent = $value;
    }

    /** Empties (or creates) a direct child element and returns it. */
    private static function clear(DOMDocument $doc, DOMXPath $xp, DOMElement $parent, string $tag): DOMElement
    {
        $n = $xp->query($tag, $parent)->item(0);
        if (!$n) {
            return $parent->appendChild($doc->createElement($tag));
        }
        while ($n->firstChild) {
            $n->removeChild($n->firstChild);
        }
        return $n;
    }

    private static function add(DOMDocument $doc, DOMElement $parent, array $fields): void
    {
        foreach ($fields as $tag => $value) {
            $parent->appendChild($doc->createElement($tag))->textContent = $value;
        }
    }

    public static function secToMmss(int $sec): string
    {
        $sec = max(0, $sec);
        return sprintf('%02d%02d', intdiv($sec, 60), $sec % 60);
    }

    public static function mmssToSec(string $mmss): int
    {
        $digits = preg_replace('/\D/', '', $mmss);
        if ($digits === '') {
            return 0;
        }
        $digits = str_pad($digits, 4, '0', STR_PAD_LEFT);
        return (int) substr($digits, 0, -2) * 60 + (int) substr($digits, -2);
    }

    /** -1 means "nobody" in the XML. */
    private static function jersey(string $v): string
    {
        return ($v === '-1' || $v === '') ? '' : $v;
    }

    private static function jerseyOut(string $v): string
    {
        return trim($v) === '' ? '-1' : trim($v);
    }
}
