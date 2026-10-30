// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rules.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rules _$RulesFromJson(Map<String, dynamic> json) => Rules(
  byOrderType: Map<String, String>.from(json['by_order_type'] as Map),
  largeGroupMinGuests: (json['large_group_min_guests'] as num?)?.toInt(),
  largeGroupPercent: json['large_group_percent'] as String?,
  coverAmount: json['cover_amount'] as String?,
);

Map<String, dynamic> _$RulesToJson(Rules instance) => <String, dynamic>{
  'by_order_type': instance.byOrderType,
  'large_group_min_guests': instance.largeGroupMinGuests,
  'large_group_percent': instance.largeGroupPercent,
  'cover_amount': instance.coverAmount,
};
