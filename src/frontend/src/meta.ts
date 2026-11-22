import { ref } from 'vue'
import { api } from './api'
import { storage } from './storage'
import type { Meta } from './types'

/** Server settings (penalty codes, upload on/off), loaded at startup and kept for offline starts. */
export const meta = ref<Meta>({ uploadEnabled: false, penaltyTypes: [] })

export async function loadMeta() {
  const cached = storage.getJson<Meta>('meta')
  if (cached) {
    // Start at once with what we had; the update comes in the background.
    meta.value = cached
    api.meta().then(save).catch(() => { /* offline is fine */ })
    return
  }
  save(await api.meta())
}

function save(fresh: Meta) {
  meta.value = fresh
  storage.setJson('meta', fresh)
}
