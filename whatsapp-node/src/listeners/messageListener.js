const logger = require('../utils/logger');
const config = require('../config/env');
const { cleanPhone } = require('../utils/formatPhone');
const {
  notifyMessageReceived,
  notifyMessageAck,
  notifyMessageSentExternal,
  notifyDisconnected,
  notifyQrGenerated,
  notifyStatusChanged,
} = require('../webhooks/notifier');

/**
 * Determina o tipo de conteúdo da mensagem.
 * @param {import('whatsapp-web.js').Message} msg
 * @returns {string} text, image, audio, video, document, sticker
 */
const getMessageType = (msg) => {
  if (msg.hasMedia) {
    if (msg.type === 'image') return 'image';
    if (msg.type === 'video') return 'video';
    if (msg.type === 'audio' || msg.type === 'ptt') return 'audio';
    if (msg.type === 'document') return 'document';
    if (msg.type === 'sticker') return 'sticker';
    return 'document'; // fallback para mídia desconhecida
  }
  return 'text';
};

/**
 * Extrai a URL de mídia de uma mensagem (se houver).
 * Retorna base64 data URI para o Laravel poder processar.
 * @param {import('whatsapp-web.js').Message} msg
 * @returns {Promise<string|null>}
 */
const extractMediaUrl = async (msg) => {
  if (!msg.hasMedia) return null;

  try {
    const media = await msg.downloadMedia();
    if (media) {
      return `data:${media.mimetype};base64,${media.data}`;
    }
  } catch (error) {
    logger.error('Erro ao baixar mídia da mensagem:', { error: error.message });
  }
  return null;
};

/**
 * Extrai o número de telefone limpo de um chatId.
 * @param {string} chatId - Ex: '5545991325057@c.us'
 * @returns {string} Ex: '5545991325057'
 */
const phoneFromChatId = (chatId) => {
  if (!chatId) return '';
  return cleanPhone(chatId.replace('@c.us', '').replace('@s.whatsapp.net', ''));
};

/**
 * Verifica se uma mensagem deve ser ignorada.
 * Ignora: grupos, status/broadcasts, mensagens vazias.
 * @param {import('whatsapp-web.js').Message} msg
 * @returns {boolean}
 */
const shouldIgnoreMessage = (msg) => {
  const chatId = msg.from || '';

  // Ignorar grupos
  if (chatId.endsWith('@g.us')) return true;

  // Ignorar broadcasts/status
  if (chatId === 'status@broadcast') return true;
  if (chatId.endsWith('@broadcast')) return true;

  // Ignorar mensagens do sistema (notificações de grupo, etc.)
  if (msg.type === 'notification' || msg.type === 'notification_template') return true;
  if (msg.type === 'e2e_notification') return true;
  if (msg.type === 'call_log') return true;

  return false;
};

/**
 * Registra todos os listeners de mensagens no client WhatsApp.
 * Encaminha eventos para o Laravel via webhooks.
 *
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 */
const registerMessageListeners = (client) => {
  if (!config.enableMessageForwarding) {
    logger.info('📭 Message forwarding desabilitado (ENABLE_MESSAGE_FORWARDING=false)');
    return;
  }

  logger.info('📬 Registrando listeners de mensagens para webhook forwarding...');

  /**
   * Listener: client.on('message')
   * Mensagens RECEBIDAS de outros contatos.
   */
  client.on('message', async (msg) => {
    try {
      if (shouldIgnoreMessage(msg)) return;

      const chatId = msg.from;
      const phone = phoneFromChatId(chatId);
      const contact = await msg.getContact();
      const type = getMessageType(msg);

      logger.info(`📩 Mensagem recebida de ${phone}`, {
        type,
        hasMedia: msg.hasMedia,
        messageId: msg.id._serialized,
      });

      // Extrair mídia (se houver) — pode ser pesado, fazer async
      let mediaUrl = null;
      if (msg.hasMedia && type !== 'sticker') {
        mediaUrl = await extractMediaUrl(msg);
      }

      await notifyMessageReceived(config.instanceId, {
        phone,
        chatId,
        text: msg.body || '',
        pushname: contact.pushname || contact.name || null,
        messageId: msg.id._serialized,
        timestamp: msg.timestamp,
        type,
        mediaUrl,
      });
    } catch (error) {
      logger.error('Erro no listener message:', { error: error.message });
    }
  });

  /**
   * Listener: client.on('message_ack')
   * Status de entrega de mensagens ENVIADAS.
   * ack levels: -1=error, 0=pending, 1=sent(server), 2=delivered, 3=read, 4=played
   */
  client.on('message_ack', async (msg, ack) => {
    try {
      // Só nos interessa acks de mensagens que NÓS enviamos
      if (!msg.fromMe) return;

      // Só propagar acks relevantes (sent, delivered, read)
      if (ack < 1 || ack > 3) return;

      const messageId = msg.id._serialized;

      logger.debug(`📨 Message ack: ${messageId} → level ${ack}`);

      await notifyMessageAck(config.instanceId, {
        messageId,
        ack,
      });
    } catch (error) {
      logger.error('Erro no listener message_ack:', { error: error.message });
    }
  });

  /**
   * Listener: client.on('message_create')
   * Captura TODAS as mensagens criadas, incluindo as que NÓS enviamos.
   * Usado para sincronizar mensagens enviadas fora do painel (direto pelo celular).
   */
  client.on('message_create', async (msg) => {
    try {
      // Só nos interessa mensagens que NÓS enviamos (fromMe = true)
      if (!msg.fromMe) return;

      // Ignorar grupos e broadcasts
      const chatId = msg.to || '';
      if (chatId.endsWith('@g.us') || chatId.endsWith('@broadcast')) return;
      if (chatId === 'status@broadcast') return;

      const phone = phoneFromChatId(chatId);
      const type = getMessageType(msg);

      // Extrair mídia (se houver)
      let mediaUrl = null;
      if (msg.hasMedia && type !== 'sticker') {
        mediaUrl = await extractMediaUrl(msg);
      }

      logger.debug(`📤 Mensagem enviada (capturada via message_create): ${phone}`, {
        type,
        messageId: msg.id._serialized,
      });

      await notifyMessageSentExternal(config.instanceId, {
        phone,
        chatId,
        text: msg.body || '',
        pushname: null, // Somos nós que enviamos
        messageId: msg.id._serialized,
        timestamp: msg.timestamp,
        type,
        mediaUrl,
      });
    } catch (error) {
      logger.error('Erro no listener message_create:', { error: error.message });
    }
  });

  /**
   * Listener: client.on('disconnected')
   * Notifica o Laravel quando a conexão cair.
   */
  client.on('disconnected', async (reason) => {
    logger.warn('🔌 Desconectado — notificando Laravel', { reason });
    await notifyDisconnected(config.instanceId, reason);
  });

  /**
   * Listener: client.on('qr')
   * Notifica o Laravel quando um QR code é gerado.
   */
  client.on('qr', async () => {
    logger.info('📸 QR Code gerado — notificando Laravel');
    await notifyQrGenerated(config.instanceId);
  });

  /**
   * Listener: client.on('ready')
   * Notifica o Laravel quando o bot está conectado.
   */
  client.on('ready', async () => {
    logger.info('✅ Bot pronto — notificando Laravel (status: connected)');
    await notifyStatusChanged(config.instanceId, 'connected');
  });

  /**
   * Listener: client.on('authenticated')
   * Notifica o Laravel quando autenticado com sucesso.
   */
  client.on('authenticated', async () => {
    logger.info('🔐 Autenticado — notificando Laravel');
    await notifyStatusChanged(config.instanceId, 'connected');
  });

  logger.info('✅ Listeners de mensagens registrados com sucesso!');
};

module.exports = { registerMessageListeners };
