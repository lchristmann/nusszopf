// REFERENCE-CAPTURE HARNESS ONLY (Nusszopf 2 finish-line phase P-2): replaces the Auth0 SDK with a
// cookie-driven fake session so the historical pages can be rendered locally for screenshots.
// Cookie `nzfake=<user id>` signs in as that user; the Hasura JWT is minted with the harness key.
import jwt from 'jsonwebtoken'

const KEY = 'nusszopf-historical-reference-harness-secret-key'

const cookieUser = req => {
  const raw = req?.headers?.cookie || ''
  const match = raw.match(/(?:^|;\s*)nzfake=([^;]+)/)
  return match ? decodeURIComponent(match[1]) : null
}

const sessionFor = id => {
  if (!id) return null
  const accessToken = jwt.sign(
    {
      sub: id,
      'https://hasura.io/jwt/claims': {
        'x-hasura-default-role': 'user',
        'x-hasura-allowed-roles': ['user', 'anonymous'],
        'x-hasura-user-id': id,
      },
    },
    KEY,
    { algorithm: 'HS256', expiresIn: '30d' }
  )
  return { user: { sub: id, name: id, nickname: id }, accessToken }
}

const auth0 = {
  getSession: async req => sessionFor(cookieUser(req)),
  getAccessToken: async req => {
    const session = sessionFor(cookieUser(req))
    if (!session) {
      const error = new Error('not logged in')
      error.status = 401
      throw error
    }
    return { accessToken: session.accessToken }
  },
  handleProfile: async (req, res) => {
    const session = sessionFor(cookieUser(req))
    if (!session) return res.status(401).json({ error: 'not_authenticated' })
    return res.status(200).json(session.user)
  },
  handleLogin: async (req, res) => {
    res.writeHead(302, { Location: '/user/projects' })
    res.end()
  },
  handleLogout: async (req, res) => {
    res.writeHead(302, { Location: '/', 'Set-Cookie': 'nzfake=; Path=/; Max-Age=0' })
    res.end()
  },
  handleCallback: async (req, res) => res.status(204).end(),
  withApiAuthRequired: handler => handler,
}

export default auth0
