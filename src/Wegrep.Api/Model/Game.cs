namespace Wegrep.Api.Model;

public class Game
{
    public required string Id { get; set; }
    public required string Guid { get; set; }
    public string? Name { get; set; }
    public required string DivisionName { get; set; }
    public required int Status { get; set; }
    public required string LocationName { get; set; }
    public required DateTime ScheduledDate { get; set; }
    public required GameTeam HomeTeam { get; set; }
    public required GameTeam AwayTeam { get; set; }
}
