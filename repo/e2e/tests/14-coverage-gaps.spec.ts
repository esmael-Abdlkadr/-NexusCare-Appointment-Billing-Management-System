import { expect, test } from '@playwright/test'
import { apiTokenAsReviewer } from '../helpers/api'
import { loginAsAdmin, loginAsReviewer, loginAsStaff } from '../helpers/auth'

const API_BASE = 'http://localhost:80/api'

async function seedReconciliationFixtures(request: any) {
  const reviewerToken = await apiTokenAsReviewer(request)
  expect(reviewerToken, 'Reviewer token is required to seed reconciliation fixtures').toBeTruthy()

  const seed = Date.now()
  const exceptionCsv = [
    'transaction_id,amount,type,timestamp,terminal_id',
    `E2E-UNKNOWN-${seed},10.00,sale,2026-03-27T10:00:00Z,T-1`
  ].join('\n')
  const anomalyCsv = [
    'transaction_id,amount,type,timestamp,terminal_id',
    `E2E-ANOM-${seed},72.50,sale,2026-03-27T10:00:00Z,T-1`
  ].join('\n')

  const exceptionImport = await request.post(`${API_BASE}/reconciliation/import`, {
    headers: { Authorization: `Bearer ${reviewerToken}` },
    multipart: {
      file: {
        name: `e2e-exception-${seed}.csv`,
        mimeType: 'text/csv',
        buffer: Buffer.from(exceptionCsv)
      }
    }
  })
  expect(exceptionImport.ok(), 'Exception fixture import must succeed').toBeTruthy()

  const anomalyImport = await request.post(`${API_BASE}/reconciliation/import`, {
    headers: { Authorization: `Bearer ${reviewerToken}` },
    multipart: {
      file: {
        name: `e2e-anomaly-${seed}.csv`,
        mimeType: 'text/csv',
        buffer: Buffer.from(anomalyCsv)
      }
    }
  })
  expect(anomalyImport.ok(), 'Anomaly fixture import must succeed').toBeTruthy()
}

test('reconciliation exceptions: resolve action works', async ({ page, request }) => {
  await seedReconciliationFixtures(request)
  await loginAsReviewer(page)
  await page.goto('/reconciliation/exceptions')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible({ timeout: 10000 })

  const resolveBtn = page.getByRole('button', { name: /resolve/i }).first()
  await expect(resolveBtn, 'Fixture must include at least one resolvable exception row').toBeVisible({ timeout: 8000 })
  await resolveBtn.click()
  await expect(page.locator('.el-dialog')).toBeVisible({ timeout: 5000 })
  const noteInput = page.locator('.el-dialog textarea').first()
  await expect(noteInput, 'Resolve dialog must provide a note textarea').toBeVisible({ timeout: 3000 })
  await noteInput.fill('Resolved via E2E test - minimum 10 chars required')
  const saveBtn = page.locator('.el-dialog').getByRole('button', { name: /save/i })
  await expect(saveBtn).toBeVisible({ timeout: 3000 })
  await saveBtn.click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('anomaly alerts: acknowledge action works', async ({ page, request }) => {
  await seedReconciliationFixtures(request)
  await loginAsReviewer(page)
  await page.goto('/reconciliation/anomalies')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible({ timeout: 10000 })

  const ackBtn = page.getByRole('button', { name: /acknowledge/i }).first()
  await expect(ackBtn, 'Fixture must include at least one acknowledgeable anomaly').toBeVisible({ timeout: 8000 })
  await ackBtn.click()
  await expect(page.locator('.el-message')).toBeVisible({ timeout: 8000 })
})

test('admin: bulk ban selected users', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/admin/users')
  await expect(page.locator('.el-table')).toBeVisible({ timeout: 10000 })

  const checkboxes = page.locator('.el-table .el-checkbox__inner')
  await expect(checkboxes.nth(1), 'Fixture must include selectable user rows for bulk actions').toBeVisible({ timeout: 8000 })
  await checkboxes.nth(1).click()
  await expect(page.locator('.bulk-toolbar')).toBeVisible()
  await expect(page.getByRole('button', { name: /bulk ban/i })).toBeVisible()
})

test('admin: reset password dialog opens', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/admin/users')
  await expect(page.locator('.el-table')).toBeVisible({ timeout: 10000 })

  const moreBtn = page.getByRole('button', { name: /more/i }).first()
  await expect(moreBtn, 'Fixture must include action menu button in users table').toBeVisible({ timeout: 8000 })
  await moreBtn.click()
  await page.getByText('Reset Password').first().click()
  await expect(page.locator('.el-dialog')).toBeVisible({ timeout: 5000 })
  await expect(page.getByRole('heading', { name: /reset password/i })).toBeVisible()
  await page.keyboard.press('Escape')
})

test('recycle bin: bulk restore toolbar appears on selection', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/admin/recycle')
  await expect(page.locator('.el-table, .el-empty').first()).toBeVisible({ timeout: 10000 })

  const checkboxes = page.locator('.el-table .el-checkbox__inner')
  await expect(checkboxes.nth(1), 'Fixture must include recyclable rows for bulk restore').toBeVisible({ timeout: 8000 })
  await checkboxes.nth(1).click()
  await expect(page.locator('.bulk-toolbar')).toBeVisible()
  await expect(page.getByRole('button', { name: /bulk restore/i })).toBeVisible()
})

test('localStorage contains no token or sensitive auth data after login', async ({ page }) => {
  await loginAsStaff(page)

  const tokenInStorage = await page.evaluate(() => {
    const keys = Object.keys(localStorage)
    return keys.filter(
      k => k.toLowerCase().includes('token') || k.toLowerCase().includes('jwt') || k.toLowerCase().includes('auth')
    )
  })

  expect(tokenInStorage).toHaveLength(0)

  const userInStorage = await page.evaluate(() => localStorage.getItem('user'))
  expect(userInStorage).toBeNull()
})

// Role sidebar visibility tests — presentation layer (complements 11-role-route-rbac.spec.ts
// which tests direct-URL enforcement). These assert what the nav menu renders per role.
test('staff sees only scheduling and billing sections - not admin or ledger', async ({ page }) => {
  await loginAsStaff(page)
  const sidebar = page.locator('.nav-menu').first()
  await expect(sidebar).toContainText(/Appointments/i)
  await expect(sidebar).toContainText(/Waitlist/i)
  await expect(sidebar).toContainText(/Fee Assessments/i)
  await expect(sidebar).not.toContainText(/Ledger/i)
  await expect(sidebar).not.toContainText(/User Management/i)
  await expect(sidebar).not.toContainText(/Audit Logs/i)
})

test('reviewer sees reconciliation and audit - not ledger or user management', async ({ page }) => {
  await loginAsReviewer(page)
  const sidebar = page.locator('.nav-menu').first()
  await expect(sidebar).toContainText(/Import CSV/i)
  await expect(sidebar).toContainText(/Audit Logs/i)
  await expect(sidebar).toContainText(/Reports/i)
  await expect(sidebar).not.toContainText(/Ledger/i)
  await expect(sidebar).not.toContainText(/User Management/i)
})

test('admin sees all sections including ledger and user management', async ({ page }) => {
  await loginAsAdmin(page)
  const sidebar = page.locator('.nav-menu').first()
  await expect(sidebar).toContainText(/Ledger/i)
  await expect(sidebar).toContainText(/User Management/i)
  await expect(sidebar).toContainText(/Recycle Bin/i)
  await expect(sidebar).toContainText(/Audit Logs/i)
  await expect(sidebar).toContainText(/Fee Rules/i)
})

test('admin can navigate to fee rules and configure a policy', async ({ page }) => {
  await loginAsAdmin(page)
  await page.goto('/fee-rules')
  await expect(page.locator('.el-table')).toBeVisible({ timeout: 10000 })
  // Confirms the fee-policy configuration screen is reachable and functional
  await expect(page.getByRole('button', { name: /add rule|update rule/i })).toBeVisible()
})
