// GENERATED CODE - DO NOT MODIFY BY HAND

part of 'rules4.dart';

// **************************************************************************
// JsonSerializableGenerator
// **************************************************************************

Rules4 _$Rules4FromJson(Map<String, dynamic> json) => Rules4(
  byOrderType: Map<String, String>.from(json['by_order_type'] as Map),
  largeGroupMinGuests: (json['large_group_min_guests'] as num?)?.toInt(),
  largeGroupPercent: json['large_group_percent'] as String?,
  coverAmount: json['cover_amount'] as String?,
);

Map<String, dynamic> _$Rules4ToJson(Rules4 instance) => <String, dynamic>{
  'by_order_type': instance.byOrderType,
  'large_group_min_guests': instance.largeGroupMinGuests,
  'large_group_percent': instance.largeGroupPercent,
  'cover_amount': instance.coverAmount,
};
