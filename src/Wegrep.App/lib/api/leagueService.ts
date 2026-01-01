import { Game } from "../model/game";
import { League } from "../model/League";
import { http } from "./httpClient";

export const leagueService = {
  getLeagues: () => http<League[]>("/league"),
  getGames: (leagueId: string) => http<Game[]>(`/league/${leagueId}/users`),
};