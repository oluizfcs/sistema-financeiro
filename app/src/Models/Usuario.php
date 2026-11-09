<?php

namespace App\Models;

use App\Controllers\Services\Cpf;
use App\Controllers\Services\Logger;
use App\Models\Database;
use PDO;
use PDOException;

class Usuario
{
    public static $tableName = 'usuario';

    public function __construct(
        private int $id,
        private string $cpf,
        private string $nome,
        private string $senha,
        private string $data_criacao,
        private string|null $data_edicao,
        private bool $enabled,
        private int $permissions = 0
    ) {
        $this->nome = htmlspecialchars($this->nome, ENT_QUOTES, 'UTF-8', false);
    }

    /**
     * Get the value of id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set the value of id
     *
     * @return self
     */
    public function setId($id)
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get the value of cpf
     */
    public function getCpf()
    {
        return $this->cpf;
    }

    public function getMaskedCpf(): string
    {
        return Cpf::maskCpf($this->cpf);
    }

    /**
     * Set the value of cpf
     *
     * @return self
     */
    public function setCpf($cpf)
    {
        $this->cpf = $cpf;

        return $this;
    }

    /**
     * Get the value of nome
     */
    public function getNome()
    {
        return $this->nome;
    }

    /**
     * Set the value of nome
     *
     * @return self
     */
    public function setNome($nome)
    {
        $this->nome = $nome;

        return $this;
    }

    /**
     * Get the value of senha
     */
    public function getSenha()
    {
        return $this->senha;
    }

    /**
     * Set the value of senha
     *
     * @return self
     */
    public function setSenha($senha)
    {
        $this->senha = $senha;

        return $this;
    }

    /**
     * Get the value of data_criacao
     */
    public function getData_criacao()
    {
        return $this->data_criacao;
    }

    /**
     * Set the value of data_criacao
     *
     * @return self
     */
    public function setData_criacao($data_criacao)
    {
        $this->data_criacao = $data_criacao;

        return $this;
    }

    /**
     * Get the value of data_edicao
     */
    public function getData_edicao()
    {
        return $this->data_edicao;
    }

    /**
     * Set the value of data_edicao
     *
     * @return self
     */
    public function setData_edicao($data_edicao)
    {
        $this->data_edicao = $data_edicao;

        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): void
    {
        $this->enabled = $enabled;
    }

    public function isAdmin(): bool
    {
        return Permissions::userCan($this->permissions, Permissions::ADMIN);
    }

    public function getPermissions(): int
    {
        return $this->permissions;
    }

    public function setPermissions(int $permissions): void
    {
        $this->permissions = $permissions;
    }

    public function save(): bool
    {
        try {
            $conn = Database::getConnection();

            $stmt = $conn->prepare('SELECT COUNT(*) FROM ' . self::$tableName . ' WHERE id = :id');
            $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);
            $stmt->execute();

            if ($stmt->fetch()[0] == 0) {
                // criar
                $stmt = $conn->prepare('INSERT INTO ' . self::$tableName . ' (nome, cpf, senha, permissions) VALUES (:nome, :cpf, :senha, :permissions)');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':cpf', $this->cpf, PDO::PARAM_STR);
                $stmt->bindParam(':senha', $this->senha, PDO::PARAM_STR);
                $stmt->bindValue(':permissions', $this->permissions, PDO::PARAM_INT);

                $stmt->execute();

                Logger::log_create($conn, self::$tableName);

                return true;
            } else {
                // atualizar
                $usuarioAntigo = self::getById($this->id);

                $stmt = $conn->prepare('UPDATE ' . self::$tableName . ' SET nome = :nome, cpf = :cpf, senha = :senha, enabled = :enabled, permissions = :permissions WHERE id = :id');
                $stmt->bindParam(':nome', $this->nome, PDO::PARAM_STR);
                $stmt->bindParam(':cpf', $this->cpf, PDO::PARAM_STR);
                $stmt->bindParam(':senha', $this->senha, PDO::PARAM_STR);
                $stmt->bindParam(':enabled', $this->enabled, PDO::PARAM_BOOL);
                $stmt->bindValue(':permissions', $this->permissions, PDO::PARAM_INT);
                $stmt->bindParam(':id', $this->id, PDO::PARAM_INT);

                $stmt->execute();

                if ($this->senha != $usuarioAntigo->getSenha()) {
                    Logger::log(self::$tableName, 'senha', '*', '*', $this->id, $_SESSION['usuario_id']);
                }
                
                $permissionDiff = Permissions::diff($usuarioAntigo->getPermissions(), $this->permissions);

                $changes = [];
                if (!empty($permissionDiff['granted'])) {
                    $changes[] = "Concedido: " . implode(', ', $permissionDiff['granted']);
                }
                if (!empty($permissionDiff['revoked'])) {
                    $changes[] = "Revogado: " . implode(', ', $permissionDiff['revoked']);
                }
                if (!empty($changes)) {
                    $novoEstado = implode(' | ', $changes);
                    
                    $estadoAnterior = implode(', ', Permissions::getLabels($usuarioAntigo->getPermissions())) ?: 'Nenhuma';
                    
                    Logger::log(
                        self::$tableName, 
                        'permissões', 
                        $estadoAnterior, 
                        $novoEstado, 
                        $this->id, 
                        $_SESSION['usuario_id']
                    );
                }

                return true;
            }
        } catch (PDOException $e) {
            if($e->getCode() == 23000) {
                Logger::error('Este CPF já está cadastrado.', ['PDOException' => $e->getMessage()]);
                $_SESSION['message'] = ['Este CPF já está cadastrado.', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/usuarios/cadastrar');
                exit;
            }
            Logger::error('Erro ao cadastrar|atualizar usuário', ['PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro ao cadastrar|atualizar usuário', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/usuarios');
            exit;
        }
    }

    public static function getById(int $id): Usuario
    {
        $usuario = Database::getById('usuario', $id);
        if (!$usuario) {
            $_SESSION['message'] = ['Usuário não encontrado', 'fail'];
                header('Location: ' . $_ENV['BASE_URL'] . '/usuarios');
            exit;
        }
        extract($usuario);
        return new Usuario($id, $cpf, $nome, $senha, $data_criacao, $data_edicao, $enabled, $permissions);
    }

    public static function getAll(bool $enabled, string $search): array
    {
        $sql = "SELECT id, nome, cpf
        FROM usuario
        WHERE `enabled` = :enabled";

        if (strlen($search) > 0) {
            $sql .= " AND nome LIKE :search OR cpf LIKE :search";
        }
        
        $sql .= ' ORDER BY nome';

        try {
            $stmt = Database::getConnection()->prepare($sql);
            $stmt->bindValue(':enabled', $enabled ? 1 : 0);

            if (strlen($search) > 0) {
                $stmt->bindValue(':search', "%$search%", PDO::PARAM_STR);
            }

            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            Logger::error('Falha ao listar usuários', ['enabled' => $enabled, 'search' => $search, 'PDOException' => $e->getMessage()]);
            $_SESSION['message'] = ['Erro inesperado, entre em contato com o desenvolvedor do sistema.', 'fail'];
            header('Location: ' . $_ENV['BASE_URL'] . '/dashboard');
            exit;
        }
    }

    public static function getByCpf(string $cpf): array|false
    {
        return Database::getByColumn(self::$tableName, 'cpf', $cpf);
    }
}
