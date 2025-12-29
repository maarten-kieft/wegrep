"use client"

import { useState, useEffect } from "react"
import type { Player, Goal, Team } from "@/lib/types"
import { goalSituations, goalTypes } from "@/lib/mock-data"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { X, Target } from "lucide-react"

interface GoalModalProps {
  team: Team
  teamSide: "home" | "away"
  players: Player[]
  onSave: (goal: Omit<Goal, "id">) => void
  onClose: () => void
  editingGoal?: Goal
  currentTime?: string
  currentPeriod: 1 | 2 | 3 | "OT"
}

export function GoalModal({
  team,
  teamSide,
  players,
  onSave,
  onClose,
  editingGoal,
  currentTime,
  currentPeriod,
}: GoalModalProps) {
  const getDefaultTime = () => {
    if (currentTime) return currentTime
    const periodStartMins = currentPeriod === "OT" ? 60 : (currentPeriod - 1) * 20
    return `${periodStartMins.toString().padStart(2, "0")}:00`
  }

  const [time, setTime] = useState(getDefaultTime())
  const [scorerNumber, setScorerNumber] = useState("")
  const [assist1Number, setAssist1Number] = useState("")
  const [assist2Number, setAssist2Number] = useState("")
  const [situation, setSituation] = useState<Goal["situation"]>("EV")
  const [type, setType] = useState<Goal["type"]>("Wrist")
  const [goalPeriod, setGoalPeriod] = useState<1 | 2 | 3 | "OT">(currentPeriod)

  const sortedPlayers = [...players].sort((a, b) => Number.parseInt(a.number) - Number.parseInt(b.number))

  useEffect(() => {
    if (editingGoal) {
      setTime(editingGoal.time)
      setScorerNumber(editingGoal.scorerNumber)
      setAssist1Number(editingGoal.assist1Number || "")
      setAssist2Number(editingGoal.assist2Number || "")
      setSituation(editingGoal.situation)
      setType(editingGoal.type)
      setGoalPeriod(editingGoal.period)
    } else if (currentTime) {
      setTime(currentTime)
    }
  }, [editingGoal, currentTime])

  useEffect(() => {
    if (!editingGoal) {
      const periodStartMins = goalPeriod === "OT" ? 60 : (goalPeriod - 1) * 20
      const [currMins] = time.split(":").map(Number)
      if (currMins < periodStartMins) {
        const minTime = `${periodStartMins.toString().padStart(2, "0")}:00`
        setTime(minTime)
      }
    }
  }, [goalPeriod, editingGoal, time])

  const getPlayerByNumber = (number: string): Player | undefined => {
    return players.find((p) => p.number === number)
  }

  const scorer = getPlayerByNumber(scorerNumber)
  const assist1 = getPlayerByNumber(assist1Number)
  const assist2 = getPlayerByNumber(assist2Number)

  const handleSubmit = () => {
    if (!time || !scorer) return

    onSave({
      time,
      team: teamSide,
      scorerNumber: scorer.number,
      scorerName: scorer.name,
      assist1Number: assist1?.number,
      assist1Name: assist1?.name,
      assist2Number: assist2?.number,
      assist2Name: assist2?.name,
      situation,
      type,
      period: goalPeriod,
    })
  }

  return (
    <div className="fixed inset-0 bg-background z-50 flex flex-col">
      <div className="flex-1 overflow-y-auto p-4">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
              <Target className="w-5 h-5 text-primary" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-foreground">{editingGoal ? "Edit Goal" : "Add Goal"}</h2>
              <p className="text-sm text-muted-foreground">{team.name}</p>
            </div>
          </div>
          <Button variant="ghost" size="icon" onClick={onClose}>
            <X className="w-5 h-5" />
          </Button>
        </div>

        {/* Form */}
        <div className="space-y-5 pb-24">
          <div className="space-y-2">
            <Label className="text-foreground">Period</Label>
            <div className="grid grid-cols-4 gap-2">
              {([1, 2, 3, "OT"] as const).map((p) => (
                <button
                  key={p}
                  onClick={() => setGoalPeriod(p)}
                  className={`p-3 rounded-lg text-sm font-medium transition-colors ${
                    goalPeriod === p ? "bg-primary text-primary-foreground" : "bg-secondary text-secondary-foreground"
                  }`}
                >
                  {p === "OT" ? "OT" : `P${p}`}
                </button>
              ))}
            </div>
          </div>

          {/* Time */}
          <div className="space-y-2">
            <Label className="text-foreground">Time (MM:SS)</Label>
            <Input
              placeholder="12:34"
              value={time}
              onChange={(e) => setTime(e.target.value)}
              className="bg-input border-border text-foreground text-lg h-12"
            />
          </div>

          <div className="space-y-2">
            <Label className="text-foreground">Goal Scorer *</Label>
            <Select value={scorerNumber} onValueChange={setScorerNumber}>
              <SelectTrigger className="w-full bg-input border-border text-foreground h-12">
                <SelectValue placeholder="Select player" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border w-full">
                {sortedPlayers.map((player) => (
                  <SelectItem key={player.number} value={player.number}>
                    #{player.number} - {player.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {scorer && (
              <p className="text-sm text-primary">
                #{scorer.number} {scorer.name}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label className="text-foreground">First Assist (optional)</Label>
            <Select value={assist1Number} onValueChange={setAssist1Number}>
              <SelectTrigger className="w-full bg-input border-border text-foreground h-12">
                <SelectValue placeholder="Select player" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border w-full">
                <SelectItem value="none">No assist</SelectItem>
                {sortedPlayers
                  .filter((p) => p.number !== scorerNumber)
                  .map((player) => (
                    <SelectItem key={player.number} value={player.number}>
                      #{player.number} - {player.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          <div className="space-y-2">
            <Label className="text-foreground">Second Assist (optional)</Label>
            <Select value={assist2Number} onValueChange={setAssist2Number}>
              <SelectTrigger className="w-full bg-input border-border text-foreground h-12">
                <SelectValue placeholder="Select player" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border w-full">
                <SelectItem value="none">No assist</SelectItem>
                {sortedPlayers
                  .filter((p) => p.number !== scorerNumber && p.number !== assist1Number)
                  .map((player) => (
                    <SelectItem key={player.number} value={player.number}>
                      #{player.number} - {player.name}
                    </SelectItem>
                  ))}
              </SelectContent>
            </Select>
          </div>

          {/* Situation */}
          <div className="space-y-2">
            <Label className="text-foreground">Situation</Label>
            <div className="grid grid-cols-3 gap-2">
              {goalSituations.map((sit) => (
                <button
                  key={sit.value}
                  onClick={() => setSituation(sit.value as Goal["situation"])}
                  className={`p-3 rounded-lg text-sm font-medium transition-colors ${
                    situation === sit.value
                      ? "bg-primary text-primary-foreground"
                      : "bg-secondary text-secondary-foreground"
                  }`}
                >
                  {sit.value}
                </button>
              ))}
            </div>
          </div>

          {/* Type */}
          <div className="space-y-2">
            <Label className="text-foreground">Shot Type</Label>
            <div className="grid grid-cols-3 gap-2">
              {goalTypes.map((t) => (
                <button
                  key={t}
                  onClick={() => setType(t as Goal["type"])}
                  className={`p-3 rounded-lg text-sm font-medium transition-colors ${
                    type === t ? "bg-primary text-primary-foreground" : "bg-secondary text-secondary-foreground"
                  }`}
                >
                  {t}
                </button>
              ))}
            </div>
          </div>
        </div>
      </div>

      {/* Submit Button */}
      <div className="p-4 bg-background border-t border-border">
        <Button
          className="w-full h-12 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold"
          onClick={handleSubmit}
          disabled={!time || !scorer}
        >
          {editingGoal ? "Update Goal" : "Record Goal"}
        </Button>
      </div>
    </div>
  )
}
