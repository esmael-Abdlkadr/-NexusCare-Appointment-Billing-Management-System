import axios from 'axios'

export const listFeeAssessments = params => axios.get('/fee-assessments', { params }).then(r => r.data)
export const assessLostDamagedFee = payload => axios.post('/fee-assessments', payload).then(r => r.data)
export const submitWaiver = (id, payload) => axios.post(`/fee-assessments/${id}/waiver`, payload).then(r => r.data)
