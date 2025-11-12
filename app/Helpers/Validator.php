<?php

namespace App\Helpers;

/**
 * Classe de validação de dados de entrada
 * 
 * @package App\Helpers
 * @author SES-MS
 * @version 2.0.0
 */
class Validator
{
    private array $data = [];
    private array $rules = [];
    private array $messages = [];
    private array $errors = [];

    /**
     * Construtor
     * 
     * @param array $data
     */
    public function __construct(array $data = [])
    {
        $this->data = $data;
    }

    /**
     * Define dados para validação
     * 
     * @param array $data
     * @return self
     */
    public function setData(array $data): self
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Define regras de validação
     * 
     * @param array $rules
     * @return self
     */
    public function rules(array $rules): self
    {
        $this->rules = $rules;
        return $this;
    }

    /**
     * Define mensagens customizadas
     * 
     * @param array $messages
     * @return self
     */
    public function messages(array $messages): self
    {
        $this->messages = $messages;
        return $this;
    }

    /**
     * Executa validação
     * 
     * @return bool
     */
    public function validate(): bool
    {
        $this->errors = [];

        foreach ($this->rules as $field => $rules) {
            $this->validateField($field, $rules);
        }

        return empty($this->errors);
    }

    /**
     * Obtém erros de validação
     * 
     * @return array
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Obtém primeiro erro de um campo
     * 
     * @param string $field
     * @return string|null
     */
    public function getError(string $field): ?string
    {
        return $this->errors[$field][0] ?? null;
    }

    /**
     * Verifica se tem erros
     * 
     * @return bool
     */
    public function hasErrors(): bool
    {
        return !empty($this->errors);
    }

    /**
     * Validação estática rápida
     * 
     * @param array $data
     * @param array $rules
     * @param array $messages
     * @return array
     */
    public static function make(array $data, array $rules, array $messages = []): array
    {
        $validator = new self($data);
        $validator->rules($rules)->messages($messages);
        
        return [
            'valid' => $validator->validate(),
            'errors' => $validator->getErrors()
        ];
    }

    /**
     * Valida um campo específico
     * 
     * @param string $field
     * @param array|string $rules
     */
    private function validateField(string $field, $rules): void
    {
        if (is_string($rules)) {
            $rules = explode('|', $rules);
        }

        $value = $this->data[$field] ?? null;

        foreach ($rules as $rule) {
            $this->applyRule($field, $value, $rule);
        }
    }

    /**
     * Aplica uma regra de validação
     * 
     * @param string $field
     * @param mixed $value
     * @param string $rule
     */
    private function applyRule(string $field, $value, string $rule): void
    {
        // Parse regra com parâmetros (ex: min:5)
        $parts = explode(':', $rule, 2);
        $ruleName = $parts[0];
        $parameter = $parts[1] ?? null;

        // Se já tem erro neste campo e é regra que depende de valor, pula
        if (isset($this->errors[$field]) && in_array($ruleName, ['min', 'max', 'email', 'cpf'])) {
            return;
        }

        switch ($ruleName) {
            case 'required':
                $this->validateRequired($field, $value);
                break;
            case 'email':
                $this->validateEmail($field, $value);
                break;
            case 'cpf':
                $this->validateCPF($field, $value);
                break;
            case 'min':
                $this->validateMin($field, $value, (int)$parameter);
                break;
            case 'max':
                $this->validateMax($field, $value, (int)$parameter);
                break;
            case 'numeric':
                $this->validateNumeric($field, $value);
                break;
            case 'integer':
                $this->validateInteger($field, $value);
                break;
            case 'date':
                $this->validateDate($field, $value);
                break;
            case 'in':
                $this->validateIn($field, $value, explode(',', $parameter));
                break;
            case 'regex':
                $this->validateRegex($field, $value, $parameter);
                break;
            case 'confirmed':
                $this->validateConfirmed($field, $value);
                break;
            case 'unique':
                $this->validateUnique($field, $value, $parameter);
                break;
            case 'phone':
                $this->validatePhone($field, $value);
                break;
            case 'cns':
                $this->validateCNS($field, $value);
                break;
        }
    }

    /**
     * Valida campo obrigatório
     */
    private function validateRequired(string $field, $value): void
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            $this->addError($field, 'required', 'O campo :field é obrigatório.');
        }
    }

    /**
     * Valida email
     */
    private function validateEmail(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
            $this->addError($field, 'email', 'O campo :field deve ser um email válido.');
        }
    }

    /**
     * Valida CPF
     */
    private function validateCPF(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !Security::validateCPF($value)) {
            $this->addError($field, 'cpf', 'O campo :field deve ser um CPF válido.');
        }
    }

    /**
     * Valida tamanho mínimo
     */
    private function validateMin(string $field, $value, int $min): void
    {
        if ($value !== null && $value !== '') {
            $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? $value : 0);
            if ($length < $min) {
                $this->addError($field, 'min', "O campo :field deve ter pelo menos {$min} caracteres.");
            }
        }
    }

    /**
     * Valida tamanho máximo
     */
    private function validateMax(string $field, $value, int $max): void
    {
        if ($value !== null && $value !== '') {
            $length = is_string($value) ? mb_strlen($value) : (is_numeric($value) ? $value : 0);
            if ($length > $max) {
                $this->addError($field, 'max', "O campo :field deve ter no máximo {$max} caracteres.");
            }
        }
    }

    /**
     * Valida se é numérico
     */
    private function validateNumeric(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !is_numeric($value)) {
            $this->addError($field, 'numeric', 'O campo :field deve ser um número.');
        }
    }

    /**
     * Valida se é inteiro
     */
    private function validateInteger(string $field, $value): void
    {
        if ($value !== null && $value !== '' && !filter_var($value, FILTER_VALIDATE_INT)) {
            $this->addError($field, 'integer', 'O campo :field deve ser um número inteiro.');
        }
    }

    /**
     * Valida data
     */
    private function validateDate(string $field, $value): void
    {
        if ($value !== null && $value !== '') {
            $date = \DateTime::createFromFormat('Y-m-d', $value);
            if (!$date || $date->format('Y-m-d') !== $value) {
                $this->addError($field, 'date', 'O campo :field deve ser uma data válida (YYYY-MM-DD).');
            }
        }
    }

    /**
     * Valida se valor está na lista
     */
    private function validateIn(string $field, $value, array $options): void
    {
        if ($value !== null && $value !== '' && !in_array($value, $options)) {
            $optionsStr = implode(', ', $options);
            $this->addError($field, 'in', "O campo :field deve ser um dos seguintes valores: {$optionsStr}.");
        }
    }

    /**
     * Valida regex
     */
    private function validateRegex(string $field, $value, string $pattern): void
    {
        if ($value !== null && $value !== '' && !preg_match($pattern, $value)) {
            $this->addError($field, 'regex', 'O campo :field tem formato inválido.');
        }
    }

    /**
     * Valida confirmação de campo (ex: password_confirmation)
     */
    private function validateConfirmed(string $field, $value): void
    {
        $confirmField = $field . '_confirmation';
        $confirmValue = $this->data[$confirmField] ?? null;
        
        if ($value !== $confirmValue) {
            $this->addError($field, 'confirmed', 'Os campos :field e confirmação não coincidem.');
        }
    }

    /**
     * Valida unicidade no banco (parâmetro: tabela,coluna)
     */
    private function validateUnique(string $field, $value, string $parameter): void
    {
        if ($value === null || $value === '') {
            return;
        }

        $parts = explode(',', $parameter);
        $table = $parts[0];
        $column = $parts[1] ?? $field;
        $except = $parts[2] ?? null; // ID para excluir da validação (updates)

        try {
            $query = "SELECT COUNT(*) FROM {$table} WHERE {$column} = ?";
            $params = [$value];

            if ($except) {
                $query .= " AND id != ?";
                $params[] = $except;
            }

            $count = \App\Config\Database::fetch($query, $params);
            
            if ($count && $count['count'] > 0) {
                $this->addError($field, 'unique', 'Este :field já está em uso.');
            }
        } catch (\Exception $e) {
            // Se der erro na consulta, assume que é válido por segurança
        }
    }

    /**
     * Valida telefone brasileiro
     */
    private function validatePhone(string $field, $value): void
    {
        if ($value !== null && $value !== '') {
            // Remove formatação
            $phone = preg_replace('/[^0-9]/', '', $value);
            
            // Deve ter 10 ou 11 dígitos
            if (strlen($phone) < 10 || strlen($phone) > 11) {
                $this->addError($field, 'phone', 'O campo :field deve ser um telefone válido.');
                return;
            }

            // Primeiro dígito deve ser válido para celular (9) se for 11 dígitos
            if (strlen($phone) === 11 && $phone[2] !== '9') {
                $this->addError($field, 'phone', 'O campo :field deve ser um telefone válido.');
            }
        }
    }

    /**
     * Valida CNS (Cartão Nacional de Saúde)
     */
    private function validateCNS(string $field, $value): void
    {
        if ($value !== null && $value !== '') {
            // Remove formatação
            $cns = preg_replace('/[^0-9]/', '', $value);
            
            // Deve ter 15 dígitos
            if (strlen($cns) !== 15) {
                $this->addError($field, 'cns', 'O campo :field deve ter 15 dígitos.');
                return;
            }

            // Validação específica do CNS
            if (!$this->isValidCNS($cns)) {
                $this->addError($field, 'cns', 'O campo :field deve ser um CNS válido.');
            }
        }
    }

    /**
     * Algoritmo de validação do CNS
     */
    private function isValidCNS(string $cns): bool
    {
        // CNS provisório (começa com 7, 8 ou 9)
        if (in_array($cns[0], ['7', '8', '9'])) {
            $sum = 0;
            for ($i = 0; $i < 15; $i++) {
                $sum += $cns[$i] * (15 - $i);
            }
            return ($sum % 11) === 0;
        }
        
        // CNS definitivo (começa com 1 ou 2)
        if (in_array($cns[0], ['1', '2'])) {
            $sum = 0;
            for ($i = 0; $i < 11; $i++) {
                $sum += $cns[$i] * (15 - $i);
            }
            
            $remainder = $sum % 11;
            $dv = $remainder < 2 ? 0 : 11 - $remainder;
            
            if ($dv === 10) {
                $sum += 2;
                $remainder = $sum % 11;
                $dv = $remainder < 2 ? 0 : 11 - $remainder;
                $secondDV = $dv === 10 ? 0 : $dv;
                
                return $cns[11] == 0 && $cns[12] == 0 && 
                       $cns[13] == 1 && $cns[14] == $secondDV;
            } else {
                return $cns[11] == $dv;
            }
        }
        
        return false;
    }

    /**
     * Adiciona erro de validação
     * 
     * @param string $field
     * @param string $rule
     * @param string $message
     */
    private function addError(string $field, string $rule, string $message): void
    {
        // Verifica se há mensagem customizada
        $customKey = "{$field}.{$rule}";
        if (isset($this->messages[$customKey])) {
            $message = $this->messages[$customKey];
        } elseif (isset($this->messages[$rule])) {
            $message = $this->messages[$rule];
        }

        // Substitui placeholder do campo
        $message = str_replace(':field', $this->getFieldName($field), $message);

        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        $this->errors[$field][] = $message;
    }

    /**
     * Obtém nome amigável do campo
     * 
     * @param string $field
     * @return string
     */
    private function getFieldName(string $field): string
    {
        $fieldNames = [
            'cpf' => 'CPF',
            'email' => 'E-mail',
            'password' => 'Senha',
            'password_confirmation' => 'Confirmação de senha',
            'name' => 'Nome',
            'nome' => 'Nome',
            'telefone' => 'Telefone',
            'phone' => 'Telefone',
            'cns_profissional' => 'CNS Profissional',
            'municipio' => 'Município',
            'ibge' => 'Código IBGE',
            'cnes' => 'CNES',
            'imei' => 'IMEI',
            'iccid' => 'ICCID',
            'competencia' => 'Competência',
        ];

        return $fieldNames[$field] ?? ucfirst(str_replace('_', ' ', $field));
    }

    /**
     * Validações de arquivo
     * 
     * @param array $file Array $_FILES
     * @param array $rules Regras específicas para arquivo
     * @return array
     */
    public static function validateFile(array $file, array $rules = []): array
    {
        $errors = [];

        // Verifica se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = 'Arquivo muito grande.';
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = 'Upload incompleto.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = 'Nenhum arquivo enviado.';
                    break;
                default:
                    $errors[] = 'Erro no upload do arquivo.';
            }
            return ['valid' => false, 'errors' => $errors];
        }

        // Validações específicas
        foreach ($rules as $rule => $value) {
            switch ($rule) {
                case 'max_size':
                    if ($file['size'] > $value) {
                        $maxMB = round($value / 1048576, 2);
                        $errors[] = "Arquivo deve ter no máximo {$maxMB}MB.";
                    }
                    break;
                case 'extensions':
                    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                    if (!in_array($ext, $value)) {
                        $allowed = implode(', ', $value);
                        $errors[] = "Extensão não permitida. Use: {$allowed}";
                    }
                    break;
                case 'mime_types':
                    if (!in_array($file['type'], $value)) {
                        $errors[] = 'Tipo de arquivo não permitido.';
                    }
                    break;
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}