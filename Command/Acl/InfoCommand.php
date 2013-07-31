<?php

/*
 * This file is part of the Sonatra package.
 *
 * (c) François Pluchino <francois.pluchino@sonatra.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Sonatra\Bundle\SecurityBundle\Command\Acl;

use Symfony\Component\Security\Core\Role\Role;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Security\Acl\Voter\FieldVote;
use Symfony\Component\Security\Core\Authentication\Token\AnonymousToken;
use Sonatra\Bundle\SecurityBundle\Acl\Util\AclUtils;

/**
 * Display the identifier rights of class/field.
 *
 * @author François Pluchino <francois.pluchino@sonatra.com>
 */
class InfoCommand extends ContainerAwareCommand
{
    protected $rightsDisplayed = array('VIEW', 'CREATE', 'EDIT',
                'DELETE', 'UNDELETE', 'OPERATOR', 'MASTER', 'OWNER', 'IDDQD',);

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this->setName('security:acl:info')
        ->setDescription('Gets the rights for a specified class and identifier, and optionnally for a given field')
        ->setDefinition(array(
                new InputArgument('identity-type', InputArgument::REQUIRED, 'The security identity type (role, user, group)'),
                new InputArgument('identity-name', InputArgument::REQUIRED, 'The security identity name to use for the right'),
                new InputArgument('domain-class-name', InputArgument::REQUIRED, 'The domain class name to get the right for'),
                new InputArgument('domain-field-name', InputArgument::OPTIONAL, 'The domain class field name to get the right for'),
                new InputOption('domainid', null, InputOption::VALUE_REQUIRED, 'This domain id (only for object)'),
                new InputOption('security-identity', null, InputOption::VALUE_REQUIRED, 'This security identity type', 'role'),
                new InputOption('host', null, InputOption::VALUE_REQUIRED, 'The hostname pattern (for default anonymous role)', 'localhost'),
                new InputOption('no-host', null, InputOption::VALUE_NONE, 'Not display the role of host'),
                new InputOption('calc', 'c', InputOption::VALUE_NONE, 'Get the rights with granted method (calculated)')
        ))
        ->setHelp(<<<EOF
The <info>acl:right</info> command gets the existing rights for the
given security identity on a specified domain (class or object).
EOF
        );
    }

    /**
     * {@inheritdoc}
     */
    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $doctrine = $this->getContainer()->get('doctrine');
        $identityType = strtolower($input->getArgument('identity-type'));
        $identity = $input->getArgument('identity-name');
        $identityName = $identity;
        $identityClass = $this->getClassname($this->getContainer()->getParameter('sonatra_security.'.$identityType.'_class'));
        $identityRepo = $doctrine->getManagerForClass($identityClass)->getRepository($identityClass);
        $identity = $identityRepo->findOneBy(array(('user' === $identityType ? 'username' : 'name') => $identity));
        $domainType = null !== $input->getOption('domainid') ? 'object' : 'class';
        $domainClass = $this->getClassname($input->getArgument('domain-class-name'));
        $domainId = $input->getOption('domainid');
        $domain = $domainClass;
        $field = $input->getArgument('domain-field-name');
        $fields = null === $field ? array() : array($field);
        $noHost = $input->getOption('no-host');
        $host = $noHost ? null : $input->getOption('host');
        $calculated = $input->getOption('calc');

        if ('group' === $identityType) {
            $calculated = true;
        }

        if (!in_array($identityType, array('role', 'user', 'group'))) {
            throw new \InvalidArgumentException('The "identity-type" argument must be "role", "user" or "group"');
        }

        if (null === $identity) {
            throw new \InvalidArgumentException(sprintf('Identity instance "%s" on "%s" not found', $input->getArgument('identity-name'), $identityClass));
        }

        // get the domain instance
        if ('object' === $domainType) {
            $domainRepo = $doctrine->getManagerForClass($domainClass)->getRepository($domainClass);
            $domain = $domainRepo->findOneBy(array('id' => $domainId));

            if (null === $domain) {
                throw new \InvalidArgumentException(sprintf('Domain instance "%s" on "%s" not found', $domainId, $domainClass));
            }
        }

        // init get acl rights
        $classRights = array();
        $fieldRights = array();

        if (null === $field) {
            $reflClass = new \ReflectionClass($domainClass);

            foreach ($reflClass->getProperties() as $property) {
                $fields[] = $property->name;
            }
        }

        // check with all acl voter
        if ($calculated) {
            $toekenIdentity = 'user' === $identityType ? $identity : 'console.';
            $sc = $this->getContainer()->get('security.context');

            $sc->setToken(new AnonymousToken('key', $toekenIdentity, $this->getHostRoles($host)));

            // get class rights
            foreach ($this->rightsDisplayed as $right) {
                if ($sc->isGranted($right, $domain)) {
                    $classRights[] = $right;
                }
            }

            // get fields rights
            foreach ($fields as $cField) {
                $fieldRights[$cField] = array();

                foreach ($this->rightsDisplayed as $right) {
                    if ($sc->isGranted($right, new FieldVote($domain, $cField))) {
                        $fieldRights[$cField][] = $right;
                    }
                }
            }

        // check with only ACL stored in table
        } else {
            $aclManager = $this->getContainer()->get('sonatra.acl.manager');
            $getMethod = 'getClassPermission';
            $getFieldMethod = 'getClassFieldPermission';

            if (is_object($domain)) {
                $getMethod = 'getObjectPermission';
                $getFieldMethod = 'getObjectFieldPermission';
            }

            // get class rights
            $classMask = $aclManager->$getMethod($identity, $domain);
            $classRights = AclUtils::convertToAclName($classMask);

            // get fields rights
            foreach ($fields as $cField) {
                $fieldRights[$cField] = array();

                foreach ($this->rightsDisplayed as $right) {
                    $fieldMask = $aclManager->$getFieldMethod($identity, $domain, $cField);
                    $fieldRights[$cField] = AclUtils::convertToAclName($fieldMask);
                }
            }
        }

        // display title
        $out = array('',
                $this->formatTitle($identityType, $identityName, $domainClass, $domainId, $field),
        );

        //display class
        $out = array_merge($out, $this->formatClass($classRights, (null !== $field)));

        // display fields
        $out = array_merge($out, $this->formatFields($fieldRights, (null !== $field)));

        $output->writeln($out);
    }

    /**
     * Display command title.
     *
     * @param string     $identityType
     * @param string     $identityName
     * @param string     $domainName
     * @param string|int $domainId
     * @param string     $domainField
     *
     * @return string The text for output console
     */
    protected function formatTitle($identityType, $identityName, $domainName, $domainId = null, $domainField = null)
    {
        $type = ' class';

        if (null !== $domainField) {
            $type = sprintf(':<comment>%s</comment> field', $domainField);

            if (null !== $domainId) {
                $type = sprintf(':<comment>%s</comment> field of class instance <comment>%s</comment>', $domainField, $domainId);
            }

        } elseif (null !== $domainId) {
            $type = sprintf(' class instance <comment>%s</comment>', $domainId);
        }

        return sprintf('Rights of <info>%s</info>%s for <info>%s</info> %s:', $domainName, $type, $identityName, $identityType);
    }

    /**
     * Format class rights section.
     *
     * @param array   $classRights
     * @param boolean $hide
     *
     * @return array The text for output console
     */
    protected function formatClass(array $classRights, $hide = false)
    {
        if ($hide) {
            return array();
        }

        $aclManager = $this->getContainer()->get('sonatra.acl.manager');
        $out = array('', '  Class rights:');
        $rights = array();
        $width = 0;

        // calculated width right names
        foreach ($this->rightsDisplayed as $right) {
            $width = strlen($right) > $width ? strlen($right) : $width;
        }

        foreach ($this->rightsDisplayed as $right) {
            $value = in_array($right, $classRights) ? 'true': 'false';
            $rights[] = sprintf("    <comment>%-${width}s</comment> : <info>%s</info>", $right, $value);
        }

        return array_merge($out, $rights);
    }

    /**
     * Format fields rights section.
     *
     * @param array   $fieldRights
     * @param boolean $hideTitle
     *
     * @return array The text for output console
     */
    protected function formatFields(array $fieldRights, $hideTitle = false)
    {
        if (0 === count($fieldRights)) {
            return array();
        }

        $aclManager = $this->getContainer()->get('sonatra.acl.manager');
        $out = array('');
        $fields = array();
        $width = 0;

        if (!$hideTitle) {
            $out = array_merge($out, array('  Fields rights:'));
        }

        // calculated width field names
        foreach ($fieldRights as $field => $rights) {
            $width = strlen($field) > $width ? strlen($field) : $width;
        }

        // display fields
        foreach ($fieldRights as $field => $rights) {
            $fields[] = sprintf("    <comment>%-${width}s</comment> : [ <info>%s</info> ]", $field, implode(", ", $rights));
        }

        return array_merge($out, $fields);
    }

    /**
     * Get classname from an entity name formated on the symfony way.
     *
     * @param string $entityName
     *
     * @return string The FQCN
     */
    protected function getClassname($entityName)
    {
        $entityName = str_replace('/', '\\', $entityName);

        try {
            if (false !== $pos = strpos($entityName, ':')) {
                $bundle = substr($entityName, 0, $pos);
                $entityName = substr($entityName, $pos + 1);

                $cn = get_class($this->getContainer()->get('kernel')->getBundle($bundle));
                $cn = substr($cn, 0, strrpos($cn, '\\'));

                $entityName = $cn . '\Entity\\' . $entityName;
            }

        } catch (\Exception $ex) {
        }

        $entityName = new \ReflectionClass($entityName);

        return $entityName->getName();
    }

    /**
     * Get the roles of host matched with anonymous role config.
     *
     * @param string|null $hostname
     *
     * @return array
     */
    protected function getHostRoles($hostname = null)
    {
        if (null === $hostname) {
            return array();
        }

        $roles = array();
        $anonymousRole = null;
        $rolesForHosts = $this->getContainer()->getParameter('sonatra_security.anonymous_authentication.hosts');

        foreach ($rolesForHosts as $host => $role) {
            if (preg_match('/.'.$host.'/', $hostname)) {
                $anonymousRole = $role;
                break;
            }
        }

        // find role for anonymous
        if (null !== $anonymousRole) {
            $roles[] = $anonymousRole;
        }

        return $roles;
    }
}
