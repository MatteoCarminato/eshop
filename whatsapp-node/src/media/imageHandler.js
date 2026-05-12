const fs = require('fs');
const path = require('path');
const { MessageMedia } = require('whatsapp-web.js');
const logger = require('../utils/logger');
const config = require('../config/env');

/**
 * Carrega uma imagem do disco e retorna como MessageMedia.
 * @param {string} [imagePath] - Caminho da imagem. Usa DEFAULT_IMAGE_PATH se não informado.
 * @returns {MessageMedia} Objeto MessageMedia para envio.
 * @throws {Error} Se a imagem não for encontrada.
 */
const loadImage = (imagePath) => {
  const resolvedPath = imagePath || config.defaultImagePath;
  const absolutePath = path.isAbsolute(resolvedPath)
    ? resolvedPath
    : path.resolve(process.cwd(), resolvedPath);

  if (!fs.existsSync(absolutePath)) {
    logger.error('Imagem não encontrada:', { path: absolutePath });
    throw new Error(`Imagem não encontrada: ${absolutePath}`);
  }

  logger.info('📷 Imagem carregada:', { path: absolutePath });
  return MessageMedia.fromFilePath(absolutePath);
};

/**
 * Verifica se uma imagem existe no caminho especificado.
 * @param {string} imagePath - Caminho da imagem.
 * @returns {boolean} True se a imagem existe.
 */
const imageExists = (imagePath) => {
  const absolutePath = path.isAbsolute(imagePath)
    ? imagePath
    : path.resolve(process.cwd(), imagePath);
  return fs.existsSync(absolutePath);
};

module.exports = { loadImage, imageExists };
