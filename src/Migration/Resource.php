<?php

namespace Utopia\Migration;

abstract class Resource implements \JsonSerializable
{
    public const string STATUS_PENDING = 'pending';

    public const string STATUS_SUCCESS = 'success';

    public const string STATUS_ERROR = 'error';

    public const string STATUS_SKIPPED = 'skip';

    public const string STATUS_PROCESSING = 'processing';

    public const string STATUS_WARNING = 'warning';

    /**
     * For some transfers (namely Firebase) we have to keep resources in cache that do not necessarily need to be Transferred
     * This status is used to mark resources that are not going to be transferred but are still needed for the transfer to work
     * e.g Documents are required for Database transfers because of schema tracing in firebase
     */
    public const string STATUS_DISREGARDED = 'disregarded';

    // Master Resources
    public const string TYPE_BUCKET = 'bucket';

    public const string TYPE_COLLECTION = 'collection';

    public const string TYPE_DATABASE = 'database';

    public const string TYPE_DOCUMENT = 'document';

    public const string TYPE_FILE = 'file';

    public const string TYPE_USER = 'user';

    public const string TYPE_TEAM = 'team';

    public const string TYPE_MEMBERSHIP = 'membership';

    public const string TYPE_FUNCTION = 'function';

    public const string TYPE_INDEX = 'index';

    // Children (Resources that are created by other resources)

    public const string TYPE_ATTRIBUTE = 'attribute';

    public const string TYPE_DEPLOYMENT = 'deployment';

    public const string TYPE_HASH = 'hash';

    public const string TYPE_ENVIRONMENT_VARIABLE = 'environment variable';

    public const array ALL_RESOURCES = [
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
        self::TYPE_ENVIRONMENT_VARIABLE,
        self::TYPE_TEAM,
        self::TYPE_MEMBERSHIP,
    ];

    protected string $id = '';

    protected string $originalId = '';

    protected string $internalId = '';

    protected string $status = self::STATUS_PENDING;

    protected string $message = '';

    protected array $permissions = [];

    abstract public static function getName(): string;

    abstract public function getGroup(): string;

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): self
    {
        $this->id = $id;

        return $this;
    }

    public function getOriginalId(): string
    {
        return $this->originalId;
    }

    public function setOriginalId(string $originalId): self
    {
        $this->originalId = $originalId;

        return $this;
    }

    public function getInternalId(): string
    {
        return $this->internalId;
    }

    public function setInternalId(string $internalId): self
    {
        $this->internalId = $internalId;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status, string $message = ''): self
    {
        $this->status = $status;
        $this->message = $message;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): self
    {
        $this->message = $message;

        return $this;
    }

    /**
     * @returns array<string>
     */
    public function getPermissions(): array
    {
        return $this->permissions;
    }

    /**
     * @param  array<string>  $permissions
     */
    public function setPermissions(array $permissions): self
    {
        $this->permissions = $permissions;

        return $this;
    }
}
