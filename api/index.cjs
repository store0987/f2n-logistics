const serverless = require('serverless-http');
const path = require('path');

// Load the server app
let app;
try {
  // Try to require the backend server
  app = require(path.join(__dirname, '..', 'backend', 'server.js'));
  if (!app || typeof app !== 'function') {
    throw new Error('server.js did not export an Express app');
  }
} catch (error) {
  console.error('Failed to load backend/server.js:', error.message);
  // Fallback: create a minimal Express app that returns error
  const express = require('express');
  app = express();
  app.use((req, res) => {
    res.status(500).json({ error: 'Backend failed to load: ' + error.message });
  });
}

module.exports = serverless(app);
