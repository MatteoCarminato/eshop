const { cleanPhone, isValidBrazilianPhone } = require('../utils/formatPhone');
const logger = require('../utils/logger');

/**
 * Valida um contato individual.
 * Suporta contatos do CSV (phone) e do WhatsApp (chatId).
 * @param {Object} contact - Contato a ser validado.
 * @param {string} contact.name - Nome do contato.
 * @param {string} [contact.phone] - Telefone do contato.
 * @param {string} [contact.chatId] - Chat ID do WhatsApp (ex: 5511999998888@c.us).
 * @returns {{ valid: boolean, reason?: string }} Resultado da validação.
 */
const validateContact = ({ name, phone, chatId }) => {
  if (!name || name.trim().length === 0) {
    return { valid: false, reason: 'Nome vazio' };
  }

  // Se tem chatId (veio do WhatsApp), já é válido
  if (chatId) {
    return { valid: true };
  }

  if (!phone || phone.trim().length === 0) {
    return { valid: false, reason: 'Telefone vazio' };
  }

  const cleaned = cleanPhone(phone);

  // Adiciona 55 se não tiver para validação
  const fullNumber = cleaned.startsWith('55') ? cleaned : `55${cleaned}`;

  if (!isValidBrazilianPhone(fullNumber)) {
    return { valid: false, reason: `Formato inválido: ${phone}` };
  }

  return { valid: true };
};

/**
 * Valida uma lista de contatos e retorna válidos e inválidos separados.
 * Remove duplicatas por número de telefone.
 * @param {Array<{name: string, phone: string}>} contacts - Lista de contatos.
 * @returns {{ valid: Array, invalid: Array<{contact: Object, reason: string}> }}
 */
const validateContacts = (contacts) => {
  const valid = [];
  const invalid = [];
  const seenPhones = new Set();

  contacts.forEach((contact) => {
    // Usar chatId como chave única se disponível, senão usar phone
    const uniqueKey =
      contact.chatId ||
      (() => {
        const cleaned = cleanPhone(contact.phone || '');
        return cleaned.startsWith('55') ? cleaned : `55${cleaned}`;
      })();

    // Verificar duplicata
    if (seenPhones.has(uniqueKey)) {
      invalid.push({ contact, reason: 'Número duplicado' });
      return;
    }

    const result = validateContact(contact);

    if (result.valid) {
      seenPhones.add(uniqueKey);
      valid.push(contact);
    } else {
      invalid.push({ contact, reason: result.reason });
    }
  });

  if (invalid.length > 0) {
    logger.warn(`⚠️ ${invalid.length} contatos inválidos encontrados:`, {
      invalid: invalid.map((i) => ({ name: i.contact.name, reason: i.reason })),
    });
  }

  logger.info(`✅ ${valid.length} contatos válidos | ❌ ${invalid.length} inválidos`);
  return { valid, invalid };
};

module.exports = { validateContact, validateContacts };
