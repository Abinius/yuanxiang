<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * M5 会员阶梯：users 落地 member_level(0新人/1红人/2达人/3合伙人) +
 * member_since(升级时间) + birthday(生日权益)。
 * 等级阈值读 tenants.settings.member.tiers（M2 已就绪），不冗余存储消费额。
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedTinyInteger('member_level')->default(0)->after('role'); // 0新人 / 1红人 / 2达人 / 3合伙人
            $table->timestamp('member_since')->nullable()->after('member_level');
            $table->date('birthday')->nullable()->after('member_since');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['member_level', 'member_since', 'birthday']);
        });
    }
};
