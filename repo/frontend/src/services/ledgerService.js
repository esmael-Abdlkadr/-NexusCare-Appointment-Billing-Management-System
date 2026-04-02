import axios from 'axios'

export const getLedger = () => axios.get('/ledger').then(r => r.data)
