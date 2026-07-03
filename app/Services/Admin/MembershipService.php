<?php

namespace App\Services\Admin;

use App\Models\Article;
use App\Models\KnowledgeBase;
use App\Models\MembershipPlan;
use App\Models\MembershipUsagePeriod;
use App\Models\Scopes\TenantScope;
use App\Models\TenantMembership;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MembershipService
{
    /**
     * @return array{
     *     tenant_id:int,
     *     plan_name:string,
     *     status:string,
     *     status_label:string,
     *     starts_at:?Carbon,
     *     ends_at:?Carbon,
     *     remaining_days:?int,
     *     article_used:int,
     *     article_limit:int,
     *     knowledge_used:int,
     *     knowledge_limit:int,
     *     article_over_limit:bool,
     *     knowledge_over_limit:bool,
     *     message:string
     * }
     */
    public function summaryForTenant(?int $tenantId): array
    {
        $tenantId = (int) ($tenantId ?? 0);
        $membership = $tenantId > 0 ? $this->currentMembership($tenantId) : null;
        $plan = $membership?->plan;
        $status = $this->status($membership);
        $articleLimit = $plan ? (int) $plan->article_monthly_limit : 0;
        $knowledgeLimit = $plan ? (int) $plan->knowledge_base_limit : 0;
        $endsAt = $membership?->ends_at;
        $remainingDays = $endsAt instanceof Carbon ? (int) max(0, Carbon::now()->diffInDays($endsAt, false)) : null;
        $articleUsed = $this->articlePublishedThisMonth($tenantId);
        $knowledgeUsed = $this->knowledgeBaseCount($tenantId);
        $articleOverLimit = $articleLimit > 0 && $articleUsed > $articleLimit;
        $knowledgeOverLimit = $knowledgeLimit > 0 && $knowledgeUsed > $knowledgeLimit;

        return [
            'tenant_id' => $tenantId,
            'plan_name' => $plan ? (string) $plan->name : '未开通会员',
            'status' => $status,
            'status_label' => $this->statusLabel($status),
            'starts_at' => $membership?->starts_at,
            'ends_at' => $endsAt,
            'remaining_days' => $remainingDays,
            'article_used' => $articleUsed,
            'article_limit' => $articleLimit,
            'knowledge_used' => $knowledgeUsed,
            'knowledge_limit' => $knowledgeLimit,
            'article_over_limit' => $articleOverLimit,
            'knowledge_over_limit' => $knowledgeOverLimit,
            'message' => $this->statusMessage($status, $plan ? (string) $plan->name : '', $remainingDays, $articleOverLimit, $knowledgeOverLimit),
        ];
    }

    public function currentMembership(int $tenantId): ?TenantMembership
    {
        if ($tenantId <= 0) {
            return null;
        }

        $active = $this->activeMembership($tenantId);
        if ($active instanceof TenantMembership) {
            return $active;
        }

        return TenantMembership::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', 'disabled')
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->first();
    }

    public function activeMembership(int $tenantId): ?TenantMembership
    {
        if ($tenantId <= 0) {
            return null;
        }

        return TenantMembership::query()
            ->with('plan')
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->orderByDesc('ends_at')
            ->orderByDesc('id')
            ->first();
    }

    public function assignMembership(
        int $tenantId,
        int $planId,
        string $period,
        string $effectiveMode,
        ?Carbon $customEndsAt,
        string $remark,
        ?int $assignedBy
    ): TenantMembership {
        if ($tenantId <= 0) {
            throw ValidationException::withMessages(['tenant_id' => '请选择有效用户。']);
        }

        $plan = MembershipPlan::query()
            ->whereKey($planId)
            ->where('is_active', true)
            ->firstOrFail();

        $current = $this->activeMembership($tenantId);
        $now = Carbon::now();
        $baseStart = $effectiveMode === 'renew' && $current?->ends_at instanceof Carbon && $current->ends_at->greaterThan($now)
            ? $current->ends_at->copy()
            : $now;
        $endsAt = $this->calculateEndsAt($baseStart, $period, $customEndsAt);

        return DB::transaction(function () use ($tenantId, $plan, $baseStart, $endsAt, $remark, $assignedBy): TenantMembership {
            TenantMembership::query()
                ->where('tenant_id', $tenantId)
                ->where('status', 'active')
                ->update(['status' => 'cancelled']);

            return TenantMembership::query()->create([
                'tenant_id' => $tenantId,
                'membership_plan_id' => (int) $plan->id,
                'starts_at' => $baseStart,
                'ends_at' => $endsAt,
                'status' => 'active',
                'remark' => $remark !== '' ? $remark : null,
                'assigned_by' => $assignedBy,
            ]);
        });
    }

    public function disableActiveMembership(int $tenantId): bool
    {
        if ($tenantId <= 0) {
            return false;
        }

        return TenantMembership::query()
            ->where('tenant_id', $tenantId)
            ->where('status', 'active')
            ->update(['status' => 'disabled']) > 0;
    }

    public function ensureCanPublishArticle(int $tenantId, int $additionalCount = 1): void
    {
        $summary = $this->summaryForTenant($tenantId);
        if (! in_array($summary['status'], ['active', 'expiring'], true)) {
            throw ValidationException::withMessages([
                'membership' => $this->unavailableMessage((string) $summary['status'], '发布文章'),
            ]);
        }

        if ($summary['article_limit'] > 0 && $summary['article_limit'] < $summary['article_used'] + $additionalCount) {
            throw ValidationException::withMessages([
                'membership' => '本月发布文章额度已用完，请升级套餐或联系管理员。',
            ]);
        }
    }

    public function ensureCanCreateKnowledgeBase(int $tenantId): void
    {
        $summary = $this->summaryForTenant($tenantId);
        if (! in_array($summary['status'], ['active', 'expiring'], true)) {
            throw ValidationException::withMessages([
                'membership' => $this->unavailableMessage((string) $summary['status'], '创建知识库'),
            ]);
        }

        if ($summary['knowledge_limit'] > 0 && $summary['knowledge_used'] >= $summary['knowledge_limit']) {
            throw ValidationException::withMessages([
                'membership' => '当前会员知识库额度已用完，请升级套餐或联系管理员。',
            ]);
        }
    }

    public function ensureCanRunUrlImport(int $tenantId): void
    {
        $summary = $this->summaryForTenant($tenantId);
        if (! in_array($summary['status'], ['active', 'expiring'], true)) {
            throw ValidationException::withMessages([
                'membership' => $this->unavailableMessage((string) $summary['status'], 'URL 智能采集'),
            ]);
        }

        if ($summary['knowledge_limit'] > 0 && $summary['knowledge_used'] >= $summary['knowledge_limit']) {
            throw ValidationException::withMessages([
                'membership' => '当前会员知识库额度已用完，请升级套餐或联系管理员。',
            ]);
        }
    }

    public function recordPublishedArticle(int $tenantId, ?int $membershipId = null): void
    {
        if ($tenantId <= 0) {
            return;
        }

        $membershipId ??= $this->activeMembership($tenantId)?->id;
        $periodStart = Carbon::now()->startOfMonth();
        $periodEnd = Carbon::now()->endOfMonth();
        $periodKey = $periodStart->format('Y-m');

        DB::transaction(function () use ($tenantId, $membershipId, $periodKey, $periodStart, $periodEnd): void {
            $period = MembershipUsagePeriod::query()
                ->where('tenant_id', $tenantId)
                ->where('period_key', $periodKey)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                $period = MembershipUsagePeriod::query()->create([
                    'tenant_id' => $tenantId,
                    'tenant_membership_id' => $membershipId,
                    'period_key' => $periodKey,
                    'period_starts_at' => $periodStart,
                    'period_ends_at' => $periodEnd,
                    'article_published_used' => $this->articlePublishedThisMonth($tenantId),
                ]);
            }

            $period->forceFill([
                'tenant_membership_id' => $membershipId,
                'article_published_used' => $this->articlePublishedThisMonth($tenantId),
            ])->save();
        });
    }

    /**
     * @return EloquentCollection<int, MembershipPlan>
     */
    public function activePlans(): EloquentCollection
    {
        return MembershipPlan::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();
    }

    private function calculateEndsAt(Carbon $startsAt, string $period, ?Carbon $customEndsAt): Carbon
    {
        return match ($period) {
            'quarter' => $startsAt->copy()->addMonthsNoOverflow(3),
            'year' => $startsAt->copy()->addYearNoOverflow(),
            'custom' => $customEndsAt instanceof Carbon && $customEndsAt->greaterThan($startsAt)
                ? $customEndsAt
                : throw ValidationException::withMessages(['ends_at' => '自定义到期时间必须晚于开始时间。']),
            default => $startsAt->copy()->addMonthNoOverflow(),
        };
    }

    private function status(?TenantMembership $membership): string
    {
        if (! $membership instanceof TenantMembership) {
            return 'none';
        }

        if ((string) $membership->status !== 'active') {
            return (string) $membership->status === 'disabled' ? 'disabled' : 'none';
        }

        if (! $membership->ends_at instanceof Carbon || $membership->ends_at->lessThanOrEqualTo(Carbon::now())) {
            return 'expired';
        }

        return Carbon::now()->diffInDays($membership->ends_at, false) <= 7 ? 'expiring' : 'active';
    }

    private function statusLabel(string $status): string
    {
        return match ($status) {
            'active' => '生效中',
            'expiring' => '即将到期',
            'expired' => '已到期',
            'disabled' => '已停用',
            default => '未开通',
        };
    }

    private function statusMessage(string $status, string $planName, ?int $remainingDays, bool $articleOverLimit, bool $knowledgeOverLimit): string
    {
        if ($status === 'disabled') {
            return '当前会员已停用，发布文章、创建知识库和 URL 智能采集暂不可用，请联系管理员处理。';
        }

        if ($articleOverLimit && $knowledgeOverLimit) {
            return '当前资源使用量已超过会员额度，请升级套餐或联系管理员调整额度。';
        }

        if ($articleOverLimit) {
            return '本月发布文章数量已超过当前会员额度，请升级套餐或联系管理员调整额度。';
        }

        if ($knowledgeOverLimit) {
            return '知识库数量已超过当前会员额度，请升级套餐或联系管理员调整额度。';
        }

        return match ($status) {
            'expiring' => $planName.'会员将在 '.($remainingDays ?? 0).' 天后到期，请联系管理员续费。',
            'expired' => '会员已到期，发布文章和创建知识库已暂停。请联系管理员续费。',
            'none' => '当前账号尚未开通会员，发布文章和创建知识库暂不可用。请联系管理员开通会员。',
            default => '',
        };
    }

    private function unavailableMessage(string $status, string $action): string
    {
        return match ($status) {
            'disabled' => '当前会员已停用，'.$action.'暂不可用，请联系管理员处理。',
            'expired' => '当前会员已到期，'.$action.'暂不可用，请联系管理员续费。',
            default => '当前会员未开通，'.$action.'暂不可用，请联系管理员开通会员。',
        };
    }

    private function articlePublishedThisMonth(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        return (int) Article::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->where('status', 'published')
            ->whereNotNull('published_at')
            ->whereBetween('published_at', [Carbon::now()->startOfMonth(), Carbon::now()->endOfMonth()])
            ->count();
    }

    private function knowledgeBaseCount(int $tenantId): int
    {
        if ($tenantId <= 0) {
            return 0;
        }

        return (int) KnowledgeBase::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenantId)
            ->count();
    }
}
