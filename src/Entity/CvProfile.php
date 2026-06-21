<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

/**
 * Class CvProfile.
 */
#[ORM\Entity(repositoryClass: 'App\Repository\CvProfileRepository')]
#[ORM\Table(name: 'cv_profile')]
class CvProfile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(length: 150)]
    private string $title;

    #[ORM\Column(type: 'text')]
    private string $contentJson;

    /**
     * @brief Build default CV profile.
     * @param string $title Profile title.
     * @param string $contentJson JSON content payload.
     * @return void
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function __construct(string $title, string $contentJson)
    {
        $this->title = $title;
        $this->contentJson = $contentJson;
    }

    /**
     * @brief Get profile identifier.
     * @param void No input parameter.
     * @return int|null
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function getId(): ?int
    {
        return $this->id;
    }

    /**
     * @brief Get profile title.
     * @param void No input parameter.
     * @return string
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function getTitle(): string
    {
        return $this->title;
    }

    /**
     * @brief Update profile title.
     * @param string $title Profile title.
     * @return self
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @brief Get profile content payload.
     * @param void No input parameter.
     * @return string
     * @date 2026-04-22
     * @author Stephane H.
     */
    public function getContentJson(): string
    {
        return $this->contentJson;
    }

    /**
     * @brief Update profile content payload.
     * @param string $contentJson JSON content payload.
     * @return self
     * @date 2026-04-24
     * @author Stephane H.
     */
    public function setContentJson(string $contentJson): self
    {
        $this->contentJson = $contentJson;

        return $this;
    }
}
