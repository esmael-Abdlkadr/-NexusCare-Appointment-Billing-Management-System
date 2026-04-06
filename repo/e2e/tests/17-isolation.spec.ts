/**
 * Frontend-observable isolation assertions (M-03).
 *
 * Verifies that scoped users (staff on Site 1 vs Site 2) cannot see
 * cross-site records in table views and filter results.
 *
 * Uses seeded fixture users:
 *   staff1  → Site 1, Dept 1
 *   staff2  → Site 2, Dept 2
 *   reviewer1 → Site 1
 *   reviewer2 → Site 2
 */
import { expect, test } from '@playwright/test'
import { loginAs, loginAsStaff, requireEnv } from '../helpers/auth'
import { apiGet, apiPost, apiTokenAsStaff } from '../helpers/api'

test.describe('site isolation — appointments', () => {
  test('staff1 (Site 1) does not see Site 2 provider identifiers in appointments table', async ({ page }) => {
    await loginAs(page, requireEnv('E2E_STAFF_USER'), requireEnv('E2E_STAFF_PASS'))
    await page.goto('/appointments')
    await page.locator('.el-table').waitFor({ timeout: 10000 })

    const tableText = await page.locator('.el-table__body-wrapper').textContent()
    expect(tableText, 'Site 1 appointment table must not contain Site 2 staff identifier').not.toContain('staff2')
    expect(tableText, 'Site 1 appointment table must not contain Site 2 reviewer identifier').not.toContain('reviewer2')
  })

  test('staff2 (Site 2) does not see Site 1 provider identifiers in appointments table', async ({ page }) => {
    const staff2User = process.env.E2E_STAFF2_USER
    const staff2Pass = process.env.E2E_STAFF2_PASS
    if (!staff2User || !staff2Pass) {
      test.skip()
      return
    }
    await loginAs(page, staff2User, staff2Pass)
    await page.goto('/appointments')
    await page.locator('.el-table').waitFor({ timeout: 10000 })

    const tableText = await page.locator('.el-table__body-wrapper').textContent()
    expect(tableText, 'Site 2 appointment table must not contain Site 1 staff identifier').not.toContain('staff1')
    expect(tableText, 'Site 2 appointment table must not contain Site 1 staff3 identifier').not.toContain('staff3')
  })
})

test.describe('site isolation — waitlist', () => {
  test('staff1 (Site 1) waitlist does not show Site 2 entries', async ({ page, request }) => {
    // Seed a waitlist entry as staff1 to ensure the table has data
    const token = await apiTokenAsStaff(request)
    const users = await apiGet(request, token, '/users/search', { per_page: 1 })
    const clientId = users?.data?.[0]?.id
    if (clientId) {
      const start = new Date()
      start.setDate(start.getDate() + 2)
      start.setHours(14, 0, 0, 0)
      const end = new Date(start)
      end.setHours(15, 0, 0, 0)
      await apiPost(request, token, '/waitlist', {
        client_id: clientId,
        service_type: `E2E-ISO-WAITLIST-${Date.now()}`,
        priority: 3,
        preferred_start: start.toISOString(),
        preferred_end: end.toISOString()
      })
    }

    await loginAsStaff(page)
    await page.goto('/waitlist')
    await page.locator('.el-table').waitFor({ timeout: 10000 })

    const tableText = await page.locator('.el-table__body-wrapper').textContent()
    expect(tableText, 'Site 1 waitlist must not contain Site 2 staff identifier').not.toContain('staff2')
    expect(tableText, 'Site 1 waitlist must not contain Site 2 reviewer identifier').not.toContain('reviewer2')
  })
})

test.describe('site isolation — audit logs', () => {
  test('reviewer1 (Site 1) audit logs do not contain Site 2 actor identifiers', async ({ page }) => {
    await loginAs(page, requireEnv('E2E_REVIEWER_USER'), requireEnv('E2E_REVIEWER_PASS'))
    await page.goto('/audit-logs')
    await page.locator('.el-table').waitFor({ timeout: 10000 })

    const tableText = await page.locator('.el-table__body-wrapper').textContent()
    expect(tableText, 'Site 1 audit logs must not contain Site 2 staff').not.toContain('staff2')
    expect(tableText, 'Site 1 audit logs must not contain Site 2 reviewer').not.toContain('reviewer2')
  })
})

test.describe('site isolation — filter options', () => {
  test('staff1 (Site 1) provider filter dropdown does not list Site 2 providers', async ({ page }) => {
    await loginAsStaff(page)
    await page.goto('/appointments/create')
    await expect(page.getByRole('heading', { name: 'Create Appointment' })).toBeVisible({ timeout: 10000 })

    // Open the Provider select dropdown
    const providerField = page.locator('.el-form-item').filter({ hasText: 'Provider' }).first()
    const providerSelect = providerField.locator('.el-select')
    await providerSelect.click({ force: true })

    // Wait for dropdown options to appear
    const options = page.locator('.el-select-dropdown:visible .el-select-dropdown__item')
    await options.first().waitFor({ state: 'visible', timeout: 5000 }).catch(() => {})

    const allOptionsText = await options.allTextContents()
    const flatText = allOptionsText.join(' ')
    expect(flatText, 'Provider dropdown must not list Site 2 staff').not.toContain('staff2')
    expect(flatText, 'Provider dropdown must not list Site 2 reviewer').not.toContain('reviewer2')
  })
})
