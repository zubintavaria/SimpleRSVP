/** @type {import('jest').Config} */
module.exports = {
  testEnvironment: 'jest-environment-jsdom',
  testMatch: ['**/tests/js/**/*.test.js'],
  setupFiles: ['./tests/js/setup.js'],
};
