const { Client, LocalAuth } = require('whatsapp-web.js');
const qrcode = require('qrcode-terminal');
const logger = require('../utils/logger');

const MAX_RETRIES = 3;
const RETRY_DELAY_MS = 5000;

const logConnectionSnapshot = (client, label, extra = {}) => {
  logger.info(`📡 WhatsApp snapshot: ${label}`, {
    hasInfo: !!client.info,
    hasPupPage: !!client.pupPage,
    hasPupBrowser: !!client.pupBrowser,
    ...extra,
  });
};

/**
 * Cria e configura o client do WhatsApp Web.
 * Salva a sessão localmente para reconexão automática.
 * @returns {Client} Instância do WhatsApp client.
 */
const createClient = () => {
  const client = new Client({
    authStrategy: new LocalAuth(),
    webVersionCache: {
      type: 'remote',
      remotePath:
        'https://raw.githubusercontent.com/nicoolasaraujo/nicoolasaraujo/main/nicoolasaraujo.json',
    },
    puppeteer: {
      headless: true,
      args: [
        '--no-sandbox',
        '--disable-setuid-sandbox',
        '--disable-dev-shm-usage',
        '--disable-accelerated-2d-canvas',
        '--no-first-run',
        '--disable-gpu',
        '--single-process',
      ],
      timeout: 60000,
    },
  });

  client.on('qr', (qr) => {
    logger.info('QR Code recebido. Escaneie com seu WhatsApp:');
    logger.info('📱 QR gerado e pronto para leitura.', { qrLength: qr ? qr.length : 0 });
    qrcode.generate(qr, { small: true });
  });

  client.on('loading_screen', (percent, message) => {
    logger.info('⏳ WhatsApp carregando', { percent, message });
  });

  client.on('change_state', (state) => {
    logger.info('🔄 Estado do WhatsApp alterado', { state });
  });

  client.on('ready', () => {
    logger.info('✅ WhatsApp client conectado e pronto!');
    logConnectionSnapshot(client, 'ready');
  });

  client.on('authenticated', () => {
    logger.info('🔐 Autenticado com sucesso!');
    logConnectionSnapshot(client, 'authenticated');
  });

  client.on('auth_failure', (msg) => {
    logger.error('❌ Falha na autenticação:', { error: msg });
  });

  client.on('disconnected', (reason) => {
    logger.warn('🔌 Desconectado:', { reason });
  });

  client.on('message_create', (message) => {
    if (message && message.fromMe) {
      logger.info('✉️ Mensagem criada pelo bot', {
        id: message.id ? message.id._serialized : null,
        to: message.to || null,
      });
    }
  });

  return client;
};

/**
 * Inicializa o client com retry automático.
 * @param {Client} client - Instância do WhatsApp client.
 * @param {number} [retries=0] - Tentativa atual.
 * @returns {Promise<void>}
 */
const initializeClient = async (client, retries = 0) => {
  try {
    logger.info(`Inicializando WhatsApp client... (tentativa ${retries + 1}/${MAX_RETRIES})`);
    logConnectionSnapshot(client, 'before-initialize', { retries });
    await client.initialize();
    logConnectionSnapshot(client, 'after-initialize-call', { retries });
  } catch (error) {
    logger.error('Erro ao inicializar WhatsApp client:', { error: error.message });

    if (retries < MAX_RETRIES - 1) {
      logger.info(`🔄 Tentando novamente em ${RETRY_DELAY_MS / 1000}s...`);
      await new Promise((resolve) => {
        setTimeout(resolve, RETRY_DELAY_MS);
      });
      return initializeClient(client, retries + 1);
    }

    logger.error(`❌ Falhou após ${MAX_RETRIES} tentativas.`);
    throw error;
  }
};

module.exports = { createClient, initializeClient };
