<?php

declare(strict_types=1);

namespace SOFe\Capital;

use Generator;
use Ramsey\Uuid\UuidInterface;
use SOFe\Capital\Database\Database;
use function array_map;
use function count;

final class Capital {
    /**
     * @param array<string, string> $labels
     * @return Generator<mixed, mixed, mixed, TransactionRef> the transaction ID
     */
    public static function transact(AccountRef $src, AccountRef $dest, int $amount, array $labels) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $event = new TransactionEvent($src, $dest, $amount, $labels);
        $event->call();

        $id = yield from $db->doTransaction($src->getId(), $dest->getId(), $amount);
        return new TransactionRef($id);
    }

    /**
     * @param array<string, string> $labels1
     * @param array<string, string> $labels2
     * @return Generator<mixed, mixed, mixed, array{TransactionRef, TransactionRef}> the transaction IDs
     */
    public static function transact2(
        AccountRef $src1, AccountRef $dest1, int $amount1, array $labels1,
        AccountRef $src2, AccountRef $dest2, int $amount2, array $labels2,
        ?UuidInterface $uuid1 = null, ?UuidInterface $uuid2 = null,
    ) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $event = new TransactionEvent($src1, $dest1, $amount1, $labels1);
        $event->call();

        $event = new TransactionEvent($src2, $dest2, $amount2, $labels2);
        $event->call();

        $ids = yield from $db->doTransaction2(
            $src1->getId(), $dest1->getId(), $amount1,
            $src2->getId(), $dest2->getId(), $amount2,
            AccountLabels::VALUE_MIN, AccountLabels::VALUE_MAX,
            $uuid1, $uuid2,
        );

        return [new TransactionRef($ids[0]), new TransactionRef($ids[1])];
    }

    /**
     * @return Generator<mixed, mixed, mixed, array<AccountRef>>
     */
    public static function findAccounts(LabelSelector $selector) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $accounts = yield from $db->findAccountN($selector);

        return array_map(fn($account) => new AccountRef($account), $accounts);
    }

    /**
     * @return Generator<mixed, mixed, mixed, AccountRef>
     */
    public static function getOracle(string $name) : Generator {
        $db = Database::get(MainClass::$typeMap);

        $labels = [
            AccountLabels::ORACLE => $name,
        ];

        $accounts = yield from self::findAccounts(new LabelSelector($labels));
        if(count($accounts) > 0) {
            return $accounts[0];
        }

        // Do not apply valueMin and valueMax on this account,
        // otherwise we will get failing transactions and it's no longer an oracle.
        $account = yield from $db->createAccount(0, $labels);
        return new AccountRef($account);
    }
}
