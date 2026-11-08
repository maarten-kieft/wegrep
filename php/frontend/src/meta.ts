import { ref } from 'vue'
import { api } from './api'
import type { Meta } from './types'

/** Server settings, loaded once at startup. */
export const meta = ref<Meta>({ uploadEnabled: false, passwordRequired: false, penaltyTypes: [] })

export async function loadMeta() {
  meta.value = await api.meta()
}
