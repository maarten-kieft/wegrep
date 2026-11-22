<?php
declare(strict_types=1);

namespace Wegrep\GameReport;

use DOMDocument;
use DOMElement;
use DOMXPath;

/**
 * Brings a game report into the layout the current Windows EGREP (v26) writes: the same elements in
 * the same order, with the defaults it fills in. The template the server hands out is an older layout
 * ("egrep v3.x"); a report in that layout made hockeydata answer with an error page, while a captured
 * upload of EGREP itself went through. Only structure and defaults are touched, never what the user entered.
 */
final class EgrepLayout
{
    private const GAMEDATA = [
        'creator', 'gamename', 'gameid', 'teama', 'teamb', 'teamashort', 'teambshort', 'teamaid', 'teambid',
        'eventname', 'venuename', 'gamedate', 'begintime', 'spectators', 'periodlength', 'periodnum',
        'overtimelength', 'overtimenum', 'gwsshots',
    ];
    // Older layouts carry these; EGREP v26 does not.
    private const GAMEDATA_DROPPED = ['teamahid', 'teambhid'];

    private const PLAYER = [
        'jerseynr', 'altjerseynr', 'licensenr', 'name1', 'name2', 'name3', 'captain', 'position', 'line',
        'sog', 'birthdate', 'extra1', 'extra2', 'startingsix', 'id',
    ];

    private const TOP_LEVEL = [
        'gamedata', 'gameofficials', 'playersA', 'playersB', 'teamA', 'teamB', 'goalsA', 'goalsB', 'penaltiesA',
        'penaltiesB', 'GKRecords', 'GKchanges', 'shotsongoal', 'saves', 'comments', 'livetime', 'timeouta', 'timeoutb',
        'gamestart', 'gameend', 'start1st', 'end1st', 'start2nd', 'end2nd', 'start3rd', 'end3rd', 'startOT', 'endOT',
        'startGWS', 'endGWS', 'gws', 'supplementaryReports',
    ];

    /**
     * @param array{PeriodNum?: int, PeriodLengthSek?: int, OvertimeNum?: int, OvertimeLengthSek?: int, ShootoutShots?: int}|null $ruleset
     */
    public static function apply(DOMDocument $doc, ?array $ruleset): void
    {
        $xp = new DOMXPath($doc);
        $game = $doc->documentElement;

        self::gamedata($doc, $xp, $game, $ruleset);
        foreach (['playersA', 'playersB'] as $tag) {
            foreach ($xp->query("$tag/player", $game) as $player) {
                self::reorder($doc, $player, self::PLAYER, ['altjerseynr' => '0', 'sog' => '0', 'extra1' => '0', 'extra2' => '0']);
            }
        }
        self::topLevel($doc, $xp, $game);
    }

    private static function gamedata(DOMDocument $doc, DOMXPath $xp, DOMElement $game, ?array $ruleset): void
    {
        $gamedata = $xp->query('gamedata', $game)->item(0) ?? $game->appendChild($doc->createElement('gamedata'));
        foreach (self::GAMEDATA_DROPPED as $tag) {
            foreach ($xp->query($tag, $gamedata) as $el) {
                $gamedata->removeChild($el);
            }
        }
        $defaults = ['creator' => '<default creator>', 'spectators' => '0'];
        if ($ruleset !== null) {
            $defaults += [
                'periodlength' => (string) intdiv((int) ($ruleset['PeriodLengthSek'] ?? 0), 60),
                'periodnum' => (string) (int) ($ruleset['PeriodNum'] ?? 0),
                'overtimelength' => (string) intdiv((int) ($ruleset['OvertimeLengthSek'] ?? 0), 60),
                'overtimenum' => (string) (int) ($ruleset['OvertimeNum'] ?? 0),
                'gwsshots' => (string) (int) ($ruleset['ShootoutShots'] ?? 0),
            ];
        }
        // The ruleset is the authority for the period settings; for the rest only fill what is empty.
        $always = ['periodlength', 'periodnum', 'overtimelength', 'overtimenum', 'gwsshots'];
        foreach ($defaults as $tag => $value) {
            $el = $xp->query($tag, $gamedata)->item(0);
            if (!$el) {
                $el = $gamedata->appendChild($doc->createElement($tag));
            }
            if (trim($el->textContent) === '' || in_array($tag, $always, true)) {
                $el->textContent = $value;
            }
        }
        // The server's own creator text is replaced: EGREP itself writes this placeholder.
        $creator = $xp->query('creator', $gamedata)->item(0);
        if ($creator && str_starts_with(strtolower($creator->textContent), 'egrep')) {
            $creator->textContent = '<default creator>';
        }
        self::reorder($doc, $gamedata, self::GAMEDATA, ['teamashort' => '', 'teambshort' => '']);
    }

    private static function topLevel(DOMDocument $doc, DOMXPath $xp, DOMElement $game): void
    {
        // The goalkeeper records were called GKrecords in the older layout.
        foreach ($xp->query('GKrecords', $game) as $old) {
            $new = $doc->createElement('GKRecords');
            while ($old->firstChild) {
                $new->appendChild($old->firstChild);
            }
            $game->replaceChild($new, $old);
        }

        foreach (['teamA', 'teamB'] as $tag) {
            self::ensure($doc, $xp, $game, $tag)->appendChild($doc->createElement('commercialPermit'));
        }
        // These exist but are empty when they are not used; a shoot-out is not supported by the app.
        foreach (['GKRecords', 'saves', 'comments', 'gamestart', 'gameend', 'start1st', 'end1st', 'start2nd', 'end2nd',
            'start3rd', 'end3rd', 'startOT', 'endOT', 'startGWS', 'endGWS', 'supplementaryReports'] as $tag) {
            self::ensure($doc, $xp, $game, $tag);
        }
        foreach (['timeouta', 'timeoutb'] as $tag) {
            $el = self::ensure($doc, $xp, $game, $tag);
            if (trim($el->textContent) === '') {
                $el->textContent = '0;0;0';
            }
        }
        $gws = self::ensure($doc, $xp, $game, 'gws');
        while ($gws->firstChild) {
            $gws->removeChild($gws->firstChild);
        }

        // At least one (empty) goalkeeper change at 00:00, as EGREP writes it.
        $changes = self::ensure($doc, $xp, $game, 'GKchanges');
        if (!$xp->query('GKchange', $changes)->length) {
            $change = $changes->appendChild($doc->createElement('GKchange'));
            $change->appendChild($doc->createElement('time'))->textContent = '0000';
            $change->appendChild($doc->createElement('gkA'));
            $change->appendChild($doc->createElement('gkB'));
        }

        self::reorder($doc, $game, self::TOP_LEVEL, []);
    }

    /** The direct child with this name, created (empty) when it is not there yet. */
    private static function ensure(DOMDocument $doc, DOMXPath $xp, DOMElement $parent, string $tag): DOMElement
    {
        $el = $xp->query($tag, $parent)->item(0);
        if ($el instanceof DOMElement) {
            // teamA and teamB are always rebuilt from scratch by the caller.
            if ($tag === 'teamA' || $tag === 'teamB') {
                while ($el->firstChild) {
                    $el->removeChild($el->firstChild);
                }
            }
            return $el;
        }
        return $parent->appendChild($doc->createElement($tag));
    }

    /**
     * Put the children of $parent in the given order. Children that are not in the list keep their relative
     * order at the end. Elements in $defaults are created when missing and filled when empty.
     *
     * @param list<string> $order
     * @param array<string, string> $defaults
     */
    private static function reorder(DOMDocument $doc, DOMElement $parent, array $order, array $defaults): void
    {
        $byName = [];
        $others = [];
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if (!$child instanceof DOMElement) {
                continue;
            }
            if (in_array($child->nodeName, $order, true) && !isset($byName[$child->nodeName])) {
                $byName[$child->nodeName] = $child;
            } else {
                $others[] = $child;
            }
            $parent->removeChild($child);
        }
        foreach ($defaults as $tag => $value) {
            $el = $byName[$tag] ?? $doc->createElement($tag);
            if (trim($el->textContent) === '') {
                $el->textContent = $value;
            }
            $byName[$tag] = $el;
        }
        foreach ($order as $tag) {
            if (isset($byName[$tag])) {
                $parent->appendChild($byName[$tag]);
            }
        }
        foreach ($others as $el) {
            $parent->appendChild($el);
        }
    }
}
