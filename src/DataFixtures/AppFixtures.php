<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Entity\Area;
use App\Entity\Contact;
use App\Entity\Department;
use App\Entity\Partner;
use App\Entity\Project;
use App\Entity\Term;
use App\Entity\User;
use App\Enum\EndorsementAuthor;
use App\Enum\Funding;
use App\Enum\ProjectType;
use App\Enum\Status;
use App\Enum\Vocabulary;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private const array TAGS = ['Bæredygtighed', 'Borgerinddragelse', 'Innovation', 'Sundhed', 'Klima', 'Mobilitet', 'Data', 'Tryghed', 'Læring', 'Fællesskab'];
    private const array STAKEHOLDERS = ['Aarhus Kommune', 'Region Midtjylland', 'Aarhus Universitet', 'Erhverv Aarhus', 'Lokale foreninger', 'Boligforeninger', 'VIA University College', 'Business Region Aarhus'];
    private const array STRATEGIES = ['Klimaplan 2030', 'Erhvervsplan', 'Børn- og ungepolitik', 'Mobilitetsplan', 'Digitaliseringsstrategi', 'Sundhedspolitik'];
    private const array DEPARTMENTS = ['ITK Development', 'CFIA', 'Aarhus CityLab', 'Stab', 'OS2', 'AI Lab', 'IOT Lab', 'GTM', 'Fut Lab'];
    private const array PARTNERS = ['Aarhus Universitet', 'VIA University College', 'Alexandra Instituttet', 'Teknologisk Institut', 'Region Midtjylland', 'Erhverv Aarhus', 'Danmarks Tekniske Universitet', 'Aarhus Vand', 'AffaldVarme Aarhus', 'Dansk Industri'];
    private const array AREAS = ['Klima og miljø', 'Mobilitet', 'Velfærd', 'Kultur og fritid', 'Uddannelse', 'Erhverv', 'Digitalisering', 'Byudvikling'];
    private const array TOPICS = [
        'Digital Europe Blueprint for Data Space for smart and sustainable cities and communities.',
        'Horizon Europe — Climate-neutral and smart cities mission.',
        'Den fællesoffentlige digitaliseringsstrategi 2022–2026.',
        'Interreg Øresund-Kattegat-Skagerrak.',
    ];

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        // The 'password' credential below is for local dev/test only; fixtures
        // are a require-dev bundle and are never loaded in production.
        $admin = (new User())
            ->setEmail('admin@example.com')
            ->setName('Administrator')
            ->setRoles(['ROLE_ADMIN']);
        $admin->setPassword($this->hasher->hashPassword($admin, 'password'));
        $manager->persist($admin);

        $editor = (new User())
            ->setEmail('editor@example.com')
            ->setName('Redaktør')
            ->setRoles(['ROLE_USER']);
        $editor->setPassword($this->hasher->hashPassword($editor, 'password'));
        $manager->persist($editor);

        $users = [$admin, $editor];

        $tags = $this->makeTerms($manager, self::TAGS, Vocabulary::Tag);
        $stakeholders = $this->makeTerms($manager, self::STAKEHOLDERS, Vocabulary::Stakeholder);
        $strategies = $this->makeTerms($manager, self::STRATEGIES, Vocabulary::Strategy);

        $departments = [];
        foreach (self::DEPARTMENTS as $name) {
            $department = (new Department())->setName($name);
            $manager->persist($department);
            $departments[] = $department;
        }

        $areas = [];
        foreach (self::AREAS as $name) {
            $area = (new Area())->setName($name);
            $manager->persist($area);
            $areas[] = $area;
        }

        $partners = [];
        foreach (self::PARTNERS as $name) {
            $partner = (new Partner())
                ->setName($name)
                ->setDescription($name.' samarbejder med kommunen om udvikling, viden og afprøvning i konkrete projekter.')
                // ascii() turns spaces into dots (it also builds e-mail addresses), which
                // a domain does not want, so drop them again.
                ->setWebsite('https://www.'.strtolower(str_replace('.', '', $this->ascii($name))).'.dk');
            $manager->persist($partner);
            $partners[] = $partner;
        }

        $contacts = [];
        $firstNames = ['Anne', 'Mette', 'Lars', 'Søren', 'Camilla', 'Jens', 'Ida', 'Mads', 'Sofie', 'Peter', 'Louise', 'Thomas'];
        $lastNames = ['Jensen', 'Nielsen', 'Hansen', 'Pedersen', 'Andersen', 'Christensen', 'Larsen', 'Sørensen'];
        for ($i = 0; $i < 14; ++$i) {
            $name = $firstNames[array_rand($firstNames)].' '.$lastNames[array_rand($lastNames)];
            $contact = (new Contact())
                ->setName($name)
                ->setEmail(strtolower(str_replace(' ', '.', $this->ascii($name))).'@aarhus.dk')
                ->setPhone('+45 '.mt_rand(20, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99).' '.mt_rand(10, 99))
                ->setDepartment($departments[array_rand($departments)]);
            $manager->persist($contact);
            $contacts[] = $contact;
        }

        $titles = [
            'Grøn omstilling af kommunens bygninger',
            'Digital borgerservice 2.0',
            'Cykelstier i midtbyen',
            'Ungdomsråd og demokrati',
            'Klimatilpasning af Aarhus Å',
            'Smart City sensornetværk',
            'Fællesskaber i udsatte boligområder',
            'Bæredygtig madproduktion i institutioner',
            'Mobilitetshub ved banegården',
            'Sundhedshuse i lokalområderne',
            'Genbrug og cirkulær økonomi',
            'Tryghedsvandringer og byrum',
            'Læringsplatform for folkeskolen',
            'Erhvervsfremme for iværksættere',
            'Biodiversitet i parker og grønne områder',
            'Energirenovering af skoler',
            'Kunst i det offentlige rum',
            'Velfærdsteknologi i ældreplejen',
            'Deleøkonomi og samkørsel',
            'Inklusion på arbejdsmarkedet',
            'Vandkvalitet i havnebadet',
            'Frivillighed og medborgerskab',
            'Datadrevet byplanlægning',
            'Klimavenlig transport for medarbejdere',
        ];

        $statuses = Status::cases();
        $types = ProjectType::cases();
        $endorsers = EndorsementAuthor::cases();
        $fundings = Funding::cases();

        foreach ($titles as $index => $title) {
            $project = (new Project())
                ->setTitle($title)
                ->setArea($areas[array_rand($areas)])
                ->setProjectType($types[array_rand($types)])
                ->setStatus($statuses[array_rand($statuses)])
                ->setOrganizationalAnchoring($departments[array_rand($departments)])
                ->setSummary('Projektet arbejder med '.mb_strtolower($title).' gennem en tværgående indsats med fokus på borgernes hverdag og kommunens strategiske mål.')
                ->setDescription('Projektet er sat i gang, fordi kommunen har brug for at styrke indsatsen omkring '.mb_strtolower($title).".\n\nDet har ophæng i byrådets vedtagne strategier og i afdelingens handleplaner og gennemføres i samarbejde med relevante fagområder og eksterne partnere.")
                ->setEndorsement(0 === $index % 3 ? false : true)
                ->setBudget(mt_rand(1, 40) * 50000);
            $project->setCreatedBy($users[array_rand($users)]);

            // Not every project belongs to a wider programme.
            if (0 !== $index % 3) {
                $project->setTopic(self::TOPICS[$index % \count(self::TOPICS)]);
            }

            if (0 !== $index % 4) {
                $project->setEndorsementAuthor($endorsers[array_rand($endorsers)]);
            }

            $project->setFunding(\array_slice($this->shuffleCopy($fundings), 0, mt_rand(1, 3)));

            $start = new \DateTimeImmutable(sprintf('2025-%02d-01', mt_rand(1, 12)));
            $project->setTimePeriodStart($start);
            $project->setTimePeriodEnd($start->modify('+'.mt_rand(6, 36).' months'));

            foreach (\array_slice($this->shuffleCopy($tags), 0, mt_rand(1, 4)) as $term) {
                $project->addTag($term);
            }
            foreach (\array_slice($this->shuffleCopy($stakeholders), 0, mt_rand(1, 3)) as $term) {
                $project->addStakeholder($term);
            }
            foreach (\array_slice($this->shuffleCopy($strategies), 0, mt_rand(0, 2)) as $term) {
                $project->addStrategy($term);
            }
            foreach (\array_slice($this->shuffleCopy($contacts), 0, mt_rand(1, 3)) as $contact) {
                $project->addContact($contact);
            }
            foreach (\array_slice($this->shuffleCopy($partners), 0, mt_rand(1, 3)) as $partner) {
                $project->addPartner($partner);
            }

            $project->setLinks(['https://www.aarhus.dk']);

            $manager->persist($project);
        }

        $manager->flush();
    }

    /**
     * @param string[] $names
     *
     * @return Term[]
     */
    private function makeTerms(ObjectManager $manager, array $names, Vocabulary $vocabulary): array
    {
        $terms = [];
        foreach ($names as $name) {
            $term = (new Term($vocabulary))->setName($name);
            $manager->persist($term);
            $terms[] = $term;
        }

        return $terms;
    }

    /**
     * @template T
     *
     * @param array<int, T> $items
     *
     * @return array<int, T>
     */
    private function shuffleCopy(array $items): array
    {
        shuffle($items);

        return $items;
    }

    private function ascii(string $value): string
    {
        return str_replace(
            ['æ', 'ø', 'å', 'Æ', 'Ø', 'Å', ' '],
            ['ae', 'oe', 'aa', 'ae', 'oe', 'aa', '.'],
            $value,
        );
    }
}
