import { describe, expect, it } from 'vitest'
import { readFileSync } from 'fs'
import { resolve } from 'path'

/**
 * Static guardrail: ensures run.sh does not echo plaintext credentials.
 * Catches regressions where someone adds a password back to startup output.
 */
describe('run.sh credential safety', () => {
  const scriptPath = resolve(__dirname, '../../../../run.sh')
  let script

  try {
    script = readFileSync(scriptPath, 'utf8')
  } catch {
    script = ''
  }

  it('does not contain hardcoded NexusCare passwords', () => {
    expect(script).not.toMatch(/NexusCare\d/i)
  })

  it('does not echo any password literal via echo statement', () => {
    // Match lines that echo a value containing @-sign password patterns (e.g. Admin@Foo1)
    const echoPasswordLines = script
      .split('\n')
      .filter(line => /^\s*echo/.test(line) && /@[A-Z][a-z]+\d/.test(line))
    expect(echoPasswordLines).toHaveLength(0)
  })

  it('does not contain common seeder password prefixes in echo output', () => {
    const dangerousEchoLines = script
      .split('\n')
      .filter(line => /^\s*echo/.test(line) && /Admin@|Staff@|Reviewer@|Banned@|Muted@|Client@/.test(line))
    expect(dangerousEchoLines).toHaveLength(0)
  })
})
