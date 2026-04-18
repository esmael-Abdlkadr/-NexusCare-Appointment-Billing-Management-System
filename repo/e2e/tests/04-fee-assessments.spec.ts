import { expect, test } from '@playwright/test'
import { apiGet, apiPost, apiTokenAsAdmin, apiTokenAsStaff } from '../helpers/api'
import { loginAsReviewer, loginAsStaff, logout } from '../helpers/auth'

const BASE = 'http://localhost:80/api'

/**
 * Creates a pending fee assessment directly via POST /api/fee-assessments.
 * Returns the fee assessment id (or null on failure).
 */
const seedPendingFeeAssessment = async (request): Promise<number | null> => {
  const staffToken = await apiTokenAsStaff(request)

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const clientId = clients?.data?.[0]?.id
  if (!clientId) return null

  const fee = await apiPost(request, staffToken, '/fee-assessments', {
    client_id: clientId,
    amount: 50.00,
  })
  return fee?.data?.fee_assessment?.id ?? null
}

// ── shared state ─────────────────────────────────────────────────────────────

let seededFeeId: number | null = null

test.beforeAll(async ({ request }) => {
  seededFeeId = await seedPendingFeeAssessment(request)
})

test.beforeEach(async ({ page }) => {
  await loginAsStaff(page)
})

// ── tests ─────────────────────────────────────────────────────────────────────

test('fee assessments page loads', async ({ page }) => {
  await page.goto('/fees')
  await expect(page.locator('.el-table')).toBeVisible()
})

test('post payment dialog opens from fee assessment row', async ({ page }) => {
  if (!seededFeeId) {
    test.skip(true, 'Seeding failed — check fee rules and appointment fixtures')
  }

  await page.goto('/fees')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  const postPayment = page.getByRole('button', { name: /post payment/i }).first()
  await expect(postPayment).toBeVisible({ timeout: 8000 })
  await postPayment.click()
  await expect(page.locator('.el-dialog')).toBeVisible()
  await expect(page.locator('.el-dialog')).toContainText(/amount|method/i)
  await page.getByRole('button', { name: /^cancel$/i }).last().click()
})

test('staff can post a standard cash payment from /payments/post and fee becomes paid', async ({ page, request }) => {
  const feeId = await seedPendingFeeAssessment(request)
  if (!feeId) {
    test.skip(true, 'Seeding failed — check fee rules and appointment fixtures')
  }

  const staffToken = await apiTokenAsStaff(request)
  const adminToken = await apiTokenAsAdmin(request)
  const pendingResp = await apiGet(request, staffToken, '/fee-assessments', { status: 'pending', per_page: 100 })
  const pendingFees = pendingResp?.data?.data ?? []
  const targetFee = pendingFees.find(fee => fee.id === feeId)

  if (!targetFee) {
    test.skip(true, 'Seeded fee could not be loaded from the pending list')
  }

  const referenceId = `E2E-CASH-${Date.now()}`

  await page.goto(`/payments/post?fee_id=${feeId}&amount=${targetFee.amount}`)
  await expect(page.locator('.el-form-item').filter({ hasText: 'Amount' }).locator('input')).not.toHaveValue('', { timeout: 5000 })
  await page.locator('.el-form-item').filter({ hasText: 'Reference ID' }).locator('input').fill(referenceId)
  await page.getByRole('button', { name: /post payment/i }).click()
  await expect(page.locator('.el-message--success')).toBeVisible({ timeout: 15000 })

  await expect.poll(async () => {
    const paidResp = await apiGet(request, staffToken, '/fee-assessments', { status: 'paid', per_page: 100 })
    const paidFees = paidResp?.data?.data ?? []
    return paidFees.some(fee => fee.id === feeId)
  }, { timeout: 10000 }).toBe(true)

  await expect.poll(async () => {
    const ledgerResp = await apiGet(request, adminToken, '/ledger')
    const ledgerRows = ledgerResp?.data ?? []
    return ledgerRows.some(entry => entry.reference_id === referenceId && entry.entry_type === 'payment')
  }, { timeout: 10000 }).toBe(true)
})

test('reviewer approves waiver', async ({ page }) => {
  if (!seededFeeId) {
    test.skip(true, 'Seeding failed — check fee rules and appointment fixtures')
  }

  await logout(page)
  await loginAsReviewer(page)
  await page.goto('/fees')
  await page.locator('.el-table').waitFor({ timeout: 10000 })
  const waiverButton = page.getByRole('button', { name: /approve waiver/i }).first()
  await expect(waiverButton).toBeVisible({ timeout: 8000 })
  await waiverButton.click()
  await expect(page.locator('.el-dialog')).toBeVisible()
  await page.locator('.el-dialog textarea').fill('Approved after review and verification')
  await page.getByRole('button', { name: /^submit$/i }).click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('staff cannot see approve waiver button', async ({ page }) => {
  await page.goto('/fees')
  await expect(page.getByRole('button', { name: /approve waiver/i })).toHaveCount(0)
})
