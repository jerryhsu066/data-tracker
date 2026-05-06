<?php

namespace App\Services;

use App\Models\User;
use Symfony\Component\Serializer\SerializerInterface;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;
use Webauthn\AttestationStatement\NoneAttestationStatementSupport;
use Webauthn\AuthenticatorAttestationResponse;
use Webauthn\AuthenticatorAttestationResponseValidator;
use Webauthn\AuthenticatorAssertionResponse;
use Webauthn\AuthenticatorAssertionResponseValidator;
use Webauthn\AuthenticatorSelectionCriteria;
use Webauthn\CeremonyStep\CeremonyStepManagerFactory;
use Webauthn\CredentialRecord;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Webauthn\PublicKeyCredential;
use Webauthn\PublicKeyCredentialCreationOptions;
use Webauthn\PublicKeyCredentialParameters;
use Webauthn\PublicKeyCredentialRequestOptions;
use Webauthn\PublicKeyCredentialRpEntity;
use Webauthn\PublicKeyCredentialUserEntity;

class WebauthnService
{
    private SerializerInterface $serializer;
    private CeremonyStepManagerFactory $factory;

    public function __construct()
    {
        $attestationManager = new AttestationStatementSupportManager([
            new NoneAttestationStatementSupport(),
        ]);

        $this->serializer = (new WebauthnSerializerFactory($attestationManager))->create();

        $this->factory = new CeremonyStepManagerFactory();
        $this->factory->setAllowedOrigins([config('webauthn.origin')]);
    }

    /**
     * Build creation options and return them as a JSON string.
     * The controller stores this in cache and returns it to the client.
     */
    public function buildCreationOptionsJson(User $user): string
    {
        $options = PublicKeyCredentialCreationOptions::create(
            rp: PublicKeyCredentialRpEntity::create(
                name: config('webauthn.rp_name'),
                id: config('webauthn.rp_id'),
            ),
            user: PublicKeyCredentialUserEntity::create(
                name: $user->email,
                id: (string) $user->id,
                displayName: $user->name,
            ),
            challenge: random_bytes(32),
            pubKeyCredParams: [
                PublicKeyCredentialParameters::create('public-key', -7),   // ES256
                PublicKeyCredentialParameters::create('public-key', -257),  // RS256
            ],
            authenticatorSelection: AuthenticatorSelectionCriteria::create(
                authenticatorAttachment: AuthenticatorSelectionCriteria::AUTHENTICATOR_ATTACHMENT_PLATFORM,
                residentKey: AuthenticatorSelectionCriteria::RESIDENT_KEY_REQUIREMENT_REQUIRED,
                userVerification: AuthenticatorSelectionCriteria::USER_VERIFICATION_REQUIREMENT_REQUIRED,
            ),
            attestation: PublicKeyCredentialCreationOptions::ATTESTATION_CONVEYANCE_PREFERENCE_NONE,
        );

        return $this->serializer->serialize($options, 'json');
    }

    /**
     * Build authentication request options and return them as a JSON string.
     */
    public function buildRequestOptionsJson(): string
    {
        $options = PublicKeyCredentialRequestOptions::create(
            challenge: random_bytes(32),
            rpId: config('webauthn.rp_id'),
            allowCredentials: [],
            userVerification: PublicKeyCredentialRequestOptions::USER_VERIFICATION_REQUIREMENT_REQUIRED,
        );

        return $this->serializer->serialize($options, 'json');
    }

    /**
     * Verify a registration response against cached options JSON.
     */
    public function verifyRegistration(
        string $credentialJson,
        string $cachedOptionsJson,
        string $host
    ): CredentialRecord {
        $options    = $this->serializer->deserialize($cachedOptionsJson, PublicKeyCredentialCreationOptions::class, 'json');
        $credential = $this->deserializeCredential($credentialJson);

        if (! $credential->response instanceof AuthenticatorAttestationResponse) {
            throw new \RuntimeException('Invalid attestation response type.');
        }

        return AuthenticatorAttestationResponseValidator::create(
            $this->factory->creationCeremony()
        )->check($credential->response, $options, $host);
    }

    /**
     * Verify an authentication assertion against cached options JSON.
     */
    public function verifyAuthentication(
        string $credentialJson,
        string $cachedOptionsJson,
        CredentialRecord $record,
        string $host,
        ?string $userHandle
    ): CredentialRecord {
        $options    = $this->serializer->deserialize($cachedOptionsJson, PublicKeyCredentialRequestOptions::class, 'json');
        $credential = $this->deserializeCredential($credentialJson);

        if (! $credential->response instanceof AuthenticatorAssertionResponse) {
            throw new \RuntimeException('Invalid assertion response type.');
        }

        return AuthenticatorAssertionResponseValidator::create(
            $this->factory->requestCeremony()
        )->check($record, $credential->response, $options, $host, $userHandle);
    }

    public function serializeRecord(CredentialRecord $record): string
    {
        return $this->serializer->serialize($record, 'json');
    }

    public function deserializeRecord(string $json): CredentialRecord
    {
        return $this->serializer->deserialize($json, CredentialRecord::class, 'json');
    }

    private function deserializeCredential(string $json): PublicKeyCredential
    {
        return $this->serializer->deserialize($json, PublicKeyCredential::class, 'json');
    }
}
