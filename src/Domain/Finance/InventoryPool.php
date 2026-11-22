<?php
namespace Tivoli\Domain\Finance;

use RuntimeException;

/** Integer hundredths of units and pesewas; no prices are inferred for unknown stock. */
final class InventoryPool
{
    private array $costs=['sales'=>0,'loss'=>0,'complimentary'=>0,'returns'=>0];
    private array $losses=[];
    private int $unknown=0;
    public function __construct(private int $qty,private ?int $value)
    {
        if($this->qty===0&&$this->value===null)$this->value=0;
        $this->validate();
    }
    private function validate():void
    {
        if($this->qty<0||$this->qty>999999999999||($this->value!==null&&($this->value<0||$this->value>999999999999))||($this->qty===0&&$this->value!==null&&$this->value!==0))throw new RuntimeException('Inventory quantity or carrying value needs source review.');
    }
    /** round(a*b/d), half up, without overflowing the integer product. Requires b <= d. */
    public static function share(int $a,int $b,int $d):int
    {
        if($a<0||$b<0||$d<1||$b>$d||max($a,$b,$d)>999999999999)throw new RuntimeException('Invalid proportional stock cost.');
        $result=intdiv($a,$d)*$b;$r=$a%$d;$rem=0;$part=0;
        foreach(str_split(decbin($b)) as $bit){$part*=2;$rem=$rem*2+($bit==='1'?$r:0);$part+=intdiv($rem,$d);$rem%=$d;}
        return $result+$part+($rem*2>=$d?1:0);
    }
    public function receive(int $qty,?int $value):void
    {
        if($qty<1||($value!==null&&$value<0))throw new RuntimeException('Invalid received stock basis.');
        $this->qty+=$qty;$this->value=$this->value===null||$value===null?null:$this->value+$value;$this->validate();
    }
    public function consume(int $qty,string $category,?string $loss=null):?int
    {
        if($qty<1||$qty>$this->qty||!array_key_exists($category,$this->costs)||($loss!==null&&isset($this->losses[$loss])))throw new RuntimeException('Stock consumption exceeds its supported pool or repeats a loss.');
        $value=$this->value===null?null:self::share($this->value,$qty,$this->qty);
        $this->qty-=$qty;if($value===null)$this->unknown+=$qty;else{$this->value-=$value;$this->costs[$category]+=$value;}
        if($loss!==null)$this->losses[$loss]=['qty'=>$qty,'value'=>$value,'category'=>$category];
        if($this->qty===0)$this->value=0;
        return $value;
    }
    public function dispose(string $loss,int $qty):void
    {
        if(!isset($this->losses[$loss])||$this->losses[$loss]['qty']!==$qty)throw new RuntimeException('Disposal must match its original verified loss.');
        unset($this->losses[$loss]);
    }
    public function restore(string $loss):void
    {
        if(!isset($this->losses[$loss]))throw new RuntimeException('Original verified loss cannot be restored.');
        $l=$this->losses[$loss];unset($this->losses[$loss]);
        if($l['value']===null)$this->unknown-=$l['qty'];else $this->costs[$l['category']]-=$l['value'];
        $this->receive($l['qty'],$l['value']);
    }
    public function returned(int $qty,int $credit):void
    {
        if($credit<0)throw new RuntimeException('Invalid supplier credit.');
        $value=$this->consume($qty,'returns');if($value!==null)$this->costs['returns']-=$credit;
    }
    public function snapshot():array
    {
        return ['qty'=>$this->qty,'value'=>$this->value,'written_off'=>array_sum(array_column($this->losses,'qty')),'costs'=>$this->costs,'unknown'=>$this->unknown];
    }
}
