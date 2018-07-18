<?php
/**
 * Created by IntelliJ IDEA.
 * User: king
 * Date: 2016/12/9
 * Time: 15:50
 */
namespace App\Fractals\Transformers;

use App\Models\Api\osv_member;
use League\Fractal\TransformerAbstract;

class MemberTransformer extends TransformerAbstract
{
//    protected $availableIncludes = [
//        'role',
//    ];

    public function transform(osv_member $member)
    {
        return [
            'uid' => $member->m_id,
            'phone' => $member->m_mobile,
            'nickname' => $member->m_name,
            'company' => $member->m_company,
            'real_name' => $member->real_name,
            'status' => $member->m_status,
            'created_at' => $member->created_at->toDateTimeString(),
            'updated_at' => $member->updated_at->toDateTimeString(),
        ];
    }

//    public function includeRole(User $user)
//    {
//        $role = $user->roles->first();
//        if (!empty($role)) {
//            return $this->item($role, new RoleTransformer(), '');
//        }
//    }
}