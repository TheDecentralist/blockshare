/**
 * db.js — no-database stand-in (rebuild, Nov 2026)
 * Same exports as the PostgreSQL version, so server.js runs unchanged.
 * Core decoding + saved report links work. People's Policy features
 * (citizen sign-up, support votes, MYCC ledger) are switched off until the
 * real database layer is restored.
 */
const OFF = () => { throw new Error("People's Policy support is not enabled yet."); };

module.exports = {
  testConnection: async () => { console.log('[DB] disabled — running in core decoder mode'); },
  resolveGeography: () => ({}),
  MYCC_RATES: {},
  POSTAL_TO_NEIGHBOURHOOD: {},
  NEIGHBOURHOOD_TO_SUPER_BLOCK: {},
  findCitizenByPhone: async () => null,
  findCitizenByPostal: async () => null,
  createCitizen: async () => OFF(),
  getCitizenById: async () => null,
  saveRecommendations: async () => [],
  getRecommendationsByReport: async () => [],
  getRecommendationById: async () => null,
  recordSupport: async () => OFF(),
  recordShareTriggered: async () => OFF(),
  getPeoplesPolicyFeed: async () => [],
  getNeighbourhoodSummary: async () => [],
  getCitizenSupportHistory: async () => [],
  getCitizenMYCC: async () => ({ balance: 0, entries: [] }),
};
