<?php

declare(strict_types=1);

namespace EventMenu\Services;

use PDO;
use RuntimeException;
use Throwable;

final class WhatsAppEventTicketSalesService
{
    private const PAGE_SIZE = 8;
    private const STATES = ['EVENT_TICKET_EVENTS','EVENT_TICKET_BATCHES','EVENT_TICKET_QUANTITY'];

    public function shouldHandle(string $state,string $text): bool
    {
        if(in_array($state,self::STATES,true))return true;
        $normalized=$this->normalize($text);
        if($state==='WELCOME'&&$normalized==='5')return true;
        return preg_match('/\\b(ingresso|ingressos|ticket|tickets|eventos|evento)\\b/u',$normalized)===1
            || preg_match('/compr(ar|e|a)\\s+(um\\s+)?ingresso/u',$normalized)===1;
    }

    /** @return array<string,mixed> */
    public function handle(PDO $pdo,int $tenantId,array $conversation,string $text): array
    {
        $state=(string)($conversation['state']??'WELCOME');
        $context=$this->context($conversation['context_json']??null);
        $normalized=$this->normalize($text);
        $conversationId=(int)($conversation['id']??0);
        if($conversationId<1||$tenantId<1)throw new RuntimeException('Conversa inválida.');

        if($normalized==='cancelar'||$normalized==='menu'||$normalized==='início'||$normalized==='inicio'){
            return $this->stateResult($pdo,$tenantId,$conversationId,'WELCOME',[],"Voltamos ao início.\n\n1 - Fazer um pedido\n2 - Repetir último pedido\n3 - Acompanhar pedido\n4 - Falar com atendente\n5 - Comprar ingresso para evento",'ticket_menu_cancelled');
        }

        if(!in_array($state,self::STATES,true)){
            if(!($state==='WELCOME'&&$normalized==='5')&&!$this->isTicketIntent($normalized))return ['handled'=>false];
            return $this->eventsResult($pdo,$tenantId,$conversationId,0);
        }

        if($state==='EVENT_TICKET_EVENTS'){
            $page=max(0,(int)($context['event_page']??0));
            if($normalized==='0')return $this->stateResult($pdo,$tenantId,$conversationId,'WELCOME',[],"Voltamos ao início.\n\n1 - Fazer um pedido\n2 - Repetir último pedido\n3 - Acompanhar pedido\n4 - Falar com atendente\n5 - Comprar ingresso para evento",'ticket_menu_cancelled');
            if(in_array($normalized,['anterior','voltar'],true)&&$page>0)return $this->eventsResult($pdo,$tenantId,$conversationId,$page-1);
            $rows=$this->availableEvents($pdo,$tenantId,$page);
            if($normalized==='9'&&count($rows)>self::PAGE_SIZE)return $this->eventsResult($pdo,$tenantId,$conversationId,$page+1);
            if(!ctype_digit($normalized))return $this->eventsResult($pdo,$tenantId,$conversationId,$page,'Escolha o evento pelo número.');
            $choice=(int)$normalized;
            if($choice<1||$choice>min(self::PAGE_SIZE,count($rows)))return $this->eventsResult($pdo,$tenantId,$conversationId,$page,'Evento inválido.');
            $event=$rows[$choice-1];
            return $this->batchesResult($pdo,$tenantId,$conversationId,(int)$event['id'],(string)$event['name'],(string)$event['slug'],(string)$event['tenant_slug'],0);
        }

        if($state==='EVENT_TICKET_BATCHES'){
            $eventId=(int)($context['event_id']??0);$page=max(0,(int)($context['batch_page']??0));
            if($normalized==='0')return $this->eventsResult($pdo,$tenantId,$conversationId,0);
            if(in_array($normalized,['anterior','voltar'],true)&&$page>0)return $this->batchesResult($pdo,$tenantId,$conversationId,$eventId,(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),$page-1);
            $rows=$this->availableBatches($pdo,$tenantId,$eventId,$page);
            if($normalized==='9'&&count($rows)>self::PAGE_SIZE)return $this->batchesResult($pdo,$tenantId,$conversationId,$eventId,(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),$page+1);
            if(!ctype_digit($normalized))return $this->batchesResult($pdo,$tenantId,$conversationId,$eventId,(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),$page,'Escolha o lote pelo número.');
            $choice=(int)$normalized;
            if($choice<1||$choice>min(self::PAGE_SIZE,count($rows)))return $this->batchesResult($pdo,$tenantId,$conversationId,$eventId,(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),$page,'Lote inválido.');
            $batch=$rows[$choice-1];$context['batch_id']=(int)$batch['id'];$context['batch_name']=(string)$batch['name'];$context['batch_price_cents']=(int)$batch['price_cents'];$context['batch_available']=(int)$batch['available'];
            return $this->stateResult($pdo,$tenantId,$conversationId,'EVENT_TICKET_QUANTITY',$context,"🎟️ *".(string)$context['event_name']."*\nLote: ".(string)$batch['name']."\nPreço por ingresso: ".$this->money((int)$batch['price_cents'])."\nDisponíveis: ".(int)$batch['available']."\n\nQuantos ingressos deseja? Informe um número de 1 a ".min(10,(int)$batch['available']).".\n\n0 - Escolher outro lote",'ticket_quantity_prompt');
        }

        if($state==='EVENT_TICKET_QUANTITY'){
            if($normalized==='0')return $this->batchesResult($pdo,$tenantId,$conversationId,(int)($context['event_id']??0),(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),0);
            if(!ctype_digit($normalized)||(int)$normalized<1||(int)$normalized>10)return $this->stateResult($pdo,$tenantId,$conversationId,'EVENT_TICKET_QUANTITY',$context,'Informe uma quantidade entre 1 e 10. Digite 0 para escolher outro lote.','ticket_quantity_invalid');
            $quantity=(int)$normalized;$eventId=(int)($context['event_id']??0);$batchId=(int)($context['batch_id']??0);
            $batch=$this->batchById($pdo,$tenantId,$eventId,$batchId);
            if(!$batch)return $this->batchesResult($pdo,$tenantId,$conversationId,$eventId,(string)($context['event_name']??''),(string)($context['event_slug']??''),(string)($context['tenant_slug']??''),0,'Esse lote acabou ou encerrou as vendas. Escolha outro.');
            if($quantity>(int)$batch['available'])return $this->stateResult($pdo,$tenantId,$conversationId,'EVENT_TICKET_QUANTITY',$context,'Restam apenas '.(int)$batch['available'].' ingresso(s) neste lote. Informe outra quantidade.','ticket_quantity_unavailable');
            $customer=$this->customerForConversation($pdo,$tenantId,$conversation);
            $phone=$this->normalizePhone((string)($conversation['phone']??''));
            $name=trim((string)($customer['name']??''));
            if($phone===''||$name==='')return $this->stateResult($pdo,$tenantId,$conversationId,'WELCOME',[],"Não consegui confirmar seu cadastro para reservar os ingressos. Digite *MENU* para recomeçar ou *ATENDENTE* para falar com a equipe.",'ticket_customer_missing');
            $buyer=['name'=>$name,'phone'=>$phone,'email'=>trim((string)($customer['email']??''))];
            $rateSubject='whatsapp-phone:'.hash('sha256',$phone);
            $reservation=(new TicketService())->reservePublic($eventId,$batchId,$quantity,$buyer,null,null,$rateSubject);
            $checkoutUrl=$this->httpsUrl(\app_absolute_url('evento-pedido.php?t='.rawurlencode((string)$reservation['public_token'])));
            $eventName=(string)($context['event_name']??'evento');$batchName=(string)($batch['name']??$context['batch_name']??'lote');$total=(int)($reservation['total_cents']??0);
            if(!empty($reservation['paid'])){
                $tickets=$reservation['tickets']??[];$ticketUrl='';if(is_array($tickets)&&isset($tickets[0]['qr_token']))$ticketUrl=$this->httpsUrl(\app_absolute_url('ingresso.php?t='.rawurlencode((string)$tickets[0]['qr_token'])));
                $reply="✅ *Reserva confirmada — ingresso gratuito*\n\nEvento: {$eventName}\nLote: {$batchName}\nQuantidade: {$quantity}\nTotal: Grátis\n\n".($ticketUrl!==''?'Seu ingresso: '.$ticketUrl."\n\n":"")."O EventMenu Connect enviará a imagem do ingresso com o QR individual para este WhatsApp.\n\nDigite *MENU* para voltar ao início.";
            }else{
                $reply="🎟️ *Sua reserva foi criada!*\n\nEvento: {$eventName}\nLote: {$batchName}\nQuantidade: {$quantity}\nTotal: ".$this->money($total)."\n\nPara concluir a compra com segurança, abra o link oficial e escolha o pagamento disponível:\n{$checkoutUrl}\n\nA reserva é temporária e expira se o pagamento não for concluído no prazo indicado na página. Após a confirmação, o ingresso individual com QR será enviado para este WhatsApp.\n\nDigite *MENU* para voltar ao início.";
            }
            return $this->stateResult($pdo,$tenantId,$conversationId,'WELCOME',[],$reply,'ticket_checkout_created',['ticket_order_id'=>(int)($reservation['order_id']??0)]);
        }

        return ['handled'=>false];
    }

    private function isTicketIntent(string $normalized):bool
    {
        return preg_match('/\\b(ingresso|ingressos|ticket|tickets|eventos|evento)\\b/u',$normalized)===1
            || preg_match('/compr(ar|e|a)\\s+(um\\s+)?ingresso/u',$normalized)===1;
    }

    private function eventsResult(PDO $pdo,int $tenantId,int $conversationId,int $page,string $prefix=''):array
    {
        $rows=$this->availableEvents($pdo,$tenantId,$page);
        if(!$rows){return $this->stateResult($pdo,$tenantId,$conversationId,'WELCOME',[],"No momento não há eventos publicados com ingressos disponíveis para venda.\n\nDigite *MENU* para voltar ao início ou *ATENDENTE* para falar com a equipe.",'ticket_no_events');}
        $lines=[];if($prefix!=='')$lines[]='⚠️ '.$prefix;$lines[]='🎟️ *COMPRAR INGRESSOS*';$lines[]='Escolha o evento:';$lines[]='';
        foreach(array_slice($rows,0,self::PAGE_SIZE)as$i=>$event){$date=trim((string)($event['starts_at']??''));$label=(string)$event['name'];if($date!=='')$label.=' · '.date('d/m H:i',strtotime($date));$lines[]=($i+1).' - '.$label;}
        if(count($rows)>self::PAGE_SIZE)$lines[]='9 - Mais eventos';if($page>0)$lines[]='Digite *ANTERIOR* para voltar.';$lines[]='0 - Voltar ao início';
        return $this->stateResult($pdo,$tenantId,$conversationId,'EVENT_TICKET_EVENTS',['event_page'=>$page],trim(implode("\n",$lines)),'ticket_events');
    }

    private function batchesResult(PDO $pdo,int $tenantId,int $conversationId,int $eventId,string $eventName,string $eventSlug,string $tenantSlug,int $page,string $prefix=''):array
    {
        $event=$this->eventById($pdo,$tenantId,$eventId);
        if(!$event)return $this->eventsResult($pdo,$tenantId,$conversationId,0,'Esse evento não está mais disponível.');
        $rows=$this->availableBatches($pdo,$tenantId,$eventId,$page);
        if(!$rows)return $this->eventsResult($pdo,$tenantId,$conversationId,0,'Este evento não possui lotes disponíveis agora.');
        $eventName=$eventName?:((string)$event['name']);$eventSlug=$eventSlug?:((string)$event['slug']);$tenantSlug=$tenantSlug?:((string)$event['tenant_slug']);
        $lines=[];if($prefix!=='')$lines[]='⚠️ '.$prefix;$lines[]='🎟️ *'. $eventName .'*';$lines[]='Escolha o lote:';$lines[]='';
        foreach(array_slice($rows,0,self::PAGE_SIZE)as$i=>$batch){$type=trim((string)($batch['ticket_type_name']??''));$label=(string)$batch['name'];if($type!=='')$label=$type.' — '.$label;$lines[]=($i+1).' - '.$label.' · '.$this->money((int)$batch['price_cents']).' · '.(int)$batch['available'].' disponível(is)';}
        if(count($rows)>self::PAGE_SIZE)$lines[]='9 - Mais lotes';if($page>0)$lines[]='Digite *ANTERIOR* para voltar.';$lines[]='0 - Escolher outro evento';
        $context=['event_id'=>$eventId,'event_name'=>$eventName,'event_slug'=>$eventSlug,'tenant_slug'=>$tenantSlug,'batch_page'=>$page];
        return $this->stateResult($pdo,$tenantId,$conversationId,'EVENT_TICKET_BATCHES',$context,trim(implode("\n",$lines)),'ticket_batches');
    }

    private function availableEvents(PDO $pdo,int $tenantId,int $page):array
    {
        $offset=max(0,$page)*self::PAGE_SIZE;
        $sql='SELECT e.id,e.name,e.slug,e.starts_at,e.ends_at,e.venue,e.address,t.slug tenant_slug
              FROM events e JOIN tenants t ON t.id=e.tenant_id
              WHERE e.tenant_id=? AND e.status="published" AND t.status="active" AND COALESCE(e.sales_enabled,1)=1
                AND (e.ends_at IS NULL OR e.ends_at>=CURRENT_TIMESTAMP)
                AND EXISTS (SELECT 1 FROM ticket_batches b LEFT JOIN ticket_types tt ON tt.id=b.ticket_type_id AND tt.tenant_id=e.tenant_id AND tt.event_id=e.id
                  WHERE b.event_id=e.id AND b.active=1 AND (tt.id IS NULL OR tt.active=1)
                    AND (b.sales_start IS NULL OR b.sales_start<=CURRENT_TIMESTAMP)
                    AND (b.sales_end IS NULL OR b.sales_end>=CURRENT_TIMESTAMP)
                    AND b.quantity_total>b.quantity_sold+b.quantity_reserved)
              ORDER BY e.starts_at,e.id LIMIT '.(self::PAGE_SIZE+1).' OFFSET '.$offset;
        $q=$pdo->prepare($sql);$q->execute([$tenantId]);return $q->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    private function availableBatches(PDO $pdo,int $tenantId,int $eventId,int $page):array
    {
        if($eventId<1)return[];$offset=max(0,$page)*self::PAGE_SIZE;
        $sql='SELECT b.id,b.name,b.price_cents,b.quantity_total-b.quantity_sold-b.quantity_reserved available,tt.name ticket_type_name,tt.access_area
              FROM ticket_batches b JOIN events e ON e.id=b.event_id AND e.tenant_id=?
              LEFT JOIN ticket_types tt ON tt.id=b.ticket_type_id AND tt.tenant_id=e.tenant_id AND tt.event_id=e.id
              WHERE b.event_id=? AND e.status="published" AND COALESCE(e.sales_enabled,1)=1 AND b.active=1
                AND (tt.id IS NULL OR tt.active=1)
                AND (b.sales_start IS NULL OR b.sales_start<=CURRENT_TIMESTAMP)
                AND (b.sales_end IS NULL OR b.sales_end>=CURRENT_TIMESTAMP)
                AND b.quantity_total>b.quantity_sold+b.quantity_reserved
                AND (e.ends_at IS NULL OR e.ends_at>=CURRENT_TIMESTAMP)
              ORDER BY COALESCE(tt.sort_order,9999),tt.name,b.id LIMIT '.(self::PAGE_SIZE+1).' OFFSET '.$offset;
        $q=$pdo->prepare($sql);$q->execute([$tenantId,$eventId]);return $q->fetchAll(PDO::FETCH_ASSOC)?:[];
    }

    private function batchById(PDO $pdo,int $tenantId,int $eventId,int $batchId):?array
    {
        $sql='SELECT b.id,b.name,b.price_cents,b.quantity_total-b.quantity_sold-b.quantity_reserved available
              FROM ticket_batches b JOIN events e ON e.id=b.event_id AND e.tenant_id=?
              LEFT JOIN ticket_types tt ON tt.id=b.ticket_type_id AND tt.tenant_id=e.tenant_id AND tt.event_id=e.id
              WHERE b.id=? AND b.event_id=? AND b.active=1 AND e.status="published" AND COALESCE(e.sales_enabled,1)=1
                AND (tt.id IS NULL OR tt.active=1) AND (b.sales_start IS NULL OR b.sales_start<=CURRENT_TIMESTAMP)
                AND (b.sales_end IS NULL OR b.sales_end>=CURRENT_TIMESTAMP) AND b.quantity_total>b.quantity_sold+b.quantity_reserved
                AND (e.ends_at IS NULL OR e.ends_at>=CURRENT_TIMESTAMP) LIMIT 1';
        $q=$pdo->prepare($sql);$q->execute([$tenantId,$batchId,$eventId]);$row=$q->fetch(PDO::FETCH_ASSOC);return$row?:null;
    }

    private function eventById(PDO $pdo,int $tenantId,int $eventId):?array
    {
        $q=$pdo->prepare('SELECT e.id,e.name,e.slug,e.starts_at,e.ends_at,t.slug tenant_slug FROM events e JOIN tenants t ON t.id=e.tenant_id WHERE e.id=? AND e.tenant_id=? AND e.status="published" AND t.status="active" AND COALESCE(e.sales_enabled,1)=1 AND (e.ends_at IS NULL OR e.ends_at>=CURRENT_TIMESTAMP) LIMIT 1');
        $q->execute([$eventId,$tenantId]);$row=$q->fetch(PDO::FETCH_ASSOC);return$row?:null;
    }

    private function customerForConversation(PDO $pdo,int $tenantId,array $conversation):array
    {
        $customerId=(int)($conversation['customer_id']??0);
        if($customerId>0){$q=$pdo->prepare('SELECT id,name,phone,email FROM customers WHERE id=? AND tenant_id=? LIMIT 1');$q->execute([$customerId,$tenantId]);$row=$q->fetch(PDO::FETCH_ASSOC);if($row)return$row;}
        $phone=$this->normalizePhone((string)($conversation['phone']??''));if($phone==='')return[];
        $identity=(new CustomerIdentityService())->findByPhone($pdo,$tenantId,$phone);return is_array($identity)?$identity:[];
    }

    private function stateResult(PDO $pdo,int $tenantId,int $conversationId,string $state,array $context,string $reply,string $kind,array $extra=[]):array
    {
        $json=$context?json_encode($context,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR):null;
        $pdo->prepare('UPDATE whatsapp_conversations SET state=?,context_json=?,last_activity_at=CURRENT_TIMESTAMP,updated_at=CURRENT_TIMESTAMP WHERE id=? AND tenant_id=?')->execute([$state,$json,$conversationId,$tenantId]);
        return ['handled'=>true,'state'=>$state,'context'=>$context,'reply'=>$reply,'kind'=>$kind]+$extra;
    }

    private function context(mixed $raw):array
    {
        if(is_array($raw))return$raw;if(!is_string($raw)||trim($raw)==='')return[];$decoded=json_decode($raw,true);return is_array($decoded)?$decoded:[];
    }

    private function normalize(string $text):string
    {
        $value=mb_strtolower(trim($text));$value=preg_replace('/\s+/u',' ',$value)??$value;return trim($value," \t\n\r\0\x0B.!?,;:");
    }

    private function normalizePhone(string $value):string
    {
        $digits=preg_replace('/\D+/','',$value)??'';$digits=ltrim($digits,'0');if(strlen($digits)===10||strlen($digits)===11)$digits='55'.$digits;return preg_match('/^\d{12,15}$/',$digits)?$digits:'';
    }

    private function money(int $cents):string{return 'R$ '.number_format($cents/100,2,',','.');}
    private function httpsUrl(string $url):string
    {
        $parts=parse_url(trim($url));if(!is_array($parts)||empty($parts['host']))return$url;
        $scheme=strtolower((string)($parts['scheme']??''));$host=(string)$parts['host'];$port=isset($parts['port'])?(int)$parts['port']:null;
        if($scheme==='http'&&!in_array(strtolower($host),['localhost','127.0.0.1','::1'],true)){$scheme='https';if($port===80)$port=null;}
        $includePort=$port!==null&&!(($scheme==='https'&&$port===443)||($scheme==='http'&&$port===80));
        $authority=$host.($includePort?':'.$port:'');
        $result=$scheme.'://'.$authority.($parts['path']??'/');if(isset($parts['query']))$result.='?'.$parts['query'];if(isset($parts['fragment']))$result.='#'.$parts['fragment'];return$result;
    }
}
