const { validateContact, validateContacts } = require('../../src/contacts/validator');

// Mock do logger para não poluir output dos testes
jest.mock('../../src/utils/logger', () => ({
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
}));

describe('validateContact', () => {
  it('deve validar contato com dados corretos', () => {
    const result = validateContact({ name: 'João', phone: '5511999998888' });
    expect(result.valid).toBe(true);
  });

  it('deve rejeitar contato sem nome', () => {
    const result = validateContact({ name: '', phone: '5511999998888' });
    expect(result.valid).toBe(false);
    expect(result.reason).toBe('Nome vazio');
  });

  it('deve rejeitar contato sem telefone', () => {
    const result = validateContact({ name: 'João', phone: '' });
    expect(result.valid).toBe(false);
    expect(result.reason).toBe('Telefone vazio');
  });

  it('deve rejeitar contato com telefone inválido', () => {
    const result = validateContact({ name: 'João', phone: '123' });
    expect(result.valid).toBe(false);
    expect(result.reason).toContain('Formato inválido');
  });

  it('deve aceitar telefone sem código do país (adiciona 55)', () => {
    const result = validateContact({ name: 'Maria', phone: '11999998888' });
    expect(result.valid).toBe(true);
  });

  it('deve validar contato do WhatsApp com chatId', () => {
    const result = validateContact({
      name: 'Carlos',
      phone: '5511999998888',
      chatId: '5511999998888@c.us',
    });
    expect(result.valid).toBe(true);
  });

  it('deve validar contato do WhatsApp com chatId mesmo sem phone', () => {
    const result = validateContact({
      name: 'Ana',
      chatId: '5511999998888@c.us',
    });
    expect(result.valid).toBe(true);
  });

  it('deve rejeitar contato do WhatsApp sem nome mesmo com chatId', () => {
    const result = validateContact({
      name: '',
      chatId: '5511999998888@c.us',
    });
    expect(result.valid).toBe(false);
    expect(result.reason).toBe('Nome vazio');
  });
});

describe('validateContacts', () => {
  it('deve separar contatos válidos e inválidos', () => {
    const contacts = [
      { name: 'João', phone: '5511999998888' },
      { name: '', phone: '5521988887777' },
      { name: 'Maria', phone: '5521988887777' },
    ];

    const { valid, invalid } = validateContacts(contacts);
    expect(valid).toHaveLength(2);
    expect(invalid).toHaveLength(1);
  });

  it('deve remover duplicatas por chatId', () => {
    const contacts = [
      { name: 'João', phone: '5511999998888', chatId: '5511999998888@c.us' },
      { name: 'João 2', phone: '5511999998888', chatId: '5511999998888@c.us' },
    ];

    const { valid, invalid } = validateContacts(contacts);
    expect(valid).toHaveLength(1);
    expect(invalid).toHaveLength(1);
    expect(invalid[0].reason).toBe('Número duplicado');
  });

  it('deve remover duplicatas por phone', () => {
    const contacts = [
      { name: 'João', phone: '5511999998888' },
      { name: 'João 2', phone: '5511999998888' },
    ];

    const { valid, invalid } = validateContacts(contacts);
    expect(valid).toHaveLength(1);
    expect(invalid).toHaveLength(1);
    expect(invalid[0].reason).toBe('Número duplicado');
  });
  it('deve retornar vazio para lista vazia', () => {
    const { valid, invalid } = validateContacts([]);
    expect(valid).toHaveLength(0);
    expect(invalid).toHaveLength(0);
  });
});
