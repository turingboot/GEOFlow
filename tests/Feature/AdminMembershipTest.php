<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\Article;
use App\Models\Author;
use App\Models\Category;
use App\Models\KnowledgeBase;
use App\Models\MembershipPlan;
use App\Models\TenantMembership;
use App\Models\UrlImportJob;
use App\Services\Admin\MembershipService;
use App\Support\Tenancy\TenantProvisioner;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminMembershipTest extends TestCase
{
    use RefreshDatabase;

    public function test_super_admin_can_manage_plans_and_assign_membership_from_user_management(): void
    {
        $superAdmin = Admin::query()->create([
            'username' => 'super_membership',
            'password' => 'secret-123',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $admin = Admin::query()->create([
            'username' => 'member_target',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);
        $plan = MembershipPlan::query()->where('name', '专业版')->firstOrFail();

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.memberships.index'))
            ->assertOk()
            ->assertSee('会员管理')
            ->assertSee('专业版')
            ->assertSee('价格')
            ->assertSee('删除');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.membership', ['adminId' => (int) $admin->id]), [
                'membership_plan_id' => (int) $plan->id,
                'period' => 'month',
                'effective_mode' => 'reopen',
                'remark' => '测试开通',
            ])
            ->assertRedirect(route('admin.admin-users.index'));

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => (int) $admin->refresh()->tenant_id,
            'membership_plan_id' => (int) $plan->id,
            'status' => 'active',
            'remark' => '测试开通',
        ]);

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.admin-users.index'))
            ->assertOk()
            ->assertSee('分配会员')
            ->assertSee('专业版');
    }

    public function test_normal_admin_can_view_own_membership_detail(): void
    {
        $admin = Admin::query()->create([
            'username' => 'member_normal',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);
        $plan = MembershipPlan::query()->where('name', '入门版')->firstOrFail();
        TenantMembership::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'membership_plan_id' => (int) $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.membership.show'))
            ->assertOk()
            ->assertSee('会员详情')
            ->assertSee('入门版')
            ->assertSee('到期时间：')
            ->assertSee('本月文章：')
            ->assertSee('知识库：')
            ->assertSee('剩余：')
            ->assertSee('本月发布文章')
            ->assertSee('知识库数量');
    }

    public function test_super_admin_can_create_and_delete_unused_plan_with_price(): void
    {
        $superAdmin = Admin::query()->create([
            'username' => 'super_plan_price',
            'password' => 'secret-123',
            'role' => 'super_admin',
            'status' => 'active',
        ]);

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.memberships.store'), [
                'name' => '前导零套餐',
                'article_monthly_limit' => '01',
                'knowledge_base_limit' => '001',
                'price' => 199.99,
                'is_custom' => '1',
                'is_active' => '1',
                'sort_order' => '0007',
            ])
            ->assertRedirect(route('admin.memberships.index'));

        $plan = MembershipPlan::query()->where('name', '前导零套餐')->firstOrFail();
        $this->assertSame(1, (int) $plan->article_monthly_limit);
        $this->assertSame(1, (int) $plan->knowledge_base_limit);
        $this->assertSame(7, (int) $plan->sort_order);
        $this->assertSame('199.99', (string) $plan->price);

        $this->actingAs($superAdmin, 'admin')
            ->get(route('admin.memberships.index'))
            ->assertOk()
            ->assertSee('￥199.99')
            ->assertSee('1 篇')
            ->assertSee('1 个')
            ->assertSee('企业定制套餐')
            ->assertSee('显示顺序')
            ->assertSee('plan_article_limit_unlimited', false)
            ->assertSee('plan_knowledge_limit_unlimited', false)
            ->assertSee('输入每月文章数')
            ->assertSee('输入知识库总数');

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.memberships.delete', ['planId' => (int) $plan->id]))
            ->assertRedirect(route('admin.memberships.index'));

        $this->assertDatabaseMissing('membership_plans', [
            'id' => (int) $plan->id,
        ]);
    }

    public function test_membership_detail_marks_over_limit_usage_as_abnormal(): void
    {
        $admin = Admin::query()->create([
            'username' => 'member_over_limit',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);
        $plan = MembershipPlan::query()->create([
            'name' => '超额测试会员',
            'article_monthly_limit' => 1,
            'knowledge_base_limit' => 1,
            'price' => 9.9,
            'is_active' => true,
            'sort_order' => 100,
        ]);
        TenantMembership::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'membership_plan_id' => (int) $plan->id,
            'starts_at' => now(),
            'ends_at' => now()->addMonth(),
            'status' => 'active',
        ]);
        $category = Category::query()->create(['name' => '超额测试分类', 'slug' => 'over-limit-category']);
        $author = Author::query()->create(['name' => 'GEOFlow']);
        Article::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'title' => '超额文章一',
            'slug' => 'over-limit-one',
            'content' => '正文',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        Article::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'title' => '超额文章二',
            'slug' => 'over-limit-two',
            'content' => '正文',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'published_at' => now(),
        ]);
        KnowledgeBase::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'name' => '超额知识库一',
            'file_type' => 'markdown',
            'content' => '正文',
            'status' => 'ready',
        ]);
        KnowledgeBase::query()->create([
            'tenant_id' => (int) $admin->tenant_id,
            'name' => '超额知识库二',
            'file_type' => 'markdown',
            'content' => '正文',
            'status' => 'ready',
        ]);

        $this->actingAs($admin, 'admin')
            ->get(route('admin.membership.show'))
            ->assertOk()
            ->assertSee('异常')
            ->assertSee('当前资源使用量已超过会员额度');
    }

    public function test_published_article_requires_active_membership_and_respects_monthly_limit(): void
    {
        $admin = Admin::query()->create([
            'username' => 'article_membership',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);
        $category = Category::query()->create(['name' => '会员文章分类', 'slug' => 'membership-article']);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        $payload = [
            'title' => '会员发布测试文章',
            'excerpt' => '摘要',
            'content' => '正文',
            'keywords' => 'GEO',
            'meta_description' => 'Meta',
            'category_id' => (int) $category->id,
            'author_id' => (int) $author->id,
            'status' => 'published',
            'review_status' => 'approved',
        ];

        $this->actingAs($admin, 'admin')
            ->post(route('admin.articles.store'), $payload)
            ->assertSessionHasErrors('membership');

        $plan = MembershipPlan::query()->create([
            'name' => '测试文章会员',
            'article_monthly_limit' => 1,
            'knowledge_base_limit' => 5,
            'is_active' => true,
            'sort_order' => 99,
        ]);
        app(MembershipService::class)->assignMembership((int) $admin->tenant_id, (int) $plan->id, 'month', 'reopen', null, '', null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.articles.store'), $payload)
            ->assertRedirect();

        $this->actingAs($admin, 'admin')
            ->post(route('admin.articles.store'), array_merge($payload, ['title' => '会员发布测试文章二']))
            ->assertSessionHasErrors('membership');

        $this->assertSame(1, Article::query()->where('status', 'published')->count());
    }

    public function test_knowledge_base_requires_active_membership_and_respects_total_limit(): void
    {
        $admin = Admin::query()->create([
            'username' => 'knowledge_membership',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);

        $payload = [
            'name' => '会员知识库',
            'file_type' => 'markdown',
            'content' => '知识库正文',
            'import_action' => 'save',
        ];

        $this->actingAs($admin, 'admin')
            ->post(route('admin.knowledge-bases.store'), $payload)
            ->assertSessionHasErrors('membership');

        $plan = MembershipPlan::query()->create([
            'name' => '测试知识库会员',
            'article_monthly_limit' => 5,
            'knowledge_base_limit' => 1,
            'is_active' => true,
            'sort_order' => 100,
        ]);
        app(MembershipService::class)->assignMembership((int) $admin->tenant_id, (int) $plan->id, 'month', 'reopen', null, '', null);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.knowledge-bases.store'), $payload)
            ->assertRedirect(route('admin.knowledge-bases.index'));

        $this->actingAs($admin, 'admin')
            ->post(route('admin.knowledge-bases.store'), array_merge($payload, ['name' => '会员知识库二']))
            ->assertSessionHasErrors('membership');

        $this->assertSame(1, KnowledgeBase::query()->count());
    }

    public function test_super_admin_can_disable_membership_and_disabled_membership_blocks_features(): void
    {
        $superAdmin = Admin::query()->create([
            'username' => 'super_disable_membership',
            'password' => 'secret-123',
            'role' => 'super_admin',
            'status' => 'active',
        ]);
        $admin = Admin::query()->create([
            'username' => 'disable_membership_target',
            'password' => 'secret-123',
            'role' => 'admin',
            'status' => 'active',
        ]);
        app(TenantProvisioner::class)->ensureForAdmin($admin);
        $plan = MembershipPlan::query()->where('name', '入门版')->firstOrFail();
        app(MembershipService::class)->assignMembership((int) $admin->tenant_id, (int) $plan->id, 'month', 'reopen', null, '', null);

        $this->actingAs($superAdmin, 'admin')
            ->post(route('admin.admin-users.membership.disable', ['adminId' => (int) $admin->id]))
            ->assertRedirect(route('admin.admin-users.index'));

        $this->assertDatabaseHas('tenant_memberships', [
            'tenant_id' => (int) $admin->tenant_id,
            'status' => 'disabled',
        ]);

        $category = Category::query()->create(['name' => '停用会员分类', 'slug' => 'disabled-membership']);
        $author = Author::query()->create(['name' => 'GEOFlow']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.articles.store'), [
                'title' => '停用会员文章',
                'excerpt' => '摘要',
                'content' => '正文',
                'keywords' => 'GEO',
                'meta_description' => 'Meta',
                'category_id' => (int) $category->id,
                'author_id' => (int) $author->id,
                'status' => 'published',
                'review_status' => 'approved',
            ])
            ->assertSessionHasErrors(['membership' => '当前会员已停用，发布文章暂不可用，请联系管理员处理。']);

        $this->actingAs($admin, 'admin')
            ->post(route('admin.knowledge-bases.store'), [
                'name' => '停用会员知识库',
                'file_type' => 'markdown',
                'content' => '知识库正文',
                'import_action' => 'save',
            ])
            ->assertSessionHasErrors(['membership' => '当前会员已停用，创建知识库暂不可用，请联系管理员处理。']);

        $job = UrlImportJob::query()->create([
            'url' => 'https://example.com',
            'normalized_url' => 'https://example.com',
            'source_domain' => 'example.com',
            'status' => 'queued',
            'current_step' => 'queued',
            'progress_percent' => 0,
            'options_json' => '{}',
            'result_json' => '',
            'error_message' => '',
        ]);

        $this->actingAs($admin, 'admin')
            ->postJson(route('admin.url-import.run', ['jobId' => (int) $job->id]))
            ->assertStatus(422)
            ->assertJsonPath('error_message', '当前会员已停用，URL 智能采集暂不可用，请联系管理员处理。');
    }
}
