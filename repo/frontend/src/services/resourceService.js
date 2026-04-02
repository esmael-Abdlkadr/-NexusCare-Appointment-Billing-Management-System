import axios from 'axios'

export const listResources = () => axios.get('/resources').then(r => r.data)
