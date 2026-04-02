import { expect, test } from '@playwright/test'
import { apiGet, apiPost, apiToken } from '../helpers/api'
import { loginAsReviewer, loginAsStaff, logout } from '../helpers/auth'

const BASE = 'http://localhost:80/api'

/**
 * Seeds a confirmed appointment and transitions it to no_show.
 * With AppointmentService now wired to FeeService, this creates
 * a pending no-show fee assessment automatically.
 * Returns the fee assessment id (or null on failure).
 */
const seedPendingFeeAssessment = async (request): Promise<number | null> => {
  const adminToken = await apiToken(request, 'admin', 'Admin@NexusCare1')
  const staffToken = await apiToken(request, 'staff1', 'Staff@NexusCare1')

  const clients = await apiGet(request, staffToken, '/users/search', { per_page: 1 })
  const providers = await apiGet(request, staffToken, '/users/search', { role: 'staff', per_page: 1 })
  const resources = await apiGet(request, staffToken, '/resources')

  const clientId = clients?.data?.[0]?.id
  const provider = providers?.data?.[0]
  const resourceId = resources?.data?.[0]?.id
  if (!clientId || !provider?.id || !resourceId) return null

  const seed = Date.now()
  const start = new Date()
  start.setDate(start.getDate() + 30 + (seed % 90))
  start.setHours(9 + (seed % 7), 0, 0, 0)
  const end = new Date(start)
  end.setHours(start.getHours() + 1, 0, 0, 0)

  const created = await apiPost(request, staffToken, '/appointments', {
    client_id: clientId,
    provider_id: provider.id,
    resource_id: resourceId,
    department_id: provider.department_id || 1,
    service_type: `E2E-FEE-${seed}`,
    start_time: start.toISOString(),
    end_time: end.toISOString(),
  })
  const apptId = created?.data?.appointment?.id
  if (!apptId) return null

  // Confirm (admin only)
  await request.patch(`${BASE}/appointments/${apptId}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'confirmed' },
  })

  // Mark no_show → triggers FeeService.assessNoShowFee() automatically
  await request.patch(`${BASE}/appointments/${apptId}/status`, {
    headers: { Authorization: `Bearer ${adminToken}` },
    data: { status: 'no_show' },
  })

  // Retrieve the resulting fee assessment
  const feesResp = await apiGet(request, staffToken, '/fee-assessments', {
    status: 'pending',
    per_page: 5,
  })
  const fees: { id: number }[] = feesResp?.data?.data ?? []
  return fees[0]?.id ?? null
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
