/**
 * Policy Decoder Machine — API Proxy Server v2.4
 * Block Share / Citizens Dashboard — blockshare.ca
 * Adds: People's Policy routes, citizen onboarding, support voting, MYCC ledger
 */

require('dotenv').config();

const http    = require('http');
const https   = require('https');
const fs      = require('fs');
const path    = require('path');
const crypto  = require('crypto');
const { fetchDataContext } = require('./data-fetcher');
const db = require('./db');

const PORT           = process.env.PORT || 3131;
const API_KEY        = process.env.ANTHROPIC_API_KEY;
const ALLOWED_ORIGIN = process.env.ALLOWED_ORIGIN || '*';
const MODEL          = process.env.MODEL || 'claude-sonnet-4-5';
const REPORTS_DIR    = path.join(__dirname, 'reports');
const REPORT_TTL_DAYS = 90;

if (!API_KEY) { console.error('ERROR: ANTHROPIC_API_KEY not set'); process.exit(1); }

// ── REPORTS DIRECTORY ──
if (!fs.existsSync(REPORTS_DIR)) fs.mkdirSync(REPORTS_DIR, { recursive: true });

// ── REPORT HELPERS ──
function generateReportId() { return 'pd_' + crypto.randomBytes(5).toString('hex'); }
function saveReport(id, payload) { fs.writeFileSync(path.join(REPORTS_DIR, `${id}.json`), JSON.stringify(payload)); }
function loadReport(id) {
  if (!/^pd_[a-f0-9]{10}$/.test(id)) return null;
  const fp = path.join(REPORTS_DIR, `${id}.json`);
  if (!fs.existsSync(fp)) return null;
  try { return JSON.parse(fs.readFileSync(fp, 'utf8')); } catch (_) { return null; }
}

// ── PRUNE OLD REPORTS ──
function pruneOldReports() {
  const cutoff = Date.now() - (REPORT_TTL_DAYS * 86400000);
  let pruned = 0;
  try {
    fs.readdirSync(REPORTS_DIR).forEach(f => {
      if (!f.endsWith('.json')) return;
      const fp = path.join(REPORTS_DIR, f);
      if (fs.statSync(fp).mtimeMs < cutoff) { fs.unlinkSync(fp); pruned++; }
    });
    if (pruned > 0) console.log(`[Reports] Pruned ${pruned} expired reports`);
  } catch (e) { console.warn(`[Reports] Prune error: ${e.message}`); }
}
pruneOldReports();
setInterval(pruneOldReports, 86400000);

// ── STATIC FILES ──
const MIME = { '.html':'text/html', '.js':'application/javascript', '.css':'text/css',
               '.json':'application/json', '.ico':'image/x-icon', '.png':'image/png', '.svg':'image/svg+xml' };

function serveStatic(res, filePath) {
  fs.readFile(filePath, (err, data) => {
    if (err) { res.writeHead(404, {'Content-Type':'text/plain'}); res.end('Not found'); return; }
    res.writeHead(200, { 'Content-Type': MIME[path.extname(filePath)] || 'application/octet-stream', 'Cache-Control': 'no-cache' });
    res.end(data);
  });
}

function setCORS(res) {
  res.setHeader('Access-Control-Allow-Origin', ALLOWED_ORIGIN);
  res.setHeader('Access-Control-Allow-Methods', 'POST, GET, OPTIONS');
  res.setHeader('Access-Control-Allow-Headers', 'Content-Type');
}

function parseBody(req) {
  return new Promise((resolve, reject) => {
    let body = '';
    req.on('data', chunk => body += chunk);
    req.on('end', () => { try { resolve(JSON.parse(body)); } catch (e) { reject(e); } });
  });
}

function jsonResponse(res, status, data) {
  res.writeHead(status, {'Content-Type':'application/json'});
  res.end(JSON.stringify(data));
}

// ── ANTHROPIC API ──
function callAnthropic(systemPrompt, userMessage) {
  return new Promise((resolve, reject) => {
    const body = JSON.stringify({ model: MODEL, max_tokens: 6000, temperature: 0.2, system: systemPrompt,
      messages: [{ role: 'user', content: userMessage }] });
    const req = https.request({
      hostname: 'api.anthropic.com', path: '/v1/messages', method: 'POST',
      headers: { 'Content-Type':'application/json', 'x-api-key': API_KEY,
                 'anthropic-version':'2023-06-01', 'Content-Length': Buffer.byteLength(body) }
    }, (res) => {
      let data = '';
      res.on('data', chunk => data += chunk);
      res.on('end', () => {
        try {
          const parsed = JSON.parse(data);
          if (parsed.error) return reject(new Error(parsed.error.message));
          resolve(parsed.content?.[0]?.text || '');
        } catch (e) { reject(new Error('Failed to parse Anthropic response')); }
      });
    });
    req.on('error', reject);
    req.write(body);
    req.end();
  });
}

const DEFAULT_SYSTEM = `You are the Policy Decoder analytical engine for the Block Share Citizens Dashboard (blockshare.ca). Apply the Source Weighting Schema v1.0.

SOURCE AUTHORITY HIERARCHY:
T1 (weight ×1.20–1.45): Output-measured third-party data — WHO HALE, OECD housing ratios, UN-Habitat public space, UITP modal share, IMF actual vs. declared spend, Auditor General findings.
T2 (weight ×0.85–1.00): World Bank WGI, Open Budget Survey, Social Progress Index, OECD fiscal data.
T3 (weight ×0.85–1.20): CIHI wait times, PROOF food insecurity, OECD childcare data.
T4 (weight ×0.65–0.90): Voting records, lobbyist filings, campaign finance, city open data.
T5 CALIBRATION (×1.10–1.30): Auditor General findings trigger hard Performativity cap of +20.

LTE INDEX (Lifetime Time Efficiency v3):
Benchmarks: Vancouver = 99.9%, Copenhagen = 63.7%, Singapore = 71.2%.

BIAS GUARD: Discount rhetorical commitment language, deficit-hawk vocabulary, incumbency-favorable framing.

SCORING RUBRIC — MANDATORY STRUCTURAL CHECKLIST:
Before scoring, evaluate these SEVEN binary facts about the policy text. These facts determine score ranges — they do not vary with interpretation:
C1. FUNDING SOURCE: Does the text identify a specific, quantified funding mechanism (tax, levy, budget line)? YES/NO
C2. ENFORCEMENT: Is there a binding enforcement or penalty mechanism for non-delivery? YES/NO
C3. OUTPUT TARGETS: Are targets outcome-based (cost burden, units occupied, displacement rate) rather than process-based (permits, timelines, approvals)? YES/NO
C4. INDEPENDENT OVERSIGHT: Is a third-party auditor, oversight body, or public dashboard named? YES/NO
C5. PUBLIC VALUE CAPTURE: Does publicly-created value (rezoning windfall, land value uplift) return to the public via tax, equity stake, or covenant? YES/NO
C6. SCALE ADEQUACY: Is the quantified target >=10% of the documented need? YES/NO
C7. REVERSIBILITY PROTECTION: Are public benefits locked in (covenants, trusts, statutory) rather than discretionary? YES/NO

PERFORMATIVITY SCORE ANCHORS (count of YES answers above):
0-1 YES = score -100 to -60 (performative theatre)
2-3 YES = score -59 to -20 (weak structural content)
3-4 YES = score -19 to +19 (mixed)
5-6 YES = score +20 to +59 (structurally significant)
7 YES = score +60 to +100 (transformative)
State the checklist results explicitly in the verdict summary. The score MUST fall within the anchored range for the checklist count.

NARRATIVE-IMPACT GAP DIMENSION ANCHORS (0-10, deterministic criteria):
- accountability_mechanism: 0-2 if C2=NO and C4=NO; 3-5 if one YES; 6-8 if both YES; 9-10 if both YES plus statutory force
- distributional_equity: 0-2 if benefits flow primarily to asset owners; 3-5 if mixed; 6-10 if benefits flow primarily to cost-burdened groups
- measurability_of_outcomes: 0-3 if only process metrics; 4-6 if some outcome metrics unmeasured; 7-10 if outcome metrics with baselines and reporting
- reversibility_risk: 9-10 if private value transfer is permanent and benefits discretionary; 5-8 if partially locked; 0-4 if public benefits are statutory/covenanted
- implementation_pathway: 0-3 if no funding and no delivery agent; 4-6 if partial; 7-10 if funded with named delivery agent

LTE PROJECTION ANCHOR: Start from the jurisdiction's benchmark LTE (Vancouver 99.9). Adjust by domain impact: a policy may move LTE at most ±0.5 points per affected domain unless it includes C5=YES public value capture at scale (then up to ±5 points). Policies that accelerate market supply without price anchoring score NEUTRAL or slightly WORSE than baseline (housing precarity effects), never better.

CONSISTENCY REQUIREMENT: Identical input text MUST produce identical checklist results. All scores derive from the checklist. Do not let rhetorical quality, length, or sophistication of the policy language influence scores — only the structural facts.


CRITICAL OUTPUT REQUIREMENT — JSON SCHEMA LOCK:
You MUST return JSON using EXACTLY these top-level key names — no variations, no additions:
{
  "verdict": { "classification": "structural|performative|mixed", "summary": "string", "doc_type": "string", "jurisdiction": "string", "policy_domain": "string", "checklist": { "C1_funding": "YES|NO", "C2_enforcement": "YES|NO", "C3_output_targets": "YES|NO", "C4_oversight": "YES|NO", "C5_public_value_capture": "YES|NO", "C6_scale_adequacy": "YES|NO", "C7_reversibility": "YES|NO", "yes_count": 0 } },
  "performativity_index": { "score": 0, "interpretation": "string", "oag_cap_applied": false },
  "narrative_impact_gap": { "accountability_mechanism": 0, "distributional_equity": 0, "measurability_of_outcomes": 0, "reversibility_risk": 0, "implementation_pathway": 0, "notes": "string" },
  "fallacies": [ { "type": "string", "severity": "high|medium|low", "description": "string", "example_quote": "string" } ],
  "winners_losers": { "winners": ["string"], "losers": ["string"] },
  "influence_distortion": { "fptp_electoral": 0, "bureaucratic_capture": 0, "donor_lobbyist": 0, "media_framing": 0, "incumbency_advantage": 0, "aggregate_note": "string" },
  "lte_analysis": { "policy_lte_projection": 0, "lte_delta_from_vancouver": 0, "lte_delta_from_copenhagen": 0, "lte_delta_from_singapore": 0, "closest_jurisdiction_match": "vancouver|copenhagen|singapore", "governance_pattern_description": "string", "lte_impacted_domains": ["string"], "structural_recommendations": [ { "icon": "emoji", "text": "string" } ] }
}
Do NOT rename these keys. Do NOT add new top-level keys. Do NOT wrap in another object. The checklist results belong inside verdict.checklist — not as a separate top-level key.

Return ONLY valid JSON — no preamble, no markdown fences.`;

// ── HTTP SERVER ──
const server = http.createServer(async (req, res) => {
  setCORS(res);
  if (req.method === 'OPTIONS') { res.writeHead(204); res.end(); return; }

  const url = req.url.split('?')[0];

  // ── GET /report/:id ──
  const reportMatch = url.match(/^\/report\/(pd_[a-f0-9]{10})$/);
  if (req.method === 'GET' && reportMatch) {
    const reportData = loadReport(reportMatch[1]);
    if (!reportData) {
      res.writeHead(404, {'Content-Type':'text/html'});
      res.end(`<!DOCTYPE html><html><head><meta charset="UTF-8"><title>Report Not Found</title>
        <style>body{font-family:sans-serif;text-align:center;padding:4rem;background:#F5F0E8;color:#1A2744}
        a{color:#C1502E}</style></head><body>
        <h1>Report not found or expired</h1>
        <p>Reports are kept for 90 days.</p>
        <p><a href="https://policy-decoder.blockshare.ca">Run a new analysis →</a></p>
        </body></html>`);
      return;
    }
    const templatePath = path.join(__dirname, 'public', 'report.html');
    fs.readFile(templatePath, 'utf8', (err, template) => {
      if (err) { res.writeHead(500); res.end('Report template missing'); return; }
      const html = template.replace('/*REPORT_DATA_INJECT*/', `window.REPORT_DATA = ${JSON.stringify(reportData)};`);
      res.writeHead(200, {'Content-Type':'text/html','Cache-Control':'no-cache'});
      res.end(html);
    });
    return;
  }

  // ── GET /peoples-policy ──
  if (req.method === 'GET' && url === '/peoples-policy') {
    const templatePath = path.join(__dirname, 'public', 'peoples-policy.html');
    fs.readFile(templatePath, 'utf8', (err, data) => {
      if (err) { res.writeHead(404); res.end('Page not found'); return; }
      res.writeHead(200, {'Content-Type':'text/html','Cache-Control':'no-cache'});
      res.end(data);
    });
    return;
  }

  // ── POST /api/decode ──
  if (req.method === 'POST' && url === '/api/decode') {
    try {
      const payload = await parseBody(req);
      const userMessage     = (payload.message || '').slice(0, 8000);
      const systemPrompt    = payload.system || DEFAULT_SYSTEM;
      const jurisdiction    = payload.jurisdiction    || 'City of Vancouver';
      const govLevel        = payload.gov_level       || 'municipal';
      const electoralSource = payload.electoral_source || '';
      const docType         = payload.doc_type        || '';

      if (!userMessage.trim()) { jsonResponse(res, 400, {error:'No message provided'}); return; }

      console.log(`[${new Date().toISOString()}] Decode — ${jurisdiction}`);

      let dataContext = null;
      try {
        dataContext = await fetchDataContext(jurisdiction, govLevel);
        console.log(`[DataFetcher] ${dataContext.fetch_status.live} live, ${dataContext.fetch_status.static} static`);
      } catch (err) { console.warn('[DataFetcher] Failed:', err.message); }

      const augmentedSystem = dataContext ? systemPrompt + '\n\n' + dataContext.summary_text : systemPrompt;
      const result = await callAnthropic(augmentedSystem, userMessage);

      let parsed;
      try { parsed = JSON.parse(result.replace(/```json|```/g, '').trim()); }
      catch (e) { jsonResponse(res, 500, {error:'Analysis returned malformed JSON. Try again.'}); return; }

      // Save report file
      const reportId = generateReportId();
      const reportPayload = {
        id: reportId,
        created_at: new Date().toISOString(),
        expires_at: new Date(Date.now() + REPORT_TTL_DAYS * 86400000).toISOString(),
        meta: { jurisdiction, gov_level: govLevel, electoral_source: electoralSource, doc_type: docType },
        analysis: parsed,
        data_context: dataContext ? { fetch_status: dataContext.fetch_status,
          indicators: dataContext.indicators, benchmarks: dataContext.benchmarks } : null,
      };
      saveReport(reportId, reportPayload);

      // Save Step 7 recommendations to DB
      const recs = parsed?.lte_analysis?.structural_recommendations || [];
      let savedRecs = [];
      if (recs.length > 0) {
        try {
          savedRecs = await db.saveRecommendations(reportId, recs, {
            jurisdiction, gov_level: govLevel, electoral_source: electoralSource,
            policy_domain: parsed?.verdict?.policy_domain || null,
          });
          console.log(`[DB] Saved ${savedRecs.length} recommendations for ${reportId}`);
        } catch (err) { console.warn('[DB] saveRecommendations failed:', err.message); }
      }

      jsonResponse(res, 200, {
        content: JSON.stringify(parsed),
        report_id: reportId,
        report_url: `/report/${reportId}`,
        data_context: reportPayload.data_context,
        saved_recommendations: savedRecs.map(r => ({ db_id: r.id, text: r.text, icon: r.icon })),
      });
    } catch (err) {
      console.error('Decode error:', err.message);
      jsonResponse(res, 500, {error: err.message});
    }
    return;
  }

  // ── POST /api/support ──
  // Body: { recommendation_id, report_id, citizen: { full_name, phone, address, postal_code, consent } }
  if (req.method === 'POST' && url === '/api/support') {
    try {
      const payload = await parseBody(req);
      const { recommendation_id, report_id, citizen: cit, citizen_id: existingCitizenId } = payload;

      if (!recommendation_id) {
        jsonResponse(res, 400, {error:'recommendation_id is required'});
        return;
      }

      // Find or create citizen
      let citizen = null;

      // Shortcut: citizen already identified this session
      if (existingCitizenId) {
        citizen = await db.getCitizenById(existingCitizenId);
      }

      if (!citizen && cit?.phone) citizen = await db.findCitizenByPhone(cit.phone);
      if (!citizen && cit?.postal_code && cit?.full_name) citizen = await db.findCitizenByPostal(cit.postal_code, cit.full_name);
      if (!citizen && cit?.full_name) {
        citizen = await db.createCitizen({
          fullName: cit.full_name, phone: cit.phone || null,
          address: cit.address || null, postalCode: cit.postal_code || null,
          consentGiven: cit.consent || false,
        });
        console.log(`[DB] New citizen created: id=${citizen.id} neighbourhood=${citizen.neighbourhood}`);
      }

      if (!citizen) {
        jsonResponse(res, 400, {error:'Could not identify citizen. Please provide name and phone.'});
        return;
      }

      // Record support
      const { support, duplicate } = await db.recordSupport(
        citizen.id, recommendation_id, report_id, citizen.postal_code
      );

      const mycc = await db.getCitizenMYCC(citizen.id);
      const geo = db.resolveGeography(citizen.postal_code);

      jsonResponse(res, 200, {
        success: true,
        duplicate,
        citizen_id: citizen.id,
        neighbourhood: citizen.neighbourhood,
        super_block: citizen.super_block_id,
        mycc_pending: mycc.mycc_pending,
        mycc_rates: db.MYCC_RATES,
        peoples_policy_url: '/peoples-policy',
        geo,
      });
    } catch (err) {
      console.error('Support error:', err.message);
      jsonResponse(res, 500, {error: err.message});
    }
    return;
  }

  // ── POST /api/support/share ──
  if (req.method === 'POST' && url === '/api/support/share') {
    try {
      const { citizen_id, recommendation_id } = await parseBody(req);
      if (!citizen_id || !recommendation_id) { jsonResponse(res, 400, {error:'citizen_id and recommendation_id required'}); return; }
      await db.recordShareTriggered(citizen_id, recommendation_id);
      const mycc = await db.getCitizenMYCC(citizen_id);
      jsonResponse(res, 200, { success: true, mycc_pending: mycc.mycc_pending });
    } catch (err) { jsonResponse(res, 500, {error: err.message}); }
    return;
  }

  // ── GET /api/peoples-policy ──
  if (req.method === 'GET' && url.startsWith('/api/peoples-policy')) {
    try {
      const qs = new URLSearchParams(req.url.split('?')[1] || '');
      const neighbourhood = qs.get('neighbourhood') || null;
      const superBlock    = qs.get('super_block') || null;
      const jurisdiction  = qs.get('jurisdiction') || null;

      const [feed, summary] = await Promise.all([
        db.getPeoplesPolicyFeed({ neighbourhood, superBlock, jurisdiction }),
        db.getNeighbourhoodSummary(),
      ]);
      jsonResponse(res, 200, { feed, neighbourhood_summary: summary });
    } catch (err) { jsonResponse(res, 500, {error: err.message}); }
    return;
  }

  // ── GET /api/recommendations/:reportId ──
  const recMatch = url.match(/^\/api\/recommendations\/(.+)$/);
  if (req.method === 'GET' && recMatch) {
    try {
      const recs = await db.getRecommendationsByReport(recMatch[1]);
      jsonResponse(res, 200, { recommendations: recs });
    } catch (err) { jsonResponse(res, 500, {error: err.message}); }
    return;
  }

  // ── STATIC FILES ──
  const publicDir = path.join(__dirname, 'public');
  const filePath  = path.join(publicDir, req.url === '/' ? 'index.html' : url);
  if (!filePath.startsWith(publicDir)) { res.writeHead(403); res.end('Forbidden'); return; }
  serveStatic(res, filePath);
});

// ── START ──
db.testConnection().then(() => {
  server.listen(PORT, '127.0.0.1', () => {
    console.log(`Policy Decoder proxy v2.4 running on 127.0.0.1:${PORT}`);
    console.log(`Model: ${MODEL} | Reports: ${REPORTS_DIR}`);
  });
});
