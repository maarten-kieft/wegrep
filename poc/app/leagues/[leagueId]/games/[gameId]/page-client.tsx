"use client"

import { useRouter } from "next/navigation"
import { mockGames } from "@/lib/mock-data"
import { GameSheet } from "@/components/game-sheet"
import { Button } from "@/components/ui/button"

export default function GamePageClient({ leagueId, gameId }: { leagueId: string; gameId: string }) {
  const router = useRouter()
  const game = mockGames.find((g) => g.id === gameId)

  if (!game) {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center">
        <div className="text-center">
          <p className="text-muted-foreground">Game not found</p>
          <Button onClick={() => router.push(`/leagues/${leagueId}/games`)} className="mt-4">
            Back to Games
          </Button>
        </div>
      </div>
    )
  }

  return <GameSheet game={game} leagueId={leagueId} />
}
