import axios from 'axios'

export const listAuditLogs = params => axios.get('/audit-logs', { params }).then(r => r.data)
