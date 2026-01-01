import { GameTeam } from "./gameTeam";

export interface Game {
    id: string,
    guid: string,
    name: string,
    divisionName: string,
    status: number,
    locationName: string,
    scheduledDate: Date,
    homeTeam: GameTeam,
    awayTeam: GameTeam
}