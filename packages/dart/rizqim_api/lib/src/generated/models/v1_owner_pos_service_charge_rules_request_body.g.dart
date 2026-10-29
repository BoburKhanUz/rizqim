// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'v1_owner_pos_service_charge_rules_request_body.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

V1OwnerPosServiceChargeRulesRequestBody
_$V1OwnerPosServiceChargeRulesRequestBodyFromJson(Map<String, dynamic> json) =>
    V1OwnerPosServiceChargeRulesRequestBody(
      byOrderType: (json['by_order_type'] as Map<String, dynamic>?)?.map(
        (k, e) => MapEntry(k, e as String),
      ),
      branchId: (json['branch_id'] as num?)?.toInt(),
      hallId: (json['hall_id'] as num?)?.toInt(),
      largeGroupMinGuests: (json['large_group_min_guests'] as num?)?.toInt(),
      largeGroupPercent: json['large_group_percent'] as String?,
      coverAmount: json['cover_amount'] as String?,
      notice: json['notice'] as String?,
    );

Map<String, dynamic> _$V1OwnerPosServiceChargeRulesRequestBodyToJson(
  V1OwnerPosServiceChargeRulesRequestBody instance,
) => <String, dynamic>{
  'by_order_type': instance.byOrderType,
  'branch_id': instance.branchId,
  'hall_id': instance.hallId,
  'large_group_min_guests': instance.largeGroupMinGuests,
  'large_group_percent': instance.largeGroupPercent,
  'cover_amount': instance.coverAmount,
  'notice': instance.notice,
};
