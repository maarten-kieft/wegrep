"use client"

import { useState, useEffect, useRef } from "react"
import { Button } from "@/components/ui/button"
import { Play, Pause, RotateCcw } from "lucide-react"

interface GameTimerProps {
  period: 1 | 2 | 3 | "OT"
  onTimeUpdate: (time: string) => void
  currentTime: string
}

export function GameTimer({ period, onTimeUpdate, currentTime }: GameTimerProps) {
  const periodStartSeconds = period === "OT" ? 60 * 60 : (period - 1) * 20 * 60

  const parseTimeToSeconds = (time: string): number => {
    const [mins, secs] = time.split(":").map(Number)
    return (mins || 0) * 60 + (secs || 0)
  }

  const formatTime = (totalSeconds: number): string => {
    const mins = Math.floor(totalSeconds / 60)
    const secs = totalSeconds % 60
    return `${mins.toString().padStart(2, "0")}:${secs.toString().padStart(2, "0")}`
  }

  const [seconds, setSeconds] = useState(() => {
    const current = parseTimeToSeconds(currentTime)
    return current > 0 ? current : periodStartSeconds
  })
  const [isRunning, setIsRunning] = useState(false)

  const prevPeriodRef = useRef(period)
  const isInitialMount = useRef(true)

  useEffect(() => {
    if (isInitialMount.current) {
      isInitialMount.current = false
      return
    }

    if (prevPeriodRef.current !== period && !isRunning) {
      setSeconds(periodStartSeconds)
      setTimeout(() => {
        onTimeUpdate(formatTime(periodStartSeconds))
      }, 0)
    }
    prevPeriodRef.current = period
  }, [period, periodStartSeconds, isRunning, onTimeUpdate])

  useEffect(() => {
    let interval: NodeJS.Timeout | null = null

    if (isRunning) {
      interval = setInterval(() => {
        setSeconds((prev) => {
          const newSeconds = prev + 1
          onTimeUpdate(formatTime(newSeconds))
          return newSeconds
        })
      }, 1000)
    }

    return () => {
      if (interval) clearInterval(interval)
    }
  }, [isRunning, onTimeUpdate])

  const handleReset = () => {
    setSeconds(periodStartSeconds)
    onTimeUpdate(formatTime(periodStartSeconds))
    setIsRunning(false)
  }

  const handleManualAdjust = (adjustment: number) => {
    const newSeconds = Math.max(periodStartSeconds, seconds + adjustment)
    setSeconds(newSeconds)
    onTimeUpdate(formatTime(newSeconds))
  }

  return (
    <div className="bg-card/80 border border-border rounded-lg p-3">
      <div className="flex items-center justify-between gap-2">
        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-9 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(-60)}
          >
            -60
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-9 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(-10)}
          >
            -10
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-8 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(-1)}
          >
            -1
          </Button>
        </div>

        <div className="text-center">
          <div className="text-3xl font-mono font-bold text-foreground tracking-wider">{formatTime(seconds)}</div>
          <div className="text-xs text-muted-foreground">{period === "OT" ? "Overtime" : `Period ${period}`}</div>
        </div>

        <div className="flex items-center gap-1">
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-8 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(1)}
          >
            +1
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-9 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(10)}
          >
            +10
          </Button>
          <Button
            variant="outline"
            size="sm"
            className="h-8 w-9 p-0 text-xs bg-transparent"
            onClick={() => handleManualAdjust(60)}
          >
            +60
          </Button>
        </div>
      </div>

      <div className="flex items-center justify-center gap-2 mt-3">
        <Button variant="outline" size="sm" className="h-9 bg-transparent" onClick={handleReset}>
          <RotateCcw className="w-4 h-4 mr-1" />
          Reset
        </Button>
        <Button
          size="sm"
          className={`h-9 ${isRunning ? "bg-destructive hover:bg-destructive/90" : "bg-primary hover:bg-primary/90"}`}
          onClick={() => setIsRunning(!isRunning)}
        >
          {isRunning ? (
            <>
              <Pause className="w-4 h-4 mr-1" />
              Stop
            </>
          ) : (
            <>
              <Play className="w-4 h-4 mr-1" />
              Start
            </>
          )}
        </Button>
      </div>
    </div>
  )
}
