<?php

namespace Utopia\Migration;

abstract class Resource implements \JsonSerializable
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

    public const TYPE_TABLE = 'table';

    public const TYPE_DATABASE = 'database';

    public const TYPE_ROW = 'row';

    public const TYPE_FILE = 'file';

    public const TYPE_USER = 'user';

    public const TYPE_TEAM = 'team';

    public const TYPE_MEMBERSHIP = 'membership';

    public const TYPE_FUNCTION = 'function';

    public const TYPE_INDEX = 'index';

    // Children (Resources that are created by other resources)

    public const TYPE_COLUMN = 'column';

    public const TYPE_DEPLOYMENT = 'deployment';

    public const TYPE_HASH = 'hash';

    public const TYPE_ENVIRONMENT_VARIABLE = 'environment-variable';

    // legacy terminologies
    public const TYPE_DOCUMENT = 'document';
    public const TYPE_ATTRIBUTE = 'attribute';
    public const TYPE_COLLECTION = 'collection';

    private const TYPE_MAP = [
        Resource::TYPE_ROW    => Resource::TYPE_DOCUMENT,
        Resource::TYPE_COLUMN => Resource::TYPE_ATTRIBUTE,
        Resource::TYPE_TABLE  => Resource::TYPE_COLLECTION,
    ];

    public const ALL_RESOURCES = [
        self::TYPE_COLUMN,
        self::TYPE_BUCKET,
        self::TYPE_TABLE,
        self::TYPE_DATABASE,
        self::TYPE_ROW,
        self::TYPE_FILE,
        self::TYPE_FUNCTION,
        self::TYPE_DEPLOYMENT,
        self::TYPE_HASH,
        self::TYPE_INDEX,
        self::TYPE_USER,
        self::TYPE_ENVIRONMENT_VARIABLE,
        self::TYPE_TEAM,
        self::TYPE_MEMBERSHIP,

        // legacy
        self::TYPE_DOCUMENT,
        self::TYPE_ATTRIBUTE,
        self::TYPE_COLLECTION,
    ];

    protected string $id = '';

    protected string $originalId = '';

    protected string $sequence = '';

    protected string $createdAt = '';
    protected string $updatedAt = '';

    protected string $status = self::STATUS_PENDING;

    protected string $message = '';

    /**
     * @var array<string>
     */
    protected array $permissions = [];

    abstract public static function getName(): string;

    abstract public function getGroup(): string;

    public static function isSupported(string|array $types, array $resources): bool
    {
        $allTypes = [];
        $types = (array) $types;

        foreach ($types as $type) {
            $allTypes[] = $type;
            if (isset(self::TYPE_MAP[$type])) {
                $allTypes[] = self::TYPE_MAP[$type];
            }
        }

        return (bool) \array_intersect($resources, $allTypes);
    }

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

    public function getSequence(): string
    {
        return $this->sequence;
    }

    public function setSequence(string $sequence): self
    {
        $this->sequence = $sequence;

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
     * @return array<string>
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

    /**
     * @returns string
     */
    public function getCreatedAt(): string
    {
        return $this->createdAt;
    }

    /**
     * @returns string
     */
    public function getUpdatedAt(): string
    {
        return $this->updatedAt;
    }


    public function setCreatedAt(string $date): self
    {
        $this->createdAt = $date;

        return $this;
    }

    public function setUpdatedAt(string $date): self
    {
        $this->updatedAt = $date;

        return $this;
    }
}
