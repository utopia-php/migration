<?php

namespace Utopia\Transfer;

abstract class Resource
{
    const STATUS_PENDING = 'PENDING';
    const STATUS_SUCCESS = 'SUCCESS';
    const STATUS_ERROR = 'ERROR';
    const STATUS_SKIPPED = 'SKIP';
    const STATUS_PROCESSING = 'PROCESSING';
    const STATUS_WARNING = 'WARNING';
    /**
     * For some transfers (namely Firebase) we have to keep resources in cache that do not necessarily need to be Transferred
     * This status is used to mark resources that are not going to be transferred but are still needed for the transfer to work
     * e.g Documents are required for Database transfers because of schema tracing in firebase
     */
    const STATUS_DISREGARDED = 'DISREGARDED';


    const TYPE_ATTRIBUTE = 'Attribute';
    const TYPE_BUCKET = 'Bucket';
    const TYPE_COLLECTION = 'Collection';
    const TYPE_DATABASE = 'Database';
    const TYPE_DOCUMENT = 'Document';
    const TYPE_FILE = 'File';
    const TYPE_FILEDATA = 'FileData';
    const TYPE_FUNCTION = 'Function';
    const TYPE_DEPLOYMENT = 'Deployment';
    const TYPE_HASH = 'Hash';
    const TYPE_INDEX = 'Index';
    const TYPE_USER = 'User';
    const TYPE_ENVVAR = 'EnvVar';
    const TYPE_TEAM = 'Team';
    const TYPE_TEAM_MEMBERSHIP = 'TeamMembership';

    const ALL_RESOURCES = [
        self::TYPE_ATTRIBUTE,
        self::TYPE_BUCKET,
        self::TYPE_COLLECTION,
        self::TYPE_DATABASE,
        self::TYPE_DOCUMENT,
        self::TYPE_FILE,
        self::TYPE_FILEDATA,
        self::TYPE_FUNCTION,
        self::TYPE_DEPLOYMENT,
        self::TYPE_HASH,
        self::TYPE_INDEX,
        self::TYPE_USER,
        self::TYPE_ENVVAR,
        self::TYPE_TEAM,
        self::TYPE_TEAM_MEMBERSHIP
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
     *
     * @return string
     */
    abstract static function getName(): string;

    /**
     * Get Parent Group
     *
     * @return
     */
    abstract public function getGroup(): string;

    /**
     * Get ID
     *
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set ID
     *
     * @param string $id
     * @return self
     */
    public function setId(string $id): self
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Get Internal ID
     *
     * @return string
     */
    public function getInternalId(): string
    {
        return $this->internalId;
    }

    /**
     * Set Internal ID
     *
     * @param string $internalId
     * @return self
     */
    public function setInternalId(string $internalId): self
    {
        $this->internalId = $internalId;
        return $this;
    }

    /**
     * Get Status
     *
     * @return string
     */
    public function getStatus(): string
    {
        return $this->status;
    }

    /**
     * Set Status
     *
     * @param string $status
     * @param string $reason
     * @return self
     */
    public function setStatus(string $status, string $reason = ''): self
    {
        $this->status = $status;
        $this->reason = $reason;
        return $this;
    }

    /**
     * Get Reason
     *
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * As Array
     *
     * @return array
     */
    abstract public function asArray(): array;
}
