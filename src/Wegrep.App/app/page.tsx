export default function Home() {
  return (
    <div className="relative h-screen w-screen overflow-hidden">
      <div className="absolute inset-0 bg-cover bg-center" style={{ backgroundImage: "url(/ice-hockey-arena-dark-blue-dramatic-lighting.jpg)" }}>
        <div className="absolute inset-0 bg-gradient-to-b from-black/60 via-black/40 to-black/70" />
      </div>

      <div className="relative z-10 flex h-full flex-col items-center justify-center gap-8 px-4">
        <h1 className="text-balance text-center font-bold text-white text-5xl md:text-7xl drop-shadow-2xl">
          Ice Hockey Game Sheet
        </h1>
        <p className="text-center text-white/90 text-xl md:text-2xl max-w-2xl drop-shadow-lg">
          Professional game reporting for leagues, teams, and officials
        </p>
        <a href="/leagues" className="mt-8 bg-primary hover:bg-primary/90 text-primary-foreground font-semibold text-xl px-12 py-6 rounded-lg shadow-2xl transition-all hover:scale-105 active:scale-95">
          Start
        </a>
      </div>
    </div>
  );
}
