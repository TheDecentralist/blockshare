/**
 * data-fetcher.js — placeholder (rebuild, Nov 2026)
 * The original live-data fetcher was lost with the old server. server.js treats
 * this module as optional enrichment, so this returns an empty context and the
 * decoder runs on its built-in benchmarks (see DEFAULT_SYSTEM in server.js).
 */
async function fetchDataContext(jurisdiction, govLevel) {
  return {
    fetch_status: { live: 0, static: 0 },
    indicators: null,
    benchmarks: null,
    summary_text: '',
  };
}
module.exports = { fetchDataContext };
