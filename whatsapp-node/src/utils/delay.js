/**
 * Cria um delay (Promise) por um tempo especificado em milissegundos.
 * @param {number} ms - Tempo em milissegundos.
 * @returns {Promise<void>}
 */
const delay = (ms) =>
  new Promise((resolve) => {
    setTimeout(resolve, ms);
  });

/**
 * Gera um delay randômico entre min e max milissegundos.
 * Útil para simular comportamento humano no envio.
 * @param {number} minMs - Delay mínimo em ms.
 * @param {number} maxMs - Delay máximo em ms.
 * @returns {Promise<void>}
 */
const randomDelay = (minMs, maxMs) => {
  const time = Math.floor(minMs + Math.random() * (maxMs - minMs));
  return delay(time);
};

module.exports = { delay, randomDelay };
