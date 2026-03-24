<?php
//include ("../../../inc/includes.php");


    function sendTelegramNotification($message) {
        // Recomendo salvar essas configs em uma tabela de configuração ou constante
        $botToken = "8681061435:AAFDezvJSgkfpTnTgahG13FyLInoXllyt2k";
        $chatId   = "8600007386";

        $url = "https://api.telegram.org/bot$botToken/sendMessage";

        $data = [
            'chat_id'    => $chatId,
            'text'       => $message,
            'parse_mode' => 'HTML' // Permite usar <b>, <i>, etc.
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);

        $result = curl_exec($ch);
        curl_close($ch);

        return $result;
    }



                        // Telegram Notification (NOVIDADE)
                       
                        $msg = "🚨 <b>Monitor de Uptime</b>\n";
                        $msg .= "O servidor <b>Teste</b> está OFFLINE!\n";
                        $msg .= "Verificado em: " . date('d/m/Y H:i:s');

                        sendTelegramNotification($msg);