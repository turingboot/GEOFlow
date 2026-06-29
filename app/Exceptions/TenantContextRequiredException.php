<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * 在缺少租户上下文时创建受租户约束的模型会抛出此异常。
 *
 * 典型场景：超级管理员处于「全部租户（只读总览）」模式（bypass，无具体 tenant_id）时尝试新建数据。
 * 由 {@see \App\Models\Concerns\BelongsToTenant} 抛出，并在 bootstrap/app.php 中渲染为后台友好提示。
 */
class TenantContextRequiredException extends RuntimeException
{
}
