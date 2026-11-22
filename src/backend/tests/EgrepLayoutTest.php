<?php
declare(strict_types=1);

use Wegrep\GameReport\GameReport;
use Wegrep\HockeyData\HockeyDataClient;

/** A small report in the older layout the server hands out (invented data). */
function oldLayoutTemplate(): string
{
    return '<?xml version="1.0" encoding="utf-16"?><game><gamedata><creator>egrep v3.x</creator><gamename>X-1</gamename><gameid>5</gameid>'
        . '<teama>Home</teama><teamb>Away</teamb><teamaid>1</teamaid><teambid>2</teambid><teamahid>1</teamahid><teambhid>2</teambhid>'
        . '<eventname>Demo</eventname><venuename>Rink</venuename><gamedate>2026-09-27</gamedate><begintime>12:00</begintime><spectators /></gamedata>'
        . '<gameofficials><gameofficial><gofunction>hcoachA</gofunction><name>Coach</name></gameofficial></gameofficials>'
        . '<playersA><player><jerseynr>9</jerseynr><name1>Doe</name1><name2>Jane</name2><name3 /><captain /><position>F</position><line>1</line><sog>0</sog>'
        . '<birthdate>2010-01-01</birthdate><extra1 /><extra2 /><licensenr>L1</licensenr><altjerseynr>0</altjerseynr><id>11</id><startingsix /></player></playersA>'
        . '<playersB /><goalsA /><goalsB /><penaltiesA /><penaltiesB /><GKrecords /><GKchanges /><shotsongoal /><saves /><comments /><livetime />'
        . '<timeouta /><timeoutb /><gamestart /><gameend /><gws><gwsstarter>A</gwsstarter><GKrecords /><GKchanges /></gws></game>';
}

const TEST_RULESET = ['PeriodNum' => 3, 'PeriodLengthSek' => 1200, 'OvertimeNum' => 1, 'OvertimeLengthSek' => 300, 'ShootoutShots' => 3];

function builtReport(?array $ruleset = null, array $extra = []): DOMDocument
{
    $state = GameReport::parse(oldLayoutTemplate()) + [];
    if ($ruleset !== null) {
        $state['ruleset'] = $ruleset;
    }
    $doc = new DOMDocument();
    $doc->loadXML(GameReport::build(oldLayoutTemplate(), $extra + $state));
    return $doc;
}

function childNames(DOMNode $node): array
{
    $names = [];
    foreach ($node->childNodes as $c) {
        if ($c instanceof DOMElement) {
            $names[] = $c->nodeName;
        }
    }
    return $names;
}

test('the report comes out in the element order of the current EGREP', function () {
    $game = builtReport(TEST_RULESET)->documentElement;
    assertSame(
        ['gamedata', 'gameofficials', 'playersA', 'playersB', 'teamA', 'teamB', 'goalsA', 'goalsB', 'penaltiesA', 'penaltiesB', 'GKRecords', 'GKchanges',
            'shotsongoal', 'saves', 'comments', 'livetime', 'timeouta', 'timeoutb', 'gamestart', 'gameend', 'start1st', 'end1st', 'start2nd', 'end2nd',
            'start3rd', 'end3rd', 'startOT', 'endOT', 'startGWS', 'endGWS', 'gws', 'supplementaryReports'],
        childNames($game)
    );
});

test('gamedata gets the period settings from the ruleset and loses the old team hid fields', function () {
    $gamedata = builtReport(TEST_RULESET)->getElementsByTagName('gamedata')->item(0);
    assertSame(
        ['creator', 'gamename', 'gameid', 'teama', 'teamb', 'teamashort', 'teambshort', 'teamaid', 'teambid', 'eventname', 'venuename', 'gamedate',
            'begintime', 'spectators', 'periodlength', 'periodnum', 'overtimelength', 'overtimenum', 'gwsshots'],
        childNames($gamedata)
    );
    $value = fn(string $tag) => $gamedata->getElementsByTagName($tag)->item(0)->textContent;
    assertSame('20', $value('periodlength'));
    assertSame('3', $value('periodnum'));
    assertSame('5', $value('overtimelength'));
    assertSame('1', $value('overtimenum'));
    assertSame('3', $value('gwsshots'));
    assertSame('0', $value('spectators'));
    assertSame('<default creator>', $value('creator'));
});

test('a player has the fields in EGREP order and numeric defaults instead of empty values', function () {
    $player = builtReport()->getElementsByTagName('player')->item(0);
    assertSame(
        ['jerseynr', 'altjerseynr', 'licensenr', 'name1', 'name2', 'name3', 'captain', 'position', 'line', 'sog', 'birthdate', 'extra1', 'extra2', 'startingsix', 'id'],
        childNames($player)
    );
    assertSame('0', $player->getElementsByTagName('extra1')->item(0)->textContent);
    assertSame('0', $player->getElementsByTagName('extra2')->item(0)->textContent);
    assertSame('Doe', $player->getElementsByTagName('name1')->item(0)->textContent, 'what is there stays');
});

test('the unused parts are present but empty, timeouts and the goalkeeper change have their defaults', function () {
    $game = builtReport()->documentElement;
    $text = fn(string $tag) => $game->getElementsByTagName($tag)->item(0)->textContent;
    assertSame('0;0;0', $text('timeouta'));
    assertSame('0;0;0', $text('timeoutb'));
    assertSame(0, $game->getElementsByTagName('gws')->item(0)->childNodes->length, 'no shoot-out data');
    assertSame(0, $game->getElementsByTagName('GKrecords')->length, 'renamed to GKRecords');
    assertSame('0000', $game->getElementsByTagName('GKchange')->item(0)->getElementsByTagName('time')->item(0)->textContent);
});

test('goal timestamps are written as dd/mm/yyyy hh:mm:ss', function () {
    $doc = builtReport(null, ['goals' => [['team' => 'home', 'time' => 69, 'scorer' => '9', 'assist1' => '', 'assist2' => '', 'strength' => 0, 'special' => '']]]);
    $stamp = $doc->getElementsByTagName('goal')->item(0)->getElementsByTagName('timestamp')->item(0)->textContent;
    assertTrue(preg_match('#^\d{2}/\d{2}/\d{4} \d{2}:\d{2}:\d{2}$#', $stamp) === 1, $stamp);
});

test('the upload body has the fields of a real EGREP upload, in the same order and not url-encoded', function () {
    $body = HockeyDataClient::uploadBody(199409, '[HD]ab+/cd', 3, 'SK (Sk1)', 'e-grep_v26.1.0.2018');
    assertSame(
        'gameId=199409&serializedData=[HD]ab+/cd&status=3&uploadUsername=SK (Sk1)&egrepVersion=e-grep_v26.1.0.2018&blueCode=&redCode=&errors=null&jsonEnabled=1',
        $body
    );
});

test('the real start and end time of the game are written as hh:mm, anything else is left empty', function () {
    $doc = builtReport(null, ['gamestart' => '20:00', 'gameend' => '21:21']);
    assertSame('20:00', $doc->getElementsByTagName('gamestart')->item(0)->textContent);
    assertSame('21:21', $doc->getElementsByTagName('gameend')->item(0)->textContent);

    $bad = builtReport(null, ['gamestart' => '25:99', 'gameend' => 'soon']);
    assertSame('', $bad->getElementsByTagName('gamestart')->item(0)->textContent);
    assertSame('', $bad->getElementsByTagName('gameend')->item(0)->textContent);
});

test('the start and end time are read back from a report', function () {
    $xml = str_replace('<gamestart /><gameend />', '<gamestart>20:00</gamestart><gameend>21:21</gameend>', oldLayoutTemplate());
    $state = GameReport::parse($xml);
    assertSame('20:00', $state['gamestart']);
    assertSame('21:21', $state['gameend']);
});
