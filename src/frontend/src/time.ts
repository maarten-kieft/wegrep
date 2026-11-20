/** 1512 seconds -> "25:12" */
export function formatTime(sec: number): string {
  const s = Math.max(0, Math.floor(sec))
  return `${String(Math.floor(s / 60)).padStart(2, '0')}:${String(s % 60).padStart(2, '0')}`
}

/** "25:12", "2512" or "25.12" -> 1512 seconds. Returns null when it can't be read. */
export function parseTime(text: string): number | null {
  const t = text.trim()
  let m = t.match(/^(\d{1,3})[:.](\d{1,2})$/)
  if (!m) m = t.match(/^(\d{1,3})(\d{2})$/)
  if (!m) return null
  const sec = Number(m[2])
  if (sec > 59) return null
  return Number(m[1]) * 60 + sec
}
