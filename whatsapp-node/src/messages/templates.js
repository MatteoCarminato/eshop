/**
 * Templates de mensagens para envio personalizado.
 * Todos os templates DEVEM usar o nome do contato para parecer natural.
 * Use variações para evitar detecção como bot.
 */

// ─────────────────────────────────────────────────────────────
// TEMPLATES GENÉRICOS
// ─────────────────────────────────────────────────────────────

/**
 * Template padrão de oferta de terreno.
 * @param {Object} params
 * @param {string} params.name - Nome do contato.
 * @returns {string} Mensagem formatada.
 */
const ofertaPadrao = ({ name }) =>
  `Olá ${name}! 👋\n\nTudo bem? Estou entrando em contato porque temos uma oportunidade incrível de terrenos que pode te interessar!\n\n📍 Localização privilegiada\n✅ Documentação 100% regularizada\n💰 Condições facilitadas de pagamento\n\nPosso te enviar mais detalhes? 😊`;

/**
 * Template de oferta com localização e preço.
 * @param {Object} params
 * @param {string} params.name - Nome do contato.
 * @param {string} params.location - Localização do terreno.
 * @param {string} params.price - Preço formatado (ex: "R$ 50.000").
 * @returns {string} Mensagem formatada.
 */
const ofertaComPreco = ({ name, location, price }) =>
  `Oi ${name}, tudo bem? 😊\n\nVi que você pode ter interesse nessa oportunidade:\n\n📍 *${location}*\n💰 A partir de *${price}*\n📐 Lotes a partir de 200m²\n✅ Escritura imediata\n\nQuer saber mais? Me responde aqui! 🏡`;

/**
 * Template de follow-up (segunda mensagem).
 * @param {Object} params
 * @param {string} params.name - Nome do contato.
 * @returns {string} Mensagem formatada.
 */
const followUp = ({ name }) =>
  `${name}, vi que ainda não conseguimos conversar! 😊\n\nSó queria confirmar se recebeu as informações sobre os terrenos. Caso tenha interesse, estou à disposição para tirar qualquer dúvida!\n\n🏡 Oportunidade por tempo limitado!`;

// ─────────────────────────────────────────────────────────────
// TEMPLATES POR ETIQUETA
// ─────────────────────────────────────────────────────────────

/**
 * Template para contatos da etiqueta "Terreno".
 * @param {Object} params
 * @param {string} params.name - Nome do contato.
 * @returns {string} Mensagem formatada.
 */
const terreno = ({ name }) =>
  `Olá ${name}! 👋\n\nTudo bem? Tenho novidades incríveis sobre *terrenos* que você demonstrou interesse!\n\n🏞️ Lotes em ótima localização\n📐 A partir de 200m²\n💰 Entrada facilitada\n✅ Documentação regularizada\n\nGostaria de conhecer as opções disponíveis? 😊`;

/**
 * Template para contatos da etiqueta "Casa".
 * @param {Object} params
 * @param {string} params.name - Nome do contato.
 * @returns {string} Mensagem formatada.
 */
const casa = ({ name }) =>
  `Oi ${name}, tudo bem? 😊\n\nLembrei de você porque surgiram ótimas oportunidades de *casas* que podem te interessar!\n\n🏠 Casas prontas para morar\n📍 Bairros valorizados\n💰 Financiamento facilitado\n🔑 Entrada a partir de 20%\n\nQuer que eu te mande as opções? 🏡`;

/**
 * Mapa de templates por nome de etiqueta.
 * Facilita a seleção automática do template correto.
 */
const templatesByLabel = {
  terreno,
  casa,
};

/**
 * Retorna o template correspondente ao nome da etiqueta.
 * Se não encontrar, retorna o template padrão.
 * @param {string} labelName - Nome da etiqueta.
 * @returns {Function} Função de template.
 */
const getTemplateByLabel = (labelName) => {
  const key = labelName.toLowerCase().trim();
  return templatesByLabel[key] || ofertaPadrao;
};

/**
 * Seleciona um template aleatoriamente entre os disponíveis.
 * Útil para variar mensagens e evitar detecção como bot.
 * @param {Array<Function>} templates - Lista de funções de template.
 * @param {Object} params - Parâmetros para o template.
 * @returns {string} Mensagem formatada.
 */
const getRandomTemplate = (templates, params) => {
  const index = Math.floor(Math.random() * templates.length);
  return templates[index](params);
};

module.exports = {
  ofertaPadrao,
  ofertaComPreco,
  followUp,
  terreno,
  casa,
  templatesByLabel,
  getTemplateByLabel,
  getRandomTemplate,
};
