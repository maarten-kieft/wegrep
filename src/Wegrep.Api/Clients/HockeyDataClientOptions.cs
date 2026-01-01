namespace Wegrep.Api.Clients;

public class HockeyDataClientOptions
{
    public static string SectionName = "HockeyDataClient";
    public required string Consumer { get; set; }
    public required string LeagugePassword { get; set; }
}
