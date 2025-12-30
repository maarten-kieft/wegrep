using Wegrep.Api.Model;

namespace Wegrep.Api.Clients;

public interface IHockeyDataClient
{
    Task<List<League>> GetLeagues();

    Task<List<Game>> GetLeaugeGames(string leagueId);
}
