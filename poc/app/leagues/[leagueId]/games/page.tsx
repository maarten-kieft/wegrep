import { Suspense } from "react"
import LeagueGamesClient from "./page-client"
import { mockLeagues } from "@/lib/mock-data"

export async function generateStaticParams() {
  return mockLeagues.map((league) => ({
    leagueId: league.id.toString(),
  }))
}

export default async function LeagueGamesPage({ params }: { params: Promise<{ leagueId: string }> }) {
  const resolvedParams = await params

  return (
    <Suspense fallback={<div className="min-h-screen bg-background flex items-center justify-center">Loading...</div>}>
      <LeagueGamesClient leagueId={resolvedParams.leagueId} />
    </Suspense>
  )
}
