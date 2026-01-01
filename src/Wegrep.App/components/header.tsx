"use client"

interface HeaderProps {
  variant: "large" | "small"

}

export function Header({ variant }: HeaderProps) {
  return (
    <div className="relative h-56 overflow-hidden">
      <div
        className="absolute inset-0 bg-cover bg-center"
        style={{ backgroundImage: "url('/ice-hockey-arena-dark-blue-dramatic-lighting.jpg')" }}
      />
      <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-background" />

      <div className="relative z-10 flex flex-col items-center justify-center h-full px-4 text-center">
        <div className="flex items-center gap-3 mb-2">
          <svg
            className="w-10 h-10 text-primary"
            viewBox="0 0 24 24"
            fill="none"
            stroke="currentColor"
            strokeWidth="2"
          >
            <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z" />
            <path d="M8 14s1.5 2 4 2 4-2 4-2" />
            <path d="M9 9h.01M15 9h.01" />
            <circle cx="12" cy="12" r="8" />
            <path d="M2 12h4M18 12h4" strokeLinecap="round" />
          </svg>
          <h1 className="text-3xl font-bold text-white tracking-tight">Hockey Sheet</h1>
        </div>
        <p className="text-blue-200 text-sm">Game reporting made simple</p>
      </div>
    </div>
  )
}


