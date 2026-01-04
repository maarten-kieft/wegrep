import { Game } from "../model/game";
import { League } from "../model/league";
import { http } from "./httpClient";

const apiHost = process.env['NEXT_PUBLIC_API_BASE_URL'];

export const leagueService = {
  getLeagues: () => http<League[]>(`${apiHost}/league`),
  getGames: (leagueId: string) => http<Game[]>(`${apiHost}/league/${leagueId}/users`),
};