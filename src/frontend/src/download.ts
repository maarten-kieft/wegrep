/** "egrep-199824-dordrecht-lions-smoke-eaters-geleen.xml": safe on every file system. */
export function reportFileName(gameId: number, home: string, away: string): string {
  const slug = (s: string) => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase()
    .replace(/[^a-z0-9]+/g, '-').replace(/^-+|-+$/g, '')
  return ['egrep', gameId, slug(home), slug(away)].filter((p) => p !== '').join('-') + '.xml'
}

/** Hand text to the user as a file. */
export function downloadText(fileName: string, text: string) {
  const url = URL.createObjectURL(new Blob([text], { type: 'text/plain;charset=utf-8' }))
  const link = document.createElement('a')
  link.href = url
  link.download = fileName
  document.body.appendChild(link)
  link.click()
  link.remove()
  setTimeout(() => URL.revokeObjectURL(url), 1000)
}
