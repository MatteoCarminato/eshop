<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * O ERP não aceitou a gravação do preço.
 *
 * A mensagem é mostrada ao usuário na própria célula da grade (vira o `title`
 * da célula vermelha), então é escrita em português e sem jargão de banco.
 */
class ErpWriteException extends RuntimeException
{
}
