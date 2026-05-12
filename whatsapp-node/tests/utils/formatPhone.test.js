const {
  cleanPhone,
  formatPhoneForWhatsApp,
  isValidBrazilianPhone,
} = require('../../src/utils/formatPhone');

describe('cleanPhone', () => {
  it('deve remover caracteres não numéricos', () => {
    expect(cleanPhone('+55 (11) 99999-8888')).toBe('5511999998888');
  });

  it('deve retornar string vazia para entrada vazia', () => {
    expect(cleanPhone('')).toBe('');
  });
});

describe('formatPhoneForWhatsApp', () => {
  it('deve formatar número completo com @c.us', () => {
    expect(formatPhoneForWhatsApp('5511999998888')).toBe('5511999998888@c.us');
  });

  it('deve adicionar 55 se não tiver código do país', () => {
    expect(formatPhoneForWhatsApp('11999998888')).toBe('5511999998888@c.us');
  });

  it('deve limpar formatação antes de formatar', () => {
    expect(formatPhoneForWhatsApp('+55 (11) 99999-8888')).toBe('5511999998888@c.us');
  });
});

describe('isValidBrazilianPhone', () => {
  it('deve validar número com 9 dígitos (celular)', () => {
    expect(isValidBrazilianPhone('5511999998888')).toBe(true);
  });

  it('deve validar número com 8 dígitos (fixo)', () => {
    expect(isValidBrazilianPhone('551199998888')).toBe(true);
  });

  it('deve rejeitar número muito curto', () => {
    expect(isValidBrazilianPhone('551199')).toBe(false);
  });

  it('deve rejeitar número sem código do país', () => {
    expect(isValidBrazilianPhone('11999998888')).toBe(false);
  });

  it('deve rejeitar número muito longo', () => {
    expect(isValidBrazilianPhone('55119999988881')).toBe(false);
  });
});
