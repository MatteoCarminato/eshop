const logger = require('../utils/logger');

/**
 * Busca todos os chats do WhatsApp conectado.
 * Retorna contatos individuais (ignora grupos).
 * Para contatos não salvos, usa o pushname (nome do perfil) ou o número.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @returns {Promise<Array<{name: string, phone: string, chatId: string, isMyContact: boolean}>>}
 */
const loadContactsFromWhatsApp = async (client) => {
  try {
    const chats = await client.getChats();

    const contacts = chats
      .filter((chat) => !chat.isGroup)
      .map((chat) => {
        const phone = chat.id.user;
        const chatId = chat.id._serialized;

        // Prioridade para o nome: 1) nome salvo, 2) pushname, 3) número
        const name = chat.name || phone;

        return {
          name,
          phone,
          chatId,
          isMyContact: !!chat.isReadOnly === false,
        };
      });

    logger.info(`📱 ${contacts.length} contatos carregados do WhatsApp`);
    return contacts;
  } catch (error) {
    logger.error('Erro ao carregar contatos do WhatsApp:', { error: error.message });
    throw error;
  }
};

/**
 * Busca contatos salvos na agenda do WhatsApp.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @returns {Promise<Array<{name: string, phone: string, chatId: string}>>}
 */
const loadSavedContacts = async (client) => {
  try {
    const wppContacts = await client.getContacts();

    const contacts = wppContacts
      .filter((contact) => {
        // Ignora grupos, broadcast, e o próprio número
        if (contact.isGroup || contact.isBusiness === undefined) return false;
        if (contact.isMe) return false;
        if (!contact.id || !contact.id.user) return false;
        // Só retorna se tiver um número válido
        return contact.id.server === 'c.us';
      })
      .map((contact) => ({
        name: contact.pushname || contact.name || contact.shortName || contact.id.user,
        phone: contact.id.user,
        chatId: contact.id._serialized,
        isMyContact: contact.isMyContact || false,
        pushname: contact.pushname || null,
      }));

    logger.info(`📇 ${contacts.length} contatos salvos carregados do WhatsApp`);
    return contacts;
  } catch (error) {
    logger.error('Erro ao carregar contatos salvos do WhatsApp:', { error: error.message });
    throw error;
  }
};

// ─────────────────────────────────────────────────────────────
// ETIQUETAS (Labels) — WhatsApp Business
// ─────────────────────────────────────────────────────────────

/**
 * Busca todas as etiquetas (labels) do WhatsApp Business.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @returns {Promise<Array<{id: string, name: string, color: string}>>}
 */
const getLabels = async (client) => {
  try {
    const labels = await client.getLabels();

    const result = labels.map((label) => ({
      id: label.id,
      name: label.name,
      color: label.hexColor || label.color || null,
    }));

    logger.info(`🏷️ ${result.length} etiquetas encontradas:`, {
      labels: result.map((l) => `${l.name} (${l.id})`),
    });

    return result;
  } catch (error) {
    logger.error('Erro ao buscar etiquetas:', { error: error.message });
    throw error;
  }
};

/**
 * Busca contatos de uma etiqueta específica pelo ID.
 * Funciona mesmo com contatos NÃO salvos na agenda.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @param {string} labelId - ID da etiqueta.
 * @returns {Promise<Array<{name: string, phone: string, chatId: string, labelName: string}>>}
 */
const loadContactsByLabelId = async (client, labelId) => {
  try {
    const chats = await client.getChatsByLabelId(labelId);

    // Buscar a label para logar o nome
    const labels = await client.getLabels();
    const label = labels.find((l) => l.id === labelId);
    const labelName = label ? label.name : labelId;

    const contacts = [];

    for (let i = 0; i < chats.length; i += 1) {
      const chat = chats[i];

      // Ignora grupos
      if (chat.isGroup) {
        // eslint-disable-next-line no-continue
        continue;
      }

      const phone = chat.id.user;
      const chatId = chat.id._serialized;

      // Para contatos não salvos, tenta pegar o pushname via getContact
      let name = chat.name || phone;
      try {
        // eslint-disable-next-line no-await-in-loop
        const contact = await chat.getContact();
        name = contact.pushname || contact.name || contact.shortName || phone;
      } catch (_err) {
        // Se falhar, usa o nome do chat ou o número
      }

      contacts.push({
        name,
        phone,
        chatId,
        labelName,
      });
    }

    logger.info(`🏷️ "${labelName}": ${contacts.length} contatos encontrados`);
    return contacts;
  } catch (error) {
    logger.error(`Erro ao buscar contatos da etiqueta ${labelId}:`, { error: error.message });
    throw error;
  }
};

/**
 * Busca contatos de uma etiqueta pelo NOME (case-insensitive).
 * Mais prático que usar o ID — basta passar "Casa" ou "Terreno".
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @param {string} labelName - Nome da etiqueta (ex: "Casa", "Terreno").
 * @returns {Promise<Array<{name: string, phone: string, chatId: string, labelName: string}>>}
 */
const loadContactsByLabelName = async (client, labelName) => {
  const labels = await getLabels(client);
  const label = labels.find((l) => l.name.toLowerCase() === labelName.toLowerCase());

  if (!label) {
    const available = labels.map((l) => l.name).join(', ');
    logger.error(`❌ Etiqueta "${labelName}" não encontrada. Disponíveis: ${available}`);
    throw new Error(`Etiqueta "${labelName}" não encontrada. Disponíveis: ${available}`);
  }

  return loadContactsByLabelId(client, label.id);
};

/**
 * Busca todos os contatos (salvos e não salvos) que tiveram conversa.
 * Ideal para envio em massa — pega todos que já interagiram.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client conectado.
 * @param {Object} [filters] - Filtros opcionais.
 * @param {boolean} [filters.savedOnly] - Apenas contatos salvos na agenda.
 * @param {boolean} [filters.withName] - Apenas contatos que têm nome (pushname ou salvo).
 * @param {string[]} [filters.excludePhones] - Números para excluir.
 * @param {string} [filters.labelName] - Filtrar por nome da etiqueta.
 * @param {string} [filters.labelId] - Filtrar por ID da etiqueta.
 * @returns {Promise<Array<{name: string, phone: string, chatId: string}>>}
 */
const loadFilteredContacts = async (client, filters = {}) => {
  const { savedOnly = false, withName = false, excludePhones = [], labelName, labelId } = filters;

  let contacts;

  // Prioridade: label > savedOnly > todos
  if (labelName) {
    contacts = await loadContactsByLabelName(client, labelName);
  } else if (labelId) {
    contacts = await loadContactsByLabelId(client, labelId);
  } else if (savedOnly) {
    contacts = await loadSavedContacts(client);
    contacts = contacts.filter((c) => c.isMyContact);
  } else {
    contacts = await loadContactsFromWhatsApp(client);
  }

  // Filtrar contatos que têm nome real (não é apenas o número)
  if (withName) {
    contacts = contacts.filter((c) => c.name !== c.phone);
  }

  // Excluir números específicos
  if (excludePhones.length > 0) {
    const excludeSet = new Set(excludePhones);
    contacts = contacts.filter((c) => !excludeSet.has(c.phone));
  }

  // Remover duplicatas por chatId
  const seen = new Set();
  contacts = contacts.filter((c) => {
    if (seen.has(c.chatId)) return false;
    seen.add(c.chatId);
    return true;
  });

  logger.info(`🎯 ${contacts.length} contatos após filtros`, { filters });
  return contacts;
};

module.exports = {
  loadContactsFromWhatsApp,
  loadSavedContacts,
  loadContactsByLabelId,
  loadContactsByLabelName,
  getLabels,
  loadFilteredContacts,
};
