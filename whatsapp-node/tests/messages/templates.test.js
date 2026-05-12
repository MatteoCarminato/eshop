const {
  ofertaPadrao,
  ofertaComPreco,
  followUp,
  getRandomTemplate,
} = require('../../src/messages/templates');

describe('ofertaPadrao', () => {
  it('deve incluir o nome do contato na mensagem', () => {
    const msg = ofertaPadrao({ name: 'João' });
    expect(msg).toContain('João');
  });

  it('deve conter emojis para parecer natural', () => {
    const msg = ofertaPadrao({ name: 'Maria' });
    expect(msg).toContain('👋');
  });
});

describe('ofertaComPreco', () => {
  it('deve incluir nome, localização e preço', () => {
    const msg = ofertaComPreco({
      name: 'Carlos',
      location: 'Bairro Novo',
      price: 'R$ 50.000',
    });
    expect(msg).toContain('Carlos');
    expect(msg).toContain('Bairro Novo');
    expect(msg).toContain('R$ 50.000');
  });
});

describe('followUp', () => {
  it('deve incluir o nome do contato', () => {
    const msg = followUp({ name: 'Ana' });
    expect(msg).toContain('Ana');
  });
});

describe('getRandomTemplate', () => {
  it('deve retornar uma mensagem de um dos templates', () => {
    const templates = [ofertaPadrao, followUp];
    const msg = getRandomTemplate(templates, { name: 'Pedro' });
    expect(msg).toContain('Pedro');
  });
});
