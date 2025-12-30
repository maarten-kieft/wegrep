using System.Text.Json.Serialization;

namespace Wegrep.Api.Clients.Model;

public class Game
{
    [JsonPropertyName("GameId")]
    public int GameId { get; set; }

    [JsonPropertyName("GameGuid")]
    public required string GameGuid { get; set; }

    [JsonPropertyName("Gamename")]
    public string? GameName { get; set; }

    [JsonPropertyName("GameRound")]
    public string? GameRound { get; set; }

    [JsonPropertyName("GameStatus")]
    public int GameStatus { get; set; }

    [JsonPropertyName("GameDay")]
    public string? GameDay { get; set; }

    [JsonPropertyName("DivisionName")]
    public required string DivisionName { get; set; }

    [JsonPropertyName("AwayRosterIsPublic")]
    public bool AwayRosterIsPublic { get; set; }

    [JsonPropertyName("AwayTeamname")]
    public required string AwayTeamName { get; set; }

    [JsonPropertyName("HomeRosterIsPublic")]
    public bool HomeRosterIsPublic { get; set; }

    [JsonPropertyName("HomeTeamname")]
    public required string HomeTeamName { get; set; }

    [JsonPropertyName("IsEgrepDownloadable")]
    public bool IsEgrepDownloadable{ get; set; }

    [JsonPropertyName("LocationName")]
    public required string LocationName { get; set; }

    [JsonPropertyName("RosterStatus")]
    public string? RosterStatus{ get; set; }

    [JsonPropertyName("ScheduledDate")]
    public DateTime ScheduledDate { get; set; }
}
