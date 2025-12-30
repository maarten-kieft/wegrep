using Microsoft.AspNetCore.Mvc;
using Wegrep.Api.Clients;
using Wegrep.Api.Model;

namespace Wegrep.Api.Controllers;

[ApiController]
[Route("[controller]")]
public class LeagueController(IHockeyDataClient hockeyDataClient) : ControllerBase
{
    [HttpGet]
    public Task<List<League>> Index()
    {
        return hockeyDataClient.GetLeagues();
    }

    [HttpGet("{leagueId}/games")]
    public Task<List<Game>> Games(string leagueId)
    {
        return hockeyDataClient.GetLeaugeGames(leagueId);
    }
}
