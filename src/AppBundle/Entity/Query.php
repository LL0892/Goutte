<?php

namespace AppBundle\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Query
 *
 * @ORM\Table(name="query")
 * @ORM\Entity(repositoryClass="AppBundle\Repository\QueryRepository")
 */
class Query
{
    /**
     * @var int
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @var string
     *
     * @ORM\Column(name="search", type="string", length=255)
     */
    private $search;

    /**
     * @var int
     *
     * @ORM\Column(name="ean", type="integer", length=13)
     */
    private $ean;


    /**
     * Get id
     *
     * @return integer 
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set search
     *
     * @param string $search
     * @return Query
     */
    public function setSearch($search)
    {
        $this->search = $search;

        return $this;
    }

    /**
     * Get search
     *
     * @return string 
     */
    public function getSearch()
    {
        return $this->search;
    }

    /**
     * Set ean
     *
     * @param int $ean
     * @return Query
     */
    public function setEan($ean)
    {
        $this->ean = $ean;

        return $this;
    }
    
    /**
     * Get ean
     * 
     * @return int
     */
    public function getEan()
    {
        return $this->ean;
    }
}
