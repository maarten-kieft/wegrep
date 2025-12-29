"use client"

import { useState, useEffect } from "react"
import type { Player, Penalty, Team } from "@/lib/types"
import { penaltyTypes } from "@/lib/mock-data"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from "@/components/ui/select"
import { X, AlertTriangle } from "lucide-react"

interface PenaltyModalProps {
  team: Team
  teamSide: "home" | "away"
  players: Player[]
  onSave: (penalty: Omit<Penalty, "id">) => void
  onClose: () => void
  editingPenalty?: Penalty
  currentTime?: string
  currentPeriod: 1 | 2 | 3 | "OT"
}

export function PenaltyModal({
  team,
  teamSide,
  players,
  onSave,
  onClose,
  editingPenalty,
  currentTime,
  currentPeriod,
}: PenaltyModalProps) {
  const getDefaultTime = () => {
    if (currentTime) return currentTime
    const periodStartMins = currentPeriod === "OT" ? 60 : (currentPeriod - 1) * 20
    return `${periodStartMins.toString().padStart(2, "0")}:00`
  }

  const [time, setTime] = useState(getDefaultTime())
  const [playerNumber, setPlayerNumber] = useState("")
  const [minutes, setMinutes] = useState("2")
  const [type, setType] = useState("")
  const [startTime, setStartTime] = useState(getDefaultTime())
  const [endTime, setEndTime] = useState("")
  const [penaltyPeriod, setPenaltyPeriod] = useState<1 | 2 | 3 | "OT">(currentPeriod)

  const sortedPlayers = [...players].sort((a, b) => Number.parseInt(a.number) - Number.parseInt(b.number))

  const player = players.find((p) => p.number === playerNumber)

  const calculateEndTime = (start: string, mins: string) => {
    if (!start) return ""
    const [m, s] = start.split(":").map(Number)
    const totalSeconds = m * 60 + s + Number.parseInt(mins) * 60
    const endMins = Math.floor(totalSeconds / 60)
    const endSecs = totalSeconds % 60
    return `${endMins.toString().padStart(2, "0")}:${endSecs.toString().padStart(2, "0")}`
  }

  useEffect(() => {
    if (editingPenalty) {
      setTime(editingPenalty.time)
      setPlayerNumber(editingPenalty.playerNumber)
      setMinutes(editingPenalty.minutes.toString())
      setType(editingPenalty.type)
      setStartTime(editingPenalty.startTime)
      setEndTime(editingPenalty.endTime)
      setPenaltyPeriod(editingPenalty.period)
    } else if (currentTime) {
      setTime(currentTime)
      setStartTime(currentTime)
      setEndTime(calculateEndTime(currentTime, minutes))
    }
  }, [editingPenalty, currentTime])

  useEffect(() => {
    if (!editingPenalty) {
      const periodStartMins = penaltyPeriod === "OT" ? 60 : (penaltyPeriod - 1) * 20
      const [currMins] = time.split(":").map(Number)
      if (currMins < periodStartMins) {
        const minTime = `${periodStartMins.toString().padStart(2, "0")}:00`
        setTime(minTime)
        setStartTime(minTime)
        setEndTime(calculateEndTime(minTime, minutes))
      }
    }
  }, [penaltyPeriod, editingPenalty, time, minutes])

  const handleSubmit = () => {
    if (!time || !player || !type || !startTime || !endTime) return

    onSave({
      time,
      team: teamSide,
      playerNumber: player.number,
      playerName: player.name,
      minutes: Number.parseInt(minutes),
      type,
      startTime,
      endTime,
      period: penaltyPeriod,
    })
  }

  const handleStartTimeChange = (value: string) => {
    setStartTime(value)
    setEndTime(calculateEndTime(value, minutes))
  }

  const handleMinutesChange = (value: string) => {
    setMinutes(value)
    if (startTime) {
      setEndTime(calculateEndTime(startTime, value))
    }
  }

  return (
    <div className="fixed inset-0 bg-background z-50 flex flex-col">
      <div className="flex-1 overflow-y-auto p-4">
        {/* Header */}
        <div className="flex items-center justify-between mb-6">
          <div className="flex items-center gap-3">
            <div className="w-10 h-10 rounded-full bg-destructive/20 flex items-center justify-center">
              <AlertTriangle className="w-5 h-5 text-destructive" />
            </div>
            <div>
              <h2 className="text-lg font-bold text-foreground">{editingPenalty ? "Edit Penalty" : "Add Penalty"}</h2>
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
                  onClick={() => setPenaltyPeriod(p)}
                  className={`p-3 rounded-lg text-sm font-medium transition-colors ${
                    penaltyPeriod === p
                      ? "bg-destructive text-destructive-foreground"
                      : "bg-secondary text-secondary-foreground"
                  }`}
                >
                  {p === "OT" ? "OT" : `P${p}`}
                </button>
              ))}
            </div>
          </div>

          {/* Time of Penalty */}
          <div className="space-y-2">
            <Label className="text-foreground">Time of Penalty (MM:SS)</Label>
            <Input
              placeholder="12:34"
              value={time}
              onChange={(e) => setTime(e.target.value)}
              className="bg-input border-border text-foreground text-lg h-12"
            />
          </div>

          <div className="space-y-2">
            <Label className="text-foreground">Player *</Label>
            <Select value={playerNumber} onValueChange={setPlayerNumber}>
              <SelectTrigger className="w-full bg-input border-border text-foreground h-12">
                <SelectValue placeholder="Select player" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border w-full">
                {sortedPlayers.map((p) => (
                  <SelectItem key={p.number} value={p.number}>
                    #{p.number} - {p.name}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
            {player && (
              <p className="text-sm text-destructive">
                #{player.number} {player.name}
              </p>
            )}
          </div>

          <div className="space-y-2">
            <Label className="text-foreground">Penalty Type *</Label>
            <Select value={type} onValueChange={setType}>
              <SelectTrigger className="w-full bg-input border-border text-foreground h-12">
                <SelectValue placeholder="Select penalty type" />
              </SelectTrigger>
              <SelectContent className="bg-popover border-border max-h-60 w-full">
                {penaltyTypes.map((t) => (
                  <SelectItem key={t} value={t}>
                    {t}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          </div>

          {/* Minutes */}
          <div className="space-y-2">
            <Label className="text-foreground">Penalty Minutes</Label>
            <div className="grid grid-cols-4 gap-2">
              {["2", "4", "5", "10"].map((m) => (
                <button
                  key={m}
                  onClick={() => handleMinutesChange(m)}
                  className={`p-3 rounded-lg text-sm font-medium transition-colors ${
                    minutes === m
                      ? "bg-destructive text-destructive-foreground"
                      : "bg-secondary text-secondary-foreground"
                  }`}
                >
                  {m} min
                </button>
              ))}
            </div>
          </div>

          {/* Start Time */}
          <div className="space-y-2">
            <Label className="text-foreground">Penalty Start Time (MM:SS)</Label>
            <Input
              placeholder="12:34"
              value={startTime}
              onChange={(e) => handleStartTimeChange(e.target.value)}
              className="bg-input border-border text-foreground text-lg h-12"
            />
          </div>

          {/* End Time */}
          <div className="space-y-2">
            <Label className="text-foreground">Penalty End Time (MM:SS)</Label>
            <Input
              placeholder="14:34"
              value={endTime}
              onChange={(e) => setEndTime(e.target.value)}
              className="bg-input border-border text-foreground text-lg h-12"
            />
            <p className="text-xs text-muted-foreground">Auto-calculated based on start time and minutes</p>
          </div>
        </div>
      </div>

      {/* Submit Button */}
      <div className="p-4 bg-background border-t border-border">
        <Button
          className="w-full h-12 bg-destructive hover:bg-destructive/90 text-destructive-foreground font-semibold"
          onClick={handleSubmit}
          disabled={!time || !player || !type || !startTime || !endTime}
        >
          {editingPenalty ? "Update Penalty" : "Record Penalty"}
        </Button>
      </div>
    </div>
  )
}
