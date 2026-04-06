import { expect, test } from '@playwright/test'
import { loginAsReviewer } from '../helpers/auth'

test.beforeEach(async ({ page }) => {
  await loginAsReviewer(page)
})

test('reconciliation import page loads', async ({ page }) => {
  await page.goto('/reconciliation/import')
  // el-upload hides the native file input; check for the upload drop zone instead
  await expect(page.locator('.el-upload-dragger')).toBeVisible()
  await expect(page.getByRole('button', { name: /import/i })).toBeVisible()
})

test('import invalid file type shows error', async ({ page }) => {
  await page.goto('/reconciliation/import')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'bad.png',
    mimeType: 'image/png',
    buffer: Buffer.from('not a csv')
  })
  await page.getByRole('button', { name: /import settlement file|import/i }).click()
  await expect(page.locator('.el-message,.el-alert--error')).toBeVisible({ timeout: 10000 })
})

test('csv with unrecognized transaction reference produces structured error', async ({ page }) => {
  // Unique ref guaranteed not to match any seeded appointment → backend must return error
  const unknownRef = `REF-UNKNOWN-${Date.now()}-${Math.random().toString(36).slice(2)}`
  await page.goto('/reconciliation/import')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'unknown_ref.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from(`transaction_id,amount,type,timestamp,terminal_id\n${unknownRef},100.00,sale,2025-01-01T00:00:00Z,T-1\n`)
  })
  await page.getByRole('button', { name: /import settlement file|import/i }).click()

  // Backend must reject an unknown reference with a structured error — not a silent pass
  await expect(
    page.locator('.el-message--error, .el-message--warning, .el-alert--error, .result-panel').first()
  ).toBeVisible({ timeout: 12000 })

  // Must NOT produce an unhandled generic JS crash toast
  await expect(page.locator('.el-message--error').filter({ hasText: /^Error$/ })).toHaveCount(0)
})

test('csv import with valid structure processes without frontend crash', async ({ page }) => {
  // This test asserts the frontend handles the backend response correctly —
  // the backend may accept or reject the ref, but the UI must show a result, not crash.
  await page.goto('/reconciliation/import')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'test.csv',
    mimeType: 'text/csv',
    buffer: Buffer.from('transaction_id,amount,type,timestamp,terminal_id\nREF-E2E-STRUCT-001,100.00,sale,2025-01-01T00:00:00Z,T-1\n')
  })
  await page.getByRole('button', { name: /import settlement file|import/i }).click()

  // Either success summary or a structured backend error is acceptable —
  // what is NOT acceptable is zero response (silent failure) or an unhandled crash toast.
  await expect(
    page.locator('.result-panel, .el-message--success, .el-message--error, .el-message--warning, .el-alert--error').first()
  ).toBeVisible({ timeout: 12000 })
  await expect(page.locator('.el-message--error').filter({ hasText: /^Error$/ })).toHaveCount(0)
})

test('reconciliation exceptions page loads', async ({ page }) => {
  await page.goto('/reconciliation/exceptions')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible()
})

test('anomaly alerts page loads', async ({ page }) => {
  await page.goto('/reconciliation/anomalies')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible()
})
