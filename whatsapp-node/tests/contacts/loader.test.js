const fs = require('fs');
const { loadContactsFromCSV } = require('../../src/contacts/loader');

// Mock do logger
jest.mock('../../src/utils/logger', () => ({
  info: jest.fn(),
  warn: jest.fn(),
  error: jest.fn(),
}));

describe('loadContactsFromCSV', () => {
  const tempFile = '/tmp/test-contacts.csv';

  afterEach(() => {
    if (fs.existsSync(tempFile)) {
      fs.unlinkSync(tempFile);
    }
  });

  it('deve carregar contatos de um CSV válido', () => {
    const csv = 'nome,telefone\nJoão Silva,5511999998888\nMaria Souza,5521988887777\n';
    fs.writeFileSync(tempFile, csv);

    const contacts = loadContactsFromCSV(tempFile);
    expect(contacts).toHaveLength(2);
    expect(contacts[0]).toEqual({ name: 'João Silva', phone: '5511999998888' });
    expect(contacts[1]).toEqual({ name: 'Maria Souza', phone: '5521988887777' });
  });

  it('deve lançar erro se arquivo não existir', () => {
    expect(() => loadContactsFromCSV('/tmp/nao-existe.csv')).toThrow(
      'Arquivo de contatos não encontrado',
    );
  });

  it('deve ignorar linhas vazias', () => {
    const csv = 'nome,telefone\nJoão,5511999998888\n\nMaria,5521988887777\n';
    fs.writeFileSync(tempFile, csv);

    const contacts = loadContactsFromCSV(tempFile);
    expect(contacts).toHaveLength(2);
  });
});
