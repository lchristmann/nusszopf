// Seeds the historical app (reference-capture harness, docs/testing/visual-regression.md) with
// tests/Visual/reference-data.json: prints the SQL for its PostgreSQL on stdout and writes the search
// documents straight into its Meilisearch, built exactly like `_parseProjectToDocument` /
// `_parseRequestToDocument` (`webapp/src/utils/functions/search.function.js`). Hasura's event triggers
// are bypassed (session_replication_role = replica), so no webhook bumps `updated_at`.
import { readFileSync } from 'node:fs'

const data = JSON.parse(readFileSync(new URL('../reference-data.json', import.meta.url), 'utf8'))
const meili = process.env.HIST_MEILI_URL ?? 'http://hist-meili:7700'
const meiliKey = process.env.HIST_MEILI_KEY ?? 'meilipassword'

const q = value => (value === null || value === undefined ? 'NULL' : `'${String(value).replaceAll("'", "''")}'`)
const slate = paragraphs => JSON.stringify(paragraphs.map(text => ({ type: 'paragraph', children: [{ text }] })))
const users = Object.fromEntries(data.users.map(user => [user.name, user]))

const sql = ['BEGIN;', "SET session_replication_role = 'replica';"]
for (const user of data.users) {
  sql.push(`INSERT INTO leads (email, name, "hasConfirmed", privacy) VALUES (${q(user.email)}, ${q(user.name)}, false, false);`)
  sql.push(`INSERT INTO users (id, email, name) VALUES (${q(user.historicalId)}, ${q(user.email)}, ${q(user.name)});`)
}
const documents = []
for (const project of data.projects) {
  const owner = users[project.user]
  const description = project.description.join('\n')
  const team = project.team.join('\n')
  sql.push(`INSERT INTO projects (id, title, goal, description, "descriptionTemplate", location, period, team, "teamTemplate", motto, visibility, contact, user_id, created_at, updated_at) VALUES (${[
    project.id, project.title, project.goal, description, slate(project.description), JSON.stringify(project.location),
    JSON.stringify(project.period),
  ].map(q).join(', ')}, ${team ? q(team) : 'NULL'}, ${team ? q(slate(project.team)) : 'NULL'}, ${project.motto ? q(project.motto) : 'NULL'}, ${q(project.visibility)}, ${q(project.contact)}, ${q(owner.historicalId)}, ${q(data.timestamp)}, ${q(data.timestamp)});`)
  sql.push(`INSERT INTO projects_analytics (project_id, views) VALUES (${q(project.id)}, ${project.views});`)
  project.requests.forEach((request, index) => {
    const at = new Date(new Date(data.timestamp).valueOf() + index * 60000).toISOString()
    sql.push(`INSERT INTO requests (id, title, description, "descriptionTemplate", category, project_id, created_at, updated_at) VALUES (${[
      request.id, request.title, request.description.join('\n'), slate(request.description), request.category, project.id, at, at,
    ].map(q).join(', ')});`)
  })

  if (project.visibility !== 'public') continue
  const base = {
    pro_title: project.title,
    pro_goal: project.goal,
    pro_description: description,
    pro_location_text: project.location.remote ? '' : project.location.searchTerm,
    pro_team: team || null,
    pro_motto: project.motto || null,
    pro_author: owner.name,
    pro_period_flexible: project.period.flexible,
    pro_period_from: project.period.flexible ? '' : new Date(project.period.from).valueOf(),
    pro_period_to: project.period.flexible ? '' : new Date(project.period.to).valueOf(),
    pro_location_remote: project.location.remote,
    pro_location_geo: project.location.remote ? {} : project.location.data.geo,
    updated_at: new Date(data.timestamp).valueOf(),
  }
  if (project.requests.length === 0) {
    documents.push({ ...base, req_type: 'none', itemsId: project.id, groupId: project.id })
  }
  for (const request of project.requests) {
    documents.push({
      req_title: request.title,
      req_description: request.description.join('\n'),
      ...base,
      req_type: request.category,
      itemsId: request.id,
      groupId: project.id,
    })
  }
}
sql.push('COMMIT;')
process.stdout.write(sql.join('\n') + '\n')

const call = async (method, path, body) => {
  const response = await fetch(`${meili}${path}`, {
    method,
    headers: { 'X-Meili-API-Key': meiliKey, 'Content-Type': 'application/json' },
    body: body === undefined ? undefined : JSON.stringify(body),
  })
  if (!response.ok && response.status !== 404) throw new Error(`${method} ${path}: ${response.status} ${await response.text()}`)
  return response.status === 204 ? null : response.json()
}
await call('DELETE', '/indexes/items')
await call('POST', '/indexes', { uid: 'items', primaryKey: 'itemsId' })
// Meilisearch v0.19's default ranking rules plus the historical recency tie-break (docs/search/README.md).
const update = await call('POST', '/indexes/items/settings', {
  rankingRules: ['typo', 'words', 'proximity', 'attribute', 'wordsPosition', 'exactness', 'desc(updated_at)'],
  attributesForFaceting: ['req_type'],
})
const added = await call('POST', '/indexes/items/documents', documents)
for (const { updateId } of [update, added]) {
  for (let i = 0; i < 50; i++) {
    const status = await call('GET', `/indexes/items/updates/${updateId}`)
    if (status.status === 'processed') break
    if (status.status === 'failed') throw new Error(JSON.stringify(status))
    await new Promise(resolve => setTimeout(resolve, 200))
  }
}
process.stderr.write(`indexed ${documents.length} documents\n`)
