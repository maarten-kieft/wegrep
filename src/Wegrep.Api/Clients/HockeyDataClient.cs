using IHBase;
using Microsoft.Extensions.Options;
using System.Runtime.Serialization.Formatters.Binary;
using System.Text.Json;
using Wegrep.Api.Model;
using HockeyDataModel = Wegrep.Api.Clients.Model;

namespace Wegrep.Api.Clients;

public class HockeyDataClient : IHockeyDataClient
{
    private HttpClient _httpClient;
    private HockeyDataClientOptions _options;

    public HockeyDataClient(HttpClient httpClient, IOptions<HockeyDataClientOptions> options)
    {
        _httpClient = httpClient;
        _httpClient.BaseAddress = new Uri("https://los.hockeydata.net/los/data-asp/api/");
        _options = options.Value;
    }

    public async Task<List<League>> GetLeagues()
    {
        var request = new HttpRequestMessage(HttpMethod.Get, $"GetListOfAllLeagues?customer={_options.Consumer}&password=");
        var response = await _httpClient.SendAsync(request);
        var bytes = await response.Content.ReadAsByteArrayAsync();

        using var ms = new MemoryStream(bytes);
        var formatter = new BinaryFormatter();
        var listItems = formatter.Deserialize(ms) as ListItemsContainer;

        return listItems?.Liste
            .Select(i => new League { Id = i.Id.ToString(), Name = i.DisplayText })
            .ToList()
            ?? [];
    }

    public async Task<List<Game>> GetLeaugeGames(string leagueId)
    {
        var games = (await SendJsonAsync<List<HockeyDataModel.Game>>(
            HttpMethod.Get, 
            $"GetAllGamesOfLeagueAsJson?leagueId=343&password={_options.LeagugePassword}&debugStatusLevel=2&selectionOption=ALL&specificDate="
        )) ?? [];

        return games.
            Select(g => 
                new Game { 
                    Id = g.GameId.ToString(),
                    Guid = g.GameGuid,
                    Name = g.GameName,
                    DivisionName = g.DivisionName,
                    Status = g.GameStatus,
                    LocationName = g.LocationName,
                    ScheduledDate = g.ScheduledDate,
                    HomeTeam = new GameTeam { 
                        Name = g.HomeTeamName,
                        PlayerRoosterIsPublic = g.HomeRosterIsPublic
                    },
                    AwayTeam = new GameTeam { 
                        Name = g.AwayTeamName, 
                        PlayerRoosterIsPublic = g.AwayRosterIsPublic 
                    },
                }
            )
            .ToList();
    }

    private async Task<T?> SendJsonAsync<T>(HttpMethod method, string url)
    {
        var request = new HttpRequestMessage(method, url);
        var response = await _httpClient.SendAsync(request);
        var json = await response.Content.ReadAsStringAsync();

        return JsonSerializer.Deserialize<T>(json);
    }
}
