require('dotenv').config();

const path = require('path');

const requiredEnvVars = ['API_KEY'];

/**
 * Valida que todas as variáveis de ambiente obrigatórias estão definidas.
 * @throws {Error} Se alguma variável obrigatória estiver faltando.
 */
const validateEnv = () => {
  const missing = requiredEnvVars.filter((key) => !process.env[key]);
  if (missing.length > 0) {
    throw new Error(`Variáveis de ambiente obrigatórias faltando: ${missing.join(', ')}`);
  }
};

validateEnv();

const config = {
  port: parseInt(process.env.PORT, 10) || 3000,
  apiKey: process.env.API_KEY,

  // Webhook para Laravel (Inbox / Tempo Real)
  webhookUrl: process.env.WEBHOOK_URL || '',
  webhookSecret: process.env.WEBHOOK_SECRET || '',
  instanceId: parseInt(process.env.INSTANCE_ID, 10) || 0,
  enableMessageForwarding: process.env.ENABLE_MESSAGE_FORWARDING === 'true',

  // Delays de envio (em ms)
  minDelayMs: parseInt(process.env.MIN_DELAY_MS, 10) || 30000,
  maxDelayMs: parseInt(process.env.MAX_DELAY_MS, 10) || 60000,
  maxMessagesPerSession: parseInt(process.env.MAX_MESSAGES_PER_SESSION, 10) || 100,
  pauseAfterMessages: parseInt(process.env.PAUSE_AFTER_MESSAGES, 10) || 20,
  pauseDurationMs: parseInt(process.env.PAUSE_DURATION_MS, 10) || 300000,

  // Modo de envio: 'csv' | 'whatsapp' | 'label' (por etiqueta do WPP Business)
  sendMode: process.env.SEND_MODE || 'csv',

  // Nome da etiqueta (usado quando sendMode = 'label')
  sendLabel: process.env.SEND_LABEL || '',

  // Caminho do CSV de contatos (usado quando sendMode = 'csv')
  contactsCsvPath: path.resolve(
    process.cwd(),
    process.env.CONTACTS_CSV_PATH || 'data/contacts.csv',
  ),

  // Imagem padrão
  defaultImagePath: path.resolve(
    process.cwd(),
    process.env.DEFAULT_IMAGE_PATH || 'media/oferta.jpg',
  ),

  // Logs
  logLevel: process.env.LOG_LEVEL || 'info',
};

module.exports = config;
