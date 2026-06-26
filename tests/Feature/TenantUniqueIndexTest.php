<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class TenantUniqueIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_remaining_business_unique_indexes_are_tenant_scoped(): void
    {
        $this->assertTrue(Schema::hasIndex('distribution_channel_secrets', 'distribution_channel_secrets_tenant_key_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('distribution_channel_secrets', 'distribution_channel_secrets_key_id_unique', 'unique'));

        $this->assertTrue(Schema::hasIndex('article_distributions', 'article_distributions_tenant_idempotency_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('article_distributions', 'article_distributions_idempotency_key_unique', 'unique'));

        $this->assertTrue(Schema::hasIndex('site_theme_replications', 'site_theme_replications_tenant_theme_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('site_theme_replications', 'site_theme_replications_theme_id_unique', 'unique'));

        $this->assertTrue(Schema::hasIndex('keyword_trend_source_secrets', 'keyword_trend_source_secrets_tenant_key_unique', 'unique'));
        $this->assertFalse(Schema::hasIndex('keyword_trend_source_secrets', 'keyword_trend_source_secrets_key_id_unique', 'unique'));
    }
}
