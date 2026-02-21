<?php

namespace App\Entity;

use App\Repository\ProfileRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProfileRepository::class)]
#[ORM\HasLifecycleCallbacks]
class Profile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'profile', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $user = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'La spécialité est obligatoire')]
    #[Assert\Choice(choices: ['coach', 'referee', 'health_specialist'], message: 'Spécialité invalide')]
    private ?string $specialty = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['pro', 'semi-pro', 'amateur'], message: 'Niveau invalide')]
    private string $level = 'amateur';

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Assert\Length(max: 2000, maxMessage: 'La biographie ne peut pas dépasser {{ limit }} caractères')]
    private ?string $bio = null;

    #[ORM\Column]
    #[Assert\PositiveOrZero(message: 'L\'expérience doit être positive')]
    private int $yearsOfExperience = 0;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    private ?string $city = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 8, nullable: true)]
    #[Assert\Range(min: -90, max: 90)]
    private ?string $latitude = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 11, scale: 8, nullable: true)]
    #[Assert\Range(min: -180, max: 180)]
    private ?string $longitude = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $certifications = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $diplomas = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 3, scale: 2, nullable: true)]
    private ?string $averageRating = null;

    #[ORM\Column]
    private int $totalReviews = 0;

    #[ORM\Column]
    private bool $isVerified = false;

    #[ORM\Column]
    private bool $isActive = true;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verifiedAt = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToMany(targetEntity: Sport::class, inversedBy: 'profiles')]
    #[ORM\JoinTable(name: 'profile_sport')]
    private Collection $sports;

    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Availability::class, cascade: ['remove'])]
    private Collection $availabilities;

    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Booking::class)]
    private Collection $bookings;

    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: Review::class, cascade: ['remove'])]
    private Collection $reviews;

    #[ORM\OneToMany(mappedBy: 'profile', targetEntity: SessionHistory::class)]
    private Collection $sessionHistories;

    public function __construct()
    {
        $this->sports = new ArrayCollection();
        $this->availabilities = new ArrayCollection();
        $this->bookings = new ArrayCollection();
        $this->reviews = new ArrayCollection();
        $this->sessionHistories = new ArrayCollection();
        $this->createdAt = new \DateTimeImmutable();
    }

    #[ORM\PreUpdate]
    public function setUpdatedAtValue(): void
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getSpecialty(): ?string
    {
        return $this->specialty;
    }

    public function setSpecialty(string $specialty): static
    {
        $this->specialty = $specialty;
        return $this;
    }

    public function getLevel(): string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getYearsOfExperience(): int
    {
        return $this->yearsOfExperience;
    }

    public function setYearsOfExperience(int $yearsOfExperience): static
    {
        $this->yearsOfExperience = $yearsOfExperience;
        return $this;
    }

    public function getCity(): ?string
    {
        return $this->city;
    }

    public function setCity(string $city): static
    {
        $this->city = $city;
        return $this;
    }

    public function getAddress(): ?string
    {
        return $this->address;
    }

    public function setAddress(?string $address): static
    {
        $this->address = $address;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function getCertifications(): ?string
    {
        return $this->certifications;
    }

    public function setCertifications(?string $certifications): static
    {
        $this->certifications = $certifications;
        return $this;
    }

    public function getDiplomas(): ?string
    {
        return $this->diplomas;
    }

    public function setDiplomas(?string $diplomas): static
    {
        $this->diplomas = $diplomas;
        return $this;
    }

    public function getAverageRating(): ?string
    {
        return $this->averageRating;
    }

    public function setAverageRating(?string $averageRating): static
    {
        $this->averageRating = $averageRating;
        return $this;
    }

    public function getTotalReviews(): int
    {
        return $this->totalReviews;
    }

    public function setTotalReviews(int $totalReviews): static
    {
        $this->totalReviews = $totalReviews;
        return $this;
    }

    public function getIsVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): static
    {
        $this->isVerified = $isVerified;
        return $this;
    }

    public function getIsActive(): bool
    {
        return $this->isActive;
    }

    public function setIsActive(bool $isActive): static
    {
        $this->isActive = $isActive;
        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verifiedAt;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verifiedAt): static
    {
        $this->verifiedAt = $verifiedAt;
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

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * @return Collection<int, Sport>
     */
    public function getSports(): Collection
    {
        return $this->sports;
    }

    public function addSport(Sport $sport): static
    {
        if (!$this->sports->contains($sport)) {
            $this->sports->add($sport);
        }
        return $this;
    }

    public function removeSport(Sport $sport): static
    {
        $this->sports->removeElement($sport);
        return $this;
    }

    /**
     * @return Collection<int, Availability>
     */
    public function getAvailabilities(): Collection
    {
        return $this->availabilities;
    }

    public function addAvailability(Availability $availability): static
    {
        if (!$this->availabilities->contains($availability)) {
            $this->availabilities->add($availability);
            $availability->setProfile($this);
        }
        return $this;
    }

    public function removeAvailability(Availability $availability): static
    {
        if ($this->availabilities->removeElement($availability)) {
            if ($availability->getProfile() === $this) {
                $availability->setProfile(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Booking>
     */
    public function getBookings(): Collection
    {
        return $this->bookings;
    }

    public function addBooking(Booking $booking): static
    {
        if (!$this->bookings->contains($booking)) {
            $this->bookings->add($booking);
            $booking->setProfile($this);
        }
        return $this;
    }

    public function removeBooking(Booking $booking): static
    {
        if ($this->bookings->removeElement($booking)) {
            if ($booking->getProfile() === $this) {
                $booking->setProfile(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Review>
     */
    public function getReviews(): Collection
    {
        return $this->reviews;
    }

    public function addReview(Review $review): static
    {
        if (!$this->reviews->contains($review)) {
            $this->reviews->add($review);
            $review->setProfile($this);
        }
        return $this;
    }

    public function removeReview(Review $review): static
    {
        if ($this->reviews->removeElement($review)) {
            if ($review->getProfile() === $this) {
                $review->setProfile(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, SessionHistory>
     */
    public function getSessionHistories(): Collection
    {
        return $this->sessionHistories;
    }

    public function addSessionHistory(SessionHistory $sessionHistory): static
    {
        if (!$this->sessionHistories->contains($sessionHistory)) {
            $this->sessionHistories->add($sessionHistory);
            $sessionHistory->setProfile($this);
        }
        return $this;
    }

    public function removeSessionHistory(SessionHistory $sessionHistory): static
    {
        if ($this->sessionHistories->removeElement($sessionHistory)) {
            if ($sessionHistory->getProfile() === $this) {
                $sessionHistory->setProfile(null);
            }
        }
        return $this;
    }

    /**
     * Recalcule la note moyenne basée sur les avis
     */
    public function recalculateRating(): void
    {
        $reviews = $this->reviews->filter(fn($review) => $review->getRating() !== null);
        $count = $reviews->count();

        if ($count === 0) {
            $this->averageRating = null;
            $this->totalReviews = 0;
            return;
        }

        $sum = 0;
        foreach ($reviews as $review) {
            $sum += $review->getRating();
        }

        $this->averageRating = number_format($sum / $count, 2);
        $this->totalReviews = $count;
    }
}
