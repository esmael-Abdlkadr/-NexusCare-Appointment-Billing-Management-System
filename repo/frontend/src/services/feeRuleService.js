import axios from 'axios'

export const listFeeRules = () =>
  axios.get('/fee-rules').then(r => r.data)

export const saveFeeRule = payload =>
  axios.post('/fee-rules', payload).then(r => r.data)

export const deleteFeeRule = id =>
  axios.delete(`/fee-rules/${id}`).then(r => r.data)
