<?php

namespace Utopia\Tests\Unit\General;

use PHPUnit\Framework\TestCase;
use Utopia\Migration\Resource;
use Utopia\Migration\Resources\Messaging\Message;
use Utopia\Migration\Resources\Messaging\Provider;
use Utopia\Migration\Resources\Messaging\Subscriber;
use Utopia\Migration\Resources\Messaging\Topic;
use Utopia\Migration\Transfer;
use Utopia\Tests\Unit\Adapters\MockDestination;
use Utopia\Tests\Unit\Adapters\MockSource;

class MessagingTest extends TestCase
{
    protected Transfer $transfer;
    protected MockSource $source;
    protected MockDestination $destination;

    public function setup(): void
    {
        $this->source = new MockSource();
        $this->destination = new MockDestination();

        $this->transfer = new Transfer(
            $this->source,
            $this->destination
        );
    }

    public function testProviderResource(): void
    {
        $provider = new Provider(
            'provider1',
            'My Mailgun',
            'mailgun',
            'email',
            true,
            ['apiKey' => 'key123', 'domain' => 'example.com'],
            ['fromName' => 'Test', 'fromEmail' => 'test@example.com'],
            '2024-01-01T00:00:00.000+00:00',
            '2024-01-01T00:00:00.000+00:00',
        );

        $this->assertSame(Resource::TYPE_PROVIDER, $provider::getName());
        $this->assertSame(Transfer::GROUP_MESSAGING, $provider->getGroup());
        $this->assertSame('provider1', $provider->getId());
        $this->assertSame('My Mailgun', $provider->getProviderName());
        $this->assertSame('mailgun', $provider->getProvider());
        $this->assertSame('email', $provider->getType());
        $this->assertTrue($provider->getEnabled());
        $this->assertSame(['apiKey' => 'key123', 'domain' => 'example.com'], $provider->getCredentials());
        $this->assertSame(['fromName' => 'Test', 'fromEmail' => 'test@example.com'], $provider->getOptions());
    }

    public function testProviderFromArray(): void
    {
        $data = [
            'id' => 'provider2',
            'name' => 'My Twilio',
            'provider' => 'twilio',
            'type' => 'sms',
            'enabled' => false,
            'credentials' => ['accountSid' => 'sid123', 'authToken' => 'token123'],
            'options' => ['from' => '+1234567890'],
            'createdAt' => '2024-01-01T00:00:00.000+00:00',
            'updatedAt' => '2024-01-01T00:00:00.000+00:00',
        ];

        $provider = Provider::fromArray($data);

        $this->assertSame('provider2', $provider->getId());
        $this->assertSame('My Twilio', $provider->getProviderName());
        $this->assertSame('twilio', $provider->getProvider());
        $this->assertSame('sms', $provider->getType());
        $this->assertFalse($provider->getEnabled());
        $this->assertSame(['accountSid' => 'sid123', 'authToken' => 'token123'], $provider->getCredentials());
        $this->assertSame(['from' => '+1234567890'], $provider->getOptions());
    }

    public function testProviderJsonSerialize(): void
    {
        $provider = new Provider(
            'provider1',
            'My FCM',
            'fcm',
            'push',
            true,
            ['serviceAccountJSON' => ['key' => 'value']],
        );

        $json = $provider->jsonSerialize();

        $this->assertSame('provider1', $json['id']);
        $this->assertSame('My FCM', $json['name']);
        $this->assertSame('fcm', $json['provider']);
        $this->assertSame('push', $json['type']);
        $this->assertTrue($json['enabled']);
        $this->assertSame(['serviceAccountJSON' => ['key' => 'value']], $json['credentials']);
    }

    public function testTopicResource(): void
    {
        $topic = new Topic(
            'topic1',
            'Newsletter',
            ['role:all'],
            '2024-01-01T00:00:00.000+00:00',
            '2024-01-01T00:00:00.000+00:00',
        );

        $this->assertSame(Resource::TYPE_TOPIC, $topic::getName());
        $this->assertSame(Transfer::GROUP_MESSAGING, $topic->getGroup());
        $this->assertSame('topic1', $topic->getId());
        $this->assertSame('Newsletter', $topic->getTopicName());
        $this->assertSame(['role:all'], $topic->getSubscribe());
    }

    public function testTopicFromArray(): void
    {
        $data = [
            'id' => 'topic2',
            'name' => 'Alerts',
            'subscribe' => ['role:member'],
            'createdAt' => '2024-01-01T00:00:00.000+00:00',
            'updatedAt' => '2024-01-01T00:00:00.000+00:00',
        ];

        $topic = Topic::fromArray($data);

        $this->assertSame('topic2', $topic->getId());
        $this->assertSame('Alerts', $topic->getTopicName());
        $this->assertSame(['role:member'], $topic->getSubscribe());
    }

    public function testTopicJsonSerialize(): void
    {
        $topic = new Topic('topic1', 'Newsletter', ['role:all']);

        $json = $topic->jsonSerialize();

        $this->assertSame('topic1', $json['id']);
        $this->assertSame('Newsletter', $json['name']);
        $this->assertSame(['role:all'], $json['subscribe']);
    }

    public function testSubscriberResource(): void
    {
        $subscriber = new Subscriber(
            'sub1',
            'topic1',
            'target1',
            'user1',
            'John Doe',
            'email',
            '2024-01-01T00:00:00.000+00:00',
            '2024-01-01T00:00:00.000+00:00',
        );

        $this->assertSame(Resource::TYPE_SUBSCRIBER, $subscriber::getName());
        $this->assertSame(Transfer::GROUP_MESSAGING, $subscriber->getGroup());
        $this->assertSame('sub1', $subscriber->getId());
        $this->assertSame('topic1', $subscriber->getTopicId());
        $this->assertSame('target1', $subscriber->getTargetId());
        $this->assertSame('user1', $subscriber->getUserId());
        $this->assertSame('John Doe', $subscriber->getUserName());
        $this->assertSame('email', $subscriber->getProviderType());
    }

    public function testSubscriberFromArray(): void
    {
        $data = [
            'id' => 'sub2',
            'topicId' => 'topic2',
            'targetId' => 'target2',
            'userId' => 'user2',
            'userName' => 'Jane Doe',
            'providerType' => 'sms',
            'createdAt' => '2024-01-01T00:00:00.000+00:00',
            'updatedAt' => '2024-01-01T00:00:00.000+00:00',
        ];

        $subscriber = Subscriber::fromArray($data);

        $this->assertSame('sub2', $subscriber->getId());
        $this->assertSame('topic2', $subscriber->getTopicId());
        $this->assertSame('target2', $subscriber->getTargetId());
        $this->assertSame('user2', $subscriber->getUserId());
        $this->assertSame('Jane Doe', $subscriber->getUserName());
        $this->assertSame('sms', $subscriber->getProviderType());
    }

    public function testSubscriberJsonSerialize(): void
    {
        $subscriber = new Subscriber(
            'sub1',
            'topic1',
            'target1',
            'user1',
            'John Doe',
            'push',
        );

        $json = $subscriber->jsonSerialize();

        $this->assertSame('sub1', $json['id']);
        $this->assertSame('topic1', $json['topicId']);
        $this->assertSame('target1', $json['targetId']);
        $this->assertSame('user1', $json['userId']);
        $this->assertSame('John Doe', $json['userName']);
        $this->assertSame('push', $json['providerType']);
    }

    public function testMessageResource(): void
    {
        $message = new Message(
            'msg1',
            'email',
            ['topic1'],
            ['user1'],
            ['target1'],
            ['subject' => 'Hello', 'content' => '<p>World</p>'],
            'draft',
            '',
            '2024-01-01T00:00:00.000+00:00',
            '2024-01-01T00:00:00.000+00:00',
        );

        $this->assertSame(Resource::TYPE_MESSAGE, $message::getName());
        $this->assertSame(Transfer::GROUP_MESSAGING, $message->getGroup());
        $this->assertSame('msg1', $message->getId());
        $this->assertSame('email', $message->getProviderType());
        $this->assertSame(['topic1'], $message->getTopics());
        $this->assertSame(['user1'], $message->getUsers());
        $this->assertSame(['target1'], $message->getTargets());
        $this->assertSame(['subject' => 'Hello', 'content' => '<p>World</p>'], $message->getData());
        $this->assertSame('draft', $message->getMessageStatus());
    }

    public function testMessageFromArray(): void
    {
        $data = [
            'id' => 'msg2',
            'providerType' => 'sms',
            'topics' => ['topic2'],
            'users' => [],
            'targets' => ['target2'],
            'data' => ['content' => 'Hello SMS'],
            'status' => 'sent',
            'scheduledAt' => '',
            'createdAt' => '2024-01-01T00:00:00.000+00:00',
            'updatedAt' => '2024-01-01T00:00:00.000+00:00',
        ];

        $message = Message::fromArray($data);

        $this->assertSame('msg2', $message->getId());
        $this->assertSame('sms', $message->getProviderType());
        $this->assertSame(['topic2'], $message->getTopics());
        $this->assertSame(['content' => 'Hello SMS'], $message->getData());
        $this->assertSame('sent', $message->getMessageStatus());
    }

    public function testMessageJsonSerialize(): void
    {
        $message = new Message(
            'msg1',
            'push',
            ['topic1'],
            [],
            [],
            ['title' => 'Alert', 'body' => 'New notification'],
            'draft',
        );

        $json = $message->jsonSerialize();

        $this->assertSame('msg1', $json['id']);
        $this->assertSame('push', $json['providerType']);
        $this->assertSame(['topic1'], $json['topics']);
        $this->assertSame(['title' => 'Alert', 'body' => 'New notification'], $json['data']);
        $this->assertSame('draft', $json['messageStatus']);
    }

    public function testMessagingTransfer(): void
    {
        $provider = new Provider(
            'provider1',
            'Test Provider',
            'mailgun',
            'email',
        );

        $topic = new Topic(
            'topic1',
            'Test Topic',
            ['role:all'],
        );

        $message = new Message(
            'msg1',
            'email',
            ['topic1'],
            [],
            [],
            ['subject' => 'Test', 'content' => 'Hello'],
            'draft',
        );

        $this->source->pushMockResource($provider);
        $this->source->pushMockResource($topic);
        $this->source->pushMockResource($message);

        $this->transfer->run(
            [Resource::TYPE_PROVIDER, Resource::TYPE_TOPIC, Resource::TYPE_MESSAGE],
            function () {},
        );

        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_MESSAGING, Resource::TYPE_PROVIDER));
        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_MESSAGING, Resource::TYPE_TOPIC));
        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_MESSAGING, Resource::TYPE_MESSAGE));

        $transferredProvider = $this->destination->getResourceById(Transfer::GROUP_MESSAGING, Resource::TYPE_PROVIDER, 'provider1');
        /** @var Provider $transferredProvider */
        $this->assertNotNull($transferredProvider);
        $this->assertSame('Test Provider', $transferredProvider->getProviderName());
        $this->assertSame('mailgun', $transferredProvider->getProvider());

        $transferredTopic = $this->destination->getResourceById(Transfer::GROUP_MESSAGING, Resource::TYPE_TOPIC, 'topic1');
        /** @var Topic $transferredTopic */
        $this->assertNotNull($transferredTopic);
        $this->assertSame('Test Topic', $transferredTopic->getTopicName());

        $transferredMessage = $this->destination->getResourceById(Transfer::GROUP_MESSAGING, Resource::TYPE_MESSAGE, 'msg1');
        /** @var Message $transferredMessage */
        $this->assertNotNull($transferredMessage);
        $this->assertSame('email', $transferredMessage->getProviderType());
        $this->assertSame(['subject' => 'Test', 'content' => 'Hello'], $transferredMessage->getData());
    }

    public function testMessagingRootResource(): void
    {
        $provider1 = new Provider('p1', 'Provider 1', 'mailgun', 'email');
        $provider2 = new Provider('p2', 'Provider 2', 'twilio', 'sms');

        $this->source->pushMockResource($provider1);
        $this->source->pushMockResource($provider2);

        $this->transfer->run(
            [Resource::TYPE_PROVIDER],
            function () {},
            'p1',
            Resource::TYPE_PROVIDER,
        );

        $this->assertCount(1, $this->destination->getResourceTypeData(Transfer::GROUP_MESSAGING, Resource::TYPE_PROVIDER));

        $transferred = $this->destination->getResourceById(Transfer::GROUP_MESSAGING, Resource::TYPE_PROVIDER, 'p1');
        $this->assertNotNull($transferred);

        $notTransferred = $this->destination->getResourceById(Transfer::GROUP_MESSAGING, Resource::TYPE_PROVIDER, 'p2');
        $this->assertNull($notTransferred);
    }
}
