<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Bundle\SecurityBundle\Acl\Rule\Definition;

use Sonatra\Bundle\SecurityBundle\Acl\Domain\AbstractAclRuleDefinition;

/**
 * The Allow ACL Rule Definition.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class AllowDefinition extends AbstractAclRuleDefinition
{
    /**
     * {@inheritdoc}
     */
    public function getName()
    {
        return 'allow';
    }
}
