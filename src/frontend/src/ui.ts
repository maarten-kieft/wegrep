// Same variants as the shadcn Button in the Next.js app (components/ui/button.tsx).
const base = 'inline-flex items-center justify-center gap-2 whitespace-nowrap rounded-md text-sm font-medium transition-all disabled:pointer-events-none disabled:opacity-50 [&_svg]:pointer-events-none [&_svg]:shrink-0 shrink-0 outline-none focus-visible:ring-ring/50 focus-visible:ring-[3px] cursor-pointer'

const variants = {
  default: 'bg-primary text-primary-foreground hover:bg-primary/90',
  destructive: 'bg-destructive/60 text-white hover:bg-destructive/90',
  outline: 'border shadow-xs bg-input/30 border-input hover:bg-input/50',
  secondary: 'bg-secondary text-secondary-foreground hover:bg-secondary/80',
  ghost: 'hover:bg-accent/50 hover:text-accent-foreground',
  link: 'text-primary underline-offset-4 hover:underline',
}
const sizes = {
  default: 'h-9 px-4 py-2',
  sm: 'h-8 rounded-md gap-1.5 px-3',
  lg: 'h-10 rounded-md px-6',
  icon: 'size-9',
  'icon-sm': 'size-8',
}

export function btn(variant: keyof typeof variants = 'default', size: keyof typeof sizes = 'default', extra = '') {
  return `${base} ${variants[variant]} ${sizes[size]} ${extra}`
}

export const card = 'bg-card text-card-foreground rounded-xl border border-border shadow-sm'

/** Selected / unselected look of the choice buttons in the modals. */
export function choice(active: boolean) {
  return `p-3 rounded-lg text-sm font-medium transition-colors cursor-pointer ${active
    ? 'bg-primary text-primary-foreground' : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'}`
}

export const asset = (file: string) => `${import.meta.env.BASE_URL}${file}`
