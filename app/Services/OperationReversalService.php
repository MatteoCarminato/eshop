<?php

namespace App\Services;

use App\Models\OperationSnapshot;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Captura e reverte operações financeiras da carteira de forma genérica e segura.
 *
 * Em vez de tentar desfazer cada efeito colateral "na mão" (consumo FIFO do caixa,
 * lucro, shortfall, reconciliação, finalização de depósito, splits), envolvemos a
 * operação inteira e gravamos um DIFF do estado tocado. A reversão restaura esse
 * diff exatamente, com um guard que bloqueia se uma operação posterior já mexeu
 * nos mesmos registros (nesse caso o usuário deve reverter a operação posterior
 * primeiro).
 */
class OperationReversalService
{
    /**
     * Tabelas observadas no snapshot.
     *  - scope: coluna para filtrar pelo cliente (null = tabela global).
     *  - soft:  usa soft delete (coluna deleted_at).
     */
    protected array $tables = [
        'transactions'         => ['scope' => 'client_id', 'soft' => true],
        'wallets'              => ['scope' => 'client_id', 'soft' => false],
        'wallet_pre_purchases' => ['scope' => 'client_id', 'soft' => true],
        'wallet_pre_sells'     => ['scope' => 'client_id', 'soft' => true],
        'treasury_lots'        => ['scope' => null,        'soft' => true],
        'treasury_sales'       => ['scope' => 'client_id', 'soft' => true],
    ];

    /** Colunas ignoradas na comparação de igualdade entre linhas. */
    protected array $ignoreColumns = ['updated_at'];

    /**
     * Executa $op envolvendo-a num snapshot reversível.
     *
     * @param  string         $type       deposit_brl|deposit_usd|pre_purchase|pre_sell|fechamento
     * @param  int|null       $clientId   cliente afetado (escopo do snapshot)
     * @param  array<int>     $depositIds âncoras conhecidas (ex.: ids dos depósitos)
     * @param  string|null    $label      rótulo amigável
     * @param  callable       $op         a operação original (retorna o que precisar)
     * @return mixed                      o retorno de $op
     */
    public function capture(string $type, ?int $clientId, array $depositIds, ?string $label, callable $op)
    {
        return DB::transaction(function () use ($type, $clientId, $depositIds, $label, $op) {
            $before  = $this->snapshotState($clientId);
            $result  = $op();
            $after   = $this->snapshotState($clientId);
            $payload = $this->diff($before, $after);

            if ($this->payloadIsEmpty($payload)) {
                // A operação não alterou nada relevante (ex.: validação falhou e
                // retornou um redirect de erro). Não registra snapshot.
                return $result;
            }

            $anchors = $this->deriveAnchors($payload, $depositIds);

            OperationSnapshot::create([
                'type'        => $type,
                'client_id'   => $clientId,
                'created_by'  => Auth::id(),
                'deposit_ids' => array_values(array_unique(array_map('intval', $anchors))),
                'label'       => $label,
                'payload'     => $payload,
            ]);

            return $result;
        });
    }

    /**
     * Reverte um snapshot. Lança RuntimeException com mensagem clara se não for
     * seguro (alguma operação posterior tocou os mesmos registros).
     *
     * @return array resumo da reversão
     */
    public function reverse(OperationSnapshot $snapshot, ?int $userId = null, ?string $reason = null): array
    {
        return DB::transaction(function () use ($snapshot, $userId, $reason) {
            /** @var OperationSnapshot $snap */
            $snap = OperationSnapshot::query()->whereKey($snapshot->id)->lockForUpdate()->firstOrFail();

            if ($snap->reversed_at !== null) {
                throw new \RuntimeException('Esta operação já foi revertida.');
            }

            $payload = $snap->payload ?? [];

            $this->assertReversible($snap, $payload);
            $touched = $this->applyReverse($payload);

            $snap->reversed_by    = $userId;
            $snap->reversed_at    = now();
            $snap->reverse_reason = $reason;
            $snap->save();

            return [
                'snapshot_id' => $snap->id,
                'type'        => $snap->type,
                'client_id'   => $snap->client_id,
                'touched'     => $touched,
            ];
        });
    }

    /**
     * Reverte vários snapshots, do mais novo para o mais antigo (ordem segura para
     * dependências, já que operações posteriores são desfeitas antes).
     *
     * @param  iterable<OperationSnapshot> $snapshots
     * @return array<int, array>
     */
    public function reverseMany(iterable $snapshots, ?int $userId = null, ?string $reason = null): array
    {
        $ordered = collect($snapshots)
            ->filter(fn (OperationSnapshot $s) => $s->reversed_at === null)
            ->sortByDesc('created_at')
            ->values();

        $results = [];
        foreach ($ordered as $snap) {
            $results[] = $this->reverse($snap, $userId, $reason);
        }

        return $results;
    }

    // ==========================================================================
    //  Snapshot / Diff
    // ==========================================================================

    /**
     * Captura o estado atual das tabelas observadas (linhas cruas, com deleted_at).
     *
     * @return array<string, array<int, array>>  table => [id => row]
     */
    protected function snapshotState(?int $clientId): array
    {
        $state = [];

        foreach ($this->tables as $table => $cfg) {
            $query = DB::table($table);

            if ($cfg['scope'] !== null && $clientId !== null) {
                $query->where($cfg['scope'], $clientId);
            }

            $rows = $query->get();

            $map = [];
            foreach ($rows as $row) {
                $arr = (array) $row;
                $map[(int) $arr['id']] = $arr;
            }
            $state[$table] = $map;
        }

        return $state;
    }

    /**
     * Diff entre dois estados: created / modified por tabela.
     * Soft-deletes aparecem como "modified" (coluna deleted_at muda).
     *
     * @return array<string, array{created: array, modified: array}>
     */
    protected function diff(array $before, array $after): array
    {
        $payload = [];

        foreach ($this->tables as $table => $cfg) {
            $beforeMap = $before[$table] ?? [];
            $afterMap  = $after[$table] ?? [];

            $created  = [];
            $modified = [];

            foreach ($afterMap as $id => $row) {
                if (!array_key_exists($id, $beforeMap)) {
                    $created[] = $row;
                    continue;
                }

                if (!$this->rowsEqual($beforeMap[$id], $row)) {
                    $modified[] = [
                        'before' => $beforeMap[$id],
                        'after'  => $row,
                    ];
                }
            }

            if (!empty($created) || !empty($modified)) {
                $payload[$table] = [
                    'created'  => $created,
                    'modified' => $modified,
                ];
            }
        }

        return $payload;
    }

    protected function payloadIsEmpty(array $payload): bool
    {
        foreach ($payload as $tableDiff) {
            if (!empty($tableDiff['created']) || !empty($tableDiff['modified'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Determina as âncoras (ids de transações) onde os botões de reversão devem
     * aparecer na carteira: depósitos BRL afetados, transações USD criadas e os
     * depósitos-fonte de pré-compras/pré-vendas.
     */
    protected function deriveAnchors(array $payload, array $explicit): array
    {
        $anchors = array_map('intval', $explicit);

        $txDiff = $payload['transactions'] ?? ['created' => [], 'modified' => []];

        foreach ($txDiff['created'] ?? [] as $row) {
            if (($row['type'] ?? null) === 'deposit') {
                $anchors[] = (int) $row['id'];
            }
        }
        foreach ($txDiff['modified'] ?? [] as $m) {
            $row = $m['after'];
            if (($row['type'] ?? null) === 'deposit' && ($row['currency'] ?? null) === 'BRL') {
                $anchors[] = (int) $row['id'];
            }
        }

        foreach (['wallet_pre_purchases', 'wallet_pre_sells'] as $t) {
            foreach ($payload[$t]['created'] ?? [] as $row) {
                if (!empty($row['source_transaction_id'])) {
                    $anchors[] = (int) $row['source_transaction_id'];
                }
            }
        }

        return $anchors;
    }

    // ==========================================================================
    //  Reverse
    // ==========================================================================

    /**
     * Garante que nenhum registro tocado pela operação foi alterado depois dela.
     * Caso contrário, reverter agora corromperia o estado: bloqueia com mensagem.
     */
    protected function assertReversible(OperationSnapshot $snap, array $payload): void
    {
        $conflicts = [];

        foreach ($payload as $table => $tableDiff) {
            // Linhas criadas: devem continuar idênticas a como ficaram após a operação.
            foreach ($tableDiff['created'] ?? [] as $row) {
                $current = $this->currentRow($table, (int) $row['id']);
                if ($current === null) {
                    $conflicts[] = "$table#{$row['id']} foi removido por outra operação";
                    continue;
                }
                if (!$this->rowsEqual($current, $row)) {
                    $conflicts[] = "$table#{$row['id']} foi alterado por uma operação posterior";
                }
            }

            // Linhas modificadas: o estado atual deve bater com o "depois" gravado.
            foreach ($tableDiff['modified'] ?? [] as $m) {
                $after   = $m['after'];
                $current = $this->currentRow($table, (int) $after['id']);
                if ($current === null) {
                    $conflicts[] = "$table#{$after['id']} foi removido por outra operação";
                    continue;
                }
                if (!$this->rowsEqual($current, $after)) {
                    $conflicts[] = "$table#{$after['id']} foi alterado por uma operação posterior";
                }
            }
        }

        if (!empty($conflicts)) {
            throw new \RuntimeException(
                'Não é seguro reverter esta operação porque outra operação mais recente ' .
                'já alterou os mesmos registros. Reverta primeiro a operação mais recente. ' .
                'Detalhes: ' . implode('; ', array_slice($conflicts, 0, 5)) .
                (count($conflicts) > 5 ? ' …' : '') . '.'
            );
        }
    }

    /**
     * Aplica a reversão: restaura linhas modificadas e remove linhas criadas.
     *
     * @return array<string, array{restored: int, removed: int}>
     */
    protected function applyReverse(array $payload): array
    {
        $touched = [];

        foreach ($payload as $table => $tableDiff) {
            $cfg     = $this->tables[$table] ?? ['soft' => false];
            $restored = 0;
            $removed  = 0;

            // 1) Restaura modificadas para o estado "antes".
            foreach ($tableDiff['modified'] ?? [] as $m) {
                $before = $m['before'];
                $this->restoreRow($table, (int) $before['id'], $before);
                $restored++;
            }

            // 2) Remove (soft ou hard) as linhas criadas pela operação.
            foreach ($tableDiff['created'] ?? [] as $row) {
                $this->removeRow($table, (int) $row['id'], (bool) $cfg['soft']);
                $removed++;
            }

            $touched[$table] = ['restored' => $restored, 'removed' => $removed];
        }

        return $touched;
    }

    protected function currentRow(string $table, int $id): ?array
    {
        $row = DB::table($table)->where('id', $id)->first();

        return $row ? (array) $row : null;
    }

    /**
     * Restaura todas as colunas (exceto id) ao estado gravado.
     */
    protected function restoreRow(string $table, int $id, array $values): void
    {
        unset($values['id']);
        $values['updated_at'] = now();

        DB::table($table)->where('id', $id)->update($values);
    }

    /**
     * Remove uma linha criada pela operação. Soft delete quando a tabela suporta,
     * hard delete caso contrário (ex.: wallets).
     */
    protected function removeRow(string $table, int $id, bool $soft): void
    {
        if ($soft) {
            DB::table($table)->where('id', $id)->update([
                'deleted_at' => now(),
                'updated_at' => now(),
            ]);
            return;
        }

        DB::table($table)->where('id', $id)->delete();
    }

    // ==========================================================================
    //  Helpers
    // ==========================================================================

    /**
     * Compara duas linhas ignorando colunas voláteis. Campos numéricos são
     * comparados com tolerância; o resto por string.
     */
    protected function rowsEqual(array $a, array $b): bool
    {
        $keys = array_unique(array_merge(array_keys($a), array_keys($b)));

        foreach ($keys as $key) {
            if (in_array($key, $this->ignoreColumns, true)) {
                continue;
            }

            $va = $a[$key] ?? null;
            $vb = $b[$key] ?? null;

            if ($va === null && $vb === null) {
                continue;
            }
            if ($va === null || $vb === null) {
                return false;
            }

            if (is_numeric($va) && is_numeric($vb)) {
                if (abs((float) $va - (float) $vb) > 0.0000001) {
                    return false;
                }
                continue;
            }

            if ((string) $va !== (string) $vb) {
                return false;
            }
        }

        return true;
    }
}
