<?php

namespace App\Services;

use App\Config\Config;
use Exception;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

/**
 * Serviço de E-mail
 * Gerencia envio de e-mails do sistema
 */
class EmailService
{
    private $mailer;
    private $config;

    public function __construct()
    {
        $this->config = Config::get('email');
        $this->setupMailer();
    }

    /**
     * Configura PHPMailer
     */
    private function setupMailer(): void
    {
        $this->mailer = new PHPMailer(true);

        try {
            // Configurações do servidor
            $this->mailer->isSMTP();
            $this->mailer->Host = $this->config['smtp']['host'];
            $this->mailer->SMTPAuth = true;
            $this->mailer->Username = $this->config['smtp']['username'];
            $this->mailer->Password = $this->config['smtp']['password'];
            $this->mailer->SMTPSecure = $this->config['smtp']['encryption'];
            $this->mailer->Port = $this->config['smtp']['port'];

            // Configurações gerais
            $this->mailer->setFrom($this->config['from']['email'], $this->config['from']['name']);
            $this->mailer->CharSet = 'UTF-8';
            $this->mailer->isHTML(true);

        } catch (Exception $e) {
            error_log("Erro ao configurar PHPMailer: " . $e->getMessage());
        }
    }

    /**
     * Envia e-mail de boas-vindas para novo usuário
     */
    public function sendWelcomeEmail(array $userData, string $temporaryPassword): array
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($userData['email'], $userData['name']);

            $this->mailer->Subject = 'Bem-vindo ao APS Digital - SES/MS';
            
            $body = $this->renderWelcomeTemplate($userData, $temporaryPassword);
            $this->mailer->Body = $body;

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'E-mail de boas-vindas enviado com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de boas-vindas: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail de boas-vindas'
            ];
        }
    }

    /**
     * Envia e-mail de recuperação de senha
     */
    public function sendPasswordResetEmail(array $userData, string $resetToken): array
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($userData['email'], $userData['name']);

            $this->mailer->Subject = 'Redefinição de Senha - APS Digital';
            
            $body = $this->renderPasswordResetTemplate($userData, $resetToken);
            $this->mailer->Body = $body;

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'E-mail de recuperação de senha enviado com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro ao enviar e-mail de recuperação: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar e-mail de recuperação'
            ];
        }
    }

    /**
     * Envia notificação de entrega de equipamentos
     */
    public function sendEquipmentDeliveryNotification(array $deliveryData, array $equipments): array
    {
        try {
            $this->mailer->clearAllRecipients();
            
            // Adicionar destinatários (responsáveis do município)
            if (!empty($deliveryData['recipient_email'])) {
                $this->mailer->addAddress($deliveryData['recipient_email'], $deliveryData['recipient_name']);
            }

            // Adicionar cópia para administradores se configurado
            if (!empty($this->config['notifications']['equipment_delivery'])) {
                foreach ($this->config['notifications']['equipment_delivery'] as $email) {
                    $this->mailer->addBCC($email);
                }
            }

            $this->mailer->Subject = 'Entrega de Equipamentos - APS Digital';
            
            $body = $this->renderEquipmentDeliveryTemplate($deliveryData, $equipments);
            $this->mailer->Body = $body;

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'Notificação de entrega enviada com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro ao enviar notificação de entrega: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar notificação de entrega'
            ];
        }
    }

    /**
     * Envia alerta de garantia vencendo
     */
    public function sendWarrantyExpirationAlert(array $equipments): array
    {
        try {
            if (empty($equipments)) {
                return [
                    'success' => true,
                    'message' => 'Nenhum equipamento com garantia vencendo'
                ];
            }

            $this->mailer->clearAllRecipients();
            
            // Adicionar destinatários configurados
            if (!empty($this->config['notifications']['warranty_expiration'])) {
                foreach ($this->config['notifications']['warranty_expiration'] as $email) {
                    $this->mailer->addAddress($email);
                }
            }

            $this->mailer->Subject = 'Alerta: Garantias de Equipamentos Vencendo - APS Digital';
            
            $body = $this->renderWarrantyExpirationTemplate($equipments);
            $this->mailer->Body = $body;

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'Alerta de garantia enviado com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro ao enviar alerta de garantia: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar alerta de garantia'
            ];
        }
    }

    /**
     * Envia relatório por e-mail
     */
    public function sendReport(array $recipients, string $reportType, string $filePath, array $reportData = []): array
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->clearAttachments();

            // Adicionar destinatários
            foreach ($recipients as $email => $name) {
                $this->mailer->addAddress($email, $name);
            }

            $this->mailer->Subject = "Relatório {$reportType} - APS Digital";
            
            $body = $this->renderReportTemplate($reportType, $reportData);
            $this->mailer->Body = $body;

            // Anexar arquivo se fornecido
            if ($filePath && file_exists($filePath)) {
                $this->mailer->addAttachment($filePath, basename($filePath));
            }

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'Relatório enviado com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro ao enviar relatório: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro ao enviar relatório'
            ];
        }
    }

    /**
     * Renderiza template de e-mail de boas-vindas
     */
    private function renderWelcomeTemplate(array $userData, string $temporaryPassword): string
    {
        $loginUrl = Config::get('app.url') . '/login';
        
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Bem-vindo ao APS Digital</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #0066cc; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .credentials { background-color: #e8f4fd; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #666; color: white; padding: 10px; text-align: center; font-size: 12px; }
                .btn { display: inline-block; background-color: #0066cc; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Bem-vindo ao APS Digital</h1>
                    <p>Sistema de Gestão da Atenção Primária à Saúde - SES/MS</p>
                </div>
                
                <div class='content'>
                    <h2>Olá, {$userData['name']}!</h2>
                    
                    <p>Seu acesso ao APS Digital foi criado com sucesso. Este sistema permitirá que você gerencie equipamentos, fichas de saúde e relatórios de forma integrada.</p>
                    
                    <div class='credentials'>
                        <h3>Suas credenciais de acesso:</h3>
                        <p><strong>CPF:</strong> {$userData['cpf']}</p>
                        <p><strong>Senha temporária:</strong> {$temporaryPassword}</p>
                    </div>
                    
                    <p><strong>Importante:</strong> Por questões de segurança, você deve alterar sua senha no primeiro acesso.</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$loginUrl}' class='btn'>Acessar o Sistema</a>
                    </p>
                    
                    <h3>Próximos passos:</h3>
                    <ol>
                        <li>Acesse o sistema usando o link acima</li>
                        <li>Faça login com seu CPF e senha temporária</li>
                        <li>Altere sua senha para uma pessoal e segura</li>
                        <li>Complete seu perfil com suas informações</li>
                    </ol>
                    
                    <p>Em caso de dúvidas, entre em contato com o suporte técnico através do e-mail: {$this->config['support']['email']}</p>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " SES-MS - Secretaria de Estado de Saúde de Mato Grosso do Sul</p>
                    <p>Este é um e-mail automático. Não responda esta mensagem.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Renderiza template de recuperação de senha
     */
    private function renderPasswordResetTemplate(array $userData, string $resetToken): string
    {
        $resetUrl = Config::get('app.url') . '/reset-password?token=' . $resetToken;
        
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Redefinição de Senha - APS Digital</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #dc3545; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .alert { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #666; color: white; padding: 10px; text-align: center; font-size: 12px; }
                .btn { display: inline-block; background-color: #dc3545; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Redefinição de Senha</h1>
                    <p>APS Digital - SES/MS</p>
                </div>
                
                <div class='content'>
                    <h2>Olá, {$userData['name']}!</h2>
                    
                    <p>Recebemos uma solicitação para redefinir a senha da sua conta no APS Digital.</p>
                    
                    <div class='alert'>
                        <strong>Atenção:</strong> Se você não solicitou esta redefinição, ignore este e-mail. Sua senha permanecerá inalterada.
                    </div>
                    
                    <p>Para criar uma nova senha, clique no botão abaixo:</p>
                    
                    <p style='text-align: center;'>
                        <a href='{$resetUrl}' class='btn'>Redefinir Senha</a>
                    </p>
                    
                    <p>Ou copie e cole o seguinte link no seu navegador:</p>
                    <p style='word-break: break-all; background-color: #e9ecef; padding: 10px; border-radius: 3px;'>{$resetUrl}</p>
                    
                    <p><strong>Este link é válido por 24 horas</strong> e pode ser usado apenas uma vez.</p>
                    
                    <p>Por questões de segurança:</p>
                    <ul>
                        <li>Escolha uma senha forte com pelo menos 8 caracteres</li>
                        <li>Use uma combinação de letras maiúsculas, minúsculas, números e símbolos</li>
                        <li>Não compartilhe sua senha com ninguém</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " SES-MS - Secretaria de Estado de Saúde de Mato Grosso do Sul</p>
                    <p>Este é um e-mail automático. Não responda esta mensagem.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Renderiza template de notificação de entrega de equipamentos
     */
    private function renderEquipmentDeliveryTemplate(array $deliveryData, array $equipments): string
    {
        $equipmentList = '';
        foreach ($equipments as $equipment) {
            $equipmentList .= "<li>{$equipment['equipment_type']} - {$equipment['serial_number']}</li>";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Entrega de Equipamentos - APS Digital</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #28a745; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .info-box { background-color: #d4edda; border: 1px solid #c3e6cb; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #666; color: white; padding: 10px; text-align: center; font-size: 12px; }
                .equipment-list { background-color: white; padding: 15px; border-radius: 5px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Entrega de Equipamentos</h1>
                    <p>APS Digital - SES/MS</p>
                </div>
                
                <div class='content'>
                    <h2>Notificação de Entrega</h2>
                    
                    <p>Informamos que foi realizada a entrega de equipamentos com os seguintes detalhes:</p>
                    
                    <div class='info-box'>
                        <h3>Informações da Entrega</h3>
                        <p><strong>Município:</strong> {$deliveryData['municipality_name']}</p>
                        <p><strong>Destinatário:</strong> {$deliveryData['recipient_name']}</p>
                        <p><strong>CPF:</strong> {$deliveryData['recipient_cpf']}</p>
                        <p><strong>Data da Entrega:</strong> " . date('d/m/Y', strtotime($deliveryData['delivery_date'])) . "</p>
                        " . (!empty($deliveryData['notes']) ? "<p><strong>Observações:</strong> {$deliveryData['notes']}</p>" : "") . "
                    </div>
                    
                    <div class='equipment-list'>
                        <h3>Equipamentos Entregues</h3>
                        <ul>{$equipmentList}</ul>
                        <p><strong>Total de itens:</strong> " . count($equipments) . "</p>
                    </div>
                    
                    <p>O destinatário deve:</p>
                    <ul>
                        <li>Verificar se todos os equipamentos estão funcionando corretamente</li>
                        <li>Manter os equipamentos em local seguro</li>
                        <li>Comunicar imediatamente qualquer problema ou dano</li>
                        <li>Utilizar os equipamentos exclusivamente para atividades da APS</li>
                    </ul>
                    
                    <p>Em caso de dúvidas ou problemas, entre em contato através do e-mail: {$this->config['support']['email']}</p>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " SES-MS - Secretaria de Estado de Saúde de Mato Grosso do Sul</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Renderiza template de alerta de garantia vencendo
     */
    private function renderWarrantyExpirationTemplate(array $equipments): string
    {
        $equipmentList = '';
        foreach ($equipments as $equipment) {
            $daysToExpiry = $equipment['days_to_expiry'];
            $equipmentList .= "
                <tr>
                    <td>{$equipment['serial_number']}</td>
                    <td>{$equipment['equipment_type']}</td>
                    <td>" . ($equipment['municipality_name'] ?? 'N/A') . "</td>
                    <td>" . date('d/m/Y', strtotime($equipment['warranty_expires_at'])) . "</td>
                    <td>{$daysToExpiry} dias</td>
                </tr>";
        }
        
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Alerta: Garantias Vencendo - APS Digital</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 800px; margin: 0 auto; padding: 20px; }
                .header { background-color: #ffc107; color: #212529; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .alert { background-color: #fff3cd; border: 1px solid #ffeeba; color: #856404; padding: 15px; border-radius: 5px; margin: 20px 0; }
                .footer { background-color: #666; color: white; padding: 10px; text-align: center; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; background-color: white; }
                th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
                th { background-color: #f8f9fa; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>⚠️ Alerta: Garantias Vencendo</h1>
                    <p>APS Digital - SES/MS</p>
                </div>
                
                <div class='content'>
                    <div class='alert'>
                        <h3>Atenção Necessária</h3>
                        <p>Os seguintes equipamentos possuem garantia que vencerá nos próximos dias. É recomendado entrar em contato com os fornecedores para verificar as condições de renovação ou extensão da garantia.</p>
                    </div>
                    
                    <h3>Equipamentos com Garantia Vencendo</h3>
                    
                    <table>
                        <thead>
                            <tr>
                                <th>Número de Série</th>
                                <th>Tipo</th>
                                <th>Município</th>
                                <th>Vencimento</th>
                                <th>Dias Restantes</th>
                            </tr>
                        </thead>
                        <tbody>
                            {$equipmentList}
                        </tbody>
                    </table>
                    
                    <p><strong>Total de equipamentos:</strong> " . count($equipments) . "</p>
                    
                    <h3>Ações Recomendadas</h3>
                    <ul>
                        <li>Entrar em contato com os fornecedores para negociar extensão da garantia</li>
                        <li>Verificar se há necessidade de manutenção preventiva</li>
                        <li>Documentar o estado atual dos equipamentos</li>
                        <li>Considerar a aquisição de equipamentos de reposição se necessário</li>
                    </ul>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " SES-MS - Secretaria de Estado de Saúde de Mato Grosso do Sul</p>
                    <p>Este é um alerta automático gerado pelo sistema APS Digital</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Renderiza template de envio de relatório
     */
    private function renderReportTemplate(string $reportType, array $reportData): string
    {
        $reportTypeNames = [
            'health_forms' => 'Fichas de Saúde',
            'equipment' => 'Equipamentos',
            'users' => 'Usuários',
            'executive' => 'Executivo'
        ];
        
        $reportName = $reportTypeNames[$reportType] ?? $reportType;
        
        return "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <title>Relatório {$reportName} - APS Digital</title>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background-color: #17a2b8; color: white; padding: 20px; text-align: center; }
                .content { background-color: #f9f9f9; padding: 20px; }
                .footer { background-color: #666; color: white; padding: 10px; text-align: center; font-size: 12px; }
                .info-box { background-color: #d1ecf1; border: 1px solid #bee5eb; padding: 15px; border-radius: 5px; margin: 20px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Relatório {$reportName}</h1>
                    <p>APS Digital - SES/MS</p>
                </div>
                
                <div class='content'>
                    <h2>Relatório Solicitado</h2>
                    
                    <p>Conforme solicitado, segue em anexo o relatório de <strong>{$reportName}</strong> gerado pelo sistema APS Digital.</p>
                    
                    <div class='info-box'>
                        <h3>Informações do Relatório</h3>
                        <p><strong>Tipo:</strong> {$reportName}</p>
                        <p><strong>Gerado em:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p><strong>Formato:</strong> CSV (Excel compatível)</p>
                    </div>
                    
                    <p>O arquivo em anexo contém os dados consolidados e pode ser aberto diretamente no Microsoft Excel ou LibreOffice Calc.</p>
                    
                    <h3>Importante:</h3>
                    <ul>
                        <li>Este relatório contém informações confidenciais</li>
                        <li>Mantenha a segurança dos dados em conformidade com a LGPD</li>
                        <li>Não compartilhe este relatório com pessoas não autorizadas</li>
                    </ul>
                    
                    <p>Em caso de dúvidas sobre o relatório, entre em contato através do e-mail: {$this->config['support']['email']}</p>
                </div>
                
                <div class='footer'>
                    <p>© " . date('Y') . " SES-MS - Secretaria de Estado de Saúde de Mato Grosso do Sul</p>
                    <p>Este é um e-mail automático. Não responda esta mensagem.</p>
                </div>
            </div>
        </body>
        </html>";
    }

    /**
     * Envia e-mail de teste para verificar configurações
     */
    public function sendTestEmail(string $to): array
    {
        try {
            $this->mailer->clearAllRecipients();
            $this->mailer->addAddress($to);

            $this->mailer->Subject = 'Teste de Configuração - APS Digital';
            $this->mailer->Body = "
                <h2>Teste de E-mail</h2>
                <p>Este é um e-mail de teste para verificar se as configurações estão funcionando corretamente.</p>
                <p><strong>Data/Hora:</strong> " . date('d/m/Y H:i:s') . "</p>
                <p><strong>Sistema:</strong> APS Digital - SES/MS</p>
            ";

            $this->mailer->send();

            return [
                'success' => true,
                'message' => 'E-mail de teste enviado com sucesso'
            ];

        } catch (Exception $e) {
            error_log("Erro no teste de e-mail: " . $e->getMessage());
            return [
                'success' => false,
                'message' => 'Erro no teste de e-mail: ' . $e->getMessage()
            ];
        }
    }
}