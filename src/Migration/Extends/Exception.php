<?php

namespace Utopia\Migration\Extends;

use Utopia\Config\Config;

class Exception extends \Exception
{
    /**
     * Error Codes
     *
     * Naming the error types based on the following convention
     * <ENTITY>_<ERROR_TYPE>
     *
     * Appwrite has the following entities:
     * - General
     * - Users
     * - Teams
     * - Memberships
     * - Avatars
     * - Storage
     * - Functions
     * - Deployments
     * - Executions
     * - Collections
     * - Documents
     * - Attributes
     * - Indexes
     * - Projects
     * - Webhooks
     * - Keys
     * - Platform
     */

    /** General */
    public const GENERAL_UNKNOWN                   = 'general_unknown';
    public const GENERAL_MOCK                      = 'general_mock';
    public const GENERAL_ACCESS_FORBIDDEN          = 'general_access_forbidden';
    public const GENERAL_UNKNOWN_ORIGIN            = 'general_unknown_origin';
    public const GENERAL_SERVICE_DISABLED          = 'general_service_disabled';
    public const GENERAL_UNAUTHORIZED_SCOPE        = 'general_unauthorized_scope';
    public const GENERAL_RATE_LIMIT_EXCEEDED       = 'general_rate_limit_exceeded';
    public const GENERAL_SMTP_DISABLED             = 'general_smtp_disabled';
    public const GENERAL_PHONE_DISABLED            = 'general_phone_disabled';
    public const GENERAL_ARGUMENT_INVALID          = 'general_argument_invalid';
    public const GENERAL_QUERY_LIMIT_EXCEEDED      = 'general_query_limit_exceeded';
    public const GENERAL_QUERY_INVALID             = 'general_query_invalid';
    public const GENERAL_ROUTE_NOT_FOUND           = 'general_route_not_found';
    public const GENERAL_CURSOR_NOT_FOUND          = 'general_cursor_not_found';
    public const GENERAL_SERVER_ERROR              = 'general_server_error';
    public const GENERAL_PROTOCOL_UNSUPPORTED      = 'general_protocol_unsupported';
    public const GENERAL_CODES_DISABLED            = 'general_codes_disabled';
    public const GENERAL_USAGE_DISABLED            = 'general_usage_disabled';
    public const GENERAL_NOT_IMPLEMENTED           = 'general_not_implemented';

    /** Users */
    public const USER_COUNT_EXCEEDED               = 'user_count_exceeded';
    public const USER_ALREADY_EXISTS               = 'user_already_exists';
    public const USER_INVALID_TOKEN                = 'user_invalid_token';
    public const USER_IP_NOT_WHITELISTED           = 'user_ip_not_whitelisted';
    public const USER_INVALID_CREDENTIALS          = 'user_invalid_credentials';
    public const USER_NOT_FOUND                    = 'user_not_found';
    public const USER_PASSWORD_RECENTLY_USED       = 'password_recently_used';
    public const USER_PASSWORD_PERSONAL_DATA       = 'password_personal_data';
    public const USER_EMAIL_ALREADY_EXISTS         = 'user_email_already_exists';
    public const USER_IDENTITY_NOT_FOUND           = 'user_identity_not_found';
    public const USER_UNAUTHORIZED                 = 'user_unauthorized';
    public const USER_AUTH_METHOD_UNSUPPORTED      = 'user_auth_method_unsupported';
    public const USER_PHONE_ALREADY_EXISTS         = 'user_phone_already_exists';
    public const USER_PHONE_NOT_FOUND              = 'user_phone_not_found';
    public const USER_MISSING_ID                   = 'user_missing_id';
    public const USER_EMAIL_ALREADY_VERIFIED        = 'user_email_alread_verified';
    public const USER_PHONE_ALREADY_VERIFIED        = 'user_phone_already_verified';

    /** Teams */
    public const TEAM_NOT_FOUND                    = 'team_not_found';
    public const TEAM_INVITE_ALREADY_EXISTS        = 'team_invite_already_exists';
    public const TEAM_INVITE_NOT_FOUND             = 'team_invite_not_found';
    public const TEAM_INVALID_SECRET               = 'team_invalid_secret';
    public const TEAM_MEMBERSHIP_MISMATCH          = 'team_membership_mismatch';
    public const TEAM_INVITE_MISMATCH              = 'team_invite_mismatch';
    public const TEAM_ALREADY_EXISTS               = 'team_already_exists';

    /** Membership */
    public const MEMBERSHIP_NOT_FOUND              = 'membership_not_found';
    public const MEMBERSHIP_ALREADY_CONFIRMED      = 'membership_already_confirmed';

    /** Avatars */
    public const AVATAR_SET_NOT_FOUND              = 'avatar_set_not_found';
    public const AVATAR_NOT_FOUND                  = 'avatar_not_found';
    public const AVATAR_IMAGE_NOT_FOUND            = 'avatar_image_not_found';
    public const AVATAR_REMOTE_URL_FAILED          = 'avatar_remote_url_failed';
    public const AVATAR_ICON_NOT_FOUND             = 'avatar_icon_not_found';

    /** Storage */
    public const STORAGE_FILE_ALREADY_EXISTS       = 'storage_file_already_exists';
    public const STORAGE_FILE_NOT_FOUND            = 'storage_file_not_found';
    public const STORAGE_DEVICE_NOT_FOUND          = 'storage_device_not_found';
    public const STORAGE_FILE_EMPTY                = 'storage_file_empty';
    public const STORAGE_FILE_TYPE_UNSUPPORTED     = 'storage_file_type_unsupported';
    public const STORAGE_INVALID_FILE_SIZE         = 'storage_invalid_file_size';
    public const STORAGE_INVALID_FILE              = 'storage_invalid_file';
    public const STORAGE_BUCKET_ALREADY_EXISTS     = 'storage_bucket_already_exists';
    public const STORAGE_BUCKET_NOT_FOUND          = 'storage_bucket_not_found';
    public const STORAGE_INVALID_CONTENT_RANGE     = 'storage_invalid_content_range';
    public const STORAGE_INVALID_RANGE             = 'storage_invalid_range';
    public const STORAGE_INVALID_ID       = 'storage_invalid_id';

    /** Functions */
    public const FUNCTION_NOT_FOUND                = 'function_not_found';
    public const FUNCTION_RUNTIME_UNSUPPORTED      = 'function_runtime_unsupported';
    public const FUNCTION_ENTRYPOINT_MISSING      = 'function_entrypoint_missing';

    /** Deployments */
    public const DEPLOYMENT_NOT_FOUND              = 'deployment_not_found';

    /** Builds */
    public const BUILD_NOT_FOUND                   = 'build_not_found';
    public const BUILD_NOT_READY                   = 'build_not_ready';
    public const BUILD_IN_PROGRESS                 = 'build_in_progress';

    /** Execution */
    public const EXECUTION_NOT_FOUND               = 'execution_not_found';

    /** Databases */
    public const DATABASE_NOT_FOUND                = 'database_not_found';
    public const DATABASE_ALREADY_EXISTS           = 'database_already_exists';
    public const DATABASE_TIMEOUT                  = 'database_timeout';

    /** Collections */
    public const COLLECTION_NOT_FOUND              = 'collection_not_found';
    public const COLLECTION_ALREADY_EXISTS         = 'collection_already_exists';
    public const COLLECTION_LIMIT_EXCEEDED         = 'collection_limit_exceeded';

    /** Documents */
    public const DOCUMENT_NOT_FOUND                = 'document_not_found';
    public const DOCUMENT_INVALID_STRUCTURE        = 'document_invalid_structure';
    public const DOCUMENT_MISSING_DATA             = 'document_missing_data';
    public const DOCUMENT_MISSING_PAYLOAD          = 'document_missing_payload';
    public const DOCUMENT_ALREADY_EXISTS           = 'document_already_exists';
    public const DOCUMENT_UPDATE_CONFLICT          = 'document_update_conflict';
    public const DOCUMENT_DELETE_RESTRICTED        = 'document_delete_restricted';

    /** Attribute */
    public const ATTRIBUTE_NOT_FOUND               = 'attribute_not_found';
    public const ATTRIBUTE_UNKNOWN                 = 'attribute_unknown';
    public const ATTRIBUTE_NOT_AVAILABLE           = 'attribute_not_available';
    public const ATTRIBUTE_FORMAT_UNSUPPORTED      = 'attribute_format_unsupported';
    public const ATTRIBUTE_DEFAULT_UNSUPPORTED     = 'attribute_default_unsupported';
    public const ATTRIBUTE_ALREADY_EXISTS          = 'attribute_already_exists';
    public const ATTRIBUTE_LIMIT_EXCEEDED          = 'attribute_limit_exceeded';
    public const ATTRIBUTE_VALUE_INVALID           = 'attribute_value_invalid';
    public const ATTRIBUTE_TYPE_INVALID            = 'attribute_type_invalid';

    /** Indexes */
    public const INDEX_NOT_FOUND                   = 'index_not_found';
    public const INDEX_LIMIT_EXCEEDED              = 'index_limit_exceeded';
    public const INDEX_ALREADY_EXISTS              = 'index_already_exists';
    public const INDEX_INVALID                     = 'index_invalid';

    /** Projects */
    public const PROJECT_NOT_FOUND                 = 'project_not_found';
    public const PROJECT_UNKNOWN                   = 'project_unknown';
    public const PROJECT_PROVIDER_DISABLED         = 'project_provider_disabled';
    public const PROJECT_PROVIDER_UNSUPPORTED      = 'project_provider_unsupported';
    public const PROJECT_ALREADY_EXISTS            = 'project_already_exists';
    public const PROJECT_INVALID_SUCCESS_URL       = 'project_invalid_success_url';
    public const PROJECT_INVALID_FAILURE_URL       = 'project_invalid_failure_url';
    public const PROJECT_RESERVED_PROJECT          = 'project_reserved_project';
    public const PROJECT_KEY_EXPIRED               = 'project_key_expired';

    public const PROJECT_SMTP_CONFIG_INVALID       = 'project_smtp_config_invalid';

    public const PROJECT_TEMPLATE_DEFAULT_DELETION = 'project_template_default_deletion';

    /** Webhooks */
    public const WEBHOOK_NOT_FOUND                 = 'webhook_not_found';

    /** Router */
    public const ROUTER_HOST_NOT_FOUND             = 'router_host_not_found';
    public const ROUTER_DOMAIN_NOT_CONFIGURED      = 'router_domain_not_configured';

    /** Proxy */
    public const RULE_RESOURCE_NOT_FOUND            = 'rule_resource_not_found';
    public const RULE_NOT_FOUND                     = 'rule_not_found';
    public const RULE_ALREADY_EXISTS                = 'rule_already_exists';
    public const RULE_VERIFICATION_FAILED           = 'rule_verification_failed';

    /** Keys */
    public const KEY_NOT_FOUND                     = 'key_not_found';

    /** Variables */
    public const VARIABLE_NOT_FOUND                = 'variable_not_found';
    public const VARIABLE_ALREADY_EXISTS           = 'variable_already_exists';

    /** Platform */
    public const PLATFORM_NOT_FOUND                = 'platform_not_found';

    /** Realtime */
    public const REALTIME_MESSAGE_FORMAT_INVALID = 'realtime_message_format_invalid';
    public const REALTIME_TOO_MANY_MESSAGES = 'realtime_too_many_messages';
    public const REALTIME_POLICY_VIOLATION = 'realtime_policy_violation';

    /** Permissions */
    public const PERMISSION_MISSING_WRITE_USERS          = 'permission_missing_write_users';
    public const PERMISSION_MISSING_WRITE_TEAMS          = 'permission_missing_write_teams';
    public const PERMISSION_MISSING_WRITE_MEMBERSHIPS    = 'permission_missing_write_memberships';
    public const PERMISSION_MISSING_WRITE_DATABASES       = 'permission_missing_write_databases';
    public const PERMISSION_MISSING_WRITE_COLLECTIONS    = 'permission_missing_write_collections';
    public const PERMISSION_MISSING_WRITE_ATTRIBUTE       = 'permission_missing_write_attribute';
    public const PERMISSION_MISSING_WRITE_INDEX           = 'permission_missing_write_index';
    public const PERMISSION_MISSING_WRITE_DOCUMENTS      = 'permission_missing_write_documents';
    public const PERMISSION_MISSING_WRITE_BUCKETS        = 'permission_missing_write_buckets';
    public const PERMISSION_MISSING_WRITE_FILES          = 'permission_missing_write_files';
    public const PERMISSION_MISSING_WRITE_FUNCTION     = 'permission_missing_write_function';
    public const PERMISSION_MISSING_WRITE_DEPLOYMENT     = 'permission_missing_write_deployment';
    public const PERMISSION_MISSING_WRITE_ENVVAR        = 'permission_missing_write_envvar';
    public const PERMISSION_MISSING_READ_USERS          = 'permission_missing_read_users';
    public const PERMISSION_MISSING_READ_TEAMS          = 'permission_missing_read_teams';
    public const PERMISSION_MISSING_READ_MEMBERSHIPS    = 'permission_missing_read_memberships';
    public const PERMISSION_MISSING_READ_DATABASES       = 'permission_missing_read_databases';
    public const PERMISSION_MISSING_READ_COLLECTIONS    = 'permission_missing_read_collections';
    public const PERMISSION_MISSING_READ_ATTRIBUTE       = 'permission_missing_read_attribute';
    public const PERMISSION_MISSING_READ_INDEX           = 'permission_missing_read_index';
    public const PERMISSION_MISSING_READ_DOCUMENTS      = 'permission_missing_read_documents';
    public const PERMISSION_MISSING_READ_BUCKETS        = 'permission_missing_read_buckets';
    public const PERMISSION_MISSING_READ_FILES          = 'permission_missing_read_files';
    public const PERMISSION_MISSING_READ_FUNCTION     = 'permission_missing_read_function';
    public const PERMISSION_MISSING_READ_DEPLOYMENT     = 'permission_missing_read_deployment';
    public const PERMISSION_MISSING_READ_ENVVAR        = 'permission_missing_read_envvar';

    protected string $type = '';

    public $errors = [
        /** General Errors */
        Exception::GENERAL_UNKNOWN => [
            'name' => Exception::GENERAL_UNKNOWN,
            'description' => 'An unknown error has occured. Please check the logs for more information.',
            'code' => 500,
        ],
        Exception::GENERAL_MOCK => [
            'name' => Exception::GENERAL_MOCK,
            'description' => 'General errors thrown by the mock controller used for testing.',
            'code' => 400,
        ],
        Exception::GENERAL_ACCESS_FORBIDDEN => [
            'name' => Exception::GENERAL_ACCESS_FORBIDDEN,
            'description' => 'Access to this API is forbidden.',
            'code' => 401,
        ],
        Exception::GENERAL_UNKNOWN_ORIGIN => [
            'name' => Exception::GENERAL_UNKNOWN_ORIGIN,
            'description' => 'The request originated from an unknown origin. If you trust this domain, please list it as a trusted platform in the Appwrite console.',
            'code' => 403,
        ],
        Exception::GENERAL_SERVICE_DISABLED => [
            'name' => Exception::GENERAL_SERVICE_DISABLED,
            'description' => 'The requested service is disabled. You can enable the service from the Appwrite console.',
            'code' => 503,
        ],
        Exception::GENERAL_UNAUTHORIZED_SCOPE => [
            'name' => Exception::GENERAL_UNAUTHORIZED_SCOPE,
            'description' => 'The current user or API key does not have the required scopes to access the requested resource.',
            'code' => 401,
        ],
        Exception::GENERAL_RATE_LIMIT_EXCEEDED => [
            'name' => Exception::GENERAL_RATE_LIMIT_EXCEEDED,
            'description' => 'Rate limit for the current endpoint has been exceeded. Please try again after some time.',
            'code' => 429,
        ],
        Exception::GENERAL_SMTP_DISABLED => [
            'name' => Exception::GENERAL_SMTP_DISABLED,
            'description' => 'SMTP is disabled on your Appwrite instance. You can <a href="/docs/email-delivery">learn more about setting up SMTP</a> in our docs.',
            'code' => 503,
        ],
        Exception::GENERAL_PHONE_DISABLED => [
            'name' => Exception::GENERAL_PHONE_DISABLED,
            'description' => 'Phone provider is not configured. Please check the _APP_SMS_PROVIDER environment variable of your Appwrite server.',
            'code' => 503,
        ],
        Exception::GENERAL_ARGUMENT_INVALID => [
            'name' => Exception::GENERAL_ARGUMENT_INVALID,
            'description' => 'The request contains one or more invalid arguments. Please refer to the endpoint documentation.',
            'code' => 400,
        ],
        Exception::GENERAL_QUERY_LIMIT_EXCEEDED => [
            'name' => Exception::GENERAL_QUERY_LIMIT_EXCEEDED,
            'description' => 'Query limit exceeded for the current attribute. Usage of more than 100 query values on a single attribute is prohibited.',
            'code' => 400,
        ],
        Exception::GENERAL_QUERY_INVALID => [
            'name' => Exception::GENERAL_QUERY_INVALID,
            'description' => 'The query\'s syntax is invalid. Please check the query and try again.',
            'code' => 400,
        ],
        Exception::GENERAL_ROUTE_NOT_FOUND => [
            'name' => Exception::GENERAL_ROUTE_NOT_FOUND,
            'description' => 'The requested route was not found. Please refer to the API docs and try again.',
            'code' => 404,
        ],
        Exception::GENERAL_CURSOR_NOT_FOUND => [
            'name' => Exception::GENERAL_CURSOR_NOT_FOUND,
            'description' => 'The cursor is invalid. This can happen if the item represented by the cursor has been deleted.',
            'code' => 400,
        ],
        Exception::GENERAL_SERVER_ERROR => [
            'name' => Exception::GENERAL_SERVER_ERROR,
            'description' => 'An internal server error occurred.',
            'code' => 500,
        ],
        Exception::GENERAL_PROTOCOL_UNSUPPORTED => [
            'name' => Exception::GENERAL_PROTOCOL_UNSUPPORTED,
            'description' => 'The request cannot be fulfilled with the current protocol. Please check the value of the _APP_OPTIONS_FORCE_HTTPS environment variable.',
            'code' => 426,
        ],
        Exception::GENERAL_CODES_DISABLED => [
            'name' => Exception::GENERAL_CODES_DISABLED,
            'description' => 'Invitation codes are disabled on this server. Please contact the server administrator.',
            'code' => 500,
        ],
        Exception::GENERAL_USAGE_DISABLED => [
            'name' => Exception::GENERAL_USAGE_DISABLED,
            'description' => 'Usage stats is not configured. Please check the value of the _APP_USAGE_STATS environment variable of your Appwrite server.',
            'code' => 501,
        ],
        Exception::GENERAL_NOT_IMPLEMENTED => [
            'name' => Exception::GENERAL_NOT_IMPLEMENTED,
            'description' => 'This method was not fully implemented yet. If you believe this is a mistake, please upgrade your Appwrite server version.',
            'code' => 405,
        ],

        /** User Errors */
        Exception::USER_COUNT_EXCEEDED => [
            'name' => Exception::USER_COUNT_EXCEEDED,
            'description' => 'The current project has exceeded the maximum number of users. Please check your user limit in the Appwrite console.',
            'code' => 501,
        ],
        Exception::USER_ALREADY_EXISTS => [
            'name' => Exception::USER_ALREADY_EXISTS,
            'description' => 'A user with the same id, email, or phone already exists in this project.',
            'code' => 409,
        ],
        Exception::USER_INVALID_TOKEN => [
            'name' => Exception::USER_INVALID_TOKEN,
            'description' => 'Invalid token passed in the request.',
            'code' => 401,
        ],
        Exception::USER_IP_NOT_WHITELISTED => [
            'name' => Exception::USER_IP_NOT_WHITELISTED,
            'description' => 'Console registration is restricted to specific IPs. Contact your administrator for more information.',
            'code' => 401,
        ],
        Exception::USER_INVALID_CREDENTIALS => [
            'name' => Exception::USER_INVALID_CREDENTIALS,
            'description' => 'Invalid credentials. Please check the email and password.',
            'code' => 401,
        ],
        Exception::USER_NOT_FOUND => [
            'name' => Exception::USER_NOT_FOUND,
            'description' => 'User with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::USER_EMAIL_ALREADY_EXISTS => [
            'name' => Exception::USER_EMAIL_ALREADY_EXISTS,
            'description' => 'A user with the same email already exists in the current project.',
            'code' => 409,
        ],
        Exception::USER_PASSWORD_RECENTLY_USED => [
            'name' => Exception::USER_PASSWORD_RECENTLY_USED,
            'description' => 'The password you are trying to use is similar to your previous password. For your security, please choose a different password and try again.',
            'code' => 400,
        ],
        Exception::USER_PASSWORD_PERSONAL_DATA => [
            'name' => Exception::USER_PASSWORD_PERSONAL_DATA,
            'description' => 'The password you are trying to use contains references to your name, email, phone or userID. For your security, please choose a different password and try again.',
            'code' => 400,
        ],
        Exception::USER_IDENTITY_NOT_FOUND => [
            'name' => Exception::USER_IDENTITY_NOT_FOUND,
            'description' => 'The identity could not be found. Please sign in with OAuth provider to create identity first.',
            'code' => 404,
        ],
        Exception::USER_UNAUTHORIZED => [
            'name' => Exception::USER_UNAUTHORIZED,
            'description' => 'The current user is not authorized to perform the requested action.',
            'code' => 401,
        ],
        Exception::USER_AUTH_METHOD_UNSUPPORTED => [
            'name' => Exception::USER_AUTH_METHOD_UNSUPPORTED,
            'description' => 'The requested authentication method is either disabled or unsupported. Please check the supported authentication methods in the Appwrite console.',
            'code' => 501,
        ],
        Exception::USER_PHONE_ALREADY_EXISTS => [
            'name' => Exception::USER_PHONE_ALREADY_EXISTS,
            'description' => 'A user with the same phone number already exists in the current project.',
            'code' => 409,
        ],
        Exception::USER_PHONE_NOT_FOUND => [
            'name' => Exception::USER_PHONE_NOT_FOUND,
            'description' => 'The current user does not have a phone number associated with their account.',
            'code' => 400,
        ],
        Exception::USER_MISSING_ID => [
            'name' => Exception::USER_MISSING_ID,
            'description' => 'Missing ID from OAuth2 provider.',
            'code' => 400,
        ],
        Exception::USER_EMAIL_ALREADY_VERIFIED => [
            'name' => Exception::USER_EMAIL_ALREADY_VERIFIED,
            'description' => 'User email is already verified',
            'code' => 409,
        ],
        Exception::USER_PHONE_ALREADY_VERIFIED => [
            'name' => Exception::USER_PHONE_ALREADY_VERIFIED,
            'description' => 'User phone is already verified',
            'code' => 409
        ],

        /** Teams */
        Exception::TEAM_NOT_FOUND => [
            'name' => Exception::TEAM_NOT_FOUND,
            'description' => 'Team with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::TEAM_INVITE_ALREADY_EXISTS => [
            'name' => Exception::TEAM_INVITE_ALREADY_EXISTS,
            'description' => 'User has already been invited or is already a member of this team',
            'code' => 409,
        ],
        Exception::TEAM_INVITE_NOT_FOUND => [
            'name' => Exception::TEAM_INVITE_NOT_FOUND,
            'description' => 'The requested team invitation could not be found.',
            'code' => 404,
        ],
        Exception::TEAM_INVALID_SECRET => [
            'name' => Exception::TEAM_INVALID_SECRET,
            'description' => 'The team invitation secret is invalid. Please request  a new invitation and try again.',
            'code' => 401,
        ],
        Exception::TEAM_MEMBERSHIP_MISMATCH => [
            'name' => Exception::TEAM_MEMBERSHIP_MISMATCH,
            'description' => 'The membership ID does not belong to the team ID.',
            'code' => 404,
        ],
        Exception::TEAM_INVITE_MISMATCH => [
            'name' => Exception::TEAM_INVITE_MISMATCH,
            'description' => 'The invite does not belong to the current user.',
            'code' => 401,
        ],
        Exception::TEAM_ALREADY_EXISTS => [
            'name' => Exception::TEAM_ALREADY_EXISTS,
            'description' => 'Team with requested ID already exists. Please choose a different ID and try again.',
            'code' => 409,
        ],

        /** Membership */
        Exception::MEMBERSHIP_NOT_FOUND => [
            'name' => Exception::MEMBERSHIP_NOT_FOUND,
            'description' => 'Membership with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::MEMBERSHIP_ALREADY_CONFIRMED => [
            'name' => Exception::MEMBERSHIP_ALREADY_CONFIRMED,
            'description' => 'Membership is already confirmed.',
            'code' => 409,
        ],

        /** Avatars */
        Exception::AVATAR_SET_NOT_FOUND => [
            'name' => Exception::AVATAR_SET_NOT_FOUND,
            'description' => 'The requested avatar set could not be found.',
            'code' => 404
        ],
        Exception::AVATAR_NOT_FOUND => [
            'name' => Exception::AVATAR_NOT_FOUND,
            'description' => 'The request avatar could not be found.',
            'code' => 404,
        ],
        Exception::AVATAR_IMAGE_NOT_FOUND => [
            'name' => Exception::AVATAR_IMAGE_NOT_FOUND,
            'description' => 'The requested image was not found at the URL.',
            'code' => 404,
        ],
        Exception::AVATAR_REMOTE_URL_FAILED => [
            'name' => Exception::AVATAR_REMOTE_URL_FAILED,
            'description' => 'Failed to fetch favicon from the requested URL.',
            'code' => 404,
        ],
        Exception::AVATAR_ICON_NOT_FOUND => [
            'name' => Exception::AVATAR_ICON_NOT_FOUND,
            'description' => 'The requested favicon could not be found.',
            'code' => 404,
        ],

        /** Storage */
        Exception::STORAGE_FILE_ALREADY_EXISTS => [
            'name' => Exception::STORAGE_FILE_ALREADY_EXISTS,
            'description' => 'A storage file with the requested ID already exists.',
            'code' => 409,
        ],
        Exception::STORAGE_FILE_NOT_FOUND => [
            'name' => Exception::STORAGE_FILE_NOT_FOUND,
            'description' => 'The requested file could not be found.',
            'code' => 404,
        ],
        Exception::STORAGE_DEVICE_NOT_FOUND => [
            'name' => Exception::STORAGE_DEVICE_NOT_FOUND,
            'description' => 'The requested storage device could not be found.',
            'code' => 400,
        ],
        Exception::STORAGE_FILE_EMPTY => [
            'name' => Exception::STORAGE_FILE_EMPTY,
            'description' => 'Empty file passed to the endpoint.',
            'code' => 400,
        ],
        Exception::STORAGE_FILE_TYPE_UNSUPPORTED => [
            'name' => Exception::STORAGE_FILE_TYPE_UNSUPPORTED,
            'description' => 'The given file extension is not supported.',
            'code' => 400,
        ],
        Exception::STORAGE_INVALID_FILE_SIZE => [
            'name' => Exception::STORAGE_INVALID_FILE_SIZE,
            'description' => 'The file size is either not valid or exceeds the maximum allowed size. Please check the file or the value of the _APP_STORAGE_LIMIT environment variable.',
            'code' => 400,
        ],
        Exception::STORAGE_INVALID_FILE => [
            'name' => Exception::STORAGE_INVALID_FILE,
            'description' => 'The uploaded file is invalid. Please check the file and try again.',
            'code' => 403,
        ],
        Exception::STORAGE_BUCKET_ALREADY_EXISTS => [
            'name' => Exception::STORAGE_BUCKET_ALREADY_EXISTS,
            'description' => 'A storage bucket with the requested ID already exists. Try again with a different ID or use ID.unique() to generate a unique ID.',
            'code' => 409,
        ],
        Exception::STORAGE_BUCKET_NOT_FOUND => [
            'name' => Exception::STORAGE_BUCKET_NOT_FOUND,
            'description' => 'Storage bucket with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::STORAGE_INVALID_CONTENT_RANGE => [
            'name' => Exception::STORAGE_INVALID_CONTENT_RANGE,
            'description' => 'The content range is invalid. Please check the value of the Content-Range header.',
            'code' => 400,
        ],
        Exception::STORAGE_INVALID_RANGE => [
            'name' => Exception::STORAGE_INVALID_RANGE,
            'description' => 'The requested range is not satisfiable. Please check the value of the Range header.',
            'code' => 416,
        ],
        Exception::STORAGE_INVALID_ID => [
            'name' => Exception::STORAGE_INVALID_ID,
            'description' => 'The value for storage id is invalid. Please check the value of the storage id header is a valid id.',
            'code' => 400,
        ],

        /** Functions  */
        Exception::FUNCTION_NOT_FOUND => [
            'name' => Exception::FUNCTION_NOT_FOUND,
            'description' => 'Function with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::FUNCTION_RUNTIME_UNSUPPORTED => [
            'name' => Exception::FUNCTION_RUNTIME_UNSUPPORTED,
            'description' => 'The requested runtime is either inactive or unsupported. Please check the value of the _APP_FUNCTIONS_RUNTIMES environment variable.',
            'code' => 404,
        ],
        Exception::FUNCTION_ENTRYPOINT_MISSING => [
            'name' => Exception::FUNCTION_RUNTIME_UNSUPPORTED,
            'description' => 'Entrypoint for your Appwrite Function is missing. Please specify it when making deployment or update the entrypoint under your function\'s "Settings" > "Configuration" > "Entrypoint".',
            'code' => 404,
        ],

        /** Builds  */
        Exception::BUILD_NOT_FOUND => [
            'name' => Exception::BUILD_NOT_FOUND,
            'description' => 'Build with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::BUILD_NOT_READY => [
            'name' => Exception::BUILD_NOT_READY,
            'description' => 'Build with the requested ID is building and not ready for execution.',
            'code' => 400,
        ],
        Exception::BUILD_IN_PROGRESS => [
            'name' => Exception::BUILD_IN_PROGRESS,
            'description' => 'Build with the requested ID is already in progress. Please wait before you can retry.',
            'code' => 400,
        ],

        /** Deployments */
        Exception::DEPLOYMENT_NOT_FOUND => [
            'name' => Exception::DEPLOYMENT_NOT_FOUND,
            'description' => 'Deployment with the requested ID could not be found.',
            'code' => 404,
        ],

        /** Executions */
        Exception::EXECUTION_NOT_FOUND => [
            'name' => Exception::EXECUTION_NOT_FOUND,
            'description' => 'Execution with the requested ID could not be found.',
            'code' => 404,
        ],

        /** Databases */
        Exception::DATABASE_NOT_FOUND => [
            'name' => Exception::DATABASE_NOT_FOUND,
            'description' => 'Database not found',
            'code' => 404
        ],
        Exception::DATABASE_ALREADY_EXISTS => [
            'name' => Exception::DATABASE_ALREADY_EXISTS,
            'description' => 'Database already exists',
            'code' => 409
        ],
        Exception::DATABASE_TIMEOUT => [
            'name' => Exception::DATABASE_TIMEOUT,
            'description' => 'Database timed out. Try adjusting your queries or adding an index.',
            'code' => 408
        ],

        /** Collections */
        Exception::COLLECTION_NOT_FOUND => [
            'name' => Exception::COLLECTION_NOT_FOUND,
            'description' => 'Collection with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::COLLECTION_ALREADY_EXISTS => [
            'name' => Exception::COLLECTION_ALREADY_EXISTS,
            'description' => 'A collection with the requested ID already exists. Try again with a different ID or use ID.unique() to generate a unique ID.',
            'code' => 409,
        ],
        Exception::COLLECTION_LIMIT_EXCEEDED => [
            'name' => Exception::COLLECTION_LIMIT_EXCEEDED,
            'description' => 'The maximum number of collections has been reached.',
            'code' => 400,
        ],

        /** Documents */
        Exception::DOCUMENT_NOT_FOUND => [
            'name' => Exception::DOCUMENT_NOT_FOUND,
            'description' => 'Document with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::DOCUMENT_INVALID_STRUCTURE => [
            'name' => Exception::DOCUMENT_INVALID_STRUCTURE,
            'description' => 'The document structure is invalid. Please ensure the attributes match the collection definition.',
            'code' => 400,
        ],
        Exception::DOCUMENT_MISSING_DATA => [
            'name' => Exception::DOCUMENT_MISSING_DATA,
            'description' => 'The document data is missing. Try again with document data populated',
            'code' => 400,
        ],
        Exception::DOCUMENT_MISSING_PAYLOAD => [
            'name' => Exception::DOCUMENT_MISSING_PAYLOAD,
            'description' => 'The document data and permissions are missing. You must provide either document data or permissions to be updated.',
            'code' => 400,
        ],
        Exception::DOCUMENT_ALREADY_EXISTS => [
            'name' => Exception::DOCUMENT_ALREADY_EXISTS,
            'description' => 'Document with the requested ID already exists. Try again with a different ID or use ID.unique() to generate a unique ID.',
            'code' => 409,
        ],
        Exception::DOCUMENT_UPDATE_CONFLICT => [
            'name' => Exception::DOCUMENT_UPDATE_CONFLICT,
            'description' => 'Remote document is newer than local.',
            'code' => 409,
        ],
        Exception::DOCUMENT_DELETE_RESTRICTED => [
            'name' => Exception::DOCUMENT_DELETE_RESTRICTED,
            'description' => 'Document cannot be deleted because it is referenced by another document.',
            'code' => 403,
        ],

        /** Attributes */
        Exception::ATTRIBUTE_NOT_FOUND => [
            'name' => Exception::ATTRIBUTE_NOT_FOUND,
            'description' => 'Attribute with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::ATTRIBUTE_UNKNOWN => [
            'name' => Exception::ATTRIBUTE_UNKNOWN,
            'description' => 'The attribute required for the index could not be found. Please confirm all your attributes are in the available state.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_NOT_AVAILABLE => [
            'name' => Exception::ATTRIBUTE_NOT_AVAILABLE,
            'description' => 'The requested attribute is not yet available. Please try again later.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_FORMAT_UNSUPPORTED => [
            'name' => Exception::ATTRIBUTE_FORMAT_UNSUPPORTED,
            'description' => 'The requested attribute format is not supported.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_DEFAULT_UNSUPPORTED => [
            'name' => Exception::ATTRIBUTE_DEFAULT_UNSUPPORTED,
            'description' => 'Default values cannot be set for array or required attributes.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_ALREADY_EXISTS => [
            'name' => Exception::ATTRIBUTE_ALREADY_EXISTS,
            'description' => 'Attribute with the requested key already exists. Attribute keys must be unique, try again with a different key.',
            'code' => 409,
        ],
        Exception::ATTRIBUTE_LIMIT_EXCEEDED => [
            'name' => Exception::ATTRIBUTE_LIMIT_EXCEEDED,
            'description' => 'The maximum number of attributes has been reached.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_VALUE_INVALID => [
            'name' => Exception::ATTRIBUTE_VALUE_INVALID,
            'description' => 'The attribute value is invalid. Please check the type, range and value of the attribute.',
            'code' => 400,
        ],
        Exception::ATTRIBUTE_TYPE_INVALID => [
            'name' => Exception::ATTRIBUTE_TYPE_INVALID,
            'description' => 'The attribute type is invalid.',
            'code' => 400,
        ],

        /** Indexes */
        Exception::INDEX_NOT_FOUND => [
            'name' => Exception::INDEX_NOT_FOUND,
            'description' => 'Index with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::INDEX_LIMIT_EXCEEDED => [
            'name' => Exception::INDEX_LIMIT_EXCEEDED,
            'description' => 'The maximum number of indexes has been reached.',
            'code' => 400,
        ],
        Exception::INDEX_ALREADY_EXISTS => [
            'name' => Exception::INDEX_ALREADY_EXISTS,
            'description' => 'Index with the requested key already exists. Try again with a different key.',
            'code' => 409,
        ],
        Exception::INDEX_INVALID => [
            'name' => Exception::INDEX_INVALID,
            'description' => 'Index invalid.',
            'code' => 400,
        ],

        /** Project Errors */
        Exception::PROJECT_NOT_FOUND => [
            'name' => Exception::PROJECT_NOT_FOUND,
            'description' => 'Project with the requested ID could not be found. Please check the value of the X-Appwrite-Project header to ensure the correct project ID is being used.',
            'code' => 404,
        ],
        Exception::PROJECT_ALREADY_EXISTS => [
            'name' => Exception::PROJECT_ALREADY_EXISTS,
            'description' => 'Project with the requested ID already exists. Try again with a different ID or use ID.unique() to generate a unique ID.',
            'code' => 409,
        ],
        Exception::PROJECT_UNKNOWN => [
            'name' => Exception::PROJECT_UNKNOWN,
            'description' => 'The project ID is either missing or not valid. Please check the value of the X-Appwrite-Project header to ensure the correct project ID is being used.',
            'code' => 400,
        ],
        Exception::PROJECT_PROVIDER_DISABLED => [
            'name' => Exception::PROJECT_PROVIDER_DISABLED,
            'description' => 'The chosen OAuth provider is disabled. You can enable the OAuth provider using the Appwrite console.',
            'code' => 412,
        ],
        Exception::PROJECT_PROVIDER_UNSUPPORTED => [
            'name' => Exception::PROJECT_PROVIDER_UNSUPPORTED,
            'description' => 'The chosen OAuth provider is unsupported. Please check the <a href="/docs/client/account?sdk=web-default#accountCreateOAuth2Session">Create OAuth2 Session docs</a> for the complete list of supported OAuth providers.',
            'code' => 501,
        ],
        Exception::PROJECT_INVALID_SUCCESS_URL => [
            'name' => Exception::PROJECT_INVALID_SUCCESS_URL,
            'description' => 'Invalid redirect URL for OAuth success.',
            'code' => 400,
        ],
        Exception::PROJECT_INVALID_FAILURE_URL => [
            'name' => Exception::PROJECT_INVALID_FAILURE_URL,
            'description' => 'Invalid redirect URL for OAuth failure.',
            'code' => 400,
        ],
        Exception::PROJECT_RESERVED_PROJECT => [
            'name' => Exception::PROJECT_RESERVED_PROJECT,
            'description' => 'The project ID is reserved. Please choose another project ID.',
            'code' => 400,
        ],
        Exception::PROJECT_KEY_EXPIRED => [
            'name' => Exception::PROJECT_KEY_EXPIRED,
            'description' => 'The project key has expired. Please generate a new key using the Appwrite console.',
            'code' => 401,
        ],
        Exception::ROUTER_HOST_NOT_FOUND => [
            'name' => Exception::ROUTER_HOST_NOT_FOUND,
            'description' => 'Host is not trusted. This could occur because you have not configured a custom domain. Add a custom domain to your project first and try again.',
            'code' => 404,
        ],
        Exception::ROUTER_DOMAIN_NOT_CONFIGURED => [
            'name' => Exception::ROUTER_DOMAIN_NOT_CONFIGURED,
            'description' => '_APP_DOMAIN, _APP_DOMAIN_TARGET, and _APP_DOMAIN_FUNCTIONS environment variables have not been configured. Please configure the domain environment variables before accessing the Appwrite Console via any IP address or hostname other than localhost. This value could be an IP like 203.0.113.0 or a hostname like example.com.',
            'code' => 500,
        ],
        Exception::RULE_RESOURCE_NOT_FOUND => [
            'name' => Exception::RULE_RESOURCE_NOT_FOUND,
            'description' => 'Resource could not be found. Please check if the resourceId and resourceType are correct, or if the resource actually exists.',
            'code' => 404,
        ],
        Exception::RULE_NOT_FOUND => [
            'name' => Exception::RULE_NOT_FOUND,
            'description' => 'Rule with the requested ID could not be found. Please check if the ID provided is correct or if the rule actually exists.',
            'code' => 404,
        ],
        Exception::RULE_ALREADY_EXISTS => [
            'name' => Exception::RULE_ALREADY_EXISTS,
            'description' => 'Domain is already used. Please try again with a different domain.',
            'code' => 409,
        ],
        Exception::RULE_VERIFICATION_FAILED => [
            'name' => Exception::RULE_VERIFICATION_FAILED,
            'description' => 'Domain verification failed. Please check if your DNS records are correct and try again.',
            'code' => 401,
        ],
        Exception::PROJECT_SMTP_CONFIG_INVALID => [
            'name' => Exception::PROJECT_SMTP_CONFIG_INVALID,
            'description' => 'Provided SMTP config is invalid. Please check the configured values and try again.',
            'code' => 400,
        ],
        Exception::PROJECT_TEMPLATE_DEFAULT_DELETION => [
            'name' => Exception::PROJECT_TEMPLATE_DEFAULT_DELETION,
            'description' => 'You can\'t delete default template. If you are trying to reset your template changes, you can ignore this error as it\'s already been reset.',
            'code' => 401,
        ],
        Exception::WEBHOOK_NOT_FOUND => [
            'name' => Exception::WEBHOOK_NOT_FOUND,
            'description' => 'Webhook with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::KEY_NOT_FOUND => [
            'name' => Exception::KEY_NOT_FOUND,
            'description' => 'Key with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::PLATFORM_NOT_FOUND => [
            'name' => Exception::PLATFORM_NOT_FOUND,
            'description' => 'Platform with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::VARIABLE_NOT_FOUND => [
            'name' => Exception::VARIABLE_NOT_FOUND,
            'description' => 'Variable with the requested ID could not be found.',
            'code' => 404,
        ],
        Exception::VARIABLE_ALREADY_EXISTS => [
            'name' => Exception::VARIABLE_ALREADY_EXISTS,
            'description' => 'Variable with the same ID already exists in this project. Try again with a different ID.',
            'code' => 409,
        ],

        /** Realtime */
        Exception::REALTIME_MESSAGE_FORMAT_INVALID => [
            'name' => Exception::REALTIME_MESSAGE_FORMAT_INVALID,
            'description' => 'Message format is not valid.',
            'code' => 1003,
        ],
        Exception::REALTIME_POLICY_VIOLATION => [
            'name' => Exception::REALTIME_POLICY_VIOLATION,
            'description' => 'Policy violation.',
            'code' => 1008,
        ],
        Exception::REALTIME_TOO_MANY_MESSAGES => [
            'name' => Exception::REALTIME_TOO_MANY_MESSAGES,
            'description' => 'Too many messages.',
            'code' => 1013,
        ],

        /** Permissions */
        Exception::PERMISSION_MISSING_WRITE_USERS      => [
            'name' => Exception::PERMISSION_MISSING_WRITE_USERS,
            'description' => 'Missing write permissions for users collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_TEAMS      => [
            'name' => Exception::PERMISSION_MISSING_WRITE_TEAMS,
            'description' => 'Missing write permissions for teams collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_MEMBERSHIPS => [
            'name' => Exception::PERMISSION_MISSING_WRITE_MEMBERSHIPS,
            'description' => 'Missing write permissions for memberships collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_DATABASES   => [
            'name' => Exception::PERMISSION_MISSING_WRITE_DATABASES,
            'description' => 'Missing write permissions for databases collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_COLLECTIONS  => [
            'name' => Exception::PERMISSION_MISSING_WRITE_COLLECTIONS,
            'description' => 'Missing write permissions for collections collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_ATTRIBUTE  => [
            'name' => Exception::PERMISSION_MISSING_WRITE_ATTRIBUTE,
            'description' => 'Missing write permissions for attributes collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_INDEX      =>  [
            'name' => Exception::PERMISSION_MISSING_WRITE_INDEX,
            'description' => 'Missing write permissions for indexes collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_DOCUMENTS  => [
            'name' => Exception::PERMISSION_MISSING_WRITE_DOCUMENTS,
            'description' => 'Missing write permissions for documents collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_BUCKETS    => [
            'name' => Exception::PERMISSION_MISSING_WRITE_BUCKETS,
            'description' => 'Missing write permissions for buckets collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_FILES      => [
            'name' => Exception::PERMISSION_MISSING_WRITE_FILES,
            'description' => 'Missing write permissions for files collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_FUNCTION   => [
            'name' => Exception::PERMISSION_MISSING_WRITE_FUNCTION,
            'description' => 'Missing write permissions for functions collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_DEPLOYMENT => [
            'name' => Exception::PERMISSION_MISSING_WRITE_DEPLOYMENT,
            'description' => 'Missing write permissions for deployments collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_WRITE_ENVVAR     => [
            'name' => Exception::PERMISSION_MISSING_WRITE_ENVVAR,
            'description' => 'Missing write permissions for environment variables collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_USERS       => [
            'name' => Exception::PERMISSION_MISSING_READ_USERS,
            'description' => 'Missing read permissions for users collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_TEAMS       => [
            'name' => Exception::PERMISSION_MISSING_READ_TEAMS,
            'description' => 'Missing read permissions for teams collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_MEMBERSHIPS => [
            'name' => Exception::PERMISSION_MISSING_READ_MEMBERSHIPS,
            'description' => 'Missing read permissions for memberships collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_DATABASES   => [
            'name' => Exception::PERMISSION_MISSING_READ_DATABASES,
            'description' => 'Missing read permissions for databases collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_COLLECTIONS => [
            'name' => Exception::PERMISSION_MISSING_READ_COLLECTIONS,
            'description' => 'Missing read permissions for collections collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_ATTRIBUTE   => [
            'name' => Exception::PERMISSION_MISSING_READ_ATTRIBUTE,
            'description' => 'Missing read permissions for attributes collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_INDEX       => [
            'name' => Exception::PERMISSION_MISSING_READ_INDEX,
            'description' => 'Missing read permissions for indexes collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_DOCUMENTS   => [
            'name' => Exception::PERMISSION_MISSING_READ_DOCUMENTS,
            'description' => 'Missing read permissions for documents collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_BUCKETS     => [
            'name' => Exception::PERMISSION_MISSING_READ_BUCKETS,
            'description' => 'Missing read permissions for buckets collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_FILES       => [
            'name' => Exception::PERMISSION_MISSING_READ_FILES,
            'description' => 'Missing read permissions for files collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_FUNCTION    => [
            'name' => Exception::PERMISSION_MISSING_READ_FUNCTION,
            'description' => 'Missing read permissions for functions collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_DEPLOYMENT  => [
            'name' => Exception::PERMISSION_MISSING_READ_DEPLOYMENT,
            'description' => 'Missing read permissions for deployments collection.',
            'code' => 403,
        ],
        Exception::PERMISSION_MISSING_READ_ENVVAR      => [
            'name' => Exception::PERMISSION_MISSING_READ_ENVVAR,
            'description' => 'Missing read permissions for environment variables collection.',
            'code' => 403,
        ],
    ];

    public function __construct(string $type = Exception::GENERAL_UNKNOWN, string $message = null, int $code = null, \Throwable $previous = null)
    {
        $this->type = $type;

        if (isset($this->errors[$type])) {
            $this->code = $this->errors[$type]['code'];
            $this->message = $this->errors[$type]['description'];
        }

        $this->message = $message ?? $this->message;
        $this->code = $code ?? $this->code;

        parent::__construct($this->message, $this->code, $previous);
    }

    /**
     * Get the type of the exception.
     *
     * @return string
     */
    public function getType(): string
    {
        return $this->type;
    }

    /**
     * Set the type of the exception.
     *
     * @param string $type
     *
     * @return void
     */
    public function setType(string $type): void
    {
        $this->type = $type;
    }
}