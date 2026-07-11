const { registerScreenRoutes } = require("./screenRoutes");
const express = require('express');
const config = require('../config/env');
const logger = require('../utils/logger');
const { loadFilteredContacts, getLabels, loadContactsByLabelId } = require('../contacts/whatsappLoader');
const { sendMessageToContact, resolveChatId } = require('../messages/sender');

/**
 * Cria o servidor Express para controle do bot via API REST.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {Object} botState - Estado compartilhado do bot.
 * @returns {express.Application} App Express configurado.
 */
const createServer = (client, botState) => {
  const app = express();
  app.use(express.json({ limit: '20mb' }));
  let isReconnecting = false;

  const ensureConnected = async () => {
    try {
      const state = typeof client.getState === 'function' ? await client.getState() : null;
      const connected = !!client.info && state !== 'UNPAIRED' && state !== 'UNPAIRED_IDLE';

      if (!connected) {
        return {
          ok: false,
          state: state || 'unknown',
          error: 'WhatsApp não está conectado.',
        };
      }

      return { ok: true, state: state || 'CONNECTED' };
    } catch (error) {
      logger.warn('Erro ao consultar estado do WhatsApp antes do envio', { error: error.message });

      if (!client.info) {
        return {
          ok: false,
          state: 'unknown',
          error: 'WhatsApp não está conectado.',
        };
      }

      return { ok: true, state: 'unknown' };
    }
  };

  // Middleware de autenticação por API Key
  const authMiddleware = (req, res, next) => {
    const apiKey = req.headers['x-api-key'];
    if (apiKey !== config.apiKey) {
      return res.status(401).json({ error: 'API Key inválida' });
    }
    next();
  };

  // Health check (sem auth)
  app.get('/health', (_req, res) => {
    res.json({ status: 'ok', timestamp: new Date().toISOString() });
  });

  // Status do bot
  app.get('/api/status', authMiddleware, (_req, res) => {
    const state = client.info ? 'connected' : 'disconnected';
    res.json({
      state,
      sending: botState.isSending || false,
      messagesSent: botState.messagesSent || 0,
      lastReport: botState.lastReport || null,
    });
  });

  // Listar contatos do WhatsApp
  app.get('/api/contacts', authMiddleware, async (req, res) => {
    try {
      const { savedOnly, withName } = req.query;
      const contacts = await loadFilteredContacts(client, {
        savedOnly: savedOnly === 'true',
        withName: withName === 'true',
      });
      res.json({ total: contacts.length, contacts });
    } catch (error) {
      logger.error('Erro ao listar contatos:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar contatos do WhatsApp' });
    }
  });

  // Listar etiquetas do WhatsApp Business
  app.get('/api/labels', authMiddleware, async (_req, res) => {
    try {
      const labels = await getLabels(client);
      res.json({ total: labels.length, labels });
    } catch (error) {
      logger.error('Erro ao listar etiquetas:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar etiquetas do WhatsApp' });
    }
  });

  // Listar contatos de uma etiqueta específica
  app.get('/api/labels/:labelId/contacts', authMiddleware, async (req, res) => {
    try {
      const { labelId } = req.params;
      const contacts = await loadContactsByLabelId(client, labelId);
      res.json({ total: contacts.length, contacts });
    } catch (error) {
      logger.error('Erro ao listar contatos da etiqueta:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar contatos da etiqueta' });
    }
  });

  // Iniciar envio
  app.post('/api/send', authMiddleware, async (req, res) => {
    if (botState.isSending) {
      return res.status(409).json({ error: 'Envio já em andamento' });
    }

    const { template, imagePath, extraParams, savedOnly, withName, excludePhones, labelName } =
      req.body;

    res.json({
      message: 'Envio iniciado',
      template: template || 'ofertaPadrao',
      imagePath: imagePath || config.defaultImagePath,
      labelName: labelName || config.sendLabel || null,
    });

    // O envio real será disparado pelo controller
    // eslint-disable-next-line no-param-reassign
    botState.pendingSend = {
      template,
      imagePath,
      extraParams,
      savedOnly,
      withName,
      excludePhones,
      labelName,
    };
  });

  // Upload de imagem temporária (para campanhas - evita enviar base64 a cada mensagem)
  app.post('/api/upload-image', authMiddleware, async (req, res) => {
    try {
      const { imageBase64, imageMimeType } = req.body;
      if (!imageBase64) {
        return res.status(400).json({ error: 'imageBase64 é obrigatório' });
      }
      const fs = require('fs');
      const path = require('path');
      const ext = (imageMimeType || 'image/jpeg').split('/')[1] || 'jpg';
      const tempFile = path.join(require('os').tmpdir(), `campaign_${Date.now()}.${ext}`);
      fs.writeFileSync(tempFile, Buffer.from(imageBase64, 'base64'));
      logger.info('📸 Imagem temporária salva', { path: tempFile });
      res.json({ success: true, imagePath: tempFile });
    } catch (error) {
      logger.error('Erro ao salvar imagem:', { error: error.message });
      res.status(500).json({ error: 'Erro ao salvar imagem' });
    }
  });

  // Desconectar WhatsApp (logout da sessão)
  app.post('/api/disconnect', authMiddleware, async (req, res) => {
    if (isReconnecting) {
      return res.status(409).json({
        success: false,
        error: 'Reconexão já está em andamento. Aguarde alguns segundos.',
      });
    }

    isReconnecting = true;

    try {
      logger.info('🔌 Solicitação de desconexão recebida');

      try {
        await client.logout();
      } catch (logoutError) {
        logger.warn('Logout retornou erro, seguindo para teardown/reinit:', {
          error: logoutError.message,
        });
      }

      try {
        if (typeof client.destroy === 'function') {
          await client.destroy();
        }
      } catch (destroyError) {
        logger.warn('Falha ao destruir client antes de reinicializar:', {
          error: destroyError.message,
        });
      }

      await new Promise((resolve) => {
        setTimeout(resolve, 1500);
      });

      await client.initialize();
      return res.json({
        success: true,
        message: 'WhatsApp desconectado e reinicializado. Novo QR será gerado em instantes.',
      });
    } catch (error) {
      logger.error('Erro ao desconectar WhatsApp:', { error: error.message });
      return res.status(500).json({ success: false, error: 'Erro ao desconectar: ' + error.message });
    } finally {
      isReconnecting = false;
    }
  });

  // Remover imagem temporária
  app.post('/api/delete-image', authMiddleware, async (req, res) => {
    try {
      const { imagePath } = req.body;
      if (imagePath) {
        const fs = require('fs');
        try { fs.unlinkSync(imagePath); } catch (e) { }
      }
      res.json({ success: true });
    } catch (error) {
      res.status(500).json({ error: 'Erro ao remover imagem' });
    }
  });

  // Enviar mensagem individual (texto + imagem opcional)
  // Aceita imagePath (caminho no servidor) OU imageBase64 (fallback)
  app.post('/api/send-message', authMiddleware, async (req, res) => {
    try {
      const { chatId, phone, message, imagePath: reqImagePath, imageBase64, imageMimeType } = req.body;
      if (!message) {
        return res.status(400).json({ error: 'Mensagem é obrigatória' });
      }
      if (!chatId && !phone) {
        return res.status(400).json({ error: 'chatId ou phone é obrigatório' });
      }

      let imagePath = reqImagePath || null;
      let tempFile = null;

      // Fallback: se mandou base64 em vez de path, salva temporariamente
      if (!imagePath && imageBase64) {
        const fs = require('fs');
        const path = require('path');
        const ext = (imageMimeType || 'image/jpeg').split('/')[1] || 'jpg';
        tempFile = path.join(require('os').tmpdir(), `send_${Date.now()}.${ext}`);
        fs.writeFileSync(tempFile, Buffer.from(imageBase64, 'base64'));
        imagePath = tempFile;
      }

      const result = await sendMessageToContact(client, {
        phone: phone || null,
        chatId: chatId || null,
        message,
        imagePath,
      });

      // Limpa apenas arquivo temporário criado por base64 (não o de campanha)
      if (tempFile) {
        const fs = require('fs');
        try { fs.unlinkSync(tempFile); } catch (e) { }
      }

      if (result.success) {
        res.json({ success: true, phone: result.phone });
      } else {
        res.status(422).json({ success: false, phone: result.phone, error: result.error });
      }
    } catch (error) {
      logger.error('Erro ao enviar mensagem:', { error: error.message });
      res.status(500).json({ error: 'Erro ao enviar mensagem' });
    }
  });

  // Parar envio
  app.post('/api/stop', authMiddleware, (_req, res) => {
    // eslint-disable-next-line no-param-reassign
    botState.shouldStop = true;
    logger.info('🛑 Parada solicitada via API');
    res.json({ message: 'Parada solicitada' });
  });

  // ────────────────────────────────────────────────────────────
  // Endpoints de Chat Individual (Inbox do Painel)
  // ────────────────────────────────────────────────────────────

  /**
   * POST /api/send-text — Envia mensagem de texto individual.
   * Body: { phone, message }
   * Response: { success, messageId }
   */
  app.post('/api/send-text', authMiddleware, async (req, res) => {
    try {
      const { phone, message } = req.body;
      if (!phone || !message) {
        return res.status(400).json({ error: 'phone e message são obrigatórios' });
      }

      const connection = await ensureConnected();
      if (!connection.ok) {
        return res.status(503).json({ success: false, error: connection.error, state: connection.state });
      }

      const { chatId, registered } = await resolveChatId(client, phone, null);
      if (!registered || !chatId) {
        return res.status(422).json({ success: false, error: 'Número não registrado no WhatsApp' });
      }

      const sentMsg = await client.sendMessage(chatId, message);

      logger.info(`📤 Texto enviado para ${phone}`, { chatId, messageId: sentMsg.id._serialized });
      res.json({
        success: true,
        messageId: sentMsg.id._serialized,
        timestamp: sentMsg.timestamp,
      });
    } catch (error) {
      logger.error('Erro ao enviar texto:', { error: error.message });
      res.status(500).json({ success: false, error: error.message });
    }
  });

  /**
   * POST /api/send-media — Envia mídia individual (imagem, doc, vídeo, áudio).
   * Body: { phone, mediaUrl (base64 data URI ou path), mediaType, message? }
   * Response: { success, messageId }
   */
  app.post('/api/send-media', authMiddleware, async (req, res) => {
    try {
      const { phone, mediaUrl, mediaType, message } = req.body;
      if (!phone || !mediaUrl) {
        return res.status(400).json({ error: 'phone e mediaUrl são obrigatórios' });
      }

      const connection = await ensureConnected();
      if (!connection.ok) {
        return res.status(503).json({ success: false, error: connection.error, state: connection.state });
      }

      const { chatId, registered } = await resolveChatId(client, phone, null);
      if (!registered || !chatId) {
        return res.status(422).json({ success: false, error: 'Número não registrado no WhatsApp' });
      }

      const { MessageMedia } = require('whatsapp-web.js');
      let media;

      if (mediaUrl.startsWith('data:')) {
        // Base64 data URI
        const matches = mediaUrl.match(/^data:(.+);base64,(.+)$/);
        if (!matches) {
          return res.status(400).json({ error: 'mediaUrl inválida (formato base64)' });
        }
        media = new MessageMedia(matches[1], matches[2]);
      } else if (mediaUrl.startsWith('http://') || mediaUrl.startsWith('https://')) {
        // URL remota
        media = await MessageMedia.fromUrl(mediaUrl);
      } else {
        // Caminho local
        media = MessageMedia.fromFilePath(mediaUrl);
      }

      const sentMsg = await client.sendMessage(chatId, media, {
        caption: message || '',
      });

      logger.info(`📤 Mídia enviada para ${phone}`, {
        chatId,
        mediaType: mediaType || 'auto',
        messageId: sentMsg.id._serialized,
      });
      res.json({
        success: true,
        messageId: sentMsg.id._serialized,
        timestamp: sentMsg.timestamp,
      });
    } catch (error) {
      logger.error('Erro ao enviar mídia:', { error: error.message });
      res.status(500).json({ success: false, error: error.message });
    }
  });

  /**
   * POST /api/typing — Simula indicador "digitando..." antes de enviar.
   * Body: { phone, duration? } (duration em ms, default 3000)
   */
  app.post('/api/typing', authMiddleware, async (req, res) => {
    try {
      const { phone, duration } = req.body;
      if (!phone) {
        return res.status(400).json({ error: 'phone é obrigatório' });
      }

      const chatId = phone.includes('@') ? phone : `${phone}@c.us`;
      const chat = await client.getChatById(chatId);
      const typingDuration = Math.min(parseInt(duration, 10) || 3000, 10000); // max 10s

      await chat.sendStateTyping();

      // Parar typing após a duração
      setTimeout(async () => {
        try {
          await chat.clearState();
        } catch (e) {
          // Ignorar — pode já ter sido limpo
        }
      }, typingDuration);

      res.json({ success: true, duration: typingDuration });
    } catch (error) {
      logger.error('Erro ao enviar typing:', { error: error.message });
      res.status(500).json({ success: false, error: error.message });
    }
  });

  /**
   * GET /api/chats — Lista conversas recentes do WhatsApp.
   * Query: limit (default 50)
   * Response: array de { chatId, name, lastMessage, timestamp, unreadCount, isGroup }
   */
  app.get('/api/chats', authMiddleware, async (req, res) => {
    try {
      const limit = Math.min(parseInt(req.query.limit, 10) || 50, 200);
      const allChats = await client.getChats();

      // Filtrar apenas chats individuais (não grupos) e ordenar por última msg
      const chats = allChats
        .filter((chat) => !chat.isGroup && chat.id._serialized !== 'status@broadcast')
        .sort((a, b) => (b.timestamp || 0) - (a.timestamp || 0))
        .slice(0, limit)
        .map((chat) => ({
          chatId: chat.id._serialized,
          name: chat.name || chat.id.user,
          lastMessage: chat.lastMessage ? chat.lastMessage.body : null,
          timestamp: chat.timestamp || null,
          unreadCount: chat.unreadCount || 0,
          isGroup: chat.isGroup,
        }));

      res.json({ total: chats.length, chats });
    } catch (error) {
      logger.error('Erro ao listar chats:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar chats' });
    }
  });

  /**
   * GET /api/chats/:chatId/messages — Busca histórico de mensagens de um chat.
   * Query: limit (default 50)
   * Response: array de { id, body, timestamp, fromMe, ack, type, hasMedia }
   */
  app.get('/api/chats/:chatId/messages', authMiddleware, async (req, res) => {
    try {
      const { chatId } = req.params;
      const limit = Math.min(parseInt(req.query.limit, 10) || 50, 200);

      const chat = await client.getChatById(chatId);
      const messages = await chat.fetchMessages({ limit });

      const formatted = messages.map((msg) => ({
        id: msg.id._serialized,
        body: msg.body || '',
        timestamp: msg.timestamp,
        fromMe: msg.fromMe,
        ack: msg.ack,
        type: msg.type,
        hasMedia: msg.hasMedia,
        from: msg.from,
        to: msg.to,
      }));

      res.json({ total: formatted.length, messages: formatted });
    } catch (error) {
      logger.error('Erro ao buscar mensagens do chat:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar mensagens' });
    }
  });

  /**
   * GET /api/contacts/:chatId/avatar — Busca foto de perfil de um contato.
   * Response: { success, avatarUrl } ou { success: false, error }
   */
  app.get('/api/contacts/:chatId/avatar', authMiddleware, async (req, res) => {
    try {
      const { chatId } = req.params;
      const formattedChatId = chatId.includes('@') ? chatId : `${chatId}@c.us`;

      const avatarUrl = await client.getProfilePicUrl(formattedChatId);

      if (avatarUrl) {
        res.json({ success: true, avatarUrl });
      } else {
        res.json({ success: false, error: 'Avatar não disponível' });
      }
    } catch (error) {
      // Comum: contato sem foto ou privacidade bloqueada
      logger.debug('Avatar não disponível:', { chatId: req.params.chatId, error: error.message });
      res.json({ success: false, error: 'Avatar não disponível' });
    }
  });

  /**
   * GET /api/groups — Lista todos os grupos do WhatsApp conectado.
   * Response: { total, groups: [{ chatId, name, participantsCount, timestamp }] }
   */
  app.get('/api/groups', authMiddleware, async (_req, res) => {
    try {
      const allChats = await client.getChats();

      const groups = allChats
        .filter((chat) => chat.isGroup)
        .sort((a, b) => (a.name || '').localeCompare(b.name || ''))
        .map((chat) => ({
          chatId: chat.id._serialized,
          name: chat.name || chat.id.user,
          participantsCount: chat.participants ? chat.participants.length : null,
          timestamp: chat.timestamp || null,
        }));

      res.json({ total: groups.length, groups });
    } catch (error) {
      logger.error('Erro ao listar grupos:', { error: error.message });
      res.status(500).json({ error: 'Erro ao carregar grupos do WhatsApp' });
    }
  });

  // Rotas de captura de tela
  registerScreenRoutes(app, client, authMiddleware);

  return app;
};

/**
 * Inicia o servidor Express.
 * @param {express.Application} app - App Express.
 * @returns {Promise<void>}
 */
const startServer = (app) =>
  new Promise((resolve) => {
    app.listen(config.port, () => {
      logger.info(`🌐 API rodando em http://localhost:${config.port}`);
      resolve();
    });
  });

module.exports = { createServer, startServer };
