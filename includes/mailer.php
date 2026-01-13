<?php
// includes/mailer.php

/**
 * Envia e-mails transacionais com layout profissional.
 * * @param string $para E-mail do destinatário
 * @param string $assunto Assunto do e-mail
 * @param string $titulo Título em destaque no corpo (H1)
 * @param string $mensagem Texto da mensagem (aceita quebra de linha)
 * @param string|null $link_botao (Opcional) URL para o botão de ação
 * @param string $texto_botao (Opcional) Texto do botão
 */
function enviarNotificacao($para, $assunto, $titulo, $mensagem, $link_botao = null, $texto_botao = "Acessar Portal") {
    
    if (empty($para)) {
        error_log("Mailer Erro: E-mail de destino vazio.");
        return false;
    }

    // --- CONFIGURAÇÕES ---
    // Idealmente, use um e-mail real autenticado da sua hospedagem
    $remetente_email = "nao-responda@wecareconsultoria.com.br"; 
    $remetente_nome  = "WeCare Consultoria";
    $logo_url        = "https://wecareconsultoria.com.br/assets/img/logo.png"; // Coloque o link real do seu logo aqui
    $cor_primaria    = "#0e2a47"; // Azul da WeCare

    // --- LAYOUT DO E-MAIL (Responsivo e Moderno) ---
    $corpo = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <meta name='viewport' content='width=device-width, initial-scale=1.0'>
        <style>
            body { background-color: #f4f6f8; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; margin: 0; padding: 0; line-height: 1.6; color: #555; }
            .container { max-width: 600px; margin: 30px auto; background: #ffffff; border-radius: 8px; box-shadow: 0 4px 10px rgba(0,0,0,0.05); overflow: hidden; }
            
            /* Cabeçalho com Logo */
            .header { background-color: #ffffff; padding: 30px 20px 10px 20px; text-align: center; border-bottom: 3px solid $cor_primaria; }
            .logo { max-height: 50px; width: auto; }
            .brand-text { font-size: 20px; font-weight: bold; color: $cor_primaria; text-decoration: none; display: block; }
            
            /* Conteúdo */
            .content { padding: 40px 30px; }
            h2 { color: #333; margin-top: 0; font-size: 22px; }
            p { margin-bottom: 20px; font-size: 16px; color: #666; }
            
            /* Botão */
            .btn-container { text-align: center; margin: 30px 0; }
            .btn { background-color: $cor_primaria; color: #ffffff !important; padding: 12px 30px; border-radius: 50px; text-decoration: none; font-weight: bold; font-size: 16px; display: inline-block; box-shadow: 0 2px 5px rgba(0,0,0,0.2); }
            .btn:hover { opacity: 0.9; }
            
            /* Rodapé */
            .footer { background-color: #f8f9fa; padding: 20px; text-align: center; font-size: 12px; color: #999; border-top: 1px solid #eee; }
            .footer a { color: #999; text-decoration: none; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <span class='brand-text'>$remetente_nome</span>
            </div>
            
            <div class='content'>
                <h2>$titulo</h2>
                <p>Olá,</p>
                <div style='color: #444;'>
                    " . nl2br($mensagem) . "
                </div>
                
                " . ($link_botao ? "
                <div class='btn-container'>
                    <a href='$link_botao' class='btn'>$texto_botao</a>
                </div>
                " : "") . "
                
                <p style='font-size: 14px; margin-top: 30px; color: #888;'>
                    Se você não solicitou este e-mail, por favor ignore-o.
                </p>
            </div>
            
            <div class='footer'>
                &copy; " . date('Y') . " $remetente_nome.<br>
                Este é um e-mail automático do sistema RatControl.
            </div>
        </div>
    </body>
    </html>
    ";

    // Cabeçalhos (Headers) para evitar Spam
    $headers  = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type: text/html; charset=UTF-8" . "\r\n";
    $headers .= "From: $remetente_nome <$remetente_email>" . "\r\n";
    $headers .= "Reply-To: $remetente_email" . "\r\n";
    $headers .= "X-Mailer: PHP/" . phpversion();

    // Envio com tratamento de erro básico
    if(mail($para, $assunto, $corpo, $headers)) {
        return true;
    } else {
        error_log("Mailer Falha: Não foi possível enviar e-mail para $para");
        return false;
    }
}
?>