// localStorage can be missing or throw (private mode, blocked storage); never let that break the app.
const PREFIX = 'wegrep:'

export const storage = {
  get(key: string): string | null {
    try {
      return localStorage.getItem(PREFIX + key)
    } catch {
      return null
    }
  },
  set(key: string, value: string) {
    try {
      localStorage.setItem(PREFIX + key, value)
    } catch {
      /* ignore */
    }
  },
  remove(key: string) {
    try {
      localStorage.removeItem(PREFIX + key)
    } catch {
      /* ignore */
    }
  },
  /** All stored keys (without the prefix) that start with the given text. */
  keys(prefix: string): string[] {
    try {
      const found: string[] = []
      for (let i = 0; i < localStorage.length; i++) {
        const k = localStorage.key(i)
        if (k?.startsWith(PREFIX + prefix)) found.push(k.slice(PREFIX.length))
      }
      return found
    } catch {
      return []
    }
  },
  getJson<T>(key: string): T | null {
    const raw = this.get(key)
    if (!raw) return null
    try {
      return JSON.parse(raw) as T
    } catch {
      return null
    }
  },
  setJson(key: string, value: unknown) {
    this.set(key, JSON.stringify(value))
  },
}
