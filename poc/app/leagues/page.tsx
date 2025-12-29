"use client"

import { useRouter } from "next/navigation"
import { mockLeagues } from "@/lib/mock-data"
import { Card, CardContent } from "@/components/ui/card"
import { ChevronRight } from "lucide-react"
import { Header } from "@/components/header"

export default function LeaguesPage() {
  const router = useRouter()

  return (
    <div className="min-h-screen bg-background pb-20 overflow-x-hidden">
      <Header variant="home" />

      <div className="px-4 pt-6 max-w-4xl mx-auto">
        <p className="text-muted-foreground text-center mb-6">Select a league to get started</p>

        <div className="grid gap-4 md:grid-cols-3">
          {mockLeagues.map((league) => (
            <Card
              key={league.id}
              className="bg-card border-border cursor-pointer hover:bg-secondary transition-colors active:scale-[0.98]"
              onClick={() => router.push(`/leagues/${league.id}/games`)}
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
      </div>
    </div>
  )
}
