<?php
namespace Tivoli\Domain\Money;
use RuntimeException;
final class ExpenseAmount
{
    public static function parse(string $value): string
    {
        if (!preg_match('/^(0|[1-9][0-9]{0,9})(?:\.([0-9]{1,2}))?$/D',$value,$parts)) { throw new RuntimeException('Enter a positive amount with at most two decimal places.'); }
        $fraction=str_pad($parts[2] ?? '',2,'0');
        if ($parts[1]==='0' && $fraction==='00') { throw new RuntimeException('Amount must be greater than zero.'); }
        return $parts[1].'.'.$fraction;
    }
    public static function display(string $value): string
    {
        if (!preg_match('/^(-?)([0-9]+)(?:\.([0-9]{1,2}))?$/D',$value,$parts)) { throw new RuntimeException('Invalid stored amount.'); }
        return $parts[1].preg_replace('/\B(?=(\d{3})+(?!\d))/',',',$parts[2]).'.'.str_pad($parts[3] ?? '',2,'0');
    }
}