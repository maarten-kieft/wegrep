"use client"

import { useState } from "react"
import type { Team, Player } from "@/lib/types"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Card, CardContent, CardHeader, CardTitle } from "@/components/ui/card"
import { Plus, Trash2, Users } from "lucide-react"
import Image from "next/image"

interface TeamRosterEditorProps {
  team: Team
  label: string
  onUpdatePlayers: (players: Player[]) => void
}

export function TeamRosterEditor({ team, label, onUpdatePlayers }: TeamRosterEditorProps) {
  const [players, setPlayers] = useState<Player[]>(team?.players ? [...team.players] : [])

  const sortByNumber = (players: Player[]) =>
    [...players].sort((a, b) => Number.parseInt(a.number) - Number.parseInt(b.number))

  const handleAddPlayer = () => {
    const newPlayer: Player = { number: "", name: "" }
    setPlayers([...players, newPlayer])
  }

  const handleRemovePlayer = (index: number) => {
    const updated = players.filter((_, i) => i !== index)
    setPlayers(updated)
    onUpdatePlayers(sortByNumber(updated.filter((p) => p.number && p.name)))
  }

  const handleUpdatePlayer = (index: number, field: "number" | "name", value: string) => {
    const updated = [...players]
    updated[index] = { ...updated[index], [field]: value }
    setPlayers(updated)
    onUpdatePlayers(sortByNumber(updated.filter((p) => p.number && p.name)))
  }

  if (!team) {
    return (
      <Card className="bg-card border-border">
        <CardContent className="p-6 text-center text-muted-foreground">Team data not available</CardContent>
      </Card>
    )
  }

  return (
    <Card className="bg-card border-border">
      <CardHeader className="pb-3">
        <div className="flex items-center gap-3">
          {team.icon ? (
            <div className="w-10 h-10 rounded-full bg-white p-0.5 flex items-center justify-center">
              <Image
                src={team.icon || "/placeholder.svg"}
                alt={team.name}
                width={36}
                height={36}
                className="rounded-full object-contain"
              />
            </div>
          ) : (
            <div className="w-10 h-10 rounded-full bg-primary/20 flex items-center justify-center">
              <Users className="w-5 h-5 text-primary" />
            </div>
          )}
          <CardTitle className="text-base">{team.name}</CardTitle>
          <span className="text-xs text-muted-foreground ml-auto">{label}</span>
        </div>
      </CardHeader>
      <CardContent className="space-y-2">
        {players.map((player, index) => (
          <div key={index} className="flex items-center gap-2">
            <Input
              placeholder="#"
              value={player.number}
              onChange={(e) => handleUpdatePlayer(index, "number", e.target.value)}
              className="w-16 bg-input border-border text-foreground text-center"
            />
            <Input
              placeholder="Player name"
              value={player.name}
              onChange={(e) => handleUpdatePlayer(index, "name", e.target.value)}
              className="flex-1 bg-input border-border text-foreground"
            />
            <Button
              variant="ghost"
              size="icon"
              className="h-9 w-9 text-muted-foreground hover:text-destructive"
              onClick={() => handleRemovePlayer(index)}
            >
              <Trash2 className="w-4 h-4" />
            </Button>
          </div>
        ))}
        <Button variant="outline" size="sm" className="w-full mt-2 bg-transparent" onClick={handleAddPlayer}>
          <Plus className="w-4 h-4 mr-1" />
          Add Player
        </Button>
      </CardContent>
    </Card>
  )
}
