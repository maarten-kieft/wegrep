"use client"

import { useState } from "react"
import { useRouter } from "next/navigation"
import type { League, Game } from "@/lib/types"
import { mockLeagues } from "@/lib/mock-data"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { ChevronRight, Calendar, MapPin } from "lucide-react"
import { Header } from "./header"
import Image from "next/image"

type GameFilter = "all" | "today-future" | "past"

export function GameSelection() {
  const router = useRouter()
  const [selectedLeague, setSelectedLeague] = useState<League | null>(null)
  const [gameFilter, setGameFilter] = useState<GameFilter>("all")

  const filterGames = (games: Game[]) => {
    const today = new Date()
    today.setHours(0, 0, 0, 0)

    return games
      .filter((game) => {
        const gameDate = new Date(game.date)
        gameDate.setHours(0, 0, 0, 0)

        if (gameFilter === "today-future") {
          return gameDate >= today
        } else if (gameFilter === "past") {
          return gameDate < today
        }
        return true
      })
      .sort((a, b) => {
        const dateA = new Date(a.date)
        const dateB = new Date(b.date)
        return gameFilter === "past" ? dateB.getTime() - dateA.getTime() : dateA.getTime() - dateB.getTime()
      })
  }

  const filteredGames = selectedLeague ? filterGames(selectedLeague.games) : []

  return (
    <div className="min-h-screen bg-background pb-20 overflow-x-hidden">
      <Header variant="home" />

      <div className="px-4 pt-6 max-w-4xl mx-auto">
        <p className="text-muted-foreground text-center mb-6">
          {selectedLeague ? "Select a game" : "Select a league to get started"}
        </p>

        {/* League Selection - Reverted to simple cards without gradients */}
        {!selectedLeague && (
          <div className="grid gap-4 md:grid-cols-3">
            {mockLeagues.map((league) => (
              <Card
                key={league.id}
                className="bg-card border-border cursor-pointer hover:bg-secondary transition-colors active:scale-[0.98]"
                onClick={() => setSelectedLeague(league)}
              >
                <CardContent className="p-4">
                  <div className="flex items-center justify-between">
                    <div>
                      <h3 className="font-semibold text-foreground">{league.name}</h3>
                      <p className="text-sm text-muted-foreground">
                        {league.games.length} game{league.games.length !== 1 ? "s" : ""}
                      </p>
                    </div>
                    <ChevronRight className="w-5 h-5 text-muted-foreground" />
                  </div>
                </CardContent>
              </Card>
            ))}
          </div>
        )}

        {/* Game Selection */}
        {selectedLeague && (
          <div className="space-y-4">
            <Button variant="ghost" className="text-muted-foreground mb-2" onClick={() => setSelectedLeague(null)}>
              ← Back to leagues
            </Button>

            <div className="flex gap-2 pb-2 flex-wrap">
              <Button
                variant={gameFilter === "all" ? "default" : "outline"}
                size="sm"
                onClick={() => setGameFilter("all")}
                className={gameFilter === "all" ? "" : "bg-transparent"}
              >
                All Games
              </Button>
              <Button
                variant={gameFilter === "today-future" ? "default" : "outline"}
                size="sm"
                onClick={() => setGameFilter("today-future")}
                className={gameFilter === "today-future" ? "" : "bg-transparent"}
              >
                Today & Future
              </Button>
              <Button
                variant={gameFilter === "past" ? "default" : "outline"}
                size="sm"
                onClick={() => setGameFilter("past")}
                className={gameFilter === "past" ? "" : "bg-transparent"}
              >
                Past
              </Button>
            </div>

            <div className="grid gap-3 md:grid-cols-2">
              {filteredGames.length === 0 ? (
                <div className="text-center py-8 text-muted-foreground md:col-span-2">
                  <p>No games found for this filter</p>
                </div>
              ) : (
                filteredGames.map((game) => (
                  <Card
                    key={game.id}
                    className="bg-card border-border cursor-pointer hover:bg-secondary transition-colors active:scale-[0.98]"
                    onClick={() => router.push(`/game/${game.id}`)}
                  >
                    <CardContent className="p-4">
                      <div className="flex items-center justify-between mb-3">
                        <div className="flex items-center gap-2 text-sm text-muted-foreground">
                          <Calendar className="w-4 h-4" />
                          <span>{game.date}</span>
                          <span>•</span>
                          <span>{game.time}</span>
                        </div>
                        <ChevronRight className="w-5 h-5 text-muted-foreground" />
                      </div>

                      <div className="flex items-center justify-between">
                        <div className="flex-1 text-center">
                          {game.homeTeam.icon ? (
                            <div className="mx-auto mb-2 w-12 h-12 rounded-full bg-white p-1 flex items-center justify-center">
                              <Image
                                src={game.homeTeam.icon || "/placeholder.svg"}
                                alt={game.homeTeam.name}
                                width={40}
                                height={40}
                                className="rounded-full object-contain"
                              />
                            </div>
                          ) : (
                            <div className="w-12 h-12 rounded-full bg-primary/20 mx-auto mb-2 flex items-center justify-center">
                              <span className="text-xs font-bold text-primary">
                                {game.homeTeam.name.substring(0, 3).toUpperCase()}
                              </span>
                            </div>
                          )}
                          <p className="font-semibold text-foreground text-sm">{game.homeTeam.name}</p>
                          <p className="text-xs text-muted-foreground">Home</p>
                        </div>

                        <div className="px-4">
                          <span className="text-xl font-bold text-muted-foreground">VS</span>
                        </div>

                        <div className="flex-1 text-center">
                          {game.awayTeam.icon ? (
                            <div className="mx-auto mb-2 w-12 h-12 rounded-full bg-white p-1 flex items-center justify-center">
                              <Image
                                src={game.awayTeam.icon || "/placeholder.svg"}
                                alt={game.awayTeam.name}
                                width={40}
                                height={40}
                                className="rounded-full object-contain"
                              />
                            </div>
                          ) : (
                            <div className="w-12 h-12 rounded-full bg-accent/20 mx-auto mb-2 flex items-center justify-center">
                              <span className="text-xs font-bold text-accent">
                                {game.awayTeam.name.substring(0, 3).toUpperCase()}
                              </span>
                            </div>
                          )}
                          <p className="font-semibold text-foreground text-sm">{game.awayTeam.name}</p>
                          <p className="text-xs text-muted-foreground">Away</p>
                        </div>
                      </div>

                      <div className="flex items-center justify-center gap-2 mt-3 text-xs text-muted-foreground">
                        <MapPin className="w-3 h-3" />
                        <span>{game.venue}</span>
                      </div>
                    </CardContent>
                  </Card>
                ))
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  )
}
