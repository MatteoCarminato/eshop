/**
 * Remove caracteres não numéricos de uma string de telefone.
 * @param {string} phone - Número de telefone.
 * @returns {string} Apenas dígitos.
 */
const cleanPhone = (phone) => phone.replace(/\D/g, '');

/**
 * Formata um número de telefone brasileiro para o formato do WhatsApp.
 * Formato esperado: 5511999998888 (país + DDD + número)
 * @param {string} phone - Número de telefone (pode ter formatação).
 * @returns {string} Número formatado para WhatsApp (com @c.us).
 */
const formatPhoneForWhatsApp = (phone) => {
  let cleaned = cleanPhone(phone);

  // Se não começa com 55, adiciona código do Brasil
  if (!cleaned.startsWith('55')) {
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
