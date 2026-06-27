<?php

/*
 * Copyright (c) OktoDark Studios
 * Website: https://www.oktodark.com
 *
 * Author: Razvan George H. (Viruzzz)
 *
 * For the full copyright and license information, please view the LICENSE.
 */

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\EquatableInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`users`')]
#[UniqueEntity(fields: ['email'], message: 'error.email_taken')]
#[UniqueEntity(fields: ['username'], message: 'error.username_taken')]
class User implements UserInterface, PasswordAuthenticatedUserInterface, EquatableInterface
{
    final public const ROLE_USER = 'ROLE_USER';
    final public const ROLE_ADMIN = 'ROLE_ADMIN';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    #[ORM\Column(type: Types::STRING, unique: true)]
    private ?string $email = null;

    #[ORM\Column(type: Types::STRING, length: 50, unique: true)]
    private ?string $username = null;

    #[ORM\Column(type: Types::STRING, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $firstName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $lastName = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $signature = null;

    #[ORM\Column(type: 'datetime')]
    private \DateTimeInterface $createdAt;

    #[ORM\Column(type: Types::JSON)]
    private array $forumSettings = [
        'showSignature' => true,
        'showOtherSignatures' => true,
        'stayHidden' => false,
    ];

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private array $socialLinks = [];

    #[ORM\Column(type: Types::JSON)]
    private array $privacy = [
        'profilePublic' => true,
        'showFirstName' => true,
        'showLastName' => true,
        'showEmail' => false,
        'showLocation' => true,
        'showSocialLinks' => true,
        'showRoles' => true,
    ];

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $active = false;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $isVerified = false;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $agreedTerms = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastLoginAt = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $lastActivityAt = null;

    #[ORM\Column(type: Types::STRING, length: 45, nullable: true)]
    private ?string $lastLoginIp = null;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $loginAlertsEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $trustedDevicesEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $twofaResendEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $trackingEnabled = true;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $newsletter = false;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $darkMode = false;

    #[ORM\Column(type: Types::INTEGER)]
    private int $reputation = 0;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $notificationPreferences = [
        'blog_email' => true,
        'blog_onsite' => true,
        'forum_email' => true,
        'forum_onsite' => true,
        'follow_email' => true,
        'follow_onsite' => true,
    ];

    #[ORM\ManyToMany(targetEntity: Badge::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_badges')]
    private Collection $badges;

    #[ORM\OneToMany(targetEntity: TrustedDevice::class, mappedBy: 'user', cascade: ['remove'])]
    private Collection $trustedDevices;

    #[ORM\OneToMany(targetEntity: ForumPost::class, mappedBy: 'author')]
    private Collection $forumPosts;

    #[ORM\OneToMany(targetEntity: ForumThread::class, mappedBy: 'author')]
    private Collection $forumThreads;

    #[ORM\ManyToMany(targetEntity: self::class, inversedBy: 'followers')]
    #[ORM\JoinTable(name: 'user_follows')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id')]
    #[ORM\InverseJoinColumn(name: 'following_user_id', referencedColumnName: 'id')]
    private Collection $following;

    #[ORM\ManyToMany(targetEntity: self::class, mappedBy: 'following')]
    private Collection $followers;

    #[ORM\ManyToMany(targetEntity: ForumThread::class, inversedBy: 'subscribers')]
    #[ORM\JoinTable(name: 'user_thread_subscriptions')]
    private Collection $subscribedThreads;

    #[ORM\OneToMany(targetEntity: Board::class, mappedBy: 'owner')]
    private Collection $ownedBoards;

    #[ORM\ManyToMany(targetEntity: Board::class, mappedBy: 'members')]
    private Collection $memberBoards;

    #[ORM\OneToMany(targetEntity: Card::class, mappedBy: 'createdBy')]
    private Collection $createdCards;

    #[ORM\OneToMany(targetEntity: CardComment::class, mappedBy: 'author')]
    private Collection $cardComments;

    #[ORM\OneToMany(targetEntity: Bug::class, mappedBy: 'reporter')]
    private Collection $reportedBugs;

    #[ORM\OneToMany(targetEntity: Bug::class, mappedBy: 'assignee')]
    private Collection $assignedBugs;

    #[ORM\OneToMany(targetEntity: ActivityLog::class, mappedBy: 'user')]
    private Collection $activityLogs;

    #[ORM\OneToMany(targetEntity: Notification::class, mappedBy: 'user', cascade: ['remove'], orphanRemoval: true)]
    private Collection $notifications;

    // ---------------------------------------------------------
    // PURE DB ROLE SYSTEM (ManyToMany)
    // ---------------------------------------------------------
    #[ORM\ManyToMany(targetEntity: Role::class, inversedBy: 'users')]
    #[ORM\JoinTable(name: 'user_role')]
    private Collection $roleEntities;

    public function __construct()
    {
        $this->trustedDevices = new ArrayCollection();
        $this->badges = new ArrayCollection();
        $this->forumPosts = new ArrayCollection();
        $this->forumThreads = new ArrayCollection();
        $this->following = new ArrayCollection();
        $this->followers = new ArrayCollection();
        $this->subscribedThreads = new ArrayCollection();
        $this->ownedBoards = new ArrayCollection();
        $this->memberBoards = new ArrayCollection();
        $this->createdCards = new ArrayCollection();
        $this->cardComments = new ArrayCollection();
        $this->reportedBugs = new ArrayCollection();
        $this->assignedBugs = new ArrayCollection();
        $this->activityLogs = new ArrayCollection();
        $this->notifications = new ArrayCollection();
        $this->roleEntities = new ArrayCollection();
        $this->createdAt = new \DateTime();
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    // ---------------------------------------------------------
    // PURE DB ROLES ONLY
    // ---------------------------------------------------------
    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        foreach ($this->roleEntities as $roleEntity) {
            $roles[] = $roleEntity->getName();
        }

        return array_unique($roles);
    }

    public function setRoles(array $roles): self
    {
        $this->roleEntities->clear();

        foreach ($roles as $role) {
            if ($role instanceof Role) {
                $this->addRoleEntity($role);
            }
        }

        return $this;
    }

    public function getRoleByName(string $name): ?Role
    {
        foreach ($this->roleEntities as $role) {
            if ($role->getName() === $name) {
                return $role;
            }
        }

        return null;
    }

    public function getRoleEntities(): Collection
    {
        return $this->roleEntities;
    }

    public function addRoleEntity(Role $role): self
    {
        if (!$this->roleEntities->contains($role)) {
            $this->roleEntities->add($role);
            $role->addUser($this);
        }

        return $this;
    }

    public function removeRoleEntity(Role $role): self
    {
        if ($this->roleEntities->removeElement($role)) {
            $role->removeUser($this);
        }

        return $this;
    }

    // ---------------------------------------------------------
    // REST OF ORIGINAL USER ENTITY
    // ---------------------------------------------------------

    public function eraseCredentials(): void
    {
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function isEqualTo(UserInterface $user): bool
    {
        if (!$user instanceof self) {
            return false;
        }

        return
            $this->password === $user->getPassword()
            && $this->getUserIdentifier() === $user->getUserIdentifier();
    }

    // IDENTITY
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): self
    {
        $this->email = $email;

        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): self
    {
        $this->username = $username;

        return $this;
    }

    public function setPassword(?string $password): self
    {
        $this->password = $password;

        return $this;
    }

    // PROFILE
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $name): self
    {
        $this->firstName = $name;

        return $this;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $name): self
    {
        $this->lastName = $name;

        return $this;
    }

    public function getFullName(): string
    {
        if (!$this->firstName && !$this->lastName) {
            return (string) $this->username;
        }

        return mb_trim($this->firstName.' '.$this->lastName);
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): self
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function getAvatarUrl(): ?string
    {
        return $this->avatar ? '/uploads/avatars/'.$this->avatar : null;
    }

    public function getSignature(): ?string
    {
        return $this->signature;
    }

    public function setSignature(?string $signature): self
    {
        $this->signature = $signature;

        return $this;
    }

    public function getCreatedAt(): \DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getForumSettings(): array
    {
        $defaults = [
            'showSignature' => true,
            'showOtherSignatures' => true,
            'stayHidden' => false,
        ];

        return array_merge($defaults, $this->forumSettings ?? []);
    }

    public function setForumSettings(array $forumSettings): self
    {
        $this->forumSettings = $forumSettings;

        return $this;
    }

    public function getSocialLinks(): array
    {
        return $this->socialLinks ?? [];
    }

    public function setSocialLinks(array $socialLinks): self
    {
        $this->socialLinks = $socialLinks;

        return $this;
    }

    // PRIVACY
    public function getPrivacy(): array
    {
        $defaults = [
            'profilePublic' => true,
            'showFirstName' => true,
            'showLastName' => true,
            'showEmail' => false,
            'showLocation' => true,
            'showSocialLinks' => true,
        ];

        $stored = \is_array($this->privacy) ? $this->privacy : [];
        $merged = array_merge($defaults, $stored);

        foreach ($merged as $key => $value) {
            $merged[$key] = filter_var($value, \FILTER_VALIDATE_BOOLEAN);
        }

        return $merged;
    }

    public function setPrivacy(array $privacy): self
    {
        foreach ($privacy as $key => $value) {
            $privacy[$key] = filter_var($value, \FILTER_VALIDATE_BOOLEAN);
        }

        $this->privacy = $privacy;

        return $this;
    }

    public function getPrivacyValue(string $key, mixed $default = null): mixed
    {
        return $this->privacy[$key] ?? $default;
    }

    public function setPrivacyValue(string $key, mixed $value): self
    {
        $this->privacy[$key] = $value;

        return $this;
    }

    // ACCOUNT STATUS
    public function setActive(bool $active): self
    {
        $this->active = $active;

        return $this;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isPending(): bool
    {
        return !$this->isVerified && !$this->active;
    }

    public function isVerifiedOnly(): bool
    {
        return $this->isVerified && !$this->active;
    }

    public function isFullyActive(): bool
    {
        return $this->isVerified && $this->active;
    }

    public function getAgreedTerms(): ?\DateTimeInterface
    {
        return $this->agreedTerms;
    }

    public function agreeTerms(): void
    {
        $this->agreedTerms = new \DateTime();
    }

    public function isVerified(): bool
    {
        return $this->isVerified;
    }

    public function setIsVerified(bool $isVerified): self
    {
        $this->isVerified = $isVerified;

        return $this;
    }

    // LOGIN & SECURITY
    public function getLastLoginAt(): ?\DateTimeInterface
    {
        return $this->lastLoginAt;
    }

    public function setLastLoginAt(?\DateTimeInterface $lastLoginAt): self
    {
        $this->lastLoginAt = $lastLoginAt;

        return $this;
    }

    public function getLastActivityAt(): ?\DateTimeInterface
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(?\DateTimeInterface $lastActivityAt): self
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    public function getLastLoginIp(): ?string
    {
        return $this->lastLoginIp;
    }

    public function setLastLoginIp(?string $lastLoginIp): self
    {
        $this->lastLoginIp = $lastLoginIp;

        return $this;
    }

    public function isLoginAlertsEnabled(): bool
    {
        return $this->loginAlertsEnabled;
    }

    public function setLoginAlertsEnabled(bool $enabled): self
    {
        $this->loginAlertsEnabled = $enabled;

        return $this;
    }

    public function isTrustedDevicesEnabled(): bool
    {
        return $this->trustedDevicesEnabled;
    }

    public function setTrustedDevicesEnabled(bool $enabled): self
    {
        $this->trustedDevicesEnabled = $enabled;

        return $this;
    }

    public function isTwofaResendEnabled(): bool
    {
        return $this->twofaResendEnabled;
    }

    public function setIsTwofaResendEnabled(bool $enabled): self
    {
        $this->twofaResendEnabled = $enabled;

        return $this;
    }

    public function isTrackingEnabled(): bool
    {
        return $this->trackingEnabled;
    }

    public function setTrackingEnabled(bool $trackingEnabled): self
    {
        $this->trackingEnabled = $trackingEnabled;

        return $this;
    }

    // PREFERENCES
    public function getNewsletter(): ?bool
    {
        return $this->newsletter;
    }

    public function setNewsletter(?bool $newsletter): self
    {
        $this->newsletter = $newsletter;

        return $this;
    }

    public function getDarkMode(): ?bool
    {
        return $this->darkMode;
    }

    public function setDarkMode(?bool $darkMode): self
    {
        $this->darkMode = $darkMode;

        return $this;
    }

    public function getReputation(): int
    {
        return $this->reputation;
    }

    public function setReputation(int $reputation): self
    {
        $this->reputation = $reputation;

        return $this;
    }

    public function addReputation(int $points): self
    {
        $this->reputation += $points;

        return $this;
    }

    // NOTIFICATION PREFERENCES (Flattened JSON)
    public function getNotificationPreferences(): ?array
    {
        // Define the base default structure inline
        $baseDefaultPrefs = [
            'blog_email' => true,
            'blog_onsite' => true,
            'forum_email' => true,
            'forum_onsite' => true,
            'follow_email' => true,
            'follow_onsite' => true,
        ];

        // Ensure $this->notificationPreferences is always an array before merging, or use an empty array if null
        $currentPrefs = \is_array($this->notificationPreferences) ? $this->notificationPreferences : [];

        // Merge stored preferences with the base defaults to ensure all keys exist
        $merged = array_replace_recursive(
            $baseDefaultPrefs,
            $currentPrefs
        );

        // Ensure all values are booleans
        array_walk($merged, static function (&$value) {
            $value = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
            if (null === $value) {
                $value = false; // Default to false if validation fails
            }
        });

        return $merged;
    }

    public function setNotificationPreferences(?array $notificationPreferences): self
    {
        // Define the base default structure inline
        $baseDefaultPrefs = [
            'blog_email' => true,
            'blog_onsite' => true,
            'forum_email' => true,
            'forum_onsite' => true,
            'follow_email' => true,
            'follow_onsite' => true,
        ];

        // If null is passed, set to null
        if (null === $notificationPreferences) {
            $this->notificationPreferences = null;
        } else {
            // Merge provided preferences with the base defaults to ensure completeness
            $merged = array_replace_recursive(
                $baseDefaultPrefs,
                $notificationPreferences
            );

            // Ensure all values are booleans
            array_walk($merged, static function (&$value) {
                $value = filter_var($value, \FILTER_VALIDATE_BOOLEAN, \FILTER_NULL_ON_FAILURE);
                if (null === $value) {
                    $value = false; // Default to false if validation fails
                }
            });

            $this->notificationPreferences = $merged;
        }

        return $this;
    }

    // Helper to get a specific flattened preference
    public function getNotificationPreference(string $key): bool
    {
        $preferences = $this->getNotificationPreferences(); // This will return defaults if $notificationPreferences is null

        return $preferences[$key] ?? false;
    }

    // Helper to set a specific flattened preference
    public function setNotificationPreference(string $key, bool $enabled): self
    {
        // Ensure we start with a complete set of preferences (or defaults)
        $preferences = $this->getNotificationPreferences();
        $preferences[$key] = $enabled;
        $this->notificationPreferences = $preferences;

        return $this;
    }

    public function getBadges(): Collection
    {
        return $this->badges;
    }

    public function addBadge(Badge $badge): self
    {
        if (!$this->badges->contains($badge)) {
            $this->badges->add($badge);
        }

        return $this;
    }

    public function removeBadge(Badge $badge): self
    {
        $this->badges->removeElement($badge);

        return $this;
    }

    // RELATIONS
    public function getTrustedDevices(): Collection
    {
        return $this->trustedDevices;
    }

    public function addTrustedDevice(TrustedDevice $device): self
    {
        if (!$this->trustedDevices->contains($device)) {
            $this->trustedDevices[] = $device;
            $device->setUser($this);
        }

        return $this;
    }

    public function removeTrustedDevice(TrustedDevice $device): self
    {
        if ($this->trustedDevices->removeElement($device)) {
            if ($device->getUser() === $this) {
                $device->setUser(null);
            }
        }

        return $this;
    }

    public function getPostCount(): int
    {
        return $this->forumPosts->count();
    }

    public function getThreadCount(): int
    {
        return $this->forumThreads->count();
    }

    // SOCIAL RELATIONS
    public function getFollowing(): Collection
    {
        return $this->following;
    }

    public function follow(self $user): self
    {
        if (!$this->following->contains($user)) {
            $this->following->add($user);
        }

        return $this;
    }

    public function unfollow(self $user): self
    {
        $this->following->removeElement($user);

        return $this;
    }

    public function getFollowers(): Collection
    {
        return $this->followers;
    }

    public function getSubscribedThreads(): Collection
    {
        return $this->subscribedThreads;
    }

    public function subscribeToThread(ForumThread $thread): self
    {
        if (!$this->subscribedThreads->contains($thread)) {
            $this->subscribedThreads->add($thread);
        }

        return $this;
    }

    public function unsubscribeFromThread(ForumThread $thread): self
    {
        $this->subscribedThreads->removeElement($thread);

        return $this;
    }

    /**
     * @return Collection<int, Notification>
     */
    public function getNotifications(): Collection
    {
        return $this->notifications;
    }

    public function addNotification(Notification $notification): self
    {
        if (!$this->notifications->contains($notification)) {
            $this->notifications->add($notification);
            $notification->setUser($this);
        }

        return $this;
    }

    public function removeNotification(Notification $notification): self
    {
        if ($this->notifications->removeElement($notification)) {
            // set the owning side to null (unless already changed)
            if ($notification->getUser() === $this) {
                $notification->setUser(null);
            }
        }

        return $this;
    }

    public function getUnreadNotificationsCount(): int
    {
        $count = 0;
        foreach ($this->notifications as $notification) {
            if (!$notification->isRead()) {
                ++$count;
            }
        }

        return $count;
    }

    public function __serialize(): array
    {
        return [$this->id, $this->email, $this->password];
    }

    public function __unserialize(array $data): void
    {
        [$this->id, $this->email, $this->password] = $data;
    }

    public function __toString(): string
    {
        return (string) $this->getUserIdentifier();
    }

    public function isOnline(): bool
    {
        if ($this->getForumSettings()['stayHidden']) {
            return false;
        }

        if ($this->lastActivityAt instanceof \DateTimeInterface) {
            $interval = (new \DateTime())->getTimestamp() - $this->lastActivityAt->getTimestamp();

            return $interval < (5 * 60);
        }

        return false;
    }
}
