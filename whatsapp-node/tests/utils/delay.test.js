const { delay, randomDelay } = require('../../src/utils/delay');

describe('delay', () => {
  it('deve resolver após o tempo especificado', async () => {
    const start = Date.now();
    await delay(100);
    const elapsed = Date.now() - start;
    expect(elapsed).toBeGreaterThanOrEqual(90);
  });
});

describe('randomDelay', () => {
  it('deve resolver com delay entre min e max', async () => {
    const start = Date.now();
    await randomDelay(50, 150);
    const elapsed = Date.now() - start;
    expect(elapsed).toBeGreaterThanOrEqual(40);
    expect(elapsed).toBeLessThan(250);
  });
});
