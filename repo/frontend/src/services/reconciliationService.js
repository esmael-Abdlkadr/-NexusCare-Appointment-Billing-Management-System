import axios from 'axios'

export const listImports = params => axios.get('/reconciliation/imports', { params }).then(r => r.data)
export const importSettlement = formData =>
  axios.post('/reconciliation/import', formData, { headers: { 'Content-Type': 'multipart/form-data' } }).then(r => r.data)
export const listExceptions = params => axios.get('/reconciliation/exceptions', { params }).then(r => r.data)
export const resolveException = (id, payload) => axios.patch(`/reconciliation/exceptions/${id}/resolve`, payload).then(r => r.data)
export const listAnomalies = () => axios.get('/reconciliation/anomalies').then(r => r.data)
export const acknowledgeAnomaly = id => axios.patch(`/reconciliation/anomalies/${id}/acknowledge`).then(r => r.data)
