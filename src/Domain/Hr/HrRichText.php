<?php
namespace Tivoli\Domain\Hr;
final class HrRichText {
    public static function sanitize(string $html): string {
        if(strlen($html)>60000)throw new \RuntimeException('Reason is too long.');
        $dom=new \DOMDocument();$previous=libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8"><div>'.$html.'</div>',LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING);libxml_clear_errors();libxml_use_internal_errors($previous);
        $walk=function(\DOMNode $node) use(&$walk):string {
            if($node instanceof \DOMText)return htmlspecialchars($node->textContent,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
            if(!$node instanceof \DOMElement||in_array(strtolower($node->tagName),['script','style','iframe','object','svg','math','template']))return '';
            $tag=strtolower($node->tagName);$content='';foreach($node->childNodes as $child)$content.=$walk($child);
            return in_array($tag,['p','div','b','strong','i','em','u','br','ul','ol','li'])?'<'.$tag.'>'.($tag==='br'?'':$content.'</'.$tag.'>'):$content;
        };
        $result='';foreach($dom->getElementsByTagName('body')->item(0)->childNodes as $child)$result.=$walk($child);
        if(mb_strlen(self::plain($result))>5000)throw new \RuntimeException('Reason must be within 5,000 characters.');return $result;
    }
    public static function plain(string $html): string {return trim(html_entity_decode(strip_tags(preg_replace('/<\/?(?:p|div|li|br)\b[^>]*>/i',"\n",$html)),ENT_QUOTES|ENT_HTML5,'UTF-8'));}
}
