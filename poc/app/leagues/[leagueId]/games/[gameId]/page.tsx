import { mockLeagues } from "@/lib/mock-data";
import GamePageClient from "./page-client"

export async function generateStaticParams() {
  return mockLeagues[0].games;
}

export default async function GamePage({ params }: { params: Promise<{ leagueId: string; gameId: string }> }) {
  const resolvedParams = await params
  const { leagueId, gameId } = resolvedParams

  return <GamePageClient leagueId={leagueId} gameId={gameId} />
}
