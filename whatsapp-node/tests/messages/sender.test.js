const { shouldPause, hasReachedLimit } = require('../../src/messages/scheduler');

// Mock do config
jest.mock('../../src/config/env', () => ({
  minDelayMs: 100,
  maxDelayMs: 200,
  maxMessagesPerSession: 100,
  pauseAfterMessages: 20,
  pauseDurationMs: 1000,
}));

// Mock do logger
jest.mock('../../src/utils/logger', () => ({
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
}));

describe('shouldPause', () => {
  it('deve retornar true a cada 20 mensagens', () => {
    expect(shouldPause(20)).toBe(true);
    expect(shouldPause(40)).toBe(true);
    expect(shouldPause(60)).toBe(true);
  });

  it('deve retornar false entre os intervalos', () => {
    expect(shouldPause(1)).toBe(false);
    expect(shouldPause(10)).toBe(false);
    expect(shouldPause(19)).toBe(false);
  });

  it('deve retornar false para 0 (nenhuma mensagem enviada)', () => {
    // sentCount > 0 é checado no fluxo do sender, 0 nunca pausa
    expect(shouldPause(0)).toBe(false);
  });
});

describe('hasReachedLimit', () => {
  it('deve retornar true quando atingir o limite', () => {
    expect(hasReachedLimit(100)).toBe(true);
    expect(hasReachedLimit(150)).toBe(true);
  });

  it('deve retornar false abaixo do limite', () => {
    expect(hasReachedLimit(0)).toBe(false);
    expect(hasReachedLimit(99)).toBe(false);
  });
});
