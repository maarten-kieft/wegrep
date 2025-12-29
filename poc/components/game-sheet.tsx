"use client"

import { useState, useCallback } from "react"
import { useRouter } from "next/navigation"
import type { Game, Goal, Penalty, Player, GameStatus } from "@/lib/types"
import { Card, CardContent } from "@/components/ui/card"
import { Button } from "@/components/ui/button"
import { Tabs, TabsContent, TabsList, TabsTrigger } from "@/components/ui/tabs"
import { GoalModal } from "./goal-modal"
import { PenaltyModal } from "./penalty-modal"
import { GameTimer } from "./game-timer"
import { TeamRosterEditor } from "./team-roster-editor"
import { Target, AlertTriangle, Pencil, Trash2, ChevronLeft, CheckCircle } from "lucide-react"
import Image from "next/image"

interface GameSheetProps {
  game: Game
  leagueId: string
}

function parseTimeToSeconds(time: string, period: 1 | 2 | 3 | "OT"): number {
  const [mins, secs] = time.split(":").map(Number)
  const periodValue = period === "OT" ? 4 : period
  return periodValue * 1200 + mins * 60 + (secs || 0)
}

export function GameSheet({ game: initialGame, leagueId }: GameSheetProps) {
  const router = useRouter()
  const [gameStatus, setGameStatus] = useState<GameStatus>("setup")
  const [game, setGame] = useState<Game>(initialGame)
  const [goals, setGoals] = useState<Goal[]>([])
  const [penalties, setPenalties] = useState<Penalty[]>([])
  const [period, setPeriod] = useState<1 | 2 | 3 | "OT">(1)
  const [currentTime, setCurrentTime] = useState("00:00")
  const [showGoalModal, setShowGoalModal] = useState(false)
  const [showPenaltyModal, setShowPenaltyModal] = useState(false)
  const [modalTeamSide, setModalTeamSide] = useState<"home" | "away">("home")
  const [editingGoal, setEditingGoal] = useState<Goal | null>(null)
  const [editingPenalty, setEditingPenalty] = useState<Penalty | null>(null)

  const handleUpdateHomeTeam = useCallback((players: Player[]) => {
    setGame((prev) => ({
      ...prev,
      homeTeam: { ...prev.homeTeam, players },
    }))
  }, [])

  const handleUpdateAwayTeam = useCallback((players: Player[]) => {
    setGame((prev) => ({
      ...prev,
      awayTeam: { ...prev.awayTeam, players },
    }))
  }, [])

  const handleAddGoal = useCallback(
    (newGoal: Omit<Goal, "id">) => {
      const goalWithId = {
        ...newGoal,
        id: Date.now().toString(),
      }
      if (editingGoal) {
        setGoals((prev) => prev.map((g) => (g.id === editingGoal.id ? { ...goalWithId, id: editingGoal.id } : g)))
        setEditingGoal(null)
      } else {
        setGoals((prev) => [...prev, goalWithId])
      }
      setShowGoalModal(false)
    },
    [editingGoal],
  )

  const handleAddPenalty = useCallback(
    (newPenalty: Omit<Penalty, "id">) => {
      const penaltyWithId = {
        ...newPenalty,
        id: Date.now().toString(),
      }
      if (editingPenalty) {
        setPenalties((prev) =>
          prev.map((p) => (p.id === editingPenalty.id ? { ...penaltyWithId, id: editingPenalty.id } : p)),
        )
        setEditingPenalty(null)
      } else {
        setPenalties((prev) => [...prev, penaltyWithId])
      }
      setShowPenaltyModal(false)
    },
    [editingPenalty],
  )

  const handleDeleteGoal = useCallback((goalId: string) => {
    setGoals((prev) => prev.filter((g) => g.id !== goalId))
  }, [])

  const handleDeletePenalty = useCallback((penaltyId: string) => {
    setPenalties((prev) => prev.filter((p) => p.id !== penaltyId))
  }, [])

  const handleEditGoal = useCallback((goal: Goal) => {
    setEditingGoal(goal)
    setModalTeamSide(goal.team)
    setShowGoalModal(true)
  }, [])

  const handleEditPenalty = useCallback((penalty: Penalty) => {
    setEditingPenalty(penalty)
    setModalTeamSide(penalty.team)
    setShowPenaltyModal(true)
  }, [])

  const openGoalModal = (team: "home" | "away") => {
    setModalTeamSide(team)
    setEditingGoal(null)
    setShowGoalModal(true)
  }

  const openPenaltyModal = (team: "home" | "away") => {
    setModalTeamSide(team)
    setEditingPenalty(null)
    setShowPenaltyModal(true)
  }

  const homeGoals = goals.filter((g) => g.team === "home").length
  const awayGoals = goals.filter((g) => g.team === "away").length

  const sortedGoals = [...goals].sort((a, b) => {
    return parseTimeToSeconds(b.time, b.period) - parseTimeToSeconds(a.time, a.period)
  })

  const sortedPenalties = [...penalties].sort((a, b) => {
    return parseTimeToSeconds(b.time, b.period) - parseTimeToSeconds(a.time, a.period)
  })

  const groupByPeriod = (items: (Goal | Penalty)[]) => {
    const groups: { [key: string]: (Goal | Penalty)[] } = {}
    items.forEach((item) => {
      const periodKey = item.period === "OT" ? "OT" : `P${item.period}`
      if (!groups[periodKey]) {
        groups[periodKey] = []
      }
      groups[periodKey].push(item)
    })
    return groups
  }

  const goalsByPeriod = groupByPeriod(sortedGoals)
  const penaltiesByPeriod = groupByPeriod(sortedPenalties)

  if (gameStatus === "setup") {
    return (
      <div className="min-h-screen bg-background overflow-x-hidden">
        <div
          className="relative h-28 bg-cover bg-center"
          style={{
            backgroundImage: "url(/ice-hockey-arena-dark-blue-dramatic-lighting.jpg)",
          }}
        >
          <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background" />
          <div className="absolute top-4 left-4">
            <Button
              variant="ghost"
              size="sm"
              onClick={() => router.push(`/leagues/${leagueId}/games`)}
              className="text-white hover:bg-white/20"
            >
              <ChevronLeft className="w-4 h-4 mr-1" />
              Back
            </Button>
          </div>
          <div className="absolute bottom-4 left-0 right-0 text-center">
            <h1 className="text-xl font-bold text-white">Game Setup</h1>
            <p className="text-white/70 text-sm">Review and edit team rosters</p>
          </div>
        </div>

        <div className="px-4 pt-6 pb-28 max-w-4xl mx-auto">
          <div className="space-y-6 lg:grid lg:grid-cols-2 lg:gap-6 lg:space-y-0">
            <TeamRosterEditor team={game.homeTeam} label="Home Team" onUpdatePlayers={handleUpdateHomeTeam} />
            <TeamRosterEditor team={game.awayTeam} label="Away Team" onUpdatePlayers={handleUpdateAwayTeam} />
          </div>
        </div>

        <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t border-border">
          <div className="max-w-4xl mx-auto">
            <Button onClick={() => setGameStatus("active")} className="w-full" size="lg">
              Start Game
            </Button>
          </div>
        </div>
      </div>
    )
  }

  if (gameStatus === "ended") {
    return (
      <div className="min-h-screen bg-background flex items-center justify-center p-4">
        <div className="max-w-md w-full space-y-6">
          <div className="text-center">
            <CheckCircle className="w-16 h-16 text-green-500 mx-auto mb-4" />
            <h1 className="text-3xl font-bold text-foreground mb-2">Game Complete</h1>
            <p className="text-muted-foreground">The game sheet has been finalized</p>
          </div>

          <Card className="bg-card border-border">
            <CardContent className="p-6">
              <div className="flex items-center justify-between mb-6">
                <div className="text-center flex-1">
                  {game.homeTeam.icon && (
                    <div className="mx-auto mb-2 w-16 h-16 rounded-full bg-white p-2 flex items-center justify-center">
                      <Image
                        src={game.homeTeam.icon || "/placeholder.svg"}
                        alt={game.homeTeam.name}
                        width={48}
                        height={48}
                        className="object-contain"
                      />
                    </div>
                  )}
                  <p className="text-sm text-muted-foreground">{game.homeTeam.name}</p>
                  <p className="text-3xl font-bold text-foreground">{homeGoals}</p>
                </div>

                <div className="px-4">
                  <span className="text-2xl font-bold text-muted-foreground">-</span>
                </div>

                <div className="text-center flex-1">
                  {game.awayTeam.icon && (
                    <div className="mx-auto mb-2 w-16 h-16 rounded-full bg-white p-2 flex items-center justify-center">
                      <Image
                        src={game.awayTeam.icon || "/placeholder.svg"}
                        alt={game.awayTeam.name}
                        width={48}
                        height={48}
                        className="object-contain"
                      />
                    </div>
                  )}
                  <p className="text-sm text-muted-foreground">{game.awayTeam.name}</p>
                  <p className="text-3xl font-bold text-foreground">{awayGoals}</p>
                </div>
              </div>

              <Button onClick={() => router.push(`/leagues/${leagueId}/games`)} className="w-full">
                Back to Games
              </Button>
            </CardContent>
          </Card>
        </div>
      </div>
    )
  }

  return (
    <div className="min-h-screen bg-background overflow-x-hidden">
      <div
        className="relative h-28 bg-cover bg-center"
        style={{
          backgroundImage: "url(/ice-hockey-arena-dark-blue-dramatic-lighting.jpg)",
        }}
      >
        <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background" />
        <div className="absolute top-4 left-4">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => router.push(`/leagues/${leagueId}/games`)}
            className="text-white hover:bg-white/20"
          >
            <ChevronLeft className="w-4 h-4 mr-1" />
            Back
          </Button>
        </div>
        <div className="absolute bottom-4 left-0 right-0 text-center">
          <h1 className="text-xl font-bold text-white">Game Sheet</h1>
        </div>
      </div>

      <div className="px-4 pt-4 pb-28 max-w-4xl mx-auto">
        <Card className="bg-card border-border mb-4">
          <CardContent className="p-4">
            <div className="flex items-center justify-between mb-4">
              <div className="text-center flex-1">
                {game.homeTeam.icon && (
                  <div className="mx-auto mb-2 w-12 h-12 rounded-full bg-white p-1 flex items-center justify-center">
                    <Image
                      src={game.homeTeam.icon || "/placeholder.svg"}
                      alt={game.homeTeam.name}
                      width={40}
                      height={40}
                      className="object-contain"
                    />
                  </div>
                )}
                <p className="text-sm text-muted-foreground">{game.homeTeam.name}</p>
                <p className="text-3xl font-bold text-foreground">{homeGoals}</p>
              </div>

              <div className="px-4">
                <span className="text-2xl font-bold text-muted-foreground">VS</span>
              </div>

              <div className="text-center flex-1">
                {game.awayTeam.icon && (
                  <div className="mx-auto mb-2 w-12 h-12 rounded-full bg-white p-1 flex items-center justify-center">
                    <Image
                      src={game.awayTeam.icon || "/placeholder.svg"}
                      alt={game.awayTeam.name}
                      width={40}
                      height={40}
                      className="object-contain"
                    />
                  </div>
                )}
                <p className="text-sm text-muted-foreground">{game.awayTeam.name}</p>
                <p className="text-3xl font-bold text-foreground">{awayGoals}</p>
              </div>
            </div>

            <GameTimer
              period={period}
              onPeriodChange={setPeriod}
              currentTime={currentTime}
              onTimeUpdate={setCurrentTime}
            />
          </CardContent>
        </Card>

        <div className="grid grid-cols-2 gap-3 mb-4">
          <Button onClick={() => openGoalModal("home")} className="flex items-center gap-2" size="lg">
            <Target className="w-5 h-5" />
            Goal {game.homeTeam.name}
          </Button>
          <Button onClick={() => openGoalModal("away")} className="flex items-center gap-2" size="lg">
            <Target className="w-5 h-5" />
            Goal {game.awayTeam.name}
          </Button>
          <Button
            onClick={() => openPenaltyModal("home")}
            variant="destructive"
            className="flex items-center gap-2"
            size="lg"
          >
            <AlertTriangle className="w-5 h-5" />
            Penalty {game.homeTeam.name}
          </Button>
          <Button
            onClick={() => openPenaltyModal("away")}
            variant="destructive"
            className="flex items-center gap-2"
            size="lg"
          >
            <AlertTriangle className="w-5 h-5" />
            Penalty {game.awayTeam.name}
          </Button>
        </div>

        <Tabs defaultValue="goals" className="w-full">
          <TabsList className="grid w-full grid-cols-2">
            <TabsTrigger value="goals">Goals ({goals.length})</TabsTrigger>
            <TabsTrigger value="penalties">Penalties ({penalties.length})</TabsTrigger>
          </TabsList>

          <TabsContent value="goals" className="space-y-3 mt-4">
            {sortedGoals.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <p>No goals recorded yet</p>
              </div>
            ) : (
              Object.entries(goalsByPeriod).map(([periodLabel, periodGoals]) => (
                <div key={periodLabel}>
                  <div className="flex items-center gap-2 mb-3">
                    <div className="h-px bg-border flex-1" />
                    <span className="text-xs text-muted-foreground font-medium px-2">
                      {periodLabel === "OT" ? "Overtime" : `Period ${periodLabel.slice(1)}`}
                    </span>
                    <div className="h-px bg-border flex-1" />
                  </div>
                  <div className="space-y-3">
                    {periodGoals.map((goal) => {
                      const isGoal = "scorerNumber" in goal
                      if (!isGoal) return null
                      const g = goal as Goal
                      const team = g.team === "home" ? game.homeTeam : game.awayTeam
                      return (
                        <Card key={g.id} className="bg-card border-l-4 border-l-primary">
                          <CardContent className="p-3">
                            <div className="flex items-start gap-3">
                              {team.icon && (
                                <div className="w-10 h-10 rounded-full bg-white p-1 flex items-center justify-center flex-shrink-0">
                                  <Image
                                    src={team.icon || "/placeholder.svg"}
                                    alt={team.name}
                                    width={32}
                                    height={32}
                                    className="object-contain"
                                  />
                                </div>
                              )}
                              <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">
                                    {g.period === "OT" ? "OT" : `P${g.period}`}
                                  </span>
                                  <span className="text-sm font-semibold text-foreground">{g.time}</span>
                                  <span className="text-xs px-2 py-0.5 rounded bg-secondary text-secondary-foreground">
                                    {g.situation}
                                  </span>
                                </div>
                                <p className="text-sm font-semibold text-foreground">
                                  #{g.scorerNumber} {g.scorerName}
                                </p>
                                {(g.assist1Number || g.assist2Number) && (
                                  <p className="text-xs text-muted-foreground">
                                    Assists:{" "}
                                    {[
                                      g.assist1Number && `#${g.assist1Number} ${g.assist1Name}`,
                                      g.assist2Number && `#${g.assist2Number} ${g.assist2Name}`,
                                    ]
                                      .filter(Boolean)
                                      .join(", ")}
                                  </p>
                                )}
                                <p className="text-xs text-muted-foreground">Shot type: {g.shotType}</p>
                              </div>
                              <div className="flex gap-1 flex-shrink-0">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleEditGoal(g)}
                                  className="h-8 w-8 p-0"
                                >
                                  <Pencil className="w-4 h-4" />
                                </Button>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleDeleteGoal(g.id)}
                                  className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                >
                                  <Trash2 className="w-4 h-4" />
                                </Button>
                              </div>
                            </div>
                          </CardContent>
                        </Card>
                      )
                    })}
                  </div>
                </div>
              ))
            )}
          </TabsContent>

          <TabsContent value="penalties" className="space-y-3 mt-4">
            {sortedPenalties.length === 0 ? (
              <div className="text-center py-8 text-muted-foreground">
                <p>No penalties recorded yet</p>
              </div>
            ) : (
              Object.entries(penaltiesByPeriod).map(([periodLabel, periodPenalties]) => (
                <div key={periodLabel}>
                  <div className="flex items-center gap-2 mb-3">
                    <div className="h-px bg-border flex-1" />
                    <span className="text-xs text-muted-foreground font-medium px-2">
                      {periodLabel === "OT" ? "Overtime" : `Period ${periodLabel.slice(1)}`}
                    </span>
                    <div className="h-px bg-border flex-1" />
                  </div>
                  <div className="space-y-3">
                    {periodPenalties.map((penalty) => {
                      const isPenalty = "playerNumber" in penalty
                      if (!isPenalty) return null
                      const p = penalty as Penalty
                      const team = p.team === "home" ? game.homeTeam : game.awayTeam
                      return (
                        <Card key={p.id} className="bg-card border-l-4 border-l-destructive">
                          <CardContent className="p-3">
                            <div className="flex items-start gap-3">
                              {team.icon && (
                                <div className="w-10 h-10 rounded-full bg-white p-1 flex items-center justify-center flex-shrink-0">
                                  <Image
                                    src={team.icon || "/placeholder.svg"}
                                    alt={team.name}
                                    width={32}
                                    height={32}
                                    className="object-contain"
                                  />
                                </div>
                              )}
                              <div className="flex-1 min-w-0">
                                <div className="flex items-center gap-2 mb-1">
                                  <span className="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary/10 text-primary">
                                    {p.period === "OT" ? "OT" : `P${p.period}`}
                                  </span>
                                  <span className="text-sm font-semibold text-foreground">{p.time}</span>
                                  <span className="text-xs px-2 py-0.5 rounded bg-destructive/10 text-destructive font-medium">
                                    {p.minutes} min
                                  </span>
                                </div>
                                <p className="text-sm font-semibold text-foreground">
                                  #{p.playerNumber} {p.playerName}
                                </p>
                                <p className="text-xs text-muted-foreground">{p.penaltyType}</p>
                                <p className="text-xs text-muted-foreground">
                                  {p.startTime} - {p.endTime}
                                </p>
                              </div>
                              <div className="flex gap-1 flex-shrink-0">
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleEditPenalty(p)}
                                  className="h-8 w-8 p-0"
                                >
                                  <Pencil className="w-4 h-4" />
                                </Button>
                                <Button
                                  variant="ghost"
                                  size="sm"
                                  onClick={() => handleDeletePenalty(p.id)}
                                  className="h-8 w-8 p-0 text-destructive hover:text-destructive"
                                >
                                  <Trash2 className="w-4 h-4" />
                                </Button>
                              </div>
                            </div>
                          </CardContent>
                        </Card>
                      )
                    })}
                  </div>
                </div>
              ))
            )}
          </TabsContent>
        </Tabs>
      </div>

      <div className="fixed bottom-0 left-0 right-0 p-4 bg-background border-t border-border">
        <div className="max-w-4xl mx-auto">
          <Button onClick={() => setGameStatus("ended")} className="w-full" size="lg" variant="default">
            End Game & Submit Sheet
          </Button>
        </div>
      </div>

      {showGoalModal && (
        <GoalModal
          isOpen={showGoalModal}
          onClose={() => {
            setShowGoalModal(false)
            setEditingGoal(null)
          }}
          onSave={handleAddGoal}
          players={modalTeamSide === "home" ? game.homeTeam.players : game.awayTeam.players}
          teamName={modalTeamSide === "home" ? game.homeTeam.name : game.awayTeam.name}
          team={modalTeamSide}
          currentPeriod={period}
          currentTime={currentTime}
          editingGoal={editingGoal}
        />
      )}

      {showPenaltyModal && (
        <PenaltyModal
          isOpen={showPenaltyModal}
          onClose={() => {
            setShowPenaltyModal(false)
            setEditingPenalty(null)
          }}
          onSave={handleAddPenalty}
          players={modalTeamSide === "home" ? game.homeTeam.players : game.awayTeam.players}
          teamName={modalTeamSide === "home" ? game.homeTeam.name : game.awayTeam.name}
          team={modalTeamSide}
          currentPeriod={period}
          currentTime={currentTime}
          editingPenalty={editingPenalty}
        />
      )}
    </div>
  )
}
