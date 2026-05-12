const logger = require('../utils/logger');
const qrcode = require('qrcode');

/**
 * Registra rotas de captura de tela do WhatsApp Web.
 * @param {express.Application} app - App Express.
 * @param {import('whatsapp-web.js').Client} client - WhatsApp client.
 * @param {Function} authMiddleware - Middleware de autenticação.
 */
const registerScreenRoutes = (app, client, authMiddleware) => {
  // Estado do QR code (armazenado in-memory)
  let lastQrCode = null;
  let qrTimestamp = null;

  // Capturar QR code quando emitido pelo client
  client.on('qr', (qr) => {
    lastQrCode = qr;
    qrTimestamp = new Date().toISOString();
    logger.info('📸 QR Code capturado e armazenado para API');
  });

  // Limpar QR quando autenticado
  client.on('authenticated', () => {
    lastQrCode = null;
    qrTimestamp = null;
  });

  /**
   * GET /api/qr
   * Retorna o QR code atual (se houver) como string para gerar imagem no frontend.
   */
  app.get('/api/qr', authMiddleware, (_req, res) => {
    if (!lastQrCode) {
      return res.json({
        available: false,
        message: 'Nenhum QR code disponível. Já autenticado ou aguardando.',
      });
    }

    res.json({
      available: true,
      qr: lastQrCode,
      timestamp: qrTimestamp,
    });
  });

  /**
   * GET /api/qr-image
   * Retorna o QR code atual em SVG para consumo direto pelo Laravel.
   */
  app.get('/api/qr-image', authMiddleware, async (_req, res) => {
    try {
      if (!lastQrCode) {
        return res.status(404).json({
          available: false,
          message: 'Nenhum QR code disponível. Já autenticado ou aguardando.',
        });
      }

      const svg = await qrcode.toString(lastQrCode, {
        type: 'svg',
        width: 300,
        margin: 1,
        errorCorrectionLevel: 'M',
      });

      res.setHeader('Content-Type', 'image/svg+xml; charset=utf-8');
      res.setHeader('Cache-Control', 'no-store, no-cache, must-revalidate, private');
      return res.send(svg);
    } catch (error) {
      logger.error('Erro ao gerar QR SVG:', { error: error.message });
      return res.status(500).json({ error: 'Erro ao gerar QR SVG' });
    }
  });

  /**
   * GET /api/screenshot
   * Captura uma screenshot da página do WhatsApp Web via Puppeteer.
   * Retorna a imagem como base64 (PNG).
   */
  app.get('/api/screenshot', authMiddleware, async (_req, res) => {
    try {
      const pupPage = client.pupPage;
      if (!pupPage) {
        return res.status(503).json({
          error: 'WhatsApp Web não está carregado ainda.',
          state: 'initializing',
        });
      }

      // Capturar screenshot como buffer PNG
      const screenshot = await pupPage.screenshot({
        type: 'png',
        fullPage: false,
        encoding: 'base64',
      });

      res.json({
        success: true,
        image: `data:image/png;base64,${screenshot}`,
        timestamp: new Date().toISOString(),
      });
    } catch (error) {
      logger.error('Erro ao capturar screenshot:', { error: error.message });
      res.status(500).json({ error: 'Erro ao capturar screenshot do WhatsApp Web' });
    }
  });

  /**
   * GET /api/screen/state
   * Retorna o estado atual da tela: qr, loading, connected.
   */
  app.get('/api/screen/state', authMiddleware, async (_req, res) => {
    try {
      const state = client.info ? 'connected' : lastQrCode ? 'qr' : 'loading';

      res.json({
        state,
        phoneConnected: !!client.info,
        qrAvailable: !!lastQrCode,
        info: client.info
          ? {
            pushname: client.info.pushname,
            phone: client.info.wid ? client.info.wid.user : null,
            platform: client.info.platform,
          }
          : null,
      });
    } catch (error) {
      logger.error('Erro ao obter estado da tela:', { error: error.message });
      res.status(500).json({ error: 'Erro ao obter estado' });
    }
  });
};

module.exports = { registerScreenRoutes };
