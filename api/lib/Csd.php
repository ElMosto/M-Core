<?php
/**
 * Validación real de un CSD (Certificado de Sello Digital) del SAT usando
 * la extensión OpenSSL de PHP.
 *
 * Los archivos .cer/.key del SAT vienen en formato DER (binario), no PEM.
 * PEM es exactamente el mismo contenido DER codificado en base64 con un
 * encabezado/pie — por lo que basta con "envolver" el binario para que
 * openssl_x509_parse()/openssl_pkey_get_private() puedan leerlo. Esta es la
 * misma técnica que usan las librerías CFDI de código abierto en PHP.
 */
final class Csd
{
    public static function derAPem(string $der, string $tipo): string
    {
        $b64 = chunk_split(base64_encode($der), 64, "\n");
        return "-----BEGIN {$tipo}-----\n{$b64}-----END {$tipo}-----\n";
    }

    /** Verifica que la contraseña abra la llave privada (.key). */
    public static function validarPassword(string $keyDer, string $password): bool
    {
        $pem = self::derAPem($keyDer, 'ENCRYPTED PRIVATE KEY');
        $recurso = @openssl_pkey_get_private($pem, $password);
        if ($recurso === false) {
            return false;
        }
        return true; // en PHP 8 no es necesario openssl_free_key()
    }

    /**
     * Extrae datos básicos del certificado (.cer): RFC/razón social (si el
     * emisor los incluye en el subject) y vigencia. Devuelve null si el
     * certificado no pudo interpretarse.
     */
    public static function leerCertificado(string $cerDer): ?array
    {
        $pem = self::derAPem($cerDer, 'CERTIFICATE');
        $datos = @openssl_x509_parse($pem);
        if (!$datos) {
            return null;
        }

        // El SAT codifica el RFC en subject->x500UniqueIdentifier o
        // serialNumber según el tipo de certificado (CSD de persona física
        // o moral); se intentan ambos.
        $subject = $datos['subject'] ?? [];
        $rfcCert = $subject['x500UniqueIdentifier'] ?? $subject['serialNumber'] ?? null;

        return [
            'rfc'        => $rfcCert ? trim(explode('/', $rfcCert)[0]) : null,
            'valido_desde' => $datos['validFrom_time_t'] ?? null,
            'valido_hasta' => $datos['validTo_time_t'] ?? null,
            'vigente'    => isset($datos['validTo_time_t']) ? ($datos['validTo_time_t'] > time()) : null,
        ];
    }

    /** Confirma que el .cer y el .key pertenecen al mismo par de llaves. */
    public static function coincidenParDeLlaves(string $cerDer, string $keyDer, string $password): ?bool
    {
        $pemCert = self::derAPem($cerDer, 'CERTIFICATE');
        $pemKey  = self::derAPem($keyDer, 'ENCRYPTED PRIVATE KEY');

        $priv = @openssl_pkey_get_private($pemKey, $password);
        if (!$priv) {
            return null;
        }
        $pub = @openssl_pkey_get_public($pemCert);
        if (!$pub) {
            return null;
        }

        $detallesPriv = openssl_pkey_get_details($priv);
        $detallesPub  = openssl_pkey_get_details($pub);

        return isset($detallesPriv['rsa']['n'], $detallesPub['rsa']['n'])
            && $detallesPriv['rsa']['n'] === $detallesPub['rsa']['n'];
    }
}
