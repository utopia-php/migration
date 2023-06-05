<?php

namespace Utopia\Transfer;

abstract class Resource
{
    public const STATUS_PENDING = 'PENDING';

    public const STATUS_SUCCESS = 'SUCCESS';

    public const STATUS_ERROR = 'ERROR';

    public const STATUS_SKIPPED = 'SKIP';

    public const STATUS_PROCESSING = 'PROCESSING';

    public const STATUS_WARNING = 'WARNING';

    /**
     * For some transfers (namely Firebase) we have to keep resources in cache that do not necessarily need to be Transferred
     * This status is used to mark resources that are not going to be transferred but are still needed for the transfer to work
     * e.g Documents are required for Database transfers because of schema tracing in firebase
     */
    public const STATUS_DISREGARDED = 'DISREGARDED';

    public const TYPE_ATTRIBUTE = 'Attribute';

    public const TYPE_BUCKET = 'Bucket';

    public const TYPE_COLLECTION = 'Collection';

    public const TYPE_DATABASE = 'Database';

    public const TYPE_DOCUMENT = 'Document';

    public const TYPE_FILE = 'File';

    public const TYPE_FUNCTION = 'Function';

    public const TYPE_DEPLOYMENT = 'Deployment';

    public const TYPE_HASH = 'Hash';

    public const TYPE_INDEX = 'Index';

    public const TYPE_USER = 'User';

    public const TYPE_ENVVAR = 'EnvVar';

    public const TYPE_TEAM = 'Team';

    public const TYPE_MEMBERSHIP = 'Membership';

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
    protected string $id = '';

    /**
     * Internal ID
     */
    protected string $internalId = '';

    /**
     * Status of the resource
     */
    protected string $status = self::STATUS_PENDING;

    /**
     * Reason for the status
     */
    protected string $reason = '';

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
    public function getId(): string
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
    public function setStatus(string $status, string $reason = ''): self
    {
        $this->status = $status;
        $this->reason = $reason;

        return $this;
    }

    /**
     * Get Reason
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * As Array
     */
    abstract public function asArray(): array;
}
