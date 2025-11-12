<?php

namespace App\Helpers;

/**
 * Classe de sanitização de dados
 * 
 * @package App\Helpers
 * @author SES-MS
 * @version 2.0.0
 */
class Sanitizer
{
    /**
     * Sanitiza string removendo caracteres especiais
     * 
     * @param string $value
     * @return string
     */
    public static function string(string $value): string
    {
        // Remove caracteres nulos
        $value = str_replace(chr(0), '', $value);
        
        // Remove espaços em branco extras
        $value = trim($value);
        
        // Converte para UTF-8 se necessário
        if (!mb_check_encoding($value, 'UTF-8')) {
            $value = mb_convert_encoding($value, 'UTF-8', 'auto');
        }
        
        return $value;
    }

    /**
     * Sanitiza CPF removendo formatação
     * 
     * @param string $cpf
     * @return string
     */
    public static function cpf(string $cpf): string
    {
        return preg_replace('/[^0-9]/', '', trim($cpf));
    }

    /**
     * Sanitiza telefone removendo formatação
     * 
     * @param string $phone
     * @return string
     */
    public static function phone(string $phone): string
    {
        return preg_replace('/[^0-9]/', '', trim($phone));
    }

    /**
     * Sanitiza email
     * 
     * @param string $email
     * @return string
     */
    public static function email(string $email): string
    {
        $email = trim(strtolower($email));
        return filter_var($email, FILTER_SANITIZE_EMAIL);
    }

    /**
     * Sanitiza nome próprio
     * 
     * @param string $name
     * @return string
     */
    public static function name(string $name): string
    {
        // Remove caracteres especiais, mantém apenas letras, espaços e acentos
        $name = preg_replace('/[^a-zA-ZÀ-ÿ\s]/', '', trim($name));
        
        // Remove múltiplos espaços
        $name = preg_replace('/\s+/', ' ', $name);
        
        // Primeira letra maiúscula em cada palavra
        return mb_convert_case($name, MB_CASE_TITLE, 'UTF-8');
    }

    /**
     * Sanitiza número inteiro
     * 
     * @param mixed $value
     * @return int
     */
    public static function integer($value): int
    {
        return (int) filter_var($value, FILTER_SANITIZE_NUMBER_INT);
    }

    /**
     * Sanitiza número decimal
     * 
     * @param mixed $value
     * @return float
     */
    public static function float($value): float
    {
        // Substitui vírgula por ponto
        $value = str_replace(',', '.', (string) $value);
        return (float) filter_var($value, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
    }

    /**
     * Sanitiza URL
     * 
     * @param string $url
     * @return string
     */
    public static function url(string $url): string
    {
        return filter_var(trim($url), FILTER_SANITIZE_URL);
    }

    /**
     * Sanitiza HTML removendo tags perigosas
     * 
     * @param string $html
     * @return string
     */
    public static function html(string $html): string
    {
        // Tags permitidas
        $allowedTags = '<p><br><strong><b><em><i><u><ul><ol><li><a><h1><h2><h3><h4><h5><h6>';
        
        // Remove tags não permitidas
        $html = strip_tags($html, $allowedTags);
        
        // Remove atributos perigosos
        $html = preg_replace('/(<[^>]*)(on\w+="[^"]*")/i', '$1', $html);
        $html = preg_replace('/(<[^>]*)(javascript:[^"]*)/i', '$1#', $html);
        
        return trim($html);
    }

    /**
     * Sanitiza entrada para SQL (além do PDO)
     * 
     * @param string $value
     * @return string
     */
    public static function sql(string $value): string
    {
        // Remove caracteres perigosos para SQL
        $dangerous = ['--', ';', '/*', '*/', 'xp_', 'sp_'];
        $value = str_ireplace($dangerous, '', $value);
        
        return trim($value);
    }

    /**
     * Sanitiza nome de arquivo
     * 
     * @param string $filename
     * @return string
     */
    public static function filename(string $filename): string
    {
        // Remove caracteres perigosos para nomes de arquivo
        $filename = preg_replace('/[^a-zA-Z0-9._-]/', '_', $filename);
        
        // Remove múltiplos underscores
        $filename = preg_replace('/_+/', '_', $filename);
        
        // Remove underscore no início e fim
        $filename = trim($filename, '_');
        
        return $filename;
    }

    /**
     * Sanitiza código IBGE
     * 
     * @param string $ibge
     * @return string
     */
    public static function ibge(string $ibge): string
    {
        // Deve ter apenas números e 7 dígitos
        $ibge = preg_replace('/[^0-9]/', '', $ibge);
        return str_pad($ibge, 7, '0', STR_PAD_LEFT);
    }

    /**
     * Sanitiza CNES
     * 
     * @param string $cnes
     * @return string
     */
    public static function cnes(string $cnes): string
    {
        // Remove formatação e mantém apenas números
        return preg_replace('/[^0-9]/', '', $cnes);
    }

    /**
     * Sanitiza data no formato brasileiro
     * 
     * @param string $date
     * @return string|null
     */
    public static function date(string $date): ?string
    {
        $date = trim($date);
        
        // Tenta converter formato brasileiro (DD/MM/YYYY) para ISO (YYYY-MM-DD)
        if (preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', $date, $matches)) {
            $day = $matches[1];
            $month = $matches[2];
            $year = $matches[3];
            
            // Valida se é data válida
            if (checkdate($month, $day, $year)) {
                return "$year-$month-$day";
            }
        }
        
        // Se já está no formato ISO, valida
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            $dateTime = \DateTime::createFromFormat('Y-m-d', $date);
            if ($dateTime && $dateTime->format('Y-m-d') === $date) {
                return $date;
            }
        }
        
        return null;
    }

    /**
     * Sanitiza valor monetário
     * 
     * @param string $value
     * @return float
     */
    public static function money(string $value): float
    {
        // Remove símbolos monetários e formatação
        $value = preg_replace('/[R$\s]/', '', $value);
        
        // Substitui vírgula por ponto
        $value = str_replace(',', '.', $value);
        
        // Remove pontos exceto o último (separador decimal)
        $parts = explode('.', $value);
        if (count($parts) > 2) {
            $decimal = array_pop($parts);
            $integer = implode('', $parts);
            $value = $integer . '.' . $decimal;
        }
        
        return (float) $value;
    }

    /**
     * Sanitiza array recursivamente
     * 
     * @param array $data
     * @param string $method Método de sanitização a aplicar
     * @return array
     */
    public static function array(array $data, string $method = 'string'): array
    {
        $sanitized = [];
        
        foreach ($data as $key => $value) {
            $sanitizedKey = self::string($key);
            
            if (is_array($value)) {
                $sanitized[$sanitizedKey] = self::array($value, $method);
            } else {
                $sanitized[$sanitizedKey] = method_exists(self::class, $method) 
                    ? self::$method($value) 
                    : self::string($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Sanitiza dados de formulário completos
     * 
     * @param array $data
     * @param array $rules Regras específicas por campo
     * @return array
     */
    public static function form(array $data, array $rules = []): array
    {
        $sanitized = [];
        
        foreach ($data as $field => $value) {
            if (is_array($value)) {
                $sanitized[$field] = self::array($value);
                continue;
            }
            
            $rule = $rules[$field] ?? 'string';
            
            switch ($rule) {
                case 'cpf':
                    $sanitized[$field] = self::cpf($value);
                    break;
                case 'phone':
                    $sanitized[$field] = self::phone($value);
                    break;
                case 'email':
                    $sanitized[$field] = self::email($value);
                    break;
                case 'name':
                    $sanitized[$field] = self::name($value);
                    break;
                case 'integer':
                    $sanitized[$field] = self::integer($value);
                    break;
                case 'float':
                    $sanitized[$field] = self::float($value);
                    break;
                case 'money':
                    $sanitized[$field] = self::money($value);
                    break;
                case 'date':
                    $sanitized[$field] = self::date($value);
                    break;
                case 'ibge':
                    $sanitized[$field] = self::ibge($value);
                    break;
                case 'cnes':
                    $sanitized[$field] = self::cnes($value);
                    break;
                case 'html':
                    $sanitized[$field] = self::html($value);
                    break;
                case 'filename':
                    $sanitized[$field] = self::filename($value);
                    break;
                default:
                    $sanitized[$field] = self::string($value);
            }
        }
        
        return $sanitized;
    }

    /**
     * Remove caracteres de controle e invisíveis
     * 
     * @param string $value
     * @return string
     */
    public static function removeControlChars(string $value): string
    {
        // Remove caracteres de controle (0-31 e 127), exceto \t, \n, \r
        return preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $value);
    }

    /**
     * Normaliza espaços em branco
     * 
     * @param string $value
     * @return string
     */
    public static function normalizeSpaces(string $value): string
    {
        // Converte todos os tipos de espaços em branco para espaço normal
        $value = preg_replace('/[\s\x{00A0}\x{1680}\x{2000}-\x{200A}\x{2028}\x{2029}\x{202F}\x{205F}\x{3000}]/u', ' ', $value);
        
        // Remove múltiplos espaços
        $value = preg_replace('/\s+/', ' ', $value);
        
        return trim($value);
    }

    /**
     * Sanitiza entrada para logs (remove informações sensíveis)
     * 
     * @param mixed $data
     * @return mixed
     */
    public static function forLog($data)
    {
        if (is_string($data)) {
            // Remove possíveis senhas, tokens, etc.
            $sensitivePatterns = [
                '/password["\']?\s*[:=]\s*["\']?[^"\']+["\']?/i',
                '/token["\']?\s*[:=]\s*["\']?[^"\']+["\']?/i',
                '/key["\']?\s*[:=]\s*["\']?[^"\']+["\']?/i',
                '/secret["\']?\s*[:=]\s*["\']?[^"\']+["\']?/i',
            ];
            
            foreach ($sensitivePatterns as $pattern) {
                $data = preg_replace($pattern, '[REDACTED]', $data);
            }
            
            return self::string($data);
        }
        
        if (is_array($data)) {
            $sanitized = [];
            foreach ($data as $key => $value) {
                if (in_array(strtolower($key), ['password', 'senha', 'token', 'secret', 'key'])) {
                    $sanitized[$key] = '[REDACTED]';
                } else {
                    $sanitized[$key] = self::forLog($value);
                }
            }
            return $sanitized;
        }
        
        return $data;
    }

    /**
     * Limita tamanho de string para evitar overflow
     * 
     * @param string $value
     * @param int $maxLength
     * @return string
     */
    public static function limitLength(string $value, int $maxLength = 255): string
    {
        if (mb_strlen($value) > $maxLength) {
            return mb_substr($value, 0, $maxLength - 3) . '...';
        }
        
        return $value;
    }
}