<?php

namespace App\Models;

use App\Config\Database;
use App\Helpers\Security;
use App\Helpers\Sanitizer;

/**
 * Model de Usuários
 * 
 * @package App\Models
 * @author SES-MS
 * @version 2.0.0
 */
class User
{
    private int $id;
    private string $nome;
    private string $cpf;
    private string $email;
    private string $senha;
    private ?string $telefone;
    private ?string $cnsPerofissional;
    private ?string $foto;
    private bool $ativo;
    private \DateTime $dtCadastro;
    private ?\DateTime $dtUltimoAcesso;

    /**
     * Encontra usuário por ID
     * 
     * @param int $id
     * @return self|null
     */
    public static function find(int $id): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_usuarios WHERE id = ?",
            [$id]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Encontra usuário por CPF
     * 
     * @param string $cpf
     * @return self|null
     */
    public static function findByCPF(string $cpf): ?self
    {
        $cpf = Sanitizer::cpf($cpf);
        
        $data = Database::fetch(
            "SELECT * FROM tb_usuarios WHERE cpf = ?",
            [$cpf]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Encontra usuário por email
     * 
     * @param string $email
     * @return self|null
     */
    public static function findByEmail(string $email): ?self
    {
        $email = Sanitizer::email($email);
        
        $data = Database::fetch(
            "SELECT * FROM tb_usuarios WHERE email = ?",
            [$email]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Lista todos os usuários com paginação
     * 
     * @param int $page
     * @param int $perPage
     * @param array $filters
     * @return array
     */
    public static function paginate(int $page = 1, int $perPage = 20, array $filters = []): array
    {
        $offset = ($page - 1) * $perPage;
        $where = self::buildWhereClause($filters);
        
        // Total de registros
        $total = Database::fetch(
            "SELECT COUNT(*) as count FROM tb_usuarios WHERE {$where['clause']}",
            $where['params']
        )['count'];

        // Dados da página
        $users = Database::fetchAll(
            "SELECT u.*, 
                    COUNT(p.id) as total_perfis,
                    COUNT(CASE WHEN p.ativo = true THEN 1 END) as perfis_ativos
             FROM tb_usuarios u
             LEFT JOIN tb_perfil_usuario p ON u.id = p.id_usuario
             WHERE {$where['clause']}
             GROUP BY u.id, u.nome, u.cpf, u.email, u.ativo, u.dt_cadastro
             ORDER BY u.dt_cadastro DESC
             LIMIT ? OFFSET ?",
            array_merge($where['params'], [$perPage, $offset])
        );

        return [
            'data' => array_map([self::class, 'fromArray'], $users),
            'total' => $total,
            'per_page' => $perPage,
            'current_page' => $page,
            'last_page' => ceil($total / $perPage)
        ];
    }

    /**
     * Constrói cláusula WHERE para filtros
     * 
     * @param array $filters
     * @return array
     */
    private static function buildWhereClause(array $filters): array
    {
        $conditions = ['1 = 1']; // Base condition
        $params = [];

        if (!empty($filters['search'])) {
            $search = '%' . $filters['search'] . '%';
            $conditions[] = "(nome ILIKE ? OR cpf LIKE ? OR email ILIKE ?)";
            $params = array_merge($params, [$search, $search, $search]);
        }

        if (isset($filters['ativo'])) {
            $conditions[] = "ativo = ?";
            $params[] = (bool)$filters['ativo'];
        }

        if (!empty($filters['municipio'])) {
            $conditions[] = "id IN (
                SELECT DISTINCT id_usuario FROM tb_perfil_usuario 
                WHERE ibge = ? AND ativo = true
            )";
            $params[] = $filters['municipio'];
        }

        return [
            'clause' => implode(' AND ', $conditions),
            'params' => $params
        ];
    }

    /**
     * Cria novo usuário
     * 
     * @param array $data
     * @return self
     * @throws \Exception
     */
    public static function create(array $data): self
    {
        // Sanitiza dados
        $data = Sanitizer::form($data, [
            'nome' => 'name',
            'cpf' => 'cpf',
            'email' => 'email',
            'telefone' => 'phone',
            'cns_profissional' => 'string'
        ]);

        // Criptografa senha
        if (isset($data['senha'])) {
            $data['senha'] = Security::hashPassword($data['senha']);
        }

        Database::beginTransaction();
        try {
            $id = Database::query(
                "INSERT INTO tb_usuarios (nome, cpf, email, senha, telefone, cns_profissional, 
                                        profissional_cadastrante, cpf_profissional_cadastrante)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?) RETURNING id",
                [
                    $data['nome'],
                    $data['cpf'],
                    $data['email'],
                    $data['senha'] ?? Security::generateSecurePassword(),
                    $data['telefone'] ?? null,
                    $data['cns_profissional'] ?? null,
                    $data['profissional_cadastrante'] ?? null,
                    $data['cpf_profissional_cadastrante'] ?? null
                ]
            )->fetch()['id'];

            Database::commit();

            return self::find($id);
        } catch (\Exception $e) {
            Database::rollback();
            throw $e;
        }
    }

    /**
     * Atualiza usuário
     * 
     * @param array $data
     * @return bool
     * @throws \Exception
     */
    public function update(array $data): bool
    {
        $data = Sanitizer::form($data, [
            'nome' => 'name',
            'email' => 'email',
            'telefone' => 'phone',
            'cns_profissional' => 'string'
        ]);

        try {
            $updated = Database::execute(
                "UPDATE tb_usuarios 
                 SET nome = ?, email = ?, telefone = ?, cns_profissional = ?
                 WHERE id = ?",
                [
                    $data['nome'] ?? $this->nome,
                    $data['email'] ?? $this->email,
                    $data['telefone'] ?? $this->telefone,
                    $data['cns_profissional'] ?? $this->cnsPerofissional,
                    $this->id
                ]
            );

            if ($updated) {
                // Atualiza propriedades do objeto
                $this->nome = $data['nome'] ?? $this->nome;
                $this->email = $data['email'] ?? $this->email;
                $this->telefone = $data['telefone'] ?? $this->telefone;
                $this->cnsPerofissional = $data['cns_profissional'] ?? $this->cnsPerofissional;
            }

            return $updated > 0;
        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Ativa/desativa usuário
     * 
     * @param bool $ativo
     * @return bool
     */
    public function setAtivo(bool $ativo): bool
    {
        $updated = Database::execute(
            "UPDATE tb_usuarios SET ativo = ? WHERE id = ?",
            [$ativo, $this->id]
        );

        if ($updated) {
            $this->ativo = $ativo;
        }

        return $updated > 0;
    }

    /**
     * Altera senha do usuário
     * 
     * @param string $novaSenha
     * @return bool
     */
    public function alterarSenha(string $novaSenha): bool
    {
        $senhaHash = Security::hashPassword($novaSenha);
        
        $updated = Database::execute(
            "UPDATE tb_usuarios SET senha = ?, reset_token = NULL, reset_token_expires = NULL WHERE id = ?",
            [$senhaHash, $this->id]
        );

        if ($updated) {
            $this->senha = $senhaHash;
        }

        return $updated > 0;
    }

    /**
     * Verifica senha
     * 
     * @param string $senha
     * @return bool
     */
    public function verificarSenha(string $senha): bool
    {
        return Security::verifyPassword($senha, $this->senha);
    }

    /**
     * Gera token de reset de senha
     * 
     * @return string
     */
    public function gerarTokenReset(): string
    {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 3600); // 1 hora

        Database::execute(
            "UPDATE tb_usuarios SET reset_token = ?, reset_token_expires = ? WHERE id = ?",
            [$token, $expires, $this->id]
        );

        return $token;
    }

    /**
     * Valida token de reset
     * 
     * @param string $token
     * @return bool
     */
    public static function validarTokenReset(string $token): ?self
    {
        $data = Database::fetch(
            "SELECT * FROM tb_usuarios 
             WHERE reset_token = ? AND reset_token_expires > NOW()",
            [$token]
        );

        return $data ? self::fromArray($data) : null;
    }

    /**
     * Obtém perfis do usuário
     * 
     * @return array
     */
    public function getPerfis(): array
    {
        return UserProfile::getByUserId($this->id);
    }

    /**
     * Obtém perfis ativos do usuário
     * 
     * @return array
     */
    public function getPerfisAtivos(): array
    {
        return UserProfile::getActiveByUserId($this->id);
    }

    /**
     * Upload de foto do usuário
     * 
     * @param array $file Array $_FILES
     * @return string|false
     */
    public function uploadFoto(array $file)
    {
        $upload = new \App\Helpers\FileUpload();
        $result = $upload->upload($file, 'user-photos', 'user_' . $this->id);

        if ($result['success']) {
            Database::execute(
                "UPDATE tb_usuarios SET foto = ? WHERE id = ?",
                [$result['relative_path'], $this->id]
            );

            $this->foto = $result['relative_path'];
            return $result['relative_path'];
        }

        return false;
    }

    /**
     * Remove foto do usuário
     * 
     * @return bool
     */
    public function removerFoto(): bool
    {
        if ($this->foto) {
            $fullPath = __DIR__ . '/../../public' . $this->foto;
            if (file_exists($fullPath)) {
                unlink($fullPath);
            }

            Database::execute(
                "UPDATE tb_usuarios SET foto = NULL WHERE id = ?",
                [$this->id]
            );

            $this->foto = null;
            return true;
        }

        return false;
    }

    /**
     * Cria objeto User a partir de array
     * 
     * @param array $data
     * @return self
     */
    public static function fromArray(array $data): self
    {
        $user = new self();
        $user->id = $data['id'];
        $user->nome = $data['nome'];
        $user->cpf = $data['cpf'];
        $user->email = $data['email'];
        $user->senha = $data['senha'];
        $user->telefone = $data['telefone'];
        $user->cnsPerofissional = $data['cns_profissional'];
        $user->foto = $data['foto'];
        $user->ativo = $data['ativo'];
        $user->dtCadastro = new \DateTime($data['dt_cadastro']);
        $user->dtUltimoAcesso = $data['dt_ultimo_acesso'] ? 
            new \DateTime($data['dt_ultimo_acesso']) : null;

        return $user;
    }

    /**
     * Converte para array
     * 
     * @return array
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'cpf' => $this->cpf,
            'cpf_formatted' => Security::formatCPF($this->cpf),
            'email' => $this->email,
            'telefone' => $this->telefone,
            'cns_profissional' => $this->cnsPerofissional,
            'foto' => $this->foto,
            'ativo' => $this->ativo,
            'dt_cadastro' => $this->dtCadastro->format('Y-m-d H:i:s'),
            'dt_cadastro_formatted' => $this->dtCadastro->format('d/m/Y H:i'),
            'dt_ultimo_acesso' => $this->dtUltimoAcesso?->format('Y-m-d H:i:s'),
            'dt_ultimo_acesso_formatted' => $this->dtUltimoAcesso?->format('d/m/Y H:i')
        ];
    }

    // Getters
    public function getId(): int { return $this->id; }
    public function getNome(): string { return $this->nome; }
    public function getCpf(): string { return $this->cpf; }
    public function getEmail(): string { return $this->email; }
    public function getTelefone(): ?string { return $this->telefone; }
    public function getCnsPerofissional(): ?string { return $this->cnsPerofissional; }
    public function getFoto(): ?string { return $this->foto; }
    public function isAtivo(): bool { return $this->ativo; }
    public function getDtCadastro(): \DateTime { return $this->dtCadastro; }
    public function getDtUltimoAcesso(): ?\DateTime { return $this->dtUltimoAcesso; }

    // Setters
    public function setNome(string $nome): void { $this->nome = $nome; }
    public function setEmail(string $email): void { $this->email = $email; }
    public function setTelefone(?string $telefone): void { $this->telefone = $telefone; }
    public function setCnsPerofissional(?string $cns): void { $this->cnsPerofissional = $cns; }
}