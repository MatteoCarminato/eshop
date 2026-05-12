const logger = require('../utils/logger');
const config = require('../config/env');
const { delay } = require('../utils/delay');

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 2000;

/**
 * Envia um evento de webhook para o Laravel.
 * Faz até 3 tentativas com backoff em caso de falha.
 *
 * @param {string} event - Nome do evento (ex: 'message_received', 'message_ack').
 * @param {number|string} instanceId - ID da instância no Laravel.
 * @param {Object} data - Dados do evento.
 * @returns {Promise<boolean>} true se enviou com sucesso.
 */
const sendWebhook = async (event, instanceId, data = {}) => {
  if (!config.webhookUrl) {
    logger.debug('Webhook URL não configurada, ignorando evento', { event });
    return false;
  }

  const payload = {
    event,
    instance_id: instanceId,
    data,
    timestamp: new Date().toISOString(),
  };

  for (let attempt = 1; attempt <= MAX_RETRIES; attempt++) {
    try {
      const controller = new AbortController();
      const timeout = setTimeout(() => controller.abort(), 10000); // 10s timeout

      const response = await fetch(config.webhookUrl, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'x-webhook-secret': config.webhookSecret || '',
          'User-Agent': 'WhatsApp-Bot/1.0',
        },
        body: JSON.stringify(payload),
        signal: controller.signal,
      });

      clearTimeout(timeout);

      if (response.ok) {
        logger.debug(`Webhook enviado com sucesso: ${event}`, {
          instanceId,
          attempt,
          status: response.status,
        });
        return true;
      }

      logger.warn(`Webhook retornou status ${response.status}: ${event}`, {
        instanceId,
        attempt,
        status: response.status,
      });
    } catch (error) {
      logger.error(`Erro ao enviar webhook (tentativa ${attempt}/${MAX_RETRIES}): ${event}`, {
        instanceId,
        error: error.message,
      });
    }

    // Retry com backoff exponencial
    if (attempt < MAX_RETRIES) {
      await delay(RETRY_DELAY_MS * attempt);
    }
  }

  logger.error(`Webhook falhou após ${MAX_RETRIES} tentativas: ${event}`, {
    instanceId,
  });
  return false;
};

/**
 * Notifica que uma mensagem foi recebida.
 */
const notifyMessageReceived = (instanceId, messageData) =>
  sendWebhook('message_received', instanceId, messageData);

/**
 * Notifica que o status de entrega de uma mensagem mudou (ack).
 */
const notifyMessageAck = (instanceId, ackData) =>
  sendWebhook('message_ack', instanceId, ackData);

/**
 * Notifica que uma mensagem foi enviada externamente (pelo celular/WhatsApp Web).
 */
const notifyMessageSentExternal = (instanceId, messageData) =>
  sendWebhook('message_sent_external', instanceId, messageData);

/**
 * Notifica que a instância foi desconectada.
 */
const notifyDisconnected = (instanceId, reason) =>
  sendWebhook('disconnected', instanceId, { reason });

/**
 * Notifica que um QR code foi gerado.
 */
const notifyQrGenerated = (instanceId) =>
  sendWebhook('qr_generated', instanceId, {});

/**
 * Notifica que o status da instância mudou.
 */
const notifyStatusChanged = (instanceId, status) =>
  sendWebhook('status_changed', instanceId, { status });

module.exports = {
  sendWebhook,
  notifyMessageReceived,
  notifyMessageAck,
  notifyMessageSentExternal,
  notifyDisconnected,
  notifyQrGenerated,
  notifyStatusChanged,
};
