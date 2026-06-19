const { formatPhoneForWhatsApp } = require('../utils/formatPhone');
const { loadImage } = require('../media/imageHandler');
const { waitBetweenMessages, waitLongPause, shouldPause, hasReachedLimit } = require('./scheduler');
const logger = require('../utils/logger');

/**
 * Resolve o chatId correto para um número, verificando se está registrado no WhatsApp.
 * Usa getNumberId() para obter o ID com LID correto (evita erro "No LID for user").
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {string} phone - Número de telefone (ex: 5545991325057).
 * @param {string} [directChatId] - ChatId direto (já resolvido).
 * @returns {Promise<{chatId: string|null, registered: boolean}>}
 */
const resolveChatId = async (client, phone, directChatId) => {
  // Se já tem chatId direto (veio do WhatsApp loader), usa direto
  if (directChatId) {
    return { chatId: directChatId, registered: true };
  }

  const formattedPhone = formatPhoneForWhatsApp(phone);
  const sanitizedNumber = formattedPhone.replace('@c.us', '');

  try {
    // getNumberId verifica se o número está registrado e retorna o ID correto
    const numberId = await client.getNumberId(sanitizedNumber);

    if (!numberId) {
      logger.warn(`⚠️ Número ${sanitizedNumber} não está registrado no WhatsApp`);
      return { chatId: null, registered: false };
    }

    // Usa o _serialized que tem o formato correto com LID
    return { chatId: numberId._serialized, registered: true };
  } catch (error) {
    logger.error(`Erro ao resolver número ${sanitizedNumber}:`, { error: error.message });
    return { chatId: null, registered: false };
  }
};

/**
 * Envia uma mensagem com imagem para um contato.
 * Verifica se o número está registrado no WhatsApp antes de enviar.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {Object} params
 * @param {string} params.phone - Número do telefone.
 * @param {string} [params.chatId] - ChatId direto (se veio do WhatsApp loader).
 * @param {string} params.message - Texto da mensagem.
 * @param {string} [params.imagePath] - Caminho da imagem (opcional).
 * @returns {Promise<{success: boolean, phone: string, error?: string}>}
 */
const sendMessageToContact = async (
  client,
  { phone, chatId: directChatId, message, imagePath },
) => {
  // Resolver o chatId correto (com LID)
  const { chatId, registered } = await resolveChatId(client, phone, directChatId);

  if (!registered || !chatId) {
    return {
      success: false,
      phone: phone || directChatId,
      error: 'Número não registrado no WhatsApp',
    };
  }

  try {
    if (imagePath) {
      const media = loadImage(imagePath);
      await client.sendMessage(chatId, media, { caption: message });
    } else {
      await client.sendMessage(chatId, message);
    }

    logger.info(`✅ Mensagem enviada para ${chatId}`);
    return { success: true, phone: phone || chatId };
  } catch (error) {
    logger.error(`❌ Erro ao enviar para ${chatId}:`, { error: error.message });
    return { success: false, phone: phone || chatId, error: error.message };
  }
};

/**
 * Envia mensagens em massa para uma lista de contatos.
 * Respeita delays e limites de anti-ban.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {Array<{name: string, phone: string}>} contacts - Lista de contatos.
 * @param {Function} templateFn - Função de template que recebe { name } e retorna string.
 * @param {Object} [options]
 * @param {string} [options.imagePath] - Caminho da imagem.
 * @param {Object} [options.extraParams] - Parâmetros extras para o template.
 * @returns {Promise<{sent: number, failed: number, results: Array}>}
 */
const sendBulkMessages = async (client, contacts, templateFn, options = {}) => {
  const { imagePath, extraParams = {} } = options;
  const results = [];
  let sentCount = 0;
  let failCount = 0;

  logger.info(`🚀 Iniciando envio em massa para ${contacts.length} contatos...`);

  for (let i = 0; i < contacts.length; i += 1) {
    const contact = contacts[i];

    // Verificar limite de sessão
    if (hasReachedLimit(sentCount)) {
      logger.warn(`🛑 Limite de mensagens por sessão atingido: ${sentCount}`);
      break;
    }

    // Pausa longa a cada N mensagens
    if (shouldPause(sentCount) && sentCount > 0) {
      // eslint-disable-next-line no-await-in-loop
      await waitLongPause();
    }

    // Montar mensagem personalizada com o nome
    const message = templateFn({ name: contact.name, ...extraParams });

    // Enviar (usa chatId direto se disponível, senão formata o phone)
    // eslint-disable-next-line no-await-in-loop
    const result = await sendMessageToContact(client, {
      phone: contact.phone,
      chatId: contact.chatId,
      message,
      imagePath,
    });

    results.push({ ...result, name: contact.name });

    if (result.success) {
      sentCount += 1;
    } else {
      failCount += 1;
    }

    logger.info(`📊 Progresso: ${i + 1}/${contacts.length} | ✅ ${sentCount} | ❌ ${failCount}`);

    // Delay entre mensagens (exceto a última)
    if (i < contacts.length - 1) {
      // eslint-disable-next-line no-await-in-loop
      await waitBetweenMessages();
    }
  }

  const report = { sent: sentCount, failed: failCount, total: contacts.length, results };
  logger.info('📋 Relatório final:', report);

  return report;
};

module.exports = { resolveChatId, sendMessageToContact, sendBulkMessages };
