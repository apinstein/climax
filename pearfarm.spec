<?php

$spec = Pearfarm_PackageSpec::create(array(Pearfarm_PackageSpec::OPT_BASEDIR => dirname(__FILE__)))
             ->setName('climax')
             ->setChannel('apinstein.pearfarm.org')
             ->setSummary('CLI Framework for PHP.')
             ->setDescription('CLI Framework for PHP. Makes it easy to build complex CLI applications. Also simple enough to use for small CLI scripts as well.')
             ->setReleaseVersion('0.0.4')
             ->setReleaseStability('alpha')
             ->setApiVersion('0.0.2')
             ->setApiStability('alpha')
             ->setLicense(Pearfarm_PackageSpec::LICENSE_MIT)
             ->setNotes('Initial release.')
             ->addMaintainer('lead', 'Alan Pinstein', 'apinstein', 'apinstein@mac.com')
             ->addGitFiles()
             ;
