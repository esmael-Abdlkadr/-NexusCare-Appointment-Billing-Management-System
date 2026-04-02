import axios from 'axios'

export const listPayments = params => axios.get('/payments', { params }).then(r => r.data)
export const postPayment = (payload, config = {}) => axios.post('/payments', payload, config).then(r => r.data)
