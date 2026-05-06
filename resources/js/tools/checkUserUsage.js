import fs from 'fs'
import path from 'path'

const root = path.resolve(process.cwd(), 'resources', 'js')
const banned = [
  /window\.user\b/,
  /\/(user)\b/, // calls to '/user' (simple heuristic)
  /currentUser\b/
]

let found = []

function walk(dir) {
  for (const file of fs.readdirSync(dir)) {
    const full = path.join(dir, file)
    const stat = fs.statSync(full)
    if (stat.isDirectory()) walk(full)
    else if (full.endsWith('.js') || full.endsWith('.vue')) {
      const text = fs.readFileSync(full, 'utf8')
      banned.forEach((re) => {
        const m = text.match(re)
        if (m) found.push({ file: full, match: m[0] })
      })
    }
  }
}

if (!fs.existsSync(root)) {
  console.error('No se encontró carpeta resources/js')
  process.exit(0)
}

walk(root)

if (found.length) {
  console.error('Usos prohibidos detectados (usar /me en su lugar):')
  found.forEach(f => console.error(` - ${f.file}: ${f.match}`))
  process.exit(1)
} else {
  console.log('OK — no se detectaron usos directos de user.')
  process.exit(0)
}
