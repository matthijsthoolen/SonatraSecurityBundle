<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Bundle\SecurityBundle\Acl\Expression;

use Sonatra\Bundle\SecurityBundle\Acl\Model\AclManagerInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Role\RoleInterface;

/**
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class AnyRoleEvaluator
{
    private $aclManager;
    private $cache;

    /**
     * Constructor.
     *
     * @param AclManagerInterface $aclManager
     */
    public function __construct(AclManagerInterface $aclManager)
    {
        $this->aclManager = $aclManager;
    }

    /**
     * Check if token ha role (with role hierarchy and group).
     *
     * @param TokenInterface $token
     * @param array          $roles
     *
     * @return boolean
     */
    public function hasAnyRole(TokenInterface $token, array $roles)
    {
        $cacheName = implode('_', $roles);

        if (isset($this->cache[$cacheName])) {
            return $this->cache[$cacheName];
        }

        $identities = $this->aclManager->getIdentities($token);
        $this->cache[$cacheName] = false;

        foreach ($identities as $i => $identity) {
            if ($identity instanceof RoleInterface && in_array($identity->getRole(), $roles)) {
                $this->cache[$cacheName] = true;
                break;
            }
        }

        return $this->cache[$cacheName];
    }
}
