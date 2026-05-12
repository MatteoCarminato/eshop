const { createClient, initializeClient } = require('./client/whatsapp');
const { createServer, startServer } = require('./api/server');
const { loadFilteredContacts } = require('./contacts/whatsappLoader');
const { loadContactsFromCSV } = require('./contacts/loader');
const { validateContacts } = require('./contacts/validator');
const { sendBulkMessages } = require('./messages/sender');
const { ofertaPadrao, getTemplateByLabel } = require('./messages/templates');
const { registerMessageListeners } = require('./listeners/messageListener');
const logger = require('./utils/logger');
const config = require('./config/env');

/**
 * Estado global do bot.
 */
const botState = {
  isSending: false,
  shouldStop: false,
  messagesSent: 0,
  lastReport: null,
  pendingSend: null,
};

/**
 * Executa o envio em massa.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {Object} [options] - Opções de envio.
 */
const executeSend = async (client, options = {}) => {
  if (botState.isSending) {
    logger.warn('Envio já em andamento, ignorando...');
    return;
  }

  try {
    botState.isSending = true;
    botState.shouldStop = false;

    let contacts;
    let templateFn = options.templateFn || ofertaPadrao;

    if (config.sendMode === 'csv') {
      // Modo CSV: carrega contatos do arquivo (ideal para testes)
      logger.info(`📄 Modo CSV — carregando de: ${config.contactsCsvPath}`);
      contacts = loadContactsFromCSV(config.contactsCsvPath);
    } else if (config.sendMode === 'label') {
      // Modo Label: carrega contatos de uma etiqueta do WhatsApp Business
      const labelName = options.labelName || config.sendLabel;
      if (!labelName) {
        logger.error('❌ SEND_MODE=label mas nenhuma etiqueta definida (SEND_LABEL ou labelName)');
        return;
      }
      logger.info(`🏷️  Modo Label — carregando contatos da etiqueta: "${labelName}"`);
      contacts = await loadFilteredContacts(client, {
        labelName,
        withName: options.withName !== false,
        excludePhones: options.excludePhones || [],
      });
      // Selecionar template específico da etiqueta (casa/terreno)
      templateFn = options.templateFn || getTemplateByLabel(labelName);
      logger.info(`📝 Template selecionado para etiqueta "${labelName}"`);
    } else {
      // Modo WhatsApp: carrega contatos do WhatsApp conectado
      logger.info('📱 Modo WhatsApp — carregando contatos do WhatsApp...');
      contacts = await loadFilteredContacts(client, {
        savedOnly: options.savedOnly || false,
        withName: options.withName !== false,
        excludePhones: options.excludePhones || [],
      });
    }

    const { valid } = validateContacts(contacts);
    if (valid.length === 0) {
      logger.warn('Nenhum contato válido encontrado!');
      return;
    }

    // Enviar mensagens
    const report = await sendBulkMessages(client, valid, templateFn, {
      imagePath: options.imagePath || config.defaultImagePath,
      extraParams: options.extraParams || {},
    });

    botState.lastReport = report;
    botState.messagesSent += report.sent;

    logger.info('🏁 Envio finalizado!', report);
  } catch (error) {
    logger.error('Erro durante envio em massa:', { error: error.message });
  } finally {
    botState.isSending = false;
  }
};

/**
 * Inicializa todo o sistema.
 */
const main = async () => {
  try {
    logger.info('🤖 Iniciando WhatsApp Bot Terrenos...');

    // 1. Criar e conectar WhatsApp client
    const client = createClient();

    // 2. Registrar listeners de mensagem (webhook forwarding para Laravel)
    registerMessageListeners(client);

    // 3. Subir a API antes de inicializar o client para capturar QR/status
    // desde o primeiro evento emitido pelo whatsapp-web.js.
    const app = createServer(client, botState);
    await startServer(app);

    await initializeClient(client);

    // 4. Quando o client estiver pronto, verificar envios pendentes
    client.on('ready', async () => {
      logger.info('Bot pronto para envio! Use a API ou edite este arquivo para disparar.');

      // Auto-envio (descomente para enviar automaticamente ao conectar)
      // await executeSend(client);
    });

    // 5. Polling para envios via API
    // eslint-disable-next-line no-param-reassign
    setInterval(async () => {
      if (botState.pendingSend && !botState.isSending) {
        const { imagePath, extraParams, savedOnly, withName, excludePhones, labelName } =
          botState.pendingSend;
        botState.pendingSend = null; // eslint-disable-line no-param-reassign
        await executeSend(client, {
          imagePath,
          extraParams,
          savedOnly,
          withName,
          excludePhones,
          labelName,
        });
      }
    }, 5000);

    logger.info('✅ Sistema inicializado com sucesso!');
  } catch (error) {
    logger.error('Erro fatal ao inicializar:', { error: error.message });
    process.exit(1);
  }
};

main();
