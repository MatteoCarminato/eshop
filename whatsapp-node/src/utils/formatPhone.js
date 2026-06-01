/**
 * Remove caracteres não numéricos de uma string de telefone.
 * @param {string} phone - Número de telefone.
 * @returns {string} Apenas dígitos.
 */
const cleanPhone = (phone) => phone.replace(/\D/g, '');

/**
 * Formata um número para o formato do WhatsApp.
 * Regras:
 * - Se vier no formato local BR (10/11 dígitos), prefixa 55.
 * - Se já vier internacional (12-15 dígitos), mantém como está.
 * @param {string} phone - Número de telefone (pode ter formatação).
 * @returns {string} Número formatado para WhatsApp (com @c.us).
 */
const formatPhoneForWhatsApp = (phone) => {
  let cleaned = cleanPhone(phone);

  // Remove prefixo internacional 00, caso exista
  if (cleaned.startsWith('00')) {
    cleaned = cleaned.slice(2);
  }

  // Número local BR (DDD + número): adiciona 55
  if (cleaned.length === 10 || cleaned.length === 11) {
    cleaned = `55${cleaned}`;
  }

  return `${cleaned}@c.us`;
};

/**
 * Valida se um número de telefone brasileiro está no formato correto.
 * @param {string} phone - Número de telefone (apenas dígitos).
 * @returns {boolean} True se o formato é válido.
 */
const isValidBrazilianPhone = (phone) => {
  const cleaned = cleanPhone(phone);
  // 55 + DDD(2) + número(8 ou 9) = 12 ou 13 dígitos
  const regex = /^55\d{2}\d{8,9}$/;
  return regex.test(cleaned);
};

module.exports = { cleanPhone, formatPhoneForWhatsApp, isValidBrazilianPhone };
