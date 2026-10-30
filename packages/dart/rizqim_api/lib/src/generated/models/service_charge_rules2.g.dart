// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'service_charge_rules2.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

ServiceChargeRules2 _$ServiceChargeRules2FromJson(Map<String, dynamic> json) =>
    ServiceChargeRules2(
      byOrderType: Map<String, String>.from(json['by_order_type'] as Map),
      largeGroupMinGuests: (json['large_group_min_guests'] as num?)?.toInt(),
      largeGroupPercent: json['large_group_percent'] as String?,
      coverAmount: json['cover_amount'] as String?,
    );

Map<String, dynamic> _$ServiceChargeRules2ToJson(
  ServiceChargeRules2 instance,
) => <String, dynamic>{
  'by_order_type': instance.byOrderType,
  'large_group_min_guests': instance.largeGroupMinGuests,
  'large_group_percent': instance.largeGroupPercent,
  'cover_amount': instance.coverAmount,
};
