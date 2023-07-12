<?php

namespace Utopia\Transfer;

abstract class Resource
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_SUCCESS = 'success';

    public const STATUS_ERROR = 'error';

    public const STATUS_SKIPPED = 'skip';

    public const STATUS_PROCESSING = 'processing';

    public const STATUS_WARNING = 'warning';

    /**
     * For some transfers (namely Firebase) we have to keep resources in cache that do not necessarily need to be Transferred
     * This status is used to mark resources that are not going to be transferred but are still needed for the transfer to work
     * e.g Documents are required for Database transfers because of schema tracing in firebase
     */
    public const STATUS_DISREGARDED = 'disregarded';

    // Master Resources
    public const TYPE_BUCKET = 'bucket';

    public const TYPE_COLLECTION = 'collection';

    public const TYPE_DATABASE = 'database';

    public const TYPE_DOCUMENT = 'document';

    public const TYPE_FILE = 'file';

    public const TYPE_USER = 'user';

    public const TYPE_TEAM = 'team';

    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPE_FUNCTION = 'function';

    // Children (Resources that are created by other resources)

    public const TYPE_ATTRIBUTE = 'attribute';

    public const TYPE_DEPLOYMENT = 'deployment';

    public const TYPE_HASH = 'hash';

    public const TYPE_INDEX = 'index';

    public const TYPE_ENVVAR = 'envvar';

    public const ALL_RESOURCES = [
        self::TYPE_ATTRIBUTE,
        self::TYPE_BUCKET,
        self::TYPE_COLLECTION,
        self::TYPE_DATABASE,
        self::TYPE_DOCUMENT,
        self::TYPE_FILE,
        self::TYPE_FUNCTION,
        self::TYPE_DEPLOYMENT,
        self::TYPE_HASH,
        self::TYPE_INDEX,
        self::TYPE_USER,
        self::TYPE_ENVVAR,
        self::TYPE_TEAM,
        self::TYPE_MEMBERSHIP,
    ];

    /**
     * ID of the resource
     */
    protected ?string $id;

    /**
     * Original ID of the resource
     */
    protected string $originalId = '';

    /**
     * Internal ID
     */
    protected string $internalId = '';

    /**
     * Status of the resource
     */
    protected string $status = self::STATUS_PENDING;

    /**
     * message for the status
     */
    protected string $message = '';

    /**
     * Gets the name of the adapter.
     */
    abstract public static function getName(): string;

    /**
     * Get Parent Group
     */
    abstract public function getGroup(): string;

    /**
     * Get ID
     */
    public function getId(): ?string
    {
        return $this->id;
    }

    /**
     * Set ID
     */
    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Get Original ID
     */
    public function getOriginalId(): string
    {
        return $this->originalId;
    }

    /**
     * Set Original ID
     */
    public function setOriginalId(string $originalId): self
    {
        $this->originalId = $originalId;

        return $this;
    }

    /**
     * Get Internal ID
     */
    public function getInternalId(): string
    {
        return $this->internalId;
    }

    /**
     * Set Internal ID
     */
    public function setInternalId(string $internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    /**
     * Get Status
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set Status
     */
    public function setStatus(string $status, string $message = ''): self
    {
        $this->status = $status;
        $this->message = $message;

        return $this;
    }

    /**
     * Get message
     */
    public function getMessage(): string
    {
        return $this->message;
    }

    /**
     * Set message
     */
    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * As Array
     */
    abstract public function asArray(): array;
}
