<?php

namespace App\Helpers;

use App\Config\Config;

/**
 * Classe para upload seguro de arquivos
 * 
 * @package App\Helpers
 * @author SES-MS
 * @version 2.0.0
 */
class FileUpload
{
    private array $allowedExtensions = [];
    private array $allowedMimeTypes = [];
    private int $maxFileSize;
    private string $uploadPath;
    private array $errors = [];

    /**
     * Construtor
     */
    public function __construct()
    {
        $uploadConfig = Config::getUploadConfig();
        
        $this->allowedExtensions = $uploadConfig['allowed_extensions'] ?? ['pdf', 'xlsx', 'xls', 'jpg', 'png', 'jpeg'];
        $this->maxFileSize = $uploadConfig['max_file_size'] ?? 10485760; // 10MB
        $this->uploadPath = $uploadConfig['path'] ?? __DIR__ . '/../../public/uploads';
        
        // MIME types correspondentes às extensões
        $this->allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'xls' => 'application/vnd.ms-excel',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
    }

    /**
     * Faz upload de um arquivo
     * 
     * @param array $file Array $_FILES['field']
     * @param string $subdir Subdiretório (documents, user-photos, etc)
     * @param string $customName Nome customizado (opcional)
     * @return array
     */
    public function upload(array $file, string $subdir = 'documents', string $customName = null): array
    {
        $this->errors = [];

        // Valida o arquivo
        if (!$this->validateFile($file)) {
            return [
                'success' => false,
                'errors' => $this->errors
            ];
        }

        // Cria diretório se não existir
        $targetDir = $this->uploadPath . '/' . $subdir;
        if (!$this->createDirectory($targetDir)) {
            $this->errors[] = 'Erro ao criar diretório de upload.';
            return [
                'success' => false,
                'errors' => $this->errors
            ];
        }

        // Gera nome do arquivo
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $filename = $customName ? $customName . '.' . $extension : $this->generateFilename($file['name']);
        $filepath = $targetDir . '/' . $filename;

        // Evita sobrescrita
        $counter = 1;
        $originalFilepath = $filepath;
        while (file_exists($filepath)) {
            $pathInfo = pathinfo($originalFilepath);
            $filepath = $pathInfo['dirname'] . '/' . $pathInfo['filename'] . '_' . $counter . '.' . $pathInfo['extension'];
            $counter++;
        }

        // Move o arquivo
        if (move_uploaded_file($file['tmp_name'], $filepath)) {
            // Define permissões seguras
            chmod($filepath, 0644);

            return [
                'success' => true,
                'filepath' => $filepath,
                'filename' => basename($filepath),
                'relative_path' => '/' . $subdir . '/' . basename($filepath),
                'size' => filesize($filepath),
                'mime_type' => $this->getMimeType($filepath)
            ];
        } else {
            $this->errors[] = 'Erro ao mover arquivo para destino final.';
            return [
                'success' => false,
                'errors' => $this->errors
            ];
        }
    }

    /**
     * Upload múltiplos arquivos
     * 
     * @param array $files Array $_FILES
     * @param string $subdir
     * @return array
     */
    public function uploadMultiple(array $files, string $subdir = 'documents'): array
    {
        $results = [];
        $totalSuccess = 0;
        $totalErrors = 0;

        foreach ($files as $key => $fileGroup) {
            if (is_array($fileGroup['name'])) {
                // Múltiplos arquivos com mesmo nome de campo
                for ($i = 0; $i < count($fileGroup['name']); $i++) {
                    $file = [
                        'name' => $fileGroup['name'][$i],
                        'type' => $fileGroup['type'][$i],
                        'tmp_name' => $fileGroup['tmp_name'][$i],
                        'error' => $fileGroup['error'][$i],
                        'size' => $fileGroup['size'][$i]
                    ];

                    $result = $this->upload($file, $subdir);
                    $results[$key][] = $result;
                    
                    if ($result['success']) {
                        $totalSuccess++;
                    } else {
                        $totalErrors++;
                    }
                }
            } else {
                // Arquivo único
                $result = $this->upload($fileGroup, $subdir);
                $results[$key] = $result;
                
                if ($result['success']) {
                    $totalSuccess++;
                } else {
                    $totalErrors++;
                }
            }
        }

        return [
            'results' => $results,
            'summary' => [
                'total_files' => $totalSuccess + $totalErrors,
                'successful' => $totalSuccess,
                'failed' => $totalErrors
            ]
        ];
    }

    /**
     * Valida arquivo antes do upload
     * 
     * @param array $file
     * @return bool
     */
    private function validateFile(array $file): bool
    {
        // Verifica se houve erro no upload
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $this->errors[] = 'Arquivo muito grande. Tamanho máximo: ' . $this->formatBytes($this->maxFileSize);
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $this->errors[] = 'Upload incompleto. Tente novamente.';
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $this->errors[] = 'Nenhum arquivo foi enviado.';
                    break;
                case UPLOAD_ERR_NO_TMP_DIR:
                    $this->errors[] = 'Diretório temporário não encontrado.';
                    break;
                case UPLOAD_ERR_CANT_WRITE:
                    $this->errors[] = 'Falha ao escrever arquivo no disco.';
                    break;
                default:
                    $this->errors[] = 'Erro desconhecido no upload.';
            }
            return false;
        }

        // Verifica tamanho
        if ($file['size'] > $this->maxFileSize) {
            $this->errors[] = 'Arquivo muito grande. Tamanho máximo: ' . $this->formatBytes($this->maxFileSize);
            return false;
        }

        if ($file['size'] === 0) {
            $this->errors[] = 'Arquivo está vazio.';
            return false;
        }

        // Verifica extensão
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, $this->allowedExtensions)) {
            $this->errors[] = 'Extensão não permitida. Permitidas: ' . implode(', ', $this->allowedExtensions);
            return false;
        }

        // Verifica MIME type
        $detectedMimeType = $this->getMimeType($file['tmp_name']);
        $expectedMimeType = $this->allowedMimeTypes[$extension] ?? null;
        
        if ($expectedMimeType && $detectedMimeType !== $expectedMimeType) {
            // Alguns tipos alternativos aceitos
            $alternativeMimes = [
                'application/pdf' => ['application/x-pdf'],
                'image/jpeg' => ['image/pjpeg'],
                'application/vnd.ms-excel' => ['application/excel', 'application/x-excel']
            ];

            $isValidAlternative = false;
            if (isset($alternativeMimes[$expectedMimeType])) {
                $isValidAlternative = in_array($detectedMimeType, $alternativeMimes[$expectedMimeType]);
            }

            if (!$isValidAlternative) {
                $this->errors[] = 'Tipo de arquivo não corresponde à extensão.';
                return false;
            }
        }

        // Verifica se é realmente um arquivo enviado via HTTP POST
        if (!is_uploaded_file($file['tmp_name'])) {
            $this->errors[] = 'Arquivo não foi enviado via formulário.';
            return false;
        }

        // Verificações de segurança adicionais para imagens
        if (in_array($extension, ['jpg', 'jpeg', 'png', 'gif'])) {
            if (!$this->validateImage($file['tmp_name'])) {
                return false;
            }
        }

        return true;
    }

    /**
     * Valida imagem
     * 
     * @param string $filepath
     * @return bool
     */
    private function validateImage(string $filepath): bool
    {
        // Verifica se é uma imagem válida
        $imageInfo = getimagesize($filepath);
        if ($imageInfo === false) {
            $this->errors[] = 'Arquivo não é uma imagem válida.';
            return false;
        }

        // Verifica dimensões máximas (exemplo: 5000x5000)
        if ($imageInfo[0] > 5000 || $imageInfo[1] > 5000) {
            $this->errors[] = 'Dimensões da imagem muito grandes. Máximo: 5000x5000 pixels.';
            return false;
        }

        // Verifica se não há código PHP embutido
        $content = file_get_contents($filepath);
        if (strpos($content, '<?php') !== false || strpos($content, '<?=') !== false) {
            $this->errors[] = 'Arquivo contém código malicioso.';
            return false;
        }

        return true;
    }

    /**
     * Gera nome único para arquivo
     * 
     * @param string $originalName
     * @return string
     */
    private function generateFilename(string $originalName): string
    {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $name = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitiza o nome
        $name = Sanitizer::filename($name);
        
        // Gera hash único
        $timestamp = date('Y-m-d_H-i-s');
        $hash = substr(md5($name . time() . rand()), 0, 8);
        
        return $name . '_' . $timestamp . '_' . $hash . '.' . $extension;
    }

    /**
     * Cria diretório se não existir
     * 
     * @param string $path
     * @return bool
     */
    private function createDirectory(string $path): bool
    {
        if (!is_dir($path)) {
            return mkdir($path, 0755, true);
        }
        return true;
    }

    /**
     * Obtém MIME type real do arquivo
     * 
     * @param string $filepath
     * @return string
     */
    private function getMimeType(string $filepath): string
    {
        if (function_exists('finfo_file')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $filepath);
            finfo_close($finfo);
            return $mimeType;
        } elseif (function_exists('mime_content_type')) {
            return mime_content_type($filepath);
        } else {
            return 'application/octet-stream';
        }
    }

    /**
     * Formata bytes para exibição
     * 
     * @param int $size
     * @return string
     */
    private function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }

    /**
     * Remove arquivo
     * 
     * @param string $filepath
     * @return bool
     */
    public static function delete(string $filepath): bool
    {
        if (file_exists($filepath) && is_file($filepath)) {
            return unlink($filepath);
        }
        return false;
    }

    /**
     * Move arquivo para outro diretório
     * 
     * @param string $sourcePath
     * @param string $targetPath
     * @return bool
     */
    public static function move(string $sourcePath, string $targetPath): bool
    {
        if (file_exists($sourcePath)) {
            // Cria diretório de destino se não existir
            $targetDir = dirname($targetPath);
            if (!is_dir($targetDir)) {
                mkdir($targetDir, 0755, true);
            }
            
            return rename($sourcePath, $targetPath);
        }
        return false;
    }

    /**
     * Obtém informações do arquivo
     * 
     * @param string $filepath
     * @return array|null
     */
    public static function getFileInfo(string $filepath): ?array
    {
        if (!file_exists($filepath)) {
            return null;
        }

        return [
            'name' => basename($filepath),
            'size' => filesize($filepath),
            'size_formatted' => self::formatBytes(filesize($filepath)),
            'mime_type' => (new self())->getMimeType($filepath),
            'extension' => strtolower(pathinfo($filepath, PATHINFO_EXTENSION)),
            'created_at' => date('Y-m-d H:i:s', filectime($filepath)),
            'modified_at' => date('Y-m-d H:i:s', filemtime($filepath))
        ];
    }

    /**
     * Limpa arquivos temporários antigos
     * 
     * @param int $maxAge Idade máxima em segundos (padrão: 1 hora)
     */
    public static function cleanTempFiles(int $maxAge = 3600): void
    {
        $tempPath = Config::get('upload.temp_path', __DIR__ . '/../../storage/temp');
        
        if (!is_dir($tempPath)) {
            return;
        }

        $files = glob($tempPath . '/*');
        $now = time();
        
        foreach ($files as $file) {
            if (is_file($file) && ($now - filemtime($file)) > $maxAge) {
                unlink($file);
            }
        }
    }

    /**
     * Define extensões permitidas
     * 
     * @param array $extensions
     * @return self
     */
    public function setAllowedExtensions(array $extensions): self
    {
        $this->allowedExtensions = $extensions;
        return $this;
    }

    /**
     * Define tamanho máximo
     * 
     * @param int $size
     * @return self
     */
    public function setMaxFileSize(int $size): self
    {
        $this->maxFileSize = $size;
        return $this;
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
     * Formata bytes estaticamente
     * 
     * @param int $size
     * @return string
     */
    private static function formatBytes(int $size): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size >= 1024 && $i < 3; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
}