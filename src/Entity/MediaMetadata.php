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

use App\Enum\MediaType;
use App\Enum\Source;
use App\Repository\MediaMetadataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MediaMetadataRepository::class)]
#[ORM\Table(name: 'tracking_item')]
#[ORM\UniqueConstraint(
    name: 'unique_item_composite',
    columns: ['media_id', 'source', 'media_type', 'season_number', 'episode_number']
)]
class MediaMetadata
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private ?int $id = null;

    /**
     * Internal media identifier (UUID or custom ID).
     * Shared across TV → Season → Episode.
     */
    #[ORM\Column(type: Types::STRING, length: 256)]
    private ?string $mediaId = null;

    /**
     * Source of metadata (TMDB, IGDB, AniList, TVMaze, Manual, etc.).
     */
    #[ORM\Column(length: 20, enumType: Source::class)]
    private ?Source $source = null;

    /**
     * Media type (movie, anime, game, tv, season, episode).
     */
    #[ORM\Column(length: 10, enumType: MediaType::class)]
    private ?MediaType $mediaType = MediaType::MOVIE;

    /**
     * Title (movie title, anime title, game title, TV show title, episode title).
     */
    #[ORM\Column(type: Types::TEXT)]
    private ?string $title = null;

    /**
     * Cover image URL.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $image = null;

    /**
     * Season number (TV / Anime).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $seasonNumber = null;

    /**
     * Episode number (TV / Anime).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true, options: ['unsigned' => true])]
    private ?int $episodeNumber = null;

    /**
     * Release date (movie release, anime start date, game release, TV premiere).
     */
    #[ORM\Column(type: Types::DATETIME_MUTABLE, nullable: true)]
    private ?\DateTimeInterface $releaseDate = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $year = null;

    /**
     * Country of origin (TMDB origin_country or TVMaze network country).
     */
    #[ORM\Column(type: Types::STRING, length: 100, nullable: true)]
    private ?string $country = null;

    /**
     * TVMaze external ID (show ID).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $externalId = null;

    /**
     * Summary / Overview.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $overview = null;

    /**
     * Genres (TV, Anime, Movie, Game).
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $genres = null;

    /**
     * Runtime (TV episode runtime, movie runtime).
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $runtime = null;

    /**
     * Estimated runtime (used for unified stats across media types).
     * Movies: minutes
     * Games: estimated hours → converted to minutes
     * TV/Anime: season/episode aggregated runtime.
     */
    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    private ?int $runtimeEstimate = null;

    /**
     * TMDB ID (movies, TV, anime).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $tmdbId = null;

    /**
     * IGDB ID (games).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $igdbId = null;

    /**
     * AniList ID (anime).
     */
    #[ORM\Column(type: Types::STRING, length: 50, nullable: true)]
    private ?string $anilistId = null;

    /**
     * Episode screenshot / still image URL.
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $screenshot = null;

    /**
     * Cast members (episode-level guest stars where available).
     *
     * @var array<int, array{name: ?string, character: ?string, image: ?string}>|null
     */
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cast = null;

    /**
     * Trailer video URL (YouTube embed where available).
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $trailer = null;

    // ─────────────────────────────────────────────
    // GETTERS / SETTERS
    // ─────────────────────────────────────────────

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMediaId(): ?string
    {
        return $this->mediaId;
    }

    public function setMediaId(string $mediaId): self
    {
        $this->mediaId = $mediaId;

        return $this;
    }

    public function getSource(): ?Source
    {
        return $this->source;
    }

    public function setSource(Source $source): self
    {
        $this->source = $source;

        return $this;
    }

    public function getMediaType(): ?MediaType
    {
        return $this->mediaType;
    }

    public function setMediaType(MediaType $mediaType): self
    {
        $this->mediaType = $mediaType;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): self
    {
        $this->image = $image;

        return $this;
    }

    public function getSeasonNumber(): ?int
    {
        return $this->seasonNumber;
    }

    public function setSeasonNumber(?int $seasonNumber): self
    {
        $this->seasonNumber = $seasonNumber;

        return $this;
    }

    public function getEpisodeNumber(): ?int
    {
        return $this->episodeNumber;
    }

    public function setEpisodeNumber(?int $episodeNumber): self
    {
        $this->episodeNumber = $episodeNumber;

        return $this;
    }

    public function getReleaseDate(): ?\DateTimeInterface
    {
        return $this->releaseDate;
    }

    public function setReleaseDate(?\DateTimeInterface $releaseDate): self
    {
        $this->releaseDate = $releaseDate;

        return $this;
    }

    public function getYear(): ?int
    {
        return $this->year;
    }

    public function setYear(?int $year): self
    {
        $this->year = $year;

        return $this;
    }

    public function getExternalId(): ?string
    {
        return $this->externalId;
    }

    public function setExternalId(?string $externalId): self
    {
        $this->externalId = $externalId;

        return $this;
    }

    public function getCountry(): ?string
    {
        return $this->country;
    }

    public function setCountry(?string $country): self
    {
        $this->country = $country;

        return $this;
    }

    public function getOverview(): ?string
    {
        return $this->overview;
    }

    public function setOverview(?string $overview): self
    {
        $this->overview = $overview;

        return $this;
    }

    public function getGenres(): ?array
    {
        return $this->genres;
    }

    public function setGenres(?array $genres): self
    {
        $this->genres = $genres;

        return $this;
    }

    public function getRuntime(): ?int
    {
        return $this->runtime;
    }

    public function setRuntime(?int $runtime): self
    {
        $this->runtime = $runtime;

        return $this;
    }

    public function getRuntimeEstimate(): ?int
    {
        return $this->runtimeEstimate;
    }

    public function setRuntimeEstimate(?int $runtimeEstimate): self
    {
        $this->runtimeEstimate = $runtimeEstimate;

        return $this;
    }

    public function getTmdbId(): ?string
    {
        return $this->tmdbId;
    }

    public function setTmdbId(?string $tmdbId): self
    {
        $this->tmdbId = $tmdbId;

        return $this;
    }

    public function getIgdbId(): ?string
    {
        return $this->igdbId;
    }

    public function setIgdbId(?string $igdbId): self
    {
        $this->igdbId = $igdbId;

        return $this;
    }

    public function getAnilistId(): ?string
    {
        return $this->anilistId;
    }

    public function setAnilistId(?string $anilistId): self
    {
        $this->anilistId = $anilistId;

        return $this;
    }

    public function getScreenshot(): ?string
    {
        return $this->screenshot;
    }

    public function setScreenshot(?string $screenshot): self
    {
        $this->screenshot = $screenshot;

        return $this;
    }

    /**
     * @return array<int, array{name: ?string, character: ?string, image: ?string}>|null
     */
    public function getCast(): ?array
    {
        return $this->cast;
    }

    /**
     * @param array<int, array{name: ?string, character: ?string, image: ?string}>|null $cast
     */
    public function setCast(?array $cast): self
    {
        $this->cast = $cast;

        return $this;
    }

    public function getTrailer(): ?string
    {
        return $this->trailer;
    }

    public function setTrailer(?string $trailer): self
    {
        $this->trailer = $trailer;

        return $this;
    }

    // ─────────────────────────────────────────────
    // APPLY IMPORTED METADATA (UNIFIED)
    // ─────────────────────────────────────────────

    public function applyImportedMetadata(array $data): void
    {
        $this->title = $data['title'] ?? $this->title;
        $this->image = $data['image'] ?? $this->image;
        $this->overview = $data['overview'] ?? $this->overview;

        if (\array_key_exists('screenshot', $data)) {
            $this->screenshot = $data['screenshot'] ?? $this->screenshot;
        } elseif (null === $this->screenshot
            && !empty($data['episodeNumber'])
            && !empty($data['image'])
        ) {
            // For episodes, the still image doubles as the screenshot.
            $this->screenshot = $data['image'];
        }

        if (\array_key_exists('cast', $data)) {
            $this->cast = $data['cast'] ?? $this->cast;
        }

        if (\array_key_exists('trailer', $data)) {
            $this->trailer = $data['trailer'] ?? $this->trailer;
        }

        if (!empty($data['releaseDate'])) {
            $this->releaseDate = new \DateTime($data['releaseDate']);
        }

        $ids = $data['externalIds'] ?? [];

        $this->tmdbId = $ids['tmdb'] ?? $this->tmdbId;
        $this->igdbId = $ids['igdb'] ?? $this->igdbId;
        $this->anilistId = $ids['anilist'] ?? $this->anilistId;
        $this->externalId = $ids['tvmaze'] ?? $this->externalId;
    }
}
