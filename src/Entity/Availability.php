<?php
// ============================================================
// src/Entity/Availability.php
// ============================================================

namespace App\Entity;

use App\Repository\AvailabilityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AvailabilityRepository::class)]
class Availability
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Profile $profile = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 0, max: 6, notInRangeMessage: 'Le jour doit être entre 0 (dimanche) et 6 (samedi)')]
    private ?int $dayOfWeek = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $startTime = null;

    #[ORM\Column(type: Types::TIME_MUTABLE)]
    #[Assert\NotBlank]
    private ?\DateTimeInterface $endTime = null;

    #[ORM\Column]
    private bool $isRecurring = true;

    #[ORM\Column(type: Types::DATE_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $specificDate = null;

    #[ORM\Column]
    private bool $isAvailable = true;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\ManyToOne(inversedBy: 'availabilities')]
    private ?Sport $sport = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getProfile(): ?Profile
    {
        return $this->profile;
    }

    public function setProfile(?Profile $profile): static
    {
        $this->profile = $profile;
        return $this;
    }

    public function getDayOfWeek(): ?int
    {
        return $this->dayOfWeek;
    }

    public function setDayOfWeek(?int $dayOfWeek): static
    {
        $this->dayOfWeek = $dayOfWeek;
        return $this;
    }

    public function getStartTime(): ?\DateTimeInterface
    {
        return $this->startTime;
    }

    public function setStartTime(\DateTimeInterface $startTime): static
    {
        $this->startTime = $startTime;
        return $this;
    }

    public function getEndTime(): ?\DateTimeInterface
    {
        return $this->endTime;
    }

    public function setEndTime(\DateTimeInterface $endTime): static
    {
        $this->endTime = $endTime;
        return $this;
    }

    public function isRecurring(): bool
    {
        return $this->isRecurring;
    }

    public function setIsRecurring(bool $isRecurring): static
    {
        $this->isRecurring = $isRecurring;
        return $this;
    }

    public function getSpecificDate(): ?\DateTimeInterface
    {
        return $this->specificDate;
    }

    public function setSpecificDate(?\DateTimeInterface $specificDate): static
    {
        $this->specificDate = $specificDate;
        return $this;
    }

    public function isAvailable(): bool
    {
        return $this->isAvailable;
    }

    public function setIsAvailable(bool $isAvailable): static
    {
        $this->isAvailable = $isAvailable;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getSport(): ?Sport
    {
        return $this->sport;
    }

    public function setSport(?Sport $sport): static
    {
        $this->sport = $sport;

        return $this;
    }
}
