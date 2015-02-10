<?php

/**
 * Copyright 2014 SURFnet bv
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *     http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

namespace Surfnet\StepupSelfService\SelfServiceBundle\Service;

use Surfnet\StepupMiddlewareClient\Identity\Dto\UnverifiedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VerifiedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClient\Identity\Dto\VettedSecondFactorSearchQuery;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\SecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\UnverifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VerifiedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactor;
use Surfnet\StepupMiddlewareClientBundle\Identity\Dto\VettedSecondFactorCollection;
use Surfnet\StepupMiddlewareClientBundle\Identity\Service\SecondFactorService as MiddlewareSecondFactorService;
use Surfnet\StepupMiddlewareClientBundle\Service\CommandService;
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\RevokeOwnSecondFactorCommand;
use Surfnet\StepupSelfService\SelfServiceBundle\Identity\Command\VerifyEmailCommand;

/**
 * @SuppressWarnings(PHPMD.TooManyMethods)
 * @SuppressWarnings(PHPMD.CouplingBetweenObjects)
 */
class SecondFactorService
{
    /**
     * @var MiddlewareSecondFactorService
     */
    private $secondFactors;

    /**
     * @var CommandService
     */
    private $commandService;

    /**
     * @param MiddlewareSecondFactorService $secondFactors
     * @param CommandService $commandService
     */
    public function __construct(MiddlewareSecondFactorService $secondFactors, CommandService $commandService)
    {
        $this->secondFactors = $secondFactors;
        $this->commandService = $commandService;
    }

    /**
     * @param VerifyEmailCommand $command
     * @return bool
     */
    public function verifyEmail(VerifyEmailCommand $command)
    {
        $result = $this->commandService->execute($command);

        return $result->isSuccessful();
    }

    /**
     * @param RevokeOwnSecondFactorCommand $command
     * @return bool
     */
    public function revoke(RevokeOwnSecondFactorCommand $command)
    {
        $result = $this->commandService->execute($command);

        return $result->isSuccessful();
    }

    /**
     * Returns whether the given registrant has registered second factors with Step-up. The state of the second factor
     * is irrelevant.
     *
     * @param string $identityId
     * @return bool
     */
    public function doSecondFactorsExistForIdentity($identityId)
    {
        $unverifiedSecondFactors = $this->findUnverifiedByIdentity($identityId);
        $verifiedSecondFactors = $this->findVerifiedByIdentity($identityId);
        $vettedSecondFactors = $this->findVettedByIdentity($identityId);

        return $unverifiedSecondFactors->getTotalItems() +
               $verifiedSecondFactors->getTotalItems() +
               $vettedSecondFactors->getTotalItems() > 0;
    }

    /**
     * Returns the given registrant's unverified second factors.
     *
     * @param string $identityId
     * @return UnverifiedSecondFactorCollection
     */
    public function findUnverifiedByIdentity($identityId)
    {
        return $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @param string $identityId
     * @return VerifiedSecondFactorCollection
     */
    public function findVerifiedByIdentity($identityId)
    {
        return $this->secondFactors->searchVerified(
            (new VerifiedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * Returns the given registrant's verified second factors.
     *
     * @param string $identityId
     * @return VettedSecondFactorCollection
     */
    public function findVettedByIdentity($identityId)
    {
        return $this->secondFactors->searchVetted(
            (new VettedSecondFactorSearchQuery())->setIdentityId($identityId)
        );
    }

    /**
     * @param string $secondFactorId
     * @return null|UnverifiedSecondFactor
     */
    public function findOneUnverified($secondFactorId)
    {
        return $this->secondFactors->getUnverified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VerifiedSecondFactor
     */
    public function findOneVerified($secondFactorId)
    {
        return $this->secondFactors->getVerified($secondFactorId);
    }

    /**
     * @param string $secondFactorId
     * @return null|VettedSecondFactor
     */
    public function findOneVetted($secondFactorId)
    {
        return $this->secondFactors->getVetted($secondFactorId);
    }

    /**
     * @param string $identityId
     * @param string $verificationNonce
     * @return UnverifiedSecondFactor|null
     */
    public function findUnverifiedByVerificationNonce($identityId, $verificationNonce)
    {
        $secondFactors = $this->secondFactors->searchUnverified(
            (new UnverifiedSecondFactorSearchQuery())
                ->setIdentityId($identityId)
                ->setVerificationNonce($verificationNonce)
        );

        $elements = $secondFactors->getElements();

        switch (count($elements)) {
            case 0:
                return null;
            case 1:
                return reset($elements);
            default:
                throw new \LogicException('There cannot be more than one unverified second factor with the same nonce');
        }
    }

    /**
     * @param string $identityId
     * @return null|string
     */
    public function getRegistrationCode($secondFactorId, $identityId)
    {
        $query = (new VerifiedSecondFactorSearchQuery())
            ->setIdentityId($identityId)
            ->setSecondFactorId($secondFactorId);

        /** @var VerifiedSecondFactor[] $verifiedSecondFactors */
        $verifiedSecondFactors = $this->secondFactors->searchVerified($query)->getElements();

        switch (count($verifiedSecondFactors)) {
            case 0:
                return null;
            case 1:
                return reset($verifiedSecondFactors)->registrationCode;
            default:
                throw new \LogicException('Searching by second factor ID cannot result in multiple results.');
        }
    }
}