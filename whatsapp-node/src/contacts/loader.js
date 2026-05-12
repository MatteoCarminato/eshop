const fs = require('fs');
const path = require('path');
// eslint-disable-next-line import/no-unresolved
const { parse } = require('csv-parse/sync');
const logger = require('../utils/logger');

/**
 * Carrega contatos de um arquivo CSV.
 * O CSV deve ter colunas: nome, telefone
 * @param {string} [filePath] - Caminho para o arquivo CSV. Padrão: data/contacts.csv
 * @returns {Array<{name: string, phone: string}>} Lista de contatos.
 */
const loadContactsFromCSV = (filePath) => {
  const csvPath = filePath || path.resolve(process.cwd(), 'data', 'contacts.csv');

  if (!fs.existsSync(csvPath)) {
    logger.error('Arquivo de contatos não encontrado:', { path: csvPath });
    throw new Error(`Arquivo de contatos não encontrado: ${csvPath}`);
  }

  const fileContent = fs.readFileSync(csvPath, 'utf-8');

  const records = parse(fileContent, {
    columns: true,
    skip_empty_lines: true,
    trim: true,
  });

  const contacts = records.map((record) => ({
    name: record.nome || record.name || '',
    phone: record.telefone || record.phone || '',
  }));

  logger.info(`📋 ${contacts.length} contatos carregados do CSV`);
  return contacts;
};

/**
 * Carrega contatos de um arquivo JSON.
 * O JSON deve ser um array de objetos: [{ name, phone }]
 * @param {string} filePath - Caminho para o arquivo JSON.
 * @returns {Array<{name: string, phone: string}>} Lista de contatos.
 */
const loadContactsFromJSON = (filePath) => {
  if (!fs.existsSync(filePath)) {
    logger.error('Arquivo de contatos não encontrado:', { path: filePath });
    throw new Error(`Arquivo de contatos não encontrado: ${filePath}`);
  }

  const fileContent = fs.readFileSync(filePath, 'utf-8');
  const contacts = JSON.parse(fileContent);

  logger.info(`📋 ${contacts.length} contatos carregados do JSON`);
  return contacts;
};

module.exports = { loadContactsFromCSV, loadContactsFromJSON };
