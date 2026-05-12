const config = require('../config/env');
const { randomDelay, delay } = require('../utils/delay');
const logger = require('../utils/logger');

/**
 * Aguarda delay randômico entre mensagens.
 * @returns {Promise<void>}
 */
const waitBetweenMessages = async () => {
  const { minDelayMs, maxDelayMs } = config;
  logger.info(`⏳ Aguardando delay entre ${minDelayMs / 1000}s e ${maxDelayMs / 1000}s...`);
  await randomDelay(minDelayMs, maxDelayMs);
};

/**
 * Aguarda pausa longa entre blocos de mensagens (anti-ban).
 * @returns {Promise<void>}
 */
const waitLongPause = async () => {
  const { pauseDurationMs } = config;
  logger.info(`🛑 Pausa longa de ${pauseDurationMs / 1000 / 60} minutos (anti-ban)...`);
  await delay(pauseDurationMs);
};

/**
 * Verifica se é necessário pausar com base no número de mensagens enviadas.
 * @param {number} sentCount - Número de mensagens já enviadas.
 * @returns {boolean} True se deve pausar.
 */
const shouldPause = (sentCount) => sentCount > 0 && sentCount % config.pauseAfterMessages === 0;

/**
 * Verifica se atingiu o limite máximo de mensagens por sessão.
 * @param {number} sentCount - Número de mensagens já enviadas.
 * @returns {boolean} True se atingiu o limite.
 */
const hasReachedLimit = (sentCount) => sentCount >= config.maxMessagesPerSession;

module.exports = {
  waitBetweenMessages,
  waitLongPause,
  shouldPause,
  hasReachedLimit,
};
