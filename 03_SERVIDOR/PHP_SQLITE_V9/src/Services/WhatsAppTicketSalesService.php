<?php

declare(strict_types=1);

namespace EventMenu\Services;

use PDO;
use Throwable;

/** Publica links de compra de ingressos diretamente na conversa do WhatsApp.
 * A reserva, o pagamento e a emissão continuam passando pelo checkout oficial do evento.
 */
final class WhatsAppTicketSalesService
{
    public function isTicketIntent(string $text): bool
    {
        $value=mb_strtolower(trim($text));
        $value=preg_replace('/\s+/u',' ',$value)??$value;
        $value=trim($value," \t\n\r\0\x0B.!?,;:");
        return in_array($value,[
            'ingresso','ingressos','comprar ingresso','comprar ingressos',
            'quero ingresso','quero comprar ingresso','quero comprar ingressos',
            'ver eventos','eventos','ver ingressos','bilhete','bilhetes','ticket','tickets',
        ],true);
    }

    public function availableEvents(PDO $pdo,int $tenantId):string
    {
        if($tenantId<1)return 'Não consegui identificar a empresa para consultar os ingressos. Digite *ATENDENTE* para falar com a equipe.';
        if(!\EventMenu\Core\TenantFeatures::events($tenantId))return 'A venda de ingressos não está habilitada para esta empresa no momento. Digite *ATENDENTE* para falar com a equipe.';
        $tenant=$pdo->prepare('SELECT slug,name FROM tenants WHERE id=? AND status="active" LIMIT 1');
        $tenant->execute([$tenantId]);
        $tenantRow=$tenant->fetch(PDO::FETCH_ASSOC);
        if(!$tenantRow)return 'A empresa está temporariamente indisponível para venda de ingressos.';

        $sql='SELECT e.id,e.name,e.slug,e.starts_at,e.venue,e.address
              FROM events e
              WHERE e.tenant_id=? AND e.status="published" AND e.sales_enabled=1
                AND ((e.ends_at IS NOT NULL AND e.ends_at>=CURRENT_TIMESTAMP) OR (e.ends_at IS NULL AND e.starts_at>=?))
                AND (e.capacity_total IS NULL OR e.capacity_total > (
                    (SELECT COUNT(*) FROM tickets t WHERE t.tenant_id=e.tenant_id AND t.event_id=e.id AND t.status IN ("reserved","paid","checked_in"))
                    + (SELECT COALESCE(SUM(1+COALESCE(g.plus_ones,0)),0) FROM event_guests g WHERE g.tenant_id=e.tenant_id AND g.event_id=e.id AND g.status IN ("invited","checked_in"))
                ))
                AND EXISTS (
                    SELECT 1 FROM ticket_batches b LEFT JOIN ticket_types tt ON tt.id=b.ticket_type_id
                    WHERE b.event_id=e.id AND b.active=1
                      AND (b.ticket_type_id IS NULL OR (tt.id IS NOT NULL AND tt.active=1 AND (tt.capacity_total IS NULL OR tt.capacity_total > (
                          SELECT COUNT(*) FROM tickets t JOIN ticket_batches b2 ON b2.id=t.batch_id
                          WHERE t.tenant_id=e.tenant_id AND t.event_id=e.id AND b2.ticket_type_id=tt.id AND t.status IN ("reserved","paid","checked_in")
                      ))))
                      AND (b.sales_start IS NULL OR b.sales_start<=CURRENT_TIMESTAMP)
                      AND (b.sales_end IS NULL OR b.sales_end>=CURRENT_TIMESTAMP)
                      AND b.quantity_total>(b.quantity_sold+COALESCE(b.quantity_reserved,0))
                )
              ORDER BY e.starts_at ASC,e.id ASC LIMIT 6';
        try{$q=$pdo->prepare($sql);$q->execute([$tenantId,date('Y-m-d H:i:s',time()-86400)]);$events=$q->fetchAll(PDO::FETCH_ASSOC)?:[];}
        catch(Throwable $e){error_log('[eventmenu-whatsapp-ticket-sales] '.$e->getMessage());return 'Não consegui consultar os ingressos agora. Tente novamente em instantes ou digite *ATENDENTE* para falar com a equipe.';}
        if(!$events)return '🎟️ No momento, não há eventos com ingressos disponíveis para compra.\n\nDigite *MENU* para voltar ao início ou *ATENDENTE* para falar com a equipe.';

        $lines=['🎟️ *INGRESSOS DISPONÍVEIS*',''];
        foreach($events as$event){
            $date=strtotime((string)($event['starts_at']??''));
            $when=$date!==false?date('d/m/Y \à\s H:i',$date):'';
            $lines[]='*'.trim((string)$event['name']).'*'.($when!==''?' — '.$when:'');
            $place=trim((string)($event['venue']??''));
            $address=trim((string)($event['address']??''));
            $location=implode(' · ',array_values(array_filter([$place,$address],static fn(string $v):bool=>$v!=='')));
            if($location!=='')$lines[]='Local: '.$location;
            $url=\app_absolute_url('evento.php?empresa='.rawurlencode((string)$tenantRow['slug']).'&evento='.rawurlencode((string)$event['slug']));
            $lines[]='Comprar ingresso: '.$url;
            $lines[]='';
        }
        $lines[]='Abra o link do evento para escolher o lote e pagar com segurança.';
        $lines[]='Use o mesmo número deste WhatsApp no checkout. Após a confirmação do pagamento, enviaremos aqui o ingresso individual com o QR Code correspondente.';
        return trim(implode("\n",$lines));
    }
}
